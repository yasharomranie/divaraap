<?php

declare(strict_types=1);

namespace Modules\Page\Filament\Admin\Resources;

use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Modules\Admin\Support\Filament\AccessControlledResource as Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Admin\Support\Filament\ResourceTableActions;
use Modules\Admin\Support\Filament\ResourceTableColumns;
use Modules\Page\Filament\Admin\Resources\PageResource\Pages;
use Modules\Page\Models\Page;
use UnitEnum;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $label = 'Page';

    protected static ?string $pluralLabel = 'Pages';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('page::admin.page');
    }

    public static function getPluralModelLabel(): string
    {
        return __('page::admin.pages');
    }

    public static function getNavigationLabel(): string
    {
        return __('page::admin.pages');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('page::admin.content');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('title')->label(__('page::admin.title'))->extraInputAttributes(['dir' => 'auto'])->required()->maxLength(150)->live(onBlur: true)
                ->afterStateUpdated(static fn (Set $set, ?string $state): mixed => $set('slug', Page::uniqueSlug((string) $state))),
            TextInput::make('slug')->label(__('page::admin.slug'))->extraInputAttributes(['dir' => 'ltr'])->required()->maxLength(150)->unique(ignoreRecord: true),
            TextInput::make('excerpt')->label(__('page::admin.excerpt'))->extraInputAttributes(['dir' => 'auto'])->maxLength(255),
            Textarea::make('body')->label(__('page::admin.body'))->rows(14)->columnSpanFull(),
            Select::make('placement')->label(__('page::admin.placement'))->options(fn (): array => collect(Page::placements())->mapWithKeys(
                static fn (string $placement): array => [$placement => __('page::admin.'.$placement)]
            )->all())->required(),
            TextInput::make('meta_title')->label(__('page::admin.meta_title'))->extraInputAttributes(['dir' => 'auto'])->maxLength(150),
            Textarea::make('meta_description')->label(__('page::admin.meta_description'))->rows(2)->maxLength(320),
            TextInput::make('sort_order')->label(__('page::admin.sort_order'))->extraInputAttributes(['dir' => 'auto'])->numeric()->default(0),
            Toggle::make('is_published')->label(__('page::admin.is_published'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            ResourceTableColumns::id(),
            TextColumn::make('title')->label(__('page::admin.title'))->formatStateUsing(fn (string $state, Page $record): string => $record->localizedValue('title'))->searchable()->sortable(),
            TextColumn::make('slug')->label(__('page::admin.slug'))->searchable(),
            TextColumn::make('placement')->label(__('page::admin.placement'))->formatStateUsing(fn (string $state): string => __('page::admin.'.$state))->badge()->sortable(),
            IconColumn::make('is_published')->label(__('page::admin.is_published'))->boolean()->label(__('page::admin.published')),
            TextColumn::make('sort_order')->label(__('page::admin.sort_order'))->sortable(),
            ResourceTableColumns::createdAtHidden(),
        ])->defaultSort('id', 'desc')->filters([
            SelectFilter::make('placement')->label(__('page::admin.placement'))->options(fn (): array => collect(Page::placements())->mapWithKeys(
                static fn (string $placement): array => [$placement => __('page::admin.'.$placement)]
            )->all()),
            TernaryFilter::make('is_published')->label(__('page::admin.published')),
        ])->actions(ResourceTableActions::editDelete());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPageResourceRecords::route('/'),
            'create' => Pages\CreatePageResourceRecord::route('/create'),
            'edit' => Pages\EditPageResourceRecord::route('/{record}/edit'),
        ];
    }
}
