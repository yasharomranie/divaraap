<div class="space-y-4" data-experiment-report="{{ $experiment->id }}">
    <h2 class="font-semibold">{{ $experiment->name }}</h2>
    <p>{{ $experiment->statusLabel() }} · {{ __('advertising::experiments.split') }}</p>
    <p>{{ __('advertising::experiments.limits') }}</p>
    @if($experiment->started_at && $experiment->started_at->lt(now()->subDays(89)))
        <p role="status">{{ __('advertising::experiments.expired_report') }}</p>
    @endif
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        @foreach($rows as $arm => $row)
            <section class="space-y-2 rounded-lg border border-gray-200 p-4 dark:border-gray-700" aria-labelledby="experiment-arm-{{ $arm }}">
                <h3 id="experiment-arm-{{ $arm }}" class="font-semibold"><bdi>{{ $arm }}</bdi> · {{ $row['title'] }}</h3>
                <dl class="space-y-2">
                    @foreach(['impressions', 'clicks', 'conversations', 'ctr'] as $metric)
                        <div class="flex flex-wrap justify-between gap-2">
                            <dt>{{ __('advertising::experiments.'.$metric) }}</dt>
                            <dd><bdi>{{ $row[$metric] === null ? '—' : number_format($row[$metric], $metric === 'ctr' ? 2 : 0).($metric === 'ctr' ? '%' : '') }}</bdi></dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endforeach
    </div>
    <p>{{ __('advertising::experiments.no_winner') }}</p>
</div>
