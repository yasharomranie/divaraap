@extends('site::layouts.app')
@section('title', __('notification::email.unsubscribe'))
@section('content')
<section class="shell shell--narrow">
    <div class="card card__body stack notification-email">
        <h1 class="text-title">{{ __('notification::email.unsubscribe') }}</h1>
        <p>{{ __('notification::email.unsubscribe_help') }}</p>
        <form method="POST" action="{{ route('notification-email.stop', $token) }}">
            @csrf
            <button class="button button--primary" type="submit">{{ __('notification::email.confirm_unsubscribe') }}</button>
        </form>
    </div>
</section>
@endsection
