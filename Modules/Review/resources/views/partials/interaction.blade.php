@if($review->hasInteractionEvidence())
    <div class="stack stack--tight" data-review-interaction>
        <span class="badge badge--positive">{{ __('review::quality.badge') }}</span>
        <p class="field__hint">{{ __('review::quality.meaning') }}</p>
    </div>
@endif
