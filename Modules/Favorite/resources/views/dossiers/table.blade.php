        <p id="comparison-scroll" class="text-muted">{{ __('favorite::comparison.scroll') }}</p>
        <div class="comparison-scroll" tabindex="0" role="region" aria-labelledby="comparison-scroll">
            <table class="comparison-table" style="min-width: {{ 110 + 170 * $listings->count() }}px">
                <caption class="visually-hidden">{{ __('favorite::comparison.title') }}</caption>
                <thead><tr><th scope="col">{{ __('favorite::comparison.specifications') }}</th>
                @foreach($listings as $listing)
                    <th scope="col">
                        @if($image = $listing->primaryImageUrl('card'))<img src="{{ $image }}" alt="{{ $listing->title }}" width="160" height="110" loading="lazy">@endif
                        <a href="{{ route('listings.show', $listing) }}" dir="auto">{{ $listing->title }}</a>
                        @if(!($readOnly ?? false))<form method="POST" action="{{ route('comparison.remove', $listing->id) }}">@csrf @method('DELETE')<button class="button button--ghost button--small" type="submit" aria-label="{{ __('favorite::comparison.remove') }}: {{ $listing->title }}">{{ __('favorite::comparison.remove') }}</button></form>@endif
                    </th>
                @endforeach
                </tr></thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr @class(['comparison-different' => $row['different']])><th scope="row">{{ $row['label'] }}</th>
                        @foreach($row['values'] as $value)<td dir="auto">{{ $value }}</td>@endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
