<?php

declare(strict_types=1);

namespace Modules\Listing\Support;

use App\Support\Money;
use App\Support\PersianSearch;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\Listing\Models\ListingCustomField;
use Modules\Location\Models\City;

final class PriceGuide
{
    public static function rules(array $input, bool $required = false): array
    {
        $category = filter_var($input['category_id'] ?? null, FILTER_VALIDATE_INT) ?: null;

        return [
            'category_id' => [$required ? 'required' : 'nullable', 'required_with:search,city_id,condition,currency', 'integer', Rule::in(array_keys(Category::priceGuideOptions()))],
            'city_id' => ['nullable', 'integer', Rule::in(array_keys(City::wantedOptions()))],
            'currency' => ['nullable', 'string', Rule::in(array_keys(ListingPanelHelper::currencyOptions()))],
            'condition' => ['nullable', 'string', 'max:120', Rule::in(array_keys(ListingCustomField::priceGuideConditions($category)))],
            'search' => ['nullable', 'string', 'min:2', 'max:120', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || PersianSearch::terms($value) === null || PersianSearch::terms($value) === []) {
                    $fail(__('listing::prices.search_error'));
                }
            }],
        ];
    }

    public static function report(array $input): array
    {
        $filters = Validator::make($input, self::rules($input, true))->validate();
        $filters['currency'] = $filters['currency'] ?? ListingPanelHelper::defaultCurrency();
        $samples = Listing::priceGuideSamples($filters);
        $limited = $samples->count() > 1000;
        $sellers = [];
        $prices = [];
        foreach ($samples->take(1000) as $sample) {
            $owner = (int) $sample->user_id;
            if (($sellers[$owner] ?? 0) >= 3 || ! Money::valid((string) $sample->price, $filters['currency'])) {
                continue;
            }
            $sellers[$owner] = ($sellers[$owner] ?? 0) + 1;
            $prices[] = Money::decimal((string) $sample->price);
        }
        usort($prices, fn (string $left, string $right): int => bccomp($left, $right, 2));
        $count = count($prices);
        $sufficient = $count >= 10 && count($sellers) >= 5;
        $stats = null;
        if ($sufficient) {
            $median = $count % 2 === 1 ? $prices[intdiv($count, 2)] : bcdiv(bcadd($prices[intdiv($count, 2) - 1], $prices[intdiv($count, 2)], 2), '2', 3);
            $median = $filters['currency'] === 'IRR' ? bcadd($median, '0.5', 0) : bcadd($median, '0.005', 2);
            $stats = ['median' => Money::decimal($median), 'lower' => $prices[intdiv($count + 3, 4) - 1], 'upper' => $prices[intdiv(3 * $count + 3, 4) - 1], 'min' => $prices[0], 'max' => $prices[$count - 1]];
        }

        return ['count' => $count, 'sellers' => count($sellers), 'limited' => $limited, 'sufficient' => $sufficient, 'stats' => $stats, 'currency' => $filters['currency'], 'from' => now()->subDays(30), 'until' => now()];
    }
}
