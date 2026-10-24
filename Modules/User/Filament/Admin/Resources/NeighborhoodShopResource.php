<?php

declare(strict_types=1);

namespace Modules\User\Filament\Admin\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Admin\Support\Filament\AccessControlledResource;
use Modules\Location\Models\City;
use Modules\User\App\Models\NeighborhoodShop;
use Modules\User\App\Models\Profile;
use Modules\User\Filament\Admin\Resources\NeighborhoodShopResource\Pages;

final class NeighborhoodShopResource extends AccessControlledResource
{
    protected static ?string $model = NeighborhoodShop::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    public static function canViewAny(): bool
    {
        return NeighborhoodShop::canManage();
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
        return __('user::neighborhoods.selection');
    }

    public static function getPluralModelLabel(): string
    {
        return __('user::neighborhoods.title');
    }

    public static function fields(bool $editing = false): array
    {
        return [
            Hidden::make('version'),
            Hidden::make('shop_version')->required(),
            Select::make('shop_user_id')->label(__('user::neighborhoods.shop'))->required()->searchable()->live()->disabled($editing)->dehydrated()
                ->getSearchResultsUsing(fn (string $search): array => Profile::advertisingShopOptions($search))
                ->getOptionLabelUsing(fn ($value): string => Profile::advertisingShops([(int) $value])[(int) $value]['name'] ?? '#'.$value)
                ->afterStateUpdated(fn ($state, Set $set) => $set('shop_version', NeighborhoodShop::currentShopHash((int) $state))),
            Placeholder::make('public_preview')->label(__('user::neighborhoods.preview'))->content(fn (Get $get) => view('user::neighborhoods.preview', ['shop' => Profile::advertisingShops([(int) $get('shop_user_id')])[(int) $get('shop_user_id')] ?? null])),
            Select::make('city_id')->label(__('user::neighborhoods.city_id'))->options(fn (): array => City::wantedOptions())->searchable()->required(),
            TextInput::make('neighborhood')->label(__('user::neighborhoods.neighborhood'))->required()->minLength(2)->maxLength(80)->helperText(__('user::neighborhoods.location_hint')),
            TextInput::make('priority')->label(__('user::neighborhoods.priority'))->numeric()->minValue(0)->maxValue(1000)->required()->default(100),
            DatePicker::make('review_until')->label(__('user::neighborhoods.review_until'))->native(true)->required()->default(fn (): string => now('Asia/Tehran')->addDays(90)->toDateString())->helperText(__('user::neighborhoods.date_hint')),
            Toggle::make('is_active')->label(__('user::neighborhoods.is_active'))->default(true)->required(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('shop_user_id')->label(__('user::neighborhoods.shop'))->formatStateUsing(fn ($state): string => Profile::advertisingShops([(int) $state])[(int) $state]['name'] ?? '#'.$state),
            TextColumn::make('city_id')->label(__('user::neighborhoods.city_id'))->formatStateUsing(fn ($state): string => City::wantedOptions()[$state] ?? '—'),
            TextColumn::make('neighborhood')->label(__('user::neighborhoods.neighborhood'))->searchable()->wrap(),
            TextColumn::make('priority')->label(__('user::neighborhoods.priority'))->sortable(),
            TextColumn::make('state')->label(__('user::neighborhoods.state'))->state(fn (NeighborhoodShop $record): string => $record->stateLabel())->badge(),
            TextColumn::make('expires_at')->label(__('user::neighborhoods.expires'))->dateTime()->timezone('Asia/Tehran'),
        ])->actions([
            Action::make('review')->label(__('user::neighborhoods.review'))->fillForm(fn (NeighborhoodShop $record): array => $record->formData())->schema(self::fields(true))
                ->modalDescription(__('user::neighborhoods.review_hint'))->action(fn (NeighborhoodShop $record, array $data) => NeighborhoodShop::saveSelection(auth()->user(), $data, (int) $record->id)),
            ...collect(['pause', 'remove'])->map(fn (string $action) => Action::make($action)->label(__('user::neighborhoods.'.$action))->requiresConfirmation()->schema([Hidden::make('version')->default(fn (NeighborhoodShop $record): string => $record->version())->required()])
                ->action(fn (NeighborhoodShop $record, array $data) => $record->changeFor(auth()->user(), $action, $data['version'])))->all(),
        ])->defaultSort('priority');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListNeighborhoodShops::route('/')];
    }
}
