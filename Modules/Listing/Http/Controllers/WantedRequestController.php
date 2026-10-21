<?php

declare(strict_types=1);

namespace Modules\Listing\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Modules\Listing\Http\Requests\WantedCloseRequest;
use Modules\Listing\Http\Requests\WantedFilterRequest;
use Modules\Listing\Http\Requests\WantedFormRequest;
use Modules\Listing\Http\Requests\WantedSuggestionRequest;
use Modules\Listing\Models\WantedRequest;
use Modules\Listing\Models\WantedResponse;

final class WantedRequestController
{
    public function index(WantedFilterRequest $request): Response
    {
        return $this->view('listing::wanted.index', ['records' => WantedRequest::browse($request->validated()), 'categories' => WantedRequest::categoryOptions(), 'cities' => WantedRequest::cityOptions()]);
    }

    public function show(Request $request, int $wanted): Response
    {
        $record = WantedRequest::published($wanted);
        $active = (string) $request->user()?->status === 'active';
        $own = $active && (int) $request->user()->id === $record->user_id;
        $response = $active && ! $own ? WantedResponse::forSeller($record, $request->user()) : null;
        $choices = $active && ! $own && ! $response ? $record->suggestionOptions($request->user()) : [];

        return $this->view('listing::wanted.show', ['record' => $record, 'categories' => WantedRequest::categoryOptions(), 'cities' => WantedRequest::cityOptions(), 'choices' => $choices, 'own' => $own, 'suggested' => $response !== null]);
    }

    public function mine(Request $request): Response
    {
        return $this->view('listing::wanted.mine', ['records' => WantedRequest::forOwner($request->user())]);
    }

    public function create(Request $request): Response
    {
        WantedRequest::actor($request->user());

        return $this->form(new WantedRequest);
    }

    public function store(WantedFormRequest $request): RedirectResponse
    {
        $record = WantedRequest::submit($request->user(), $request->validated());

        return redirect()->route('wanted.own', $record)->with('status', __('listing::wanted.saved'));
    }

    public function own(Request $request, int $wanted): Response
    {
        $record = WantedRequest::owned($request->user(), $wanted);
        $responses = WantedResponse::forOwner($record, $request->user());

        return $this->view('listing::wanted.own', ['record' => $record, 'responses' => $responses, 'cards' => WantedResponse::listingCards($record, $responses), 'categories' => WantedRequest::categoryOptions(), 'cities' => WantedRequest::cityOptions()]);
    }

    public function edit(Request $request, int $wanted): Response
    {
        $record = WantedRequest::owned($request->user(), $wanted);
        abort_unless(in_array($record->state, ['pending', 'open', 'rejected'], true) && $record->expires_at->isFuture(), 409);

        return $this->form($record);
    }

    public function update(WantedFormRequest $request, int $wanted): RedirectResponse
    {
        $record = WantedRequest::owned($request->user(), $wanted);
        $record->updateFor($request->user(), $request->validated(), (int) $request->validated('revision'));

        return redirect()->route('wanted.own', $record)->with('status', __('listing::wanted.saved'));
    }

    public function close(WantedCloseRequest $request, int $wanted): RedirectResponse
    {
        $record = WantedRequest::owned($request->user(), $wanted);
        $record->closeFor($request->user(), $request->validated('state'), (int) $request->validated('revision'));

        return redirect()->route('wanted.own', $record)->with('status', __('listing::wanted.closed_saved'));
    }

    public function suggest(WantedSuggestionRequest $request, int $wanted): RedirectResponse
    {
        WantedResponse::submit($request->user(), $wanted, (int) $request->validated('listing_id'), (int) $request->validated('revision'));

        return redirect()->route('wanted.show', $wanted)->with('status', __('listing::wanted.suggestion_saved'));
    }

    private function form(WantedRequest $record): Response
    {
        return $this->view('listing::wanted.form', ['record' => $record, 'requestKey' => Str::uuid()->toString(), 'categories' => WantedRequest::categoryOptions(), 'cities' => WantedRequest::cityOptions()]);
    }

    private function view(string $view, array $data): Response
    {
        return response()->view($view, $data)->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
