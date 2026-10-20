<?php

declare(strict_types=1);

namespace Modules\Review\Filament\Admin\Resources;

use App\Support\PersianLocale;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Modules\Admin\Support\Filament\AccessControlledResource as Resource;
use Modules\Admin\Support\Filament\ResourceTableActions;
use Modules\Admin\Support\Filament\ResourceTableColumns;
use Modules\Review\Filament\Admin\Resources\ReviewResource\Pages;
use Modules\Review\Models\Review;
use UnitEnum;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-star';

    protected static string|UnitEnum|null $navigationGroup = 'Moderation';

    protected static ?string $label = 'Review';

    protected static ?string $pluralLabel = 'Reviews';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('review::admin.review');
    }

    public static function getPluralModelLabel(): string
    {
        return __('review::admin.reviews');
    }

    public static function getNavigationLabel(): string
    {
        return __('review::admin.reviews');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('review::admin.moderation');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('interaction_verified_at')->label(__('review::quality.evidence_date'))->formatStateUsing(fn ($state): string => $state ? PersianLocale::date(Carbon::parse($state)->timezone('Asia/Tehran'), true) : '—')->disabled()->dehydrated(false),
            TextInput::make('seller_id')->label(__('review::admin.seller'))->extraInputAttributes(['dir' => 'auto'])->numeric()->required(),
            TextInput::make('author_id')->label(__('review::admin.author'))->extraInputAttributes(['dir' => 'auto'])->numeric()->required(),
            TextInput::make('listing_id')->label(__('review::admin.listing'))->extraInputAttributes(['dir' => 'auto'])->numeric(),
            TextInput::make('rating')->label(__('review::admin.rating'))->extraInputAttributes(['dir' => 'auto'])->numeric()->minValue(1)->maxValue(5)->required(),
            TextInput::make('title')->label(__('review::admin.title'))->extraInputAttributes(['dir' => 'auto'])->maxLength(120),
            Textarea::make('body')->label(__('review::admin.body'))->rows(4)->maxLength(2000),
            Toggle::make('is_approved')->label(__('review::admin.is_approved'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            ResourceTableColumns::id(),
            TextColumn::make('rating')->label(__('review::admin.rating'))->badge()->sortable(),
            TextColumn::make('title')->label(__('review::admin.title'))->searchable()->limit(40),
            TextColumn::make('seller_id')->label(__('review::admin.seller'))->sortable(),
            TextColumn::make('author_id')->label(__('review::admin.author'))->sortable(),
            IconColumn::make('interaction_evidence')->label(__('review::quality.badge'))->boolean()->state(fn (Review $record): bool => $record->hasInteractionEvidence()),
            IconColumn::make('is_approved')->label(__('review::admin.is_approved'))->boolean()->label(__('review::admin.approved')),
            ResourceTableColumns::createdAtHidden(),
        ])->defaultSort('id', 'desc')->filters([
            SelectFilter::make('rating')->label(__('review::admin.rating'))->options([1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5']),
            TernaryFilter::make('is_approved')->label(__('review::admin.approved')),
        ])->actions(ResourceTableActions::editDelete());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReviewResourceRecords::route('/'),
            'create' => Pages\CreateReviewResourceRecord::route('/create'),
            'edit' => Pages\EditReviewResourceRecord::route('/{record}/edit'),
        ];
    }
}
