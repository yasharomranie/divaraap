<?php

declare(strict_types=1);

namespace Modules\Category\Filament\Admin\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Admin\Support\Filament\AccessControlledResource as Resource;
use Modules\Admin\Support\Filament\ResourceTableActions;
use Modules\Admin\Support\Filament\ResourceTableColumns;
use Modules\Category\Filament\Admin\Resources\CategoryResource\Pages;
use Modules\Category\Models\Category;
use UnitEnum;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    public static function getModelLabel(): string
    {
        return __('category::admin.category');
    }

    public static function getPluralModelLabel(): string
    {
        return __('category::admin.categories');
    }

    public static function getNavigationLabel(): string
    {
        return __('category::admin.categories');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('category::admin.catalog');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')->label(__('category::admin.name'))->extraInputAttributes(['dir' => 'auto'])->required()->maxLength(255)->live(onBlur: true)->afterStateUpdated(fn ($state, $set) => $set('slug', Category::uniqueSlug((string) $state))),
            TextInput::make('slug')->label(__('category::admin.slug'))->extraInputAttributes(['dir' => 'ltr'])->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('description')->label(__('category::admin.description'))->extraInputAttributes(['dir' => 'auto'])->maxLength(500),
            TextInput::make('icon')->label(__('category::admin.icon'))->extraInputAttributes(['dir' => 'auto'])->maxLength(100),
            Select::make('parent_id')->label(__('category::admin.parent_category'))->options(fn (): array => Category::rootIdNameOptions())->nullable()->searchable(),
            TextInput::make('sort_order')->label(__('category::admin.sort_order'))->extraInputAttributes(['dir' => 'auto'])->numeric()->default(0),
            Toggle::make('is_active')->label(__('category::admin.is_active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            ResourceTableColumns::id(),
            TextColumn::make('name')->label(__('category::admin.name'))
                ->searchable()
                ->formatStateUsing(fn (string $state, Category $record): string => $record->parent_id === null ? Category::localizedName($state) : '↳ '.Category::localizedName($state))
                ->weight(fn (Category $record): string => $record->parent_id === null ? 'semi-bold' : 'normal'),
            TextColumn::make('parent.name')->label(__('category::admin.parent'))->default('-'),
            TextColumn::make('children_count')->label(__('category::admin.subcategories')),
            TextColumn::make('listings_count')->label(__('category::admin.listings')),
            ResourceTableColumns::activeIcon(),
            TextColumn::make('sort_order')->label(__('category::admin.sort_order'))->sortable(),
        ])->actions([
            Action::make('toggleChildren')
                ->label(fn (Category $record, Pages\ListCategories $livewire): string => $livewire->hasExpandedChildren($record) ? __('category::admin.hide_subcategories') : __('category::admin.show_subcategories'))
                ->icon(fn (Category $record, Pages\ListCategories $livewire): string => $livewire->hasExpandedChildren($record) ? 'heroicon-o-chevron-down' : 'heroicon-o-chevron-right')
                ->action(fn (Category $record, Pages\ListCategories $livewire) => $livewire->toggleChildren($record))
                ->visible(fn (Category $record): bool => $record->parent_id === null && $record->children_count > 0),
            ...ResourceTableActions::editActivityDelete(static::class),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'activities' => Pages\ListCategoryActivities::route('/{record}/activities'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
