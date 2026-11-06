@extends('site::layouts.app')
@section('title', __('notification::email.unsubscribe'))
@section('content')
<section class="shell shell--narrow"><div class="card card__body notification-email"><p role="status">{{ __('notification::email.unsubscribed') }}</p></div></section>
@endsection
