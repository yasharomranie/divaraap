<?php

declare(strict_types=1);

namespace Modules\Advertising\Filament\Admin\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Modules\Admin\Support\Filament\ResourceTableActions;
use Modules\Advertising\Filament\Admin\Pages\AdvertisingPreview;
use Modules\Advertising\Filament\Admin\Resources\BannerResource\Pages;
use Modules\Advertising\Models\Banner;
use Modules\Advertising\Models\BannerPlacement;
use Modules\Advertising\Models\Campaign;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\Location\Models\City;
use Modules\Page\Models\Page;
use Modules\Site\App\Support\LocalMedia;
use Modules\User\App\Models\Profile;
use Modules\Video\Models\Video;

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
            Select::make('campaign_id')->label(__('advertising::campaign.campaign'))->options(fn () => Campaign::options())->searchable()->exists(Campaign::class, 'id')->helperText(__('advertising::campaign.optional_campaign')),
            Select::make('creative_format')->label(__('advertising::shop.format'))->options(['image' => __('advertising::shop.image'), 'shop' => __('advertising::shop.card'), 'collection' => __('advertising::collection.format'), 'service' => __('advertising::service.format'), 'filter' => __('advertising::filters.format'), 'sponsor' => __('advertising::sponsor.format'), 'deadline' => __('advertising::deadline.format'), 'video' => __('advertising::video.format'), 'guide' => __('advertising::guide.format')])->default('image')->required()->live(),
            Select::make('shop_user_id')->label(__('advertising::shop.shop'))->searchable()->getSearchResultsUsing(fn (string $search): array => Profile::advertisingShopOptions($search))
                ->getOptionLabelUsing(fn ($value): ?string => Profile::advertisingShops([(int) $value])[(int) $value]['name'] ?? null)
                ->options(fn (): array => Profile::advertisingShopOptions())->required()->visible(fn (Get $get): bool => in_array($get('creative_format'), ['shop', 'sponsor'], true))->helperText(__('advertising::shop.hint')),
            Select::make('collection_listing_ids')->label(__('advertising::collection.items'))->multiple()->minItems(2)->maxItems(6)->required()->searchable()
                ->options(fn (): array => Listing::advertisingCollectionOptions())->getSearchResultsUsing(fn (string $search): array => Listing::advertisingCollectionOptions($search))
                ->getOptionLabelsUsing(fn (array $values): array => collect(Listing::advertisingCollectionItems($values))->mapWithKeys(fn (array $item): array => [$item['id'] => $item['title'].' #'.$item['id']])->all())
                ->visible(fn (Get $get): bool => $get('creative_format') === 'collection')->helperText(__('advertising::collection.hint')),
            Select::make('service_listing_id')->label(__('advertising::service.listing'))->searchable()->required()
                ->options(fn (): array => Listing::advertisingServiceOptions())->getSearchResultsUsing(fn (string $search): array => Listing::advertisingServiceOptions($search))
                ->getOptionLabelUsing(fn ($value): ?string => Listing::advertisingServiceItems([(int) $value])[(int) $value]['title'] ?? null)
                ->visible(fn (Get $get): bool => $get('creative_format') === 'service')->helperText(__('advertising::service.hint')),
            Repeater::make('filter_choices')->label(__('advertising::filters.choices'))->required()->minItems(2)->maxItems(4)->defaultItems(2)->reorderableWithButtons()->columns(2)
                ->schema([
                    TextInput::make('label')->label(__('advertising::filters.label'))->required()->maxLength(60)->columnSpanFull(),
                    TextInput::make('search')->label(__('advertising::filters.search'))->maxLength(120),
                    Select::make('category_id')->label(__('advertising::filters.category'))->options(fn (): array => Category::advertisingFilterOptions())->searchable(),
                    Select::make('city_id')->label(__('advertising::filters.city'))->options(fn (): array => collect(City::advertisingFilterOptions())->mapWithKeys(fn (array $city, $id): array => [$id => $city['name']])->all())->searchable(),
                    Select::make('sort')->label(__('advertising::filters.sort'))->options(['smart' => __('site::messages.sort_relevance'), 'newest' => __('site::messages.sort_newest'), 'oldest' => __('site::messages.sort_oldest'), 'price_asc' => __('site::messages.sort_price_asc'), 'price_desc' => __('site::messages.sort_price_desc')])->default('newest')->required(),
                    TextInput::make('min_price')->label(__('advertising::filters.min_price'))->maxLength(32),
                    TextInput::make('max_price')->label(__('advertising::filters.max_price'))->maxLength(32),
                ])->visible(fn (Get $get): bool => $get('creative_format') === 'filter')->helperText(__('advertising::filters.hint'))->columnSpanFull(),
            Select::make('sponsored_category_id')->label(__('advertising::sponsor.category'))->options(fn (): array => Category::advertisingFilterOptions())->searchable()->required()
                ->visible(fn (Get $get): bool => $get('creative_format') === 'sponsor')->helperText(__('advertising::sponsor.hint')),
            Select::make('offer_listing_id')->label(__('advertising::deadline.listing'))->searchable()->required()
                ->options(fn (): array => Listing::advertisingCollectionOptions())->getSearchResultsUsing(fn (string $search): array => Listing::advertisingCollectionOptions($search))
                ->getOptionLabelUsing(fn ($value): ?string => Listing::advertisingCollectionItems([(int) $value])[(int) $value]['title'] ?? null)
                ->visible(fn (Get $get): bool => $get('creative_format') === 'deadline')->helperText(__('advertising::deadline.hint')),
            Select::make('guide_page_id')->label(__('advertising::guide.page'))->searchable()->required()
                ->options(fn (): array => Page::advertisingGuideOptions())->getSearchResultsUsing(fn (string $search): array => Page::advertisingGuideOptions($search))
                ->getOptionLabelUsing(fn ($value): ?string => Page::advertisingGuides([(int) $value])[(int) $value]['title'] ?? null)
                ->visible(fn (Get $get): bool => $get('creative_format') === 'guide')->helperText(__('advertising::guide.hint')),
            Select::make('video_id')->label(__('advertising::video.clip'))->searchable()->required()
                ->options(fn (): array => Video::advertisingOptions())->getSearchResultsUsing(fn (string $search): array => Video::advertisingOptions($search))
                ->getOptionLabelUsing(fn ($value): ?string => Video::advertisingClips([(int) $value])[(int) $value]['title'] ?? null)
                ->visible(fn (Get $get): bool => $get('creative_format') === 'video')->helperText(__('advertising::video.hint')),
            Textarea::make('video_transcript')->label(__('advertising::video.transcript'))->required()->maxLength(2000)->rows(3)
                ->visible(fn (Get $get): bool => $get('creative_format') === 'video')->helperText(__('advertising::video.transcript_hint')),
            FileUpload::make('video_captions')->live()->label(__('advertising::video.captions'))->disk('public')->directory(LocalMedia::managedDirectory('banners/captions'))->visibility('public')
                ->acceptedFileTypes(['text/vtt', 'text/plain'])->maxSize(128)->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file): string => Str::ulid().'.vtt')
                ->visible(fn (Get $get): bool => $get('creative_format') === 'video')->helperText(__('advertising::video.captions_hint')),
            Select::make('video_caption_language')->label(__('advertising::video.caption_language'))->options(['fa' => 'فارسی', 'en' => 'English'])->default('fa')->required()
                ->visible(fn (Get $get): bool => $get('creative_format') === 'video' && filled($get('video_captions'))),
            TextInput::make('title')->label(__('advertising::admin.title'))->required()->maxLength(150),
            TextInput::make('alt')->label(__('advertising::admin.alt'))->required()->maxLength(200),
            FileUpload::make('image')->label(__('advertising::admin.image'))->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->disk('public')->directory(LocalMedia::managedDirectory('banners'))->visibility('public')->maxSize(4096)->required()->helperText(__('advertising::admin.image_hint')),
            FileUpload::make('mobile_image')->label(__('advertising::admin.mobile_image'))->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->disk('public')->directory(LocalMedia::managedDirectory('banners'))->visibility('public')->maxSize(4096)->helperText(__('advertising::admin.mobile_hint')),
            TextInput::make('url')->label(__('advertising::admin.url'))->url()->maxLength(2048)->extraInputAttributes(['dir' => 'ltr'])->visible(fn (Get $get): bool => in_array($get('creative_format'), [null, 'image'], true)),
            Toggle::make('new_tab')->label(__('advertising::admin.new_tab'))->default(false),
            Select::make('device')->label(__('advertising::admin.device'))->options(['both' => __('advertising::admin.both'), 'mobile' => __('advertising::admin.mobile'), 'desktop' => __('advertising::admin.desktop')])->required()->default('both'),
            TextInput::make('daily_frequency_cap')->label(__('advertising::metrics.daily_cap'))->integer()->minValue(1)->maxValue(10000)->helperText(__('advertising::metrics.daily_cap_hint')),
            Toggle::make('is_active')->label(__('advertising::admin.is_active'))->default(true),
            DateTimePicker::make('starts_at')->label(__('advertising::admin.starts_at'))->timezone(fn (Get $get): string => $get('creative_format') === 'deadline' ? 'Asia/Tehran' : config('app.timezone'))->seconds(false),
            DateTimePicker::make('ends_at')->label(__('advertising::admin.ends_at'))->timezone(fn (Get $get): string => $get('creative_format') === 'deadline' ? 'Asia/Tehran' : config('app.timezone'))->seconds(false)->required(fn (Get $get): bool => $get('creative_format') === 'deadline')->after('starts_at')->helperText(fn (Get $get): ?string => $get('creative_format') === 'deadline' ? __('advertising::deadline.timezone') : null),
            Repeater::make('assignments')->label(__('advertising::admin.placements'))->relationship()->schema([
                Select::make('placement_id')->label(__('advertising::admin.placement'))->options(fn (Get $get) => BannerPlacement::query()->where('key', $get('../../creative_format') === 'sponsor' ? '=' : '!=', 'category-top')->get()->mapWithKeys(fn ($p) => [$p->id => __('advertising::admin.'.$p->key)]))->required()->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                TextInput::make('priority')->label(__('advertising::admin.priority'))->integer()->minValue(1)->maxValue(1000000)->default(1)->required(),
            ])->columns(2)->defaultItems(0)->columnSpanFull()->helperText(__('advertising::admin.priority_hint')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            ImageColumn::make('image')->label(__('advertising::admin.image'))->disk('public')->imageWidth(80)->imageHeight(32),
            TextColumn::make('title')->label(__('advertising::admin.title'))->searchable()->sortable(),
            TextColumn::make('campaign.title')->label(__('advertising::campaign.campaign'))->placeholder('—'),
            IconColumn::make('is_active')->label(__('advertising::admin.is_active'))->boolean(),
            TextColumn::make('review_status')->label(__('admin::operations.review_status'))->formatStateUsing(fn ($state) => __('admin::operations.review_'.$state))->badge(),
            TextColumn::make('device')->label(__('advertising::admin.device'))->formatStateUsing(fn ($state) => __('advertising::admin.'.$state)),
            TextColumn::make('starts_at')->label(__('advertising::admin.starts_at'))->dateTime()->timezone(config('app.timezone')),
            TextColumn::make('ends_at')->label(__('advertising::admin.ends_at'))->dateTime()->timezone(config('app.timezone')),
        ])->filters([TernaryFilter::make('is_active')->label(__('advertising::admin.is_active'))])->actions([Action::make('preview')->label(__('advertising::preview.action'))->icon('heroicon-o-eye')->url(fn (Banner $record) => AdvertisingPreview::getUrl(['banner' => $record->id, 'placement' => $record->creative_format === 'sponsor' ? 'category-top' : 'home-top'])), ...ResourceTableActions::editDelete()])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListBanners::route('/'), 'create' => Pages\CreateBanner::route('/create'), 'edit' => Pages\EditBanner::route('/{record}/edit')];
    }
}
