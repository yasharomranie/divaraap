@extends('panel::layouts.panel', ['panelSection' => 'inbox'])
@section('title', __('conversation::replies.title'))
@section('panel_content')
@php $failedId = is_scalar(old('reply_id')) ? (string) old('reply_id') : ''; @endphp
<div class="saved-replies stack stack--loose">
    <header class="stack stack--tight">
        <a href="{{ route('panel.inbox.index') }}" class="button button--ghost">{{ __('conversation::replies.back') }}</a>
        <h1 class="title-page">{{ __('conversation::replies.title') }}</h1>
        <p class="text-muted">{{ __('conversation::replies.hint') }}</p>
        <p class="field__hint">{{ __('conversation::replies.remaining', ['count' => \Modules\Conversation\App\Models\SavedReply::LIMIT]) }}</p>
    </header>
    @if($errors->any())
        <div class="alert alert--critical" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <details class="card saved-replies__item" @if($replies->isEmpty() || ($errors->any() && $failedId === '')) open @endif>
        <summary>{{ __('conversation::replies.add') }}</summary>
        <form action="{{ route('panel.saved-replies.store') }}" method="post" class="stack">
            @csrf
            @include('conversation::partials.reply-fields', ['prefix' => 'new', 'title' => $failedId !== '' ? '' : old('title', ''), 'body' => $failedId !== '' ? '' : old('body', ''), 'position' => $failedId !== '' ? 0 : old('position', 0)])
            <button class="button button--primary" type="submit">{{ __('conversation::replies.add') }}</button>
        </form>
    </details>
    @forelse($replies as $reply)
        @php $failed = $failedId === (string) $reply->id; $version = $failed ? old('version', $reply->version) : $reply->version; @endphp
        <details class="card saved-replies__item" data-reply-id="{{ $reply->id }}" @if($failed) open @endif>
            <summary><span dir="auto">{{ $reply->title }}</span><span class="text-meta">{{ __('conversation::replies.edit') }}</span></summary>
            <form action="{{ route('panel.saved-replies.update', $reply->id) }}" method="post" class="stack">
                @csrf
                @method('PUT')
                <input type="hidden" name="reply_id" value="{{ $reply->id }}">
                <input type="hidden" name="version" value="{{ is_scalar($version) ? $version : '' }}">
                @include('conversation::partials.reply-fields', ['prefix' => 'reply-'.$reply->id, 'title' => $failed ? old('title', $reply->title) : $reply->title, 'body' => $failed ? old('body', $reply->body) : $reply->body, 'position' => $failed ? old('position', $reply->position) : $reply->position])
                <button class="button button--primary" type="submit">{{ __('conversation::replies.save') }}</button>
            </form>
            <form action="{{ route('panel.saved-replies.destroy', $reply->id) }}" method="post" data-confirm="{{ __('conversation::replies.confirm') }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="version" value="{{ $reply->version }}">
                <button class="button button--ghost" type="submit">{{ __('conversation::replies.delete') }}</button>
            </form>
        </details>
    @empty
        <p class="text-muted">{{ __('conversation::replies.empty') }}</p>
    @endforelse
    <p class="field__hint">{{ __('conversation::replies.private') }}</p>
</div>
@endsection
