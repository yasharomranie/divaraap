<div class="stack" style="display:grid;gap:16px;min-width:0">
    @foreach($messages->getCollection()->reverse() as $message)
        <article class="card card__body stack" style="border:1px solid var(--line-hairline,#ddd);border-radius:12px;padding:16px;min-width:0">
            <strong>{{ __('support::messages.'.$message->kind) }}</strong>
            <p dir="auto" style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $message->body }}</p>
            <time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->timezone('Asia/Tehran')->format('Y/m/d H:i') }}</time>
        </article>
    @endforeach
</div>
{{ $messages->links('components.pagination') }}
