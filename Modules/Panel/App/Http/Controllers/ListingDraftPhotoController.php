<?php

declare(strict_types=1);

namespace Modules\Panel\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Listing\Models\ListingDraft;

final class ListingDraftPhotoController extends Controller
{
    public function __invoke(Request $request, string $draft, string $file)
    {
        $photo = ListingDraft::photoFor($request->user(), (int) $draft, $file);

        return Storage::disk('local')->response($photo['path'], null, ['Content-Type' => $photo['mime'], 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
