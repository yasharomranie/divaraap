<?php

declare(strict_types=1);

namespace Modules\Listing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Listing\Support\DraftConflict;
use Modules\User\App\Models\User;
use Throwable;

final class ListingDraft extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'files' => 'encrypted:array', 'version' => 'integer'];
    }

    public static function currentFor(User $user): ?self
    {
        return self::where('user_id', $user->getKey())->first();
    }

    public static function saveFor(User $user, ?int $id, int $version, array $payload, array $uploads = [], array $removed = []): self
    {
        abort_unless((string) $user->status === 'active', 403);
        Validator::make(['payload' => $payload], ['payload' => ['array', 'max:25'], 'payload.listingTitle' => ['nullable', 'string', 'max:255'], 'payload.description' => ['nullable', 'string', 'max:5000'], 'payload.customFieldValues' => ['nullable', 'array', 'max:100']])->validate();
        if (strlen(json_encode($payload, JSON_THROW_ON_ERROR)) > 65536) {
            throw ValidationException::withMessages(['draft' => __('panel::drafts.failed')]);
        }
        $createdPaths = [];
        $deletedPaths = [];
        try {
            $draft = DB::transaction(function () use ($user, $id, $version, $payload, $uploads, $removed, &$createdPaths, &$deletedPaths): self {
                if ($id === null) {
                    $candidate = self::firstOrCreate(['user_id' => $user->getKey()], ['payload' => [], 'files' => [], 'version' => 0]);
                    $draft = self::whereKey($candidate->getKey())->lockForUpdate()->firstOrFail();
                } else {
                    $draft = self::whereKey($id)->where('user_id', $user->getKey())->lockForUpdate()->first();
                }
                if (! $draft || $draft->version !== $version) {
                    throw new DraftConflict;
                }
                $files = $draft->files;
                foreach ($removed as $key) {
                    if (isset($files[$key])) {
                        $deletedPaths[] = $files[$key]['path'];
                        unset($files[$key]);
                    }
                }
                foreach (['photo' => (int) config('quick-listing.max_photo_count', 20), 'video' => (int) config('video.max_listing_videos', 5)] as $type => $limit) {
                    $count = count(array_filter($files, fn ($file) => $file['type'] === $type));
                    foreach ($uploads as $key => $upload) {
                        if (! isset($files[$key]) && ($upload['type'] ?? null) === $type) {
                            $count++;
                        }
                    }
                    if ($count > $limit) {
                        throw ValidationException::withMessages([$type === 'photo' ? 'photos' : 'videos' => __('panel::drafts.file_limit', ['count' => $limit])]);
                    }
                }
                foreach ($uploads as $key => $upload) {
                    if (isset($files[$key])) {
                        continue;
                    }
                    $file = $upload['file'];
                    $type = $upload['type'];
                    $rules = $type === 'photo'
                        ? ['required', 'image', 'mimes:jpg,jpeg,png', 'max:'.config('quick-listing.max_photo_size_kb', 5120)]
                        : ['required', 'file', config('video.processing') === 'direct' ? 'mimetypes:video/mp4' : 'mimetypes:video/mp4,video/quicktime,video/webm,video/x-matroska,video/x-msvideo', 'max:'.config('video.max_upload_size_kb', 102400)];
                    Validator::make(['file' => $file], ['file' => $rules])->validate();
                    $path = 'listing-drafts/'.$user->getKey().'/'.$draft->getKey().'/'.Str::uuid().'.'.$file->guessExtension();
                    $stream = fopen($file->getRealPath(), 'rb');
                    try {
                        if (! Storage::disk('local')->put($path, $stream)) {
                            throw new \RuntimeException('Draft upload could not be saved.');
                        }
                    } finally {
                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                    }
                    $createdPaths[] = $path;
                    $files[$key] = ['path' => $path, 'type' => $type, 'name' => mb_substr(basename($file->getClientOriginalName()), 0, 255), 'mime' => $file->getMimeType()];
                }
                if ($draft->payload !== $payload || $draft->files !== $files || $draft->version === 0) {
                    $draft->forceFill(['payload' => $payload, 'files' => $files, 'version' => $draft->version + 1])->save();
                }

                return $draft;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($createdPaths);
            throw $exception;
        }
        Storage::disk('local')->delete($deletedPaths);

        return $draft;
    }

    public static function fromListing(User $user, Listing $listing, string $version, bool $photos, bool $location): self
    {
        abort_unless((string) $user->status === 'active', 403);
        $paths = [];
        try {
            return DB::transaction(function () use ($user, $listing, $version, $photos, $location, &$paths): self {
                $source = Listing::whereKey($listing->id)->lockForUpdate()->firstOrFail();
                $source->assertOwnedBy($user);
                if (! hash_equals($source->duplicationVersion(), $version)) {
                    throw ValidationException::withMessages(['source' => __('listing::duplicate.changed')]);
                }
                if (self::currentFor($user)) {
                    throw new DraftConflict;
                }
                $uploads = $photos ? $source->duplicationPhotos() : [];
                if ($photos && $uploads === []) {
                    throw ValidationException::withMessages(['photos' => __('listing::duplicate.no_photos')]);
                }
                $draft = self::saveFor($user, null, 0, $source->duplicationPayload($location), $uploads);
                $paths = array_column($draft->files, 'path');

                return $draft;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }
    }

    public static function consume(User $user, int $id, int $version, ?callable $publish = null): mixed
    {
        abort_unless((string) $user->status === 'active', 403);
        $paths = [];
        $result = DB::transaction(function () use ($user, $id, $version, $publish, &$paths) {
            $draft = self::whereKey($id)->where('user_id', $user->getKey())->lockForUpdate()->first();
            if (! $draft || $draft->version !== $version) {
                throw new DraftConflict;
            }
            if ($publish) {
                foreach ($draft->files as $file) {
                    if (! Storage::disk('local')->exists($file['path'])) {
                        throw new \RuntimeException('A draft attachment is missing.');
                    }
                }
            }
            $result = $publish ? $publish($draft->files) : null;
            $paths = array_column($draft->files, 'path');
            $draft->forceFill(['payload' => [], 'files' => [], 'version' => $draft->version + 1])->save();
            $draft->delete();

            return $result;
        });
        Storage::disk('local')->delete($paths);

        return $result;
    }

    public static function photoFor(User $user, int $id, string $key): array
    {
        $draft = self::whereKey($id)->where('user_id', $user->getKey())->firstOrFail();
        $file = $draft->files[$key] ?? null;
        abort_unless($file && $file['type'] === 'photo' && Storage::disk('local')->exists($file['path']), 404);

        return $file;
    }

    public static function upload(array $file): UploadedFile
    {
        return new UploadedFile(Storage::disk('local')->path($file['path']), $file['name'], $file['mime'], null, true);
    }
}
