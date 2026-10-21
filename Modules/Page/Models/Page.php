<?php

declare(strict_types=1);

namespace Modules\Page\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

class Page extends Model
{
    use SoftDeletes;

    public const PLACEMENT_FOOTER = 'footer';

    public const PLACEMENT_LEGAL = 'legal';

    public const PLACEMENT_HELP = 'help';

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'body',
        'meta_title',
        'meta_description',
        'placement',
        'is_published',
        'sort_order',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'sort_order' => 'integer',
    ];

    public static function advertisingGuides(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return self::query()->published()->withPlacement(self::PLACEMENT_HELP)->whereIn('id', $ids)->get()
            ->mapWithKeys(function (self $page): array {
                if (trim($page->title) === '' || trim($page->body) === '' || trim($page->slug) === '') {
                    return [];
                }

                return [$page->id => [
                    'title' => $page->localizedValue('title'),
                    'excerpt' => Str::limit(strip_tags($page->localizedValue('excerpt')), 180),
                    'url' => route('pages.show', ['slug' => $page->slug]),
                    'signature' => hash('sha256', json_encode([$page->slug, $page->title, $page->excerpt, $page->body], JSON_THROW_ON_ERROR)),
                ]];
            })->all();
    }

    public static function advertisingGuideOptions(string $search = ''): array
    {
        $ids = self::query()->published()->withPlacement(self::PLACEMENT_HELP)
            ->when(trim($search) !== '', fn (Builder $query) => $query->where('title', 'like', '%'.mb_substr($search, 0, 120).'%'))
            ->orderBy('title')->limit(50)->pluck('id')->all();

        return collect(self::advertisingGuides($ids))->mapWithKeys(fn (array $page, int $id): array => [$id => $page['title'].' #'.$id])->all();
    }

    public static function placements(): array
    {
        return [
            self::PLACEMENT_FOOTER,
            self::PLACEMENT_LEGAL,
            self::PLACEMENT_HELP,
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeWithPlacement(Builder $query, string $placement): Builder
    {
        return $query->where('placement', $placement);
    }

    public static function findPublished(string $slug): ?self
    {
        return static::query()->published()->where('slug', $slug)->first();
    }

    public static function navigation(string $placement): Collection
    {
        return static::query()
            ->published()
            ->withPlacement($placement)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get(['id', 'title', 'slug']);
    }

    public static function publishedSlugs(): Collection
    {
        return static::query()
            ->published()
            ->orderBy('sort_order')
            ->get(['slug', 'updated_at']);
    }

    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $base = $base !== '' ? $base : 'page';
        $slug = $base;
        $suffix = 1;

        while (static::query()->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    public function localizedValue(string $field): string
    {
        $value = (string) $this->getAttribute($field);
        $key = 'page::content.'.$this->getAttribute('slug').'.'.$field;

        if (app()->getLocale() !== 'fa' || ! Lang::has($key, 'en') || $value !== Lang::get($key, [], 'en')) {
            return $value;
        }

        return (string) Lang::get($key, [], 'fa');
    }

    public function metaTitleValue(): string
    {
        $meta = (string) $this->getAttribute('meta_title');

        return $meta !== '' ? $meta : $this->localizedValue('title');
    }

    public function metaDescriptionValue(): string
    {
        $meta = $this->localizedValue('meta_description');

        return $meta !== '' ? $meta : $this->localizedValue('excerpt');
    }

    public function bodyParagraphs(): array
    {
        $body = $this->localizedValue('body');

        return collect(preg_split('/\R{2,}/u', $body) ?: [])
            ->map(static fn (string $block): string => trim($block))
            ->filter(static fn (string $block): bool => $block !== '')
            ->values()
            ->all();
    }
}
