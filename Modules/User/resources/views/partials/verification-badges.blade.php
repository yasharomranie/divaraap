@if($verification['email'] || $verification['reviewed'])
    <div class="row row--wrap seller-verification" aria-label="{{ __('user::verification.title') }}">
        @if($verification['email'])
            <span class="badge badge--positive" title="{{ __('user::verification.email_hint') }}" data-verification="email">{{ __('user::verification.email') }}</span>
        @endif
        @if($verification['reviewed'])
            <span class="badge badge--positive" title="{{ __('user::verification.reviewed_hint') }}" data-verification="staff">{{ __('user::verification.reviewed') }}</span>
        @endif
    </div>
    @if($verification['reviewed'])
        <p class="field__hint">{{ __('user::verification.reviewed_hint') }}</p>
        <p class="field__hint">{{ __('user::verification.date') }}: {{ \App\Support\PersianLocale::date($verification['reviewed_at']->copy()->timezone('Asia/Tehran'), true) }}</p>
    @endif
@endif
