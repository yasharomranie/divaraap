<?php

declare(strict_types=1);

namespace Modules\Panel\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Category\Models\Category;
use Modules\Listing\Support\ListingTextSuggestions;

final class ListingTextSuggestionController extends Controller
{
    public function generate(Request $request)
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'city' => ['nullable', 'string', 'max:255'],
            'description_limit' => ['nullable', 'integer', 'in:1450,4000'],
        ]);
        $data['category'] = Category::textSuggestionLabel(isset($data['category_id']) ? (int) $data['category_id'] : null);
        $suggestions = ListingTextSuggestions::generate($data, (int) ($data['description_limit'] ?? 4000));

        return response()->json($suggestions)->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
