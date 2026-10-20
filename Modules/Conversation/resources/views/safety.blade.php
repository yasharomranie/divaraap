@extends('panel::layouts.panel', ['panelSection' => 'inbox'])
@section('title', __('conversation::safety.title'))
@section('panel_content')
<header class="stack stack--tight">
    <a class="button button--ghost" href="{{ route('panel.inbox.index', ['conversation' => $conversation->id]) }}">{{ __('conversation::safety.back') }}</a>
    <h1 class="title-page">{{ __('conversation::safety.title') }}</h1>
    <p dir="auto">{{ $partner['name'] ?? __('conversation::safety.unknown') }}</p>
</header>
<section class="card"><div class="card__body stack stack--loose">
    <h2 class="title-section">{{ $blockedByMe ? __('conversation::safety.unblock') : __('conversation::safety.block') }}</h2>
    <p>{{ __('conversation::safety.block_hint') }}</p>
    @if($blockedByMe)<p>{{ __('conversation::safety.mine') }}</p>@endif
    @if($communicationBlocked)<p class="alert" role="status">{{ __('conversation::safety.unavailable') }}</p>@endif
    <form class="stack stack--tight" method="post" action="{{ route('conversations.block', $conversation) }}">
        @csrf
        <input type="hidden" name="blocked" value="{{ $blockedByMe ? 0 : 1 }}">
        <label class="row"><input type="checkbox" name="confirmed" value="1" required><span>{{ __('conversation::safety.confirm') }}</span></label>
        <button class="button {{ $blockedByMe ? 'button--secondary' : 'button--critical' }}" type="submit">{{ $blockedByMe ? __('conversation::safety.unblock') : __('conversation::safety.block') }}</button>
    </form>
    <a class="button button--ghost" href="{{ route('panel.blocked-users') }}">{{ __('conversation::safety.blocked_users') }}</a>
</div></section>
<section class="card"><div class="card__body stack stack--loose">
    <h2 class="title-section">{{ __('conversation::safety.report') }}</h2>
    <p>{{ __('conversation::safety.report_hint') }}</p>
    <form class="stack stack--loose" method="post" action="{{ route('conversations.report', $conversation) }}">
        @csrf
        <div class="field">
            <label class="field__label" for="safety-reason">{{ __('conversation::safety.reason') }}</label>
            <select class="select" id="safety-reason" name="reason" required>
                @foreach(\Modules\Report\Models\Report::conversationReasons() as $reason)<option value="{{ $reason }}" @selected(old('reason') === $reason)>{{ __('report::messages.reason_'.$reason) }}</option>@endforeach
            </select>
        </div>
        <div class="field">
            <label class="field__label" for="safety-message">{{ __('conversation::safety.message') }}</label>
            <select class="select" id="safety-message" name="message_id">
                <option value="">{{ __('conversation::safety.general') }}</option>
                @foreach($messages as $message)<option value="{{ $message->id }}" @selected(is_scalar(old('message_id')) && (string) old('message_id') === (string) $message->id)>{{ \Illuminate\Support\Str::limit($message->body, 80) }}</option>@endforeach
            </select>
        </div>
        <div class="field">
            <label class="field__label" for="safety-details">{{ __('conversation::safety.details') }}</label>
            <textarea class="textarea" dir="auto" id="safety-details" name="details" maxlength="1000" rows="3">{{ is_string(old('details')) ? old('details') : '' }}</textarea>
        </div>
        <label class="row"><input type="checkbox" name="confirmed" value="1" required><span>{{ __('conversation::safety.report_confirm') }}</span></label>
        <button class="button button--primary" type="submit">{{ __('conversation::safety.report') }}</button>
    </form>
</div></section>
@endsection
