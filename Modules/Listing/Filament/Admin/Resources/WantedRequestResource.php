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
use Modules\Listing\Filament\Admin\Resources\WantedRequestResource\Pages;
use Modules\Listing\Models\WantedRequest;
use UnitEnum;

final class WantedRequestResource extends AccessControlledResource
{
    protected static ?string $model = WantedRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass';

    public static function canViewAny(): bool
    {
        return WantedRequest::canModerate();
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
        return __('listing::wanted.title');
    }

    public static function getPluralModelLabel(): string
    {
        return __('listing::services.moderation');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin::operations.group');
    }

    public static function table(Table $table): Table
    {
        $details = fn (WantedRequest $record) => view('listing::wanted.review', ['record' => $record, 'categories' => WantedRequest::categoryOptions('all'), 'cities' => WantedRequest::cityOptions()]);
        $actions = [Action::make('details')->label(__('listing::wanted.body'))->modalSubmitAction(false)->modalContent($details)];
        foreach (['approve', 'reject', 'retract'] as $decision) {
            $actions[] = Action::make($decision)->label(__('listing::wanted.'.$decision))->requiresConfirmation()->modalContent($details)
                ->visible(fn (WantedRequest $record): bool => ($decision === 'retract' ? $record->state === 'open' : $record->state === 'pending') && $record->expires_at->isFuture())
                ->schema([
                    Hidden::make('revision')->default(fn (WantedRequest $record): int => $record->revision)->required(),
                    Textarea::make('note')->label(__('listing::wanted.review_note'))->maxLength(1000)->required($decision === 'retract'),
                ])->action(function (WantedRequest $record, array $data) use ($decision): void {
                    if ($decision === 'retract') {
                        $record->retract($data['note'], (int) $data['revision']);
                    } else {
                        $record->moderate($decision === 'approve', $data['note'] ?? null, (int) $data['revision']);
                    }
                });
        }

        return $table->columns([
            TextColumn::make('id')->label('#')->sortable(),
            TextColumn::make('title')->label(__('listing::wanted.title_label'))->searchable()->wrap(),
            TextColumn::make('kind')->label(__('listing::services.kind'))->formatStateUsing(fn (string $state): string => __('listing::services.'.$state))->badge(),
            TextColumn::make('state')->label(__('listing::wanted.state'))->formatStateUsing(fn (WantedRequest $record): string => $record->stateLabel())->badge(),
            TextColumn::make('revision')->label(__('listing::wanted.revision'))->numeric(),
            TextColumn::make('expires_at')->label(__('listing::wanted.expires'))->dateTime()->timezone('Asia/Tehran'),
        ])->filters([SelectFilter::make('kind')->label(__('listing::services.kind'))->options(['product' => __('listing::services.product'), 'service' => __('listing::services.service')]), SelectFilter::make('state')->label(__('listing::wanted.state'))->options(collect(['pending', 'open', 'rejected', 'fulfilled', 'closed'])->mapWithKeys(fn ($state): array => [$state => __('listing::wanted.'.$state)]))])->actions($actions)->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListWantedRequests::route('/')];
    }
}
