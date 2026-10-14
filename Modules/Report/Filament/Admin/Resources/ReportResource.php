<?php

declare(strict_types=1);

namespace Modules\Report\Filament\Admin\Resources;

use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Admin\Support\Filament\ResourceTableActions;
use Modules\Admin\Support\Filament\ResourceTableColumns;
use Modules\Report\Filament\Admin\Resources\ReportResource\Pages;
use Modules\Report\Models\Report;
use UnitEnum;

class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-flag';

    protected static string|UnitEnum|null $navigationGroup = 'Moderation';

    protected static ?string $label = 'Report';

    protected static ?string $pluralLabel = 'Reports';

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('report::admin.report');
    }

    public static function getPluralModelLabel(): string
    {
        return __('report::admin.reports');
    }

    public static function getNavigationLabel(): string
    {
        return __('report::admin.reports');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('report::admin.moderation');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('subject_type')->label(__('report::admin.subject_type'))->options([
                Report::SUBJECT_LISTING => __('report::admin.listing'),
                Report::SUBJECT_USER => __('report::admin.user'),
            ])->required(),
            TextInput::make('subject_id')->label(__('report::admin.subject_id'))->extraInputAttributes(['dir' => 'auto'])->numeric()->required(),
            Select::make('reason')->label(__('report::admin.reason'))->options(fn (): array => collect(Report::reasons())->mapWithKeys(
                static fn (string $reason): array => [$reason => __('report::messages.reason_'.$reason)]
            )->all())->required(),
            Textarea::make('details')->label(__('report::admin.details'))->rows(3)->maxLength(1000),
            Select::make('status')->label(__('report::admin.status'))->options(fn (): array => collect(Report::statuses())->mapWithKeys(
                static fn (string $status): array => [$status => __('report::messages.status_'.$status)]
            )->all())->required(),
            Textarea::make('resolution_note')->label(__('report::admin.resolution_note'))->rows(2)->maxLength(1000),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            ResourceTableColumns::id(),
            TextColumn::make('subject_type')->label(__('report::admin.subject_type'))->formatStateUsing(fn (string $state): string => __('report::admin.'.$state))->badge()->sortable(),
            TextColumn::make('subject_id')->label(__('report::admin.subject'))->sortable(),
            TextColumn::make('reason')->label(__('report::admin.reason'))->formatStateUsing(fn (string $state): string => __('report::messages.reason_'.$state))->badge()->searchable(),
            TextColumn::make('status')->label(__('report::admin.status'))->formatStateUsing(fn (string $state): string => __('report::messages.status_'.$state))->badge()->sortable(),
            TextColumn::make('reporter_id')->label(__('report::admin.reporter')),
            ResourceTableColumns::createdAtHidden(),
        ])->defaultSort('id', 'desc')->filters([
            SelectFilter::make('status')->label(__('report::admin.status'))->options(fn (): array => collect(Report::statuses())->mapWithKeys(
                static fn (string $status): array => [$status => __('report::messages.status_'.$status)]
            )->all()),
            SelectFilter::make('subject_type')->label(__('report::admin.subject_type'))->options([
                Report::SUBJECT_LISTING => __('report::admin.listing'),
                Report::SUBJECT_USER => __('report::admin.user'),
            ]),
        ])->actions(ResourceTableActions::editDelete());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReportResourceRecords::route('/'),
            'create' => Pages\CreateReportResourceRecord::route('/create'),
            'edit' => Pages\EditReportResourceRecord::route('/{record}/edit'),
        ];
    }
}
