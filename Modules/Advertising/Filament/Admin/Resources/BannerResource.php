<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Resources;

use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Admin\Support\Filament\ResourceTableActions;
use Modules\Advertising\Filament\Admin\Resources\BannerResource\Pages;
use Modules\Advertising\Models\Banner;
use Modules\Advertising\Models\BannerPlacement;
use Modules\Site\App\Support\LocalMedia;

final class BannerResource extends AdvertisingResource
{
    protected static ?string $model = Banner::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    public static function getModelLabel(): string
    {
        return __('advertising::admin.banner');
    }

    public static function getPluralModelLabel(): string
    {
        return __('advertising::admin.banners');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('title')->label(__('advertising::admin.title'))->required()->maxLength(150),
            TextInput::make('alt')->label(__('advertising::admin.alt'))->required()->maxLength(200),
            FileUpload::make('image')->label(__('advertising::admin.image'))->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->disk('public')->directory(LocalMedia::managedDirectory('banners'))->visibility('public')->maxSize(4096)->required()->helperText(__('advertising::admin.image_hint')),
            FileUpload::make('mobile_image')->label(__('advertising::admin.mobile_image'))->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->disk('public')->directory(LocalMedia::managedDirectory('banners'))->visibility('public')->maxSize(4096)->helperText(__('advertising::admin.mobile_hint')),
            TextInput::make('url')->label(__('advertising::admin.url'))->url()->maxLength(2048)->extraInputAttributes(['dir' => 'ltr']),
            Toggle::make('new_tab')->label(__('advertising::admin.new_tab'))->default(false),
            Select::make('device')->label(__('advertising::admin.device'))->options(['both' => __('advertising::admin.both'), 'mobile' => __('advertising::admin.mobile'), 'desktop' => __('advertising::admin.desktop')])->required()->default('both'),
            Toggle::make('is_active')->label(__('advertising::admin.is_active'))->default(true),
            DateTimePicker::make('starts_at')->label(__('advertising::admin.starts_at'))->timezone(config('app.timezone'))->seconds(false),
            DateTimePicker::make('ends_at')->label(__('advertising::admin.ends_at'))->timezone(config('app.timezone'))->seconds(false)->after('starts_at'),
            Repeater::make('assignments')->label(__('advertising::admin.placements'))->relationship()->schema([
                Select::make('placement_id')->label(__('advertising::admin.placement'))->options(fn () => BannerPlacement::query()->get()->mapWithKeys(fn ($p) => [$p->id => __('advertising::admin.'.$p->key)]))->required()->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                TextInput::make('priority')->label(__('advertising::admin.priority'))->integer()->minValue(1)->maxValue(1000000)->default(1)->required(),
            ])->columns(2)->defaultItems(0)->columnSpanFull()->helperText(__('advertising::admin.priority_hint')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            ImageColumn::make('image')->label(__('advertising::admin.image'))->disk('public')->imageWidth(80)->imageHeight(32),
            TextColumn::make('title')->label(__('advertising::admin.title'))->searchable()->sortable(),
            IconColumn::make('is_active')->label(__('advertising::admin.is_active'))->boolean(),
            TextColumn::make('device')->label(__('advertising::admin.device'))->formatStateUsing(fn ($state) => __('advertising::admin.'.$state)),
            TextColumn::make('starts_at')->label(__('advertising::admin.starts_at'))->dateTime()->timezone(config('app.timezone')),
            TextColumn::make('ends_at')->label(__('advertising::admin.ends_at'))->dateTime()->timezone(config('app.timezone')),
        ])->filters([TernaryFilter::make('is_active')->label(__('advertising::admin.is_active'))])->actions(ResourceTableActions::editDelete())->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListBanners::route('/'), 'create' => Pages\CreateBanner::route('/create'), 'edit' => Pages\EditBanner::route('/{record}/edit')];
    }
}
