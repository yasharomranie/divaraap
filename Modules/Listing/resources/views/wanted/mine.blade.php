@extends('panel::layouts.panel', ['panelSection' => 'wanted'])
@section('title', __('listing::wanted.mine'))
@section('panel_content')
<div class="wanted stack stack--loose"><h1 class="title-page">{{ __('listing::wanted.mine') }}</h1><div class="wanted-actions"><a class="button button--primary" href="{{ route('wanted.create') }}">{{ __('listing::wanted.create') }}</a><a class="button button--ghost" href="{{ route('wanted.index') }}">{{ __('listing::wanted.browse') }}</a></div>
@forelse($records as $record)<article class="card stack" data-wanted="{{ $record->id }}"><h2 dir="auto">{{ $record->title }}</h2><p><span class="badge">{{ $record->stateLabel() }}</span></p><a href="{{ route('wanted.own', $record) }}" class="button button--ghost">{{ __('listing::wanted.manage') }}</a></article>@empty<p>{{ __('listing::wanted.empty') }}</p>@endforelse
{{ $records->links() }}</div>
@endsection
