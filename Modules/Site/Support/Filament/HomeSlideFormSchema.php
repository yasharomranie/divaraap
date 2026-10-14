<?php

declare(strict_types=1);

namespace Modules\Site\Support\Filament;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Modules\Site\App\Support\LocalMedia;

final class HomeSlideFormSchema
{
    public static function make(array $defaults, callable $normalizeSlides): Repeater
    {
        return Repeater::make('home_slides')->label(__('site::admin.homepage_slides'))
            ->helperText(__('site::admin.use_1_to_5_slides_upload_a_wide_image_for_each_slide_to_improve_the_hero_area'))
            ->schema([
                FileUpload::make('image_path')->label(__('site::admin.slide_image'))
                    ->image()
                    ->disk(LocalMedia::disk())
                    ->directory(LocalMedia::managedDirectory('home-slides'))
                    ->visibility('public')
                    ->imageEditor()
                    ->imagePreviewHeight('200')
                    ->helperText(__('site::admin.recommended_1600x1000_or_wider'))
                    ->columnSpanFull(),
                TextInput::make('badge')->label(__('site::admin.badge'))->extraInputAttributes(['dir' => 'auto'])
                    ->label(__('site::admin.badge'))
                    ->maxLength(255),
                TextInput::make('title')->label(__('site::admin.title'))->extraInputAttributes(['dir' => 'auto'])
                    ->label(__('site::admin.title'))
                    ->required()
                    ->maxLength(255),
                Textarea::make('subtitle')->label(__('site::admin.subtitle'))
                    ->rows(3)
                    ->required()
                    ->maxLength(500)
                    ->columnSpanFull(),
                TextInput::make('primary_button_text')->label(__('site::admin.primary_button'))->extraInputAttributes(['dir' => 'auto'])
                    ->label(__('site::admin.primary_button'))
                    ->required()
                    ->maxLength(120),
                TextInput::make('secondary_button_text')->label(__('site::admin.secondary_button'))->extraInputAttributes(['dir' => 'auto'])
                    ->label(__('site::admin.secondary_button'))
                    ->required()
                    ->maxLength(120),
            ])
            ->columns(2)
            ->default($defaults)
            ->minItems(1)
            ->maxItems(5)
            ->collapsible()
            ->collapsed()
            ->cloneable()
            ->reorderableWithButtons()
            ->addActionLabel(__('site::admin.add_slide'))
            ->itemLabel(fn (array $state): string => filled($state['title'] ?? null) ? (string) $state['title'] : __('site::admin.new_slide'))
            ->dehydrateStateUsing(fn ($state) => $normalizeSlides($state));
    }
}
