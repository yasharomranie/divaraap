<?php

declare(strict_types=1);

namespace Modules\Video\Support\Filament;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Modules\Video\Enums\VideoStatus;
use Modules\Video\Models\Video;

class VideoTableSchema
{
    public static function configure(Table $table, bool $showOwner = true): Table
    {
        $columns = [
            TextColumn::make('title')->label(__('video::admin.title'))
                ->searchable()
                ->sortable()
                ->limit(40)
                ->formatStateUsing(fn (Video $record): string => $record->titleLabel()),
            TextColumn::make('listing.title')->label(__('video::admin.listing'))
                ->searchable()
                ->sortable()
                ->limit(40),
        ];

        if ($showOwner) {
            $columns[] = TextColumn::make('user.email')->label(__('video::admin.owner'))
                ->searchable()
                ->sortable()
                ->toggleable();
        }

        return $table
            ->columns([
                ...$columns,
                TextColumn::make('status')->label(__('video::admin.status'))
                    ->badge()
                    ->color(fn (Video $record): string => $record->statusColor())
                    ->formatStateUsing(fn (Video $record): string => $record->statusLabel()),
                IconColumn::make('is_active')->label(__('video::admin.visible'))
                    ->boolean(),
                TextColumn::make('resolution')->label(__('video::admin.resolution'))
                    ->state(fn (Video $record): string => $record->resolutionLabel()),
                TextColumn::make('duration')->label(__('video::admin.duration'))
                    ->state(fn (Video $record): string => $record->durationLabel()),
                TextColumn::make('size')->label(__('video::admin.size'))
                    ->state(fn (Video $record): string => $record->sizeLabel()),
                TextColumn::make('processed_at')->label(__('video::admin.processed'))
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('video::admin.status'))
                    ->options(VideoStatus::options()),
                SelectFilter::make('listing_id')->label(__('video::admin.listing'))
                    ->relationship('listing', 'title')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_active')->label(__('video::admin.visible')),
                ...($showOwner ? [
                    SelectFilter::make('user_id')->label(__('video::admin.owner'))
                        ->relationship('user', 'email')
                        ->searchable()
                        ->preload(),
                ] : []),
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                Action::make('watch')
                    ->icon('heroicon-o-play-circle')
                    ->color('gray')
                    ->modalHeading(fn (Video $record): string => $record->titleLabel())
                    ->modalWidth('5xl')
                    ->modalSubmitAction(false)
                    ->modalContent(
                        fn (Video $record): View => view('video::filament.video-player', ['video' => $record->loadMissing('listing')]),
                    ),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
