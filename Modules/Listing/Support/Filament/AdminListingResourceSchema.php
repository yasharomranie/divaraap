<?php

declare(strict_types=1);

namespace Modules\Listing\Support\Filament;

use App\Rules\IranianMobile;
use App\Support\Filament\CurrencySelect;
use App\Support\Filament\ModelStateSelect;
use App\Support\Filament\ModelStateSelectColumn;
use App\Support\Filament\MoneyInput;
use App\Support\IranianPhone;
use App\Support\Money;
use App\Support\PersianLocale;
use Cheesegrits\FilamentGoogleMaps\Fields\Map;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Modules\Admin\Support\Filament\ResourceTableActions;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\Listing\States\ListingStatus;
use Modules\Listing\Support\ListingCustomFieldSchemaBuilder;
use Modules\Listing\Support\ListingPanelHelper;
use Modules\Location\Models\City;
use Modules\Location\Models\Country;
use Modules\Location\Support\CountryCodeManager;
use Modules\Site\App\Support\LocalMedia;
use Modules\Video\Support\Filament\VideoFormSchema;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class AdminListingResourceSchema
{
    public static function form(): array
    {
        return [
            TextInput::make('title')->label(__('listing::admin.title'))->extraInputAttributes(['dir' => 'auto'])->required()->maxLength(255)->live(onBlur: true)->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state).'-'.Str::random(4))),
            TextInput::make('slug')->label(__('listing::admin.slug'))->extraInputAttributes(['dir' => 'ltr'])->required()->maxLength(255)->unique(ignoreRecord: true),
            Textarea::make('description')->label(__('listing::admin.description'))->rows(4),
            MoneyInput::make('price')->label(__('listing::admin.price'))->nullable(),
            CurrencySelect::make()->label(__('listing::admin.currency')),
            Select::make('category_id')->label(__('listing::admin.category'))
                ->options(fn (): array => Category::activeIdNameOptions())
                ->searchable()
                ->live()
                ->afterStateUpdated(fn ($state, $set) => $set('custom_fields', []))
                ->nullable(),
            Select::make('user_id')->label(__('listing::admin.user'))->relationship('user', 'email')->label(__('listing::admin.owner'))->searchable()->preload()->nullable(),
            Section::make(__('listing::admin.custom_fields'))
                ->description(__('listing::admin.category_specific_listing_attributes'))
                ->schema(fn (Get $get): array => ListingCustomFieldSchemaBuilder::formComponents(
                    ($categoryId = $get('category_id')) ? (int) $categoryId : null
                ))
                ->columns(2)
                ->columnSpanFull()
                ->visible(fn (Get $get): bool => ListingCustomFieldSchemaBuilder::hasFields(
                    ($categoryId = $get('category_id')) ? (int) $categoryId : null
                )),
            ModelStateSelect::make('status')->label(__('listing::admin.status'))->options(ListingStatus::options())->required(),
            PhoneInput::make('contact_phone')->rules([new IranianMobile])->dehydrateStateUsing(fn ($state) => $state === null ? null : IranianPhone::normalize((string) $state))->label(__('listing::admin.contact_phone'))->defaultCountry(CountryCodeManager::defaultCountryIso2())->nullable(),
            TextInput::make('contact_email')->label(__('listing::admin.contact_email'))->extraInputAttributes(['dir' => 'ltr'])->email()->maxLength(255),
            Toggle::make('is_featured')->label(__('listing::admin.is_featured'))->default(false),
            Select::make('country')->label(__('listing::admin.country'))
                ->options(fn (): array => Country::nameOptions())
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdated(fn ($state, $set) => $set('city', null))
                ->nullable(),
            Select::make('city')->label(__('listing::admin.city'))
                ->options(fn (Get $get): array => City::nameOptions($get('country')))
                ->searchable()
                ->preload()
                ->nullable(),
            Map::make('location')->label(__('listing::admin.location'))
                ->visible(fn (): bool => ListingPanelHelper::googleMapsEnabled())
                ->draggable()
                ->clickable()
                ->autocomplete('city')
                ->autocompleteReverse(true)
                ->reverseGeocode([
                    'city' => '%L',
                ])
                ->defaultLocation([41.0082, 28.9784])
                ->defaultZoom(10)
                ->height('320px')
                ->columnSpanFull(),
            SpatieMediaLibraryFileUpload::make('images')->label(__('listing::admin.images'))
                ->collection('listing-images')
                ->disk(fn (): string => LocalMedia::disk())
                ->customProperties(fn (): array => Listing::mediaCustomProperties())
                ->multiple()
                ->image()
                ->reorderable(),
            VideoFormSchema::listingSection(),
        ];
    }

    public static function configureTable(Table $table, string $resourceClass): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('images')->label(__('listing::admin.images'))
                    ->collection('listing-images')
                    ->circular(),
                TextColumn::make('id')->label(__('listing::admin.id'))->sortable(),
                TextColumn::make('title')->label(__('listing::admin.title'))->searchable()->sortable()->limit(40),
                TextColumn::make('category.name')->label(__('listing::admin.category'))->sortable(),
                TextColumn::make('user.email')->label(__('listing::admin.owner'))->searchable()->toggleable()->sortable(),
                TextColumn::make('price')->label(__('listing::admin.price'))
                    ->formatStateUsing(fn (Listing $record): string => $record->panelPriceLabel())
                    ->sortable(),
                ModelStateSelectColumn::make('status')->label(__('listing::admin.status'))
                    ->options(ListingStatus::options())
                    ->disabled(fn (Listing $record): bool => ! $resourceClass::canEdit($record))
                    ->sortable(),
                IconColumn::make('is_featured')->label(__('listing::admin.is_featured'))->boolean()->label(__('listing::admin.featured'))->sortable(),
                TextColumn::make('city')->label(__('listing::admin.city'))->sortable(),
                TextColumn::make('country')->label(__('listing::admin.country'))->sortable(),
                TextColumn::make('created_at')->label(__('listing::admin.created_at'))->formatStateUsing(fn ($state): string => PersianLocale::date($state, true))->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('listing::admin.status'))->options(ListingStatus::options()),
                SelectFilter::make('category_id')->label(__('listing::admin.category'))
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('user_id')->label(__('listing::admin.owner'))
                    ->relationship('user', 'email')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('country')->label(__('listing::admin.country'))
                    ->options(fn (): array => Country::nameOptions())
                    ->searchable(),
                SelectFilter::make('city')->label(__('listing::admin.city'))
                    ->options(fn (): array => City::nameOptions(null, false))
                    ->searchable(),
                TernaryFilter::make('is_featured')->label(__('listing::admin.featured')),
                Filter::make('created_at')
                    ->label(__('listing::admin.created_date'))
                    ->schema([
                        DatePicker::make('from')->label(__('listing::admin.from')),
                        DatePicker::make('until')->label(__('listing::admin.until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $builder, string $date): Builder => $builder->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $builder, string $date): Builder => $builder->whereDate('created_at', '<=', $date))),
                Filter::make('price')
                    ->label(__('listing::admin.price_range'))
                    ->schema([
                        MoneyInput::make('min')->label(__('listing::admin.min'))->extraInputAttributes(['dir' => 'auto'])->label(__('listing::admin.min')),
                        MoneyInput::make('max')->label(__('listing::admin.max'))->extraInputAttributes(['dir' => 'auto'])->label(__('listing::admin.max')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(Money::valid($data['min'] ?? null, 'IRR'), fn (Builder $builder): Builder => $builder->where('currency', 'IRR')->where('price', '>=', Money::decimal($data['min'])))
                        ->when(Money::valid($data['max'] ?? null, 'IRR'), fn (Builder $builder): Builder => $builder->where('currency', 'IRR')->where('price', '<=', Money::decimal($data['max'])))),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            ->filtersFormWidth('7xl')
            ->persistFiltersInSession()
            ->defaultSort('id', 'desc')
            ->actions(ResourceTableActions::editActivityDelete($resourceClass));
    }
}
