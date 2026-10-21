<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Advertising\Filament\Admin\Resources\AdvertisingExperimentResource\Pages;
use Modules\Advertising\Models\AdvertisingExperiment;
use Modules\Advertising\Models\AdvertisingInvoice;
use Modules\Advertising\Models\Banner;
use Modules\Advertising\Models\BannerPlacement;

final class AdvertisingExperimentResource extends AdvertisingResource
{
    protected static ?string $model = AdvertisingExperiment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-beaker';

    public static function canViewAny(): bool
    {
        return AdvertisingInvoice::billingAdmin();
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
        return __('advertising::experiments.experiment');
    }

    public static function getPluralModelLabel(): string
    {
        return __('advertising::experiments.title');
    }

    public static function form(Schema $schema): Schema
    {
        $options = fn (): array => Banner::query()->available()->where('creative_format', 'image')->where('device', 'both')->orderBy('title')->get(['id', 'title'])->mapWithKeys(fn ($banner): array => [$banner->id => $banner->title.' (#'.$banner->id.')'])->all();

        return $schema->schema([
            TextInput::make('name')->label(__('advertising::admin.title'))->required()->maxLength(120)->helperText(__('advertising::experiments.setup_hint')),
            Select::make('placement_id')->label(__('advertising::admin.placement'))->options(fn (): array => BannerPlacement::query()->where('is_active', true)->where('device', 'both')->where('key', '!=', 'category-top')->pluck('key', 'id')->map(fn ($key) => __('advertising::admin.'.$key))->all())->required(),
            Select::make('banner_a_id')->label(__('advertising::experiments.arm_a'))->options($options)->searchable()->required(),
            Select::make('banner_b_id')->label(__('advertising::experiments.arm_b'))->options($options)->searchable()->required()->different('banner_a_id'),
            TextInput::make('duration_days')->label(__('advertising::orders.duration'))->integer()->minValue(1)->maxValue(30)->default(7)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('advertising::admin.title'))->searchable(),
            TextColumn::make('state')->label(__('advertising::billing.state'))->formatStateUsing(fn (AdvertisingExperiment $record): string => $record->statusLabel())->badge(),
            TextColumn::make('duration_days')->label(__('advertising::orders.duration'))->numeric(),
            TextColumn::make('started_at')->label(__('advertising::experiments.started'))->dateTime()->timezone('Asia/Tehran'),
            TextColumn::make('ends_at')->label(__('advertising::experiments.ends'))->dateTime()->timezone('Asia/Tehran'),
        ])->actions([
            Action::make('start')->label(__('advertising::experiments.start'))->requiresConfirmation()->modalDescription(__('advertising::experiments.start_hint'))->visible(fn (AdvertisingExperiment $record): bool => $record->state === 'draft')->action(fn (AdvertisingExperiment $record) => $record->start()),
            Action::make('stop')->label(__('advertising::experiments.stop'))->color('danger')->requiresConfirmation()->visible(fn (AdvertisingExperiment $record): bool => $record->state === 'running' && $record->ends_at->isFuture())->action(fn (AdvertisingExperiment $record) => $record->stop()),
            Action::make('report')->label(__('advertising::experiments.report'))->modalSubmitAction(false)->modalCancelActionLabel(__('advertising::experiments.close'))->modalContent(fn (AdvertisingExperiment $record) => view('advertising::experiments.report', ['experiment' => $record, 'rows' => $record->report()])),
        ])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListExperiments::route('/'), 'create' => Pages\CreateExperiment::route('/create')];
    }
}
