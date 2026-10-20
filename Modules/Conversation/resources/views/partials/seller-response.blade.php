<section class="stack stack--tight seller-response" aria-label="{{ __('conversation::response.title') }}">
    <h2 class="title-card">{{ __('conversation::response.title') }}</h2>
    @if($responseSummary)
        <p>{{ __('conversation::response.median', ['time' => \Modules\Conversation\App\Models\Conversation::sellerResponseLabel($responseSummary)]) }}</p>
        <p>{{ __('conversation::response.rate', ['rate' => \App\Support\PersianLocale::number($responseSummary['rate']), 'answered' => \App\Support\PersianLocale::number($responseSummary['answered']), 'total' => \App\Support\PersianLocale::number($responseSummary['total'])]) }}</p>
        <p class="field__hint">{{ __('conversation::response.hint') }}</p>
    @else
        <p class="field__hint">{{ __('conversation::response.insufficient') }}</p>
        @if($responseOwner ?? false)<p class="field__hint">{{ __('conversation::response.minimum') }}</p>@endif
    @endif
</section>
