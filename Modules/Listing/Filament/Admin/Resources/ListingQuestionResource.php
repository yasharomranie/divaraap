<?php

declare(strict_types=1);

namespace Modules\Listing\Filament\Admin\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Admin\Support\Filament\AccessControlledResource;
use Modules\Listing\Models\ListingQuestion;
use UnitEnum;

final class ListingQuestionResource extends AccessControlledResource
{
    protected static ?string $model = ListingQuestion::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    public static function canViewAny(): bool
    {
        return ListingQuestion::canModerate();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getModelLabel(): string
    {
        return __('listing::questions.title');
    }

    public static function getPluralModelLabel(): string
    {
        return __('listing::questions.title');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin::operations.group');
    }

    public static function table(Table $table): Table
    {
        $details = fn (ListingQuestion $record) => view('listing::questions.review', compact('record'));
        $actions = [Action::make('details')->label(__('listing::questions.review'))->modalSubmitAction(false)->modalContent($details)];
        foreach (['publish', 'reject'] as $decision) {
            $actions[] = Action::make($decision)->label(__('listing::questions.'.$decision))->requiresConfirmation()->modalContent($details)
                ->visible(fn (ListingQuestion $record): bool => $decision === 'publish' ? $record->state === 'answered' : $record->state !== 'rejected')
                ->schema([Hidden::make('revision')->default(fn (ListingQuestion $record): int => $record->revision)->required(), Textarea::make('note')->label(__('listing::questions.note'))->maxLength(1000)->required($decision === 'reject')])
                ->action(fn (ListingQuestion $record, array $data) => $record->moderate($decision === 'publish', $data['note'] ?? null, (int) $data['revision']));
        }

        return $table->columns([TextColumn::make('id')->label('#')->sortable(), TextColumn::make('listing_id')->label(__('listing::questions.listing'))->numeric(), TextColumn::make('question')->label(__('listing::questions.question'))->limit(80)->wrap()->searchable(), TextColumn::make('state')->label(__('listing::questions.state'))->formatStateUsing(fn (ListingQuestion $record): string => $record->stateLabel())->badge(), TextColumn::make('revision')->label(__('listing::questions.revision'))->numeric()])
            ->filters([SelectFilter::make('state')->label(__('listing::questions.state'))->options(collect(['pending', 'answered', 'published', 'rejected'])->mapWithKeys(fn ($state): array => [$state => __('listing::questions.'.$state)]))])->actions($actions)->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListingQuestionResource\Pages\ListListingQuestions::route('/')];
    }
}
