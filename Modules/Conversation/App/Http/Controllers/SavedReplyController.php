<?php

declare(strict_types=1);

namespace Modules\Conversation\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Conversation\App\Models\SavedReply;

final class SavedReplyController extends Controller
{
    public function index(Request $request)
    {
        return response()->view('conversation::saved-replies', ['replies' => SavedReply::forOwner($request->user())])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request)
    {
        SavedReply::storeFor($request->user(), $this->payload($request));

        return redirect()->route('panel.saved-replies.index')->with('success', __('conversation::replies.saved'));
    }

    public function update(Request $request, string $reply)
    {
        $data = $this->payload($request);
        $version = $request->validate(['version' => ['required', 'integer', 'min:1', 'max:2147483647']])['version'];
        SavedReply::changeFor($request->user(), (int) $reply, (int) $version, $data);

        return redirect()->route('panel.saved-replies.index')->with('success', __('conversation::replies.saved'));
    }

    public function destroy(Request $request, string $reply)
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1', 'max:2147483647']]);
        SavedReply::changeFor($request->user(), (int) $reply, (int) $data['version'], null);

        return redirect()->route('panel.saved-replies.index')->with('success', __('conversation::replies.deleted'));
    }

    private function payload(Request $request): array
    {
        $request->merge(['title' => is_string($request->input('title')) ? trim($request->input('title')) : $request->input('title'), 'body' => is_string($request->input('body')) ? trim($request->input('body')) : $request->input('body')]);

        return $request->validate([
            'title' => ['required', 'string', 'max:80'],
            'body' => ['required', 'string', 'max:2000'],
            'position' => ['nullable', 'integer', 'between:0,9999'],
        ], [], ['title' => __('conversation::replies.label'), 'body' => __('conversation::replies.body'), 'position' => __('conversation::replies.position')]);
    }
}
