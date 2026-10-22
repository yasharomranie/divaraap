<?php

declare(strict_types=1);

namespace Modules\Favorite\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Modules\Favorite\App\Http\Requests\ComparisonDossierRequest;
use Modules\Favorite\App\Models\ComparisonDossier;
use Modules\Favorite\App\Support\ListingComparison;

final class ComparisonDossierController
{
    public function index(Request $request): Response
    {
        return $this->view('favorite::dossiers.index', ['records' => ComparisonDossier::forOwner($request->user())]);
    }

    public function create(Request $request, ListingComparison $comparison): Response
    {
        ComparisonDossier::actor($request->user());

        return $this->view('favorite::dossiers.create', ['listings' => $comparison->listings(), 'requestKey' => (string) Str::uuid()]);
    }

    public function store(ComparisonDossierRequest $request, ListingComparison $comparison): RedirectResponse
    {
        ComparisonDossier::createFor($request->user(), $request->validated(), $comparison->listings());

        return redirect()->route('dossiers.index')->with('status', __('favorite::dossier.saved'));
    }

    public function change(ComparisonDossierRequest $request, int $dossier): RedirectResponse
    {
        ComparisonDossier::owned($request->user(), $dossier)->changeFor($request->user(), $request->validated('action'), (int) $request->validated('revision'));

        return redirect()->route('dossiers.index')->with('status', __('favorite::dossier.changed'));
    }

    public function shared(Request $request, string $token, ListingComparison $comparison): Response
    {
        $record = ComparisonDossier::shared($token);
        $listings = $record->listings();
        abort_unless($listings->count() >= 2, 404);
        $rows = $comparison->rows($listings);
        $differences = $request->boolean('differences');
        if ($differences) {
            $rows = array_values(array_filter($rows, fn (array $row): bool => $row['different']));
        }

        return $this->view('favorite::dossiers.shared', compact('record', 'listings', 'rows', 'differences'));
    }

    private function view(string $view, array $data): Response
    {
        return response()->view($view, $data)->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer');
    }
}
