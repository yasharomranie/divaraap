<?php

declare(strict_types=1);

namespace Modules\Listing\Filament\Admin\Resources;

use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Admin\Support\Filament\ResourceTableActions;
use Modules\Category\Models\Category;
use Modules\Listing\Filament\Admin\Resources\ListingCustomFieldResource\Pages;
use Modules\Listing\Models\ListingCustomField;
use UnitEnum;

class ListingCustomFieldResource extends Resource
{
    protected static ?string $model = ListingCustomField::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 30;

    public static function getModelLabel(): string
    {
        return __('listing::admin.custom_fields');
    }

    public static function getPluralModelLabel(): string
    {
        return __('listing::admin.custom_fields');
    }

    public static function getNavigationLabel(): string
    {
        return __('listing::admin.custom_fields');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('listing::admin.catalog');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('label')->label(__('listing::admin.label'))->extraInputAttributes(['dir' => 'auto'])
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, $set, ?ListingCustomField $record): void {
                    $set('name', ListingCustomField::uniqueNameFromLabel((string) $state, $record));
                }),
            TextInput::make('name')->label(__('listing::admin.name'))->extraInputAttributes(['dir' => 'auto'])
                ->required()
                ->maxLength(255)
                ->regex('/^[a-z0-9_]+$/')
                ->helperText(__('listing::admin.only_lowercase_letters_numbers_and_underscore'))
                ->unique(ignoreRecord: true),
            Select::make('type')->label(__('listing::admin.type'))
                ->required()
                ->options(ListingCustomField::typeOptions())
                ->live(),
            Select::make('category_id')->label(__('listing::admin.category'))
                ->options(fn (): array => Category::activeIdNameOptions())
                ->searchable()
                ->preload()
                ->nullable()
                ->helperText(__('listing::admin.leave_empty_to_apply_this_field_to_all_categories')),
            TagsInput::make('options')->label(__('listing::admin.select_options'))
                ->placeholder(__('listing::admin.add_an_option_and_press_enter'))
                ->visible(fn ($get): bool => $get('type') === ListingCustomField::TYPE_SELECT)
                ->helperText(__('listing::admin.used_only_for_select_type_fields')),
            TextInput::make('sort_order')->label(__('listing::admin.sort_order'))->extraInputAttributes(['dir' => 'auto'])
                ->numeric()
                ->default(0),
            TextInput::make('placeholder')->label(__('listing::admin.placeholder'))->extraInputAttributes(['dir' => 'auto'])
                ->maxLength(255),
            Textarea::make('help_text')->label(__('listing::admin.help_text'))
                ->rows(2)
                ->maxLength(500),
            Toggle::make('is_required')->label(__('listing::admin.is_required'))
                ->default(false),
            Toggle::make('is_active')->label(__('listing::admin.is_active'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label(__('listing::admin.id'))->sortable(),
                TextColumn::make('label')->label(__('listing::admin.label'))->searchable()->sortable(),
                TextColumn::make('name')->label(__('listing::admin.name'))->searchable()->copyable(),
                TextColumn::make('type')->label(__('listing::admin.type'))->sortable(),
                TextColumn::make('category.name')->label(__('listing::admin.category'))->default(__('listing::admin.all_categories')),
                IconColumn::make('is_required')->label(__('listing::admin.is_required'))->boolean()->label(__('listing::admin.required')),
                IconColumn::make('is_active')->label(__('listing::admin.is_active'))->boolean()->label(__('listing::admin.active')),
                TextColumn::make('sort_order')->label(__('listing::admin.sort_order'))->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->actions(ResourceTableActions::editDelete());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListListingCustomFields::route('/'),
            'create' => Pages\CreateListingCustomField::route('/create'),
            'edit' => Pages\EditListingCustomField::route('/{record}/edit'),
        ];
    }
}
