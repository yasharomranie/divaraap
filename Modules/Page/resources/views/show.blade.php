@extends('site::layouts.app')

@section('title', $page->metaTitleValue())
@section('description', $page->metaDescriptionValue())

@section('content')
<div class="shell shell--narrow page">
    <article class="stack stack--loose">
        <nav class="breadcrumb" aria-label="{{ __('site::messages.breadcrumb') }}">
            <a href="{{ route('home') }}">{{ __('site::messages.home') }}</a>
            <span class="breadcrumb__separator">/</span>
            <span>{{ $page->localizedValue('title') }}</span>
        </nav>

        <header class="stack stack--tight">
            <h1 class="title-page" dir="auto">{{ $page->localizedValue('title') }}</h1>
            @if(filled($page->localizedValue('excerpt')))
                <p class="text-lead">{{ $page->localizedValue('excerpt') }}</p>
            @endif
        </header>

        @includeIf('advertising::slot', ['placementKey' => 'page-top', 'position' => 0])

        <div class="card">
            <div class="card__body">
                <div class="prose" dir="auto">
                    @foreach($page->bodyParagraphs() as $paragraph)
                        @php $lines = preg_split('/\R/u', $paragraph); @endphp
                        @if(count($lines) > 1)
                            <h2>{{ array_shift($lines) }}</h2>
                            <p>{{ implode(' ', $lines) }}</p>
                        @else
                            <p>{{ $paragraph }}</p>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        @includeIf('advertising::slot', ['placementKey' => 'page-bottom', 'position' => 0])

        <p class="text-meta">
            {{ __('page::messages.last_updated', ['date' => \App\Support\PersianLocale::date($page->getAttribute('updated_at'))]) }}
        </p>
    </article>
</div>
@endsection
