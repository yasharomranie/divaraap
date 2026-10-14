<?php

declare(strict_types=1);

namespace Modules\Listing\Support;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Listing\Models\ListingCustomField;

class ListingCustomFieldSchemaBuilder
{
    public static function hasFields(?int $categoryId): bool
    {
        return ListingCustomField::query()
            ->active()
            ->forCategory($categoryId)
            ->exists();
    }

    public static function formComponents(?int $categoryId): array
    {
        return ListingCustomField::query()
            ->active()
            ->forCategory($categoryId)
            ->ordered()
            ->get()
            ->map(fn (ListingCustomField $field): ?Component => self::makeFieldComponent($field))
            ->filter()
            ->values()
            ->all();
    }

    public static function presentableValues(?int $categoryId, array $values): array
    {
        if ($values === []) {
            return [];
        }

        $fieldsByName = ListingCustomField::query()
            ->active()
            ->forCategory($categoryId)
            ->ordered()
            ->get()
            ->keyBy('name');

        $result = [];
        $labels = __('listing::messages.field_labels');
        $options = __('listing::messages.field_options');

        foreach ($values as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $field = $fieldsByName->get((string) $key);
            $label = $field?->label ?: Str::headline((string) $key);
            $label = is_array($labels) ? ($labels[$label] ?? $label) : $label;

            if (is_bool($value)) {
                $displayValue = $value ? __('listing::messages.yes') : __('listing::messages.no');
            } elseif (is_array($value)) {
                $displayValue = implode(', ', array_map(fn ($item): string => (string) $item, $value));
            } elseif ($field?->type === ListingCustomField::TYPE_DATE) {
                try {
                    $displayValue = Carbon::parse((string) $value)->locale(app()->getLocale())->isoFormat('LL');
                } catch (\Throwable) {
                    $displayValue = (string) $value;
                }
            } else {
                $displayValue = (string) $value;
            }

            if ($field?->type === ListingCustomField::TYPE_SELECT && is_array($options)) {
                $displayValue = $options[$displayValue] ?? $displayValue;
            }

            if (trim($displayValue) === '') {
                continue;
            }

            $result[] = [
                'label' => $label,
                'value' => $displayValue,
            ];
        }

        return $result;
    }

    private static function makeFieldComponent(ListingCustomField $field): ?Component
    {
        $statePath = "custom_fields.{$field->name}";

        $component = match ($field->type) {
            ListingCustomField::TYPE_TEXT => TextInput::make($statePath)->maxLength(255),
            ListingCustomField::TYPE_TEXTAREA => Textarea::make($statePath)->rows(3)->columnSpanFull(),
            ListingCustomField::TYPE_NUMBER => TextInput::make($statePath)->numeric(),
            ListingCustomField::TYPE_SELECT => Select::make($statePath)->options(collect($field->selectOptions())->map(fn (string $option): string => __('listing::messages.field_options')[$option] ?? $option)->all())->searchable(),
            ListingCustomField::TYPE_BOOLEAN => Toggle::make($statePath),
            ListingCustomField::TYPE_DATE => DatePicker::make($statePath),
            default => null,
        };

        if (! $component) {
            return null;
        }

        $component = $component->label(__('listing::messages.field_labels')[$field->label] ?? $field->label);

        if ($field->is_required) {
            $component = $component->required();
        }

        if (filled($field->placeholder) && method_exists($component, 'placeholder')) {
            $component = $component->placeholder($field->placeholder);
        }

        if (filled($field->help_text)) {
            $component = $component->helperText($field->help_text);
        }

        return $component;
    }
}
