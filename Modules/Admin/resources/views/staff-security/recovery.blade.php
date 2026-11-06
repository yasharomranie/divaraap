@extends('site::layouts.app')
@section('title', __('admin::security.recovery'))
@section('content')
<section class="shell shell--narrow"><div class="card card__body stack staff-security">
    <h1 class="text-title">{{ __('admin::security.recovery') }}</h1><p>{{ __('admin::security.recovery_help') }}</p>
    <ul dir="ltr" class="staff-security__codes">@foreach($codes as $code)<li><code>{{ $code }}</code></li>@endforeach</ul>
    <a class="button button--primary" href="{{ url(app(\Modules\Admin\Models\StaffSecurity::class)->home(auth()->user())) }}">{{ __('admin::security.continue') }}</a>
</div></section>
@endsection
