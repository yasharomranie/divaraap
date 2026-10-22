@extends('panel::layouts.panel', ['panelSection' => $panelKey])
@section('title', __($copy.'.mine'))
@section('panel_content')
<div class="wanted stack stack--loose"><h1 class="title-page">{{ __($copy.'.mine') }}</h1><div class="wanted-actions"><a class="button button--primary" href="{{ route($routePrefix.'.create') }}">{{ __($copy.'.create') }}</a><a class="button button--ghost" href="{{ route($routePrefix.'.index') }}">{{ __($copy.'.browse') }}</a></div>
@forelse($records as $record)<article class="card stack" data-wanted="{{ $record->id }}"><h2 dir="auto">{{ $record->title }}</h2><p><span class="badge">{{ $record->stateLabel() }}</span></p><a href="{{ route($routePrefix.'.own', $record) }}" class="button button--ghost">{{ __($copy.'.manage') }}</a></article>@empty<p>{{ __($copy.'.empty') }}</p>@endforelse
{{ $records->links() }}</div>
@endsection
