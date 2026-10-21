@if($errors->any())
<div class="alert alert--critical" role="alert" id="wanted-errors">
    <p>{{ __('listing::wanted.error_summary') }}</p>
    <ul>@foreach($errors->messages() as $field => $messages)<li><a href="#wanted-{{ $field }}">{{ $messages[0] }}</a></li>@endforeach</ul>
</div>
@endif
