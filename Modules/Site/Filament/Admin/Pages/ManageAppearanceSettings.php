<?php

declare(strict_types=1);

namespace Modules\Site\Filament\Admin\Pages;

use App\Support\AppearanceTheme;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Site\App\Settings\AppearanceSettings;
use UnitEnum;

final class ManageAppearanceSettings extends SettingsPage
{
    public string $previewDevice = 'mobile';

    protected static string $settings = AppearanceSettings::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?int $navigationSort = 2;

    protected string $view = 'site::filament.appearance-settings';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole('admin');
    }

    public function canEdit(): bool
    {
        return self::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        return __('site::appearance.title');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('site::admin.settings');
    }

    public function getTitle(): string
    {
        return __('site::appearance.title');
    }

    public function getSubheading(): ?string
    {
        return __('site::appearance.description');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return AppearanceTheme::normalize($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return AppearanceTheme::normalize($data);
    }

    public function resetDraft(): void
    {
        abort_unless(self::canAccess(), 403);
        $this->form->fill(AppearanceTheme::defaults());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('website')->label(__('site::appearance.open_site'))->url(url('/'))->openUrlInNewTab(),
            Action::make('resetDraft')->label(__('site::appearance.reset'))->color('gray')
                ->requiresConfirmation()->modalDescription(__('site::appearance.reset_description'))->action('resetDraft'),
        ];
    }

    public function form(Schema $schema): Schema
    {
        $sections = [Section::make(__('site::appearance.colors'))
            ->schema(array_map(static fn (string $key) => ColorPicker::make('colors.'.$key)
                ->label(__('site::appearance.color_'.$key))->extraInputAttributes(['dir' => 'ltr'])->hex()->required()
                ->regex('/\A#[0-9a-fA-F]{6}\z/')->rules(['size:7'])->live(debounce: 400), array_keys(AppearanceTheme::colors())))
            ->columns(['default' => 1, 'md' => 3])->columnSpanFull()->collapsible()];
        $groups = [];
        foreach (AppearanceTheme::fonts() as $key => $font) {
            $fields = [];
            foreach (['mobile', 'desktop'] as $device) {
                $fields[] = TextInput::make('typography.'.$key.'.'.$device)
                    ->label(__('site::appearance.'.$device))->numeric()->integer()->required()
                    ->minValue(10)->maxValue(72)->suffix(__('site::appearance.pixels'))
                    ->extraInputAttributes(['dir' => 'ltr'])->live(onBlur: true);
            }
            $groups[$font['group']][] = Section::make(__('site::appearance.font_'.$key))->schema($fields)->columns(2)->compact();
        }
        foreach ($groups as $key => $fields) {
            $sections[] = Section::make(__('site::appearance.group_'.$key))
                ->description(__('site::appearance.device_hint'))->schema($fields)
                ->columns(['default' => 1, 'lg' => 2])->columnSpanFull()->collapsible()->collapsed();
        }

        return $schema->components($sections);
    }
}
