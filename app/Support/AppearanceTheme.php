<?php

declare(strict_types=1);

namespace App\Support;

use Modules\Site\App\Settings\AppearanceSettings;
use Spatie\LaravelSettings\Exceptions\MissingSettings;

final class AppearanceTheme
{
    public static function colors(): array
    {
        return [
            'accent' => '#0066cc',
            'primary' => '#0b0b0c',
            'page' => '#f5f5f7',
            'surface' => '#ffffff',
            'header' => '#ffffff',
            'footer' => '#ffffff',
            'hero' => '#ffffff',
            'text' => '#1d1d1f',
            'heading' => '#0b0b0c',
            'muted' => '#6e6e76',
            'border' => '#d6d6db',
            'success' => '#176f3f',
            'warning' => '#9a6400',
            'danger' => '#c81e28',
            'header_text' => '#1d1d1f',
            'footer_text' => '#55555c',
            'price' => '#0b0b0c',
            'panel' => '#ffffff',
            'banner' => '#0b0b0c',
        ];
    }

    public static function fonts(): array
    {
        return [
            'body' => ['group' => 'general', 'desktop' => 16, 'mobile' => 16, 'selector' => 'body, .text-body'],
            'lead' => ['group' => 'general', 'desktop' => 18, 'mobile' => 18, 'selector' => '.text-lead'],
            'muted' => ['group' => 'general', 'desktop' => 13, 'mobile' => 13, 'selector' => '.text-muted'],
            'meta' => ['group' => 'general', 'desktop' => 12, 'mobile' => 12, 'selector' => '.text-meta, .text-eyebrow, .breadcrumb'],
            'hero_title' => ['group' => 'headings', 'desktop' => 36, 'mobile' => 26, 'selector' => '.title-hero'],
            'page_title' => ['group' => 'headings', 'desktop' => 32, 'mobile' => 24, 'selector' => '.title-page'],
            'section_title' => ['group' => 'headings', 'desktop' => 26, 'mobile' => 19, 'selector' => '.title-section'],
            'card_title' => ['group' => 'headings', 'desktop' => 18, 'mobile' => 18, 'selector' => '.title-card, .card__title'],
            'mobile_navigation' => ['group' => 'header', 'desktop' => 12, 'mobile' => 12, 'selector' => '.mobile-tabs__item'],
            'brand' => ['group' => 'header', 'desktop' => 16, 'mobile' => 16, 'selector' => '.brand'],
            'navigation' => ['group' => 'header', 'desktop' => 13, 'mobile' => 13, 'selector' => '.category-bar__link, .location-trigger, .menu__item'],
            'drawer' => ['group' => 'header', 'desktop' => 16, 'mobile' => 16, 'selector' => '.nav-list__item, .drawer__title'],
            'search' => ['group' => 'header', 'desktop' => 16, 'mobile' => 16, 'selector' => '.site-search__input, .marketplace-hero .input'],
            'listing_title' => ['group' => 'listing', 'desktop' => 18, 'mobile' => 18, 'selector' => '.listing-card__title'],
            'listing_price' => ['group' => 'listing', 'desktop' => 16, 'mobile' => 16, 'selector' => '.listing-card__price'],
            'listing_meta' => ['group' => 'listing', 'desktop' => 12, 'mobile' => 12, 'selector' => '.listing-card__meta'],
            'detail_price' => ['group' => 'listing', 'desktop' => 32, 'mobile' => 24, 'selector' => '.text-price--large'],
            'price' => ['group' => 'listing', 'desktop' => 18, 'mobile' => 18, 'selector' => '.text-price:not(.text-price--large)'],
            'description' => ['group' => 'listing', 'desktop' => 16, 'mobile' => 16, 'selector' => '.prose'],
            'specifications' => ['group' => 'listing', 'desktop' => 13, 'mobile' => 13, 'selector' => '.spec-list__row'],
            'seller' => ['group' => 'listing', 'desktop' => 16, 'mobile' => 16, 'selector' => '.seller-card__name, .contact-panel__value'],
            'reviews' => ['group' => 'listing', 'desktop' => 16, 'mobile' => 16, 'selector' => '.review-item__body'],
            'filters' => ['group' => 'listing', 'desktop' => 13, 'mobile' => 13, 'selector' => '.filter-group__title, .result-bar__count, .chip'],
            'category' => ['group' => 'home', 'desktop' => 13, 'mobile' => 13, 'selector' => '.category-card__name, .pill, .home-sidebar__categories > a'],
            'category_count' => ['group' => 'home', 'desktop' => 12, 'mobile' => 12, 'selector' => '.category-card__count'],
            'statistics' => ['group' => 'home', 'desktop' => 26, 'mobile' => 26, 'selector' => '.stat-card__value, .hero__stat strong'],
            'statistics_label' => ['group' => 'home', 'desktop' => 12, 'mobile' => 12, 'selector' => '.stat-card__label, .hero__stats'],
            'banner_title' => ['group' => 'home', 'desktop' => 21, 'mobile' => 21, 'selector' => '.promo-banner__title'],
            'banner_text' => ['group' => 'home', 'desktop' => 13, 'mobile' => 13, 'selector' => '.promo-banner__text'],
            'label' => ['group' => 'forms', 'desktop' => 13, 'mobile' => 13, 'selector' => '.field__label'],
            'input' => ['group' => 'forms', 'desktop' => 16, 'mobile' => 16, 'selector' => '.input, .select, .textarea'],
            'help' => ['group' => 'forms', 'desktop' => 12, 'mobile' => 12, 'selector' => '.field__hint, .field__error'],
            'button' => ['group' => 'forms', 'desktop' => 16, 'mobile' => 16, 'selector' => '.button'],
            'button_small' => ['group' => 'forms', 'desktop' => 13, 'mobile' => 13, 'selector' => '.button--small'],
            'button_large' => ['group' => 'forms', 'desktop' => 16, 'mobile' => 16, 'selector' => '.button--large'],
            'badge' => ['group' => 'forms', 'desktop' => 11, 'mobile' => 11, 'selector' => '.badge, .panel-nav__count, .thread-preview__badge'],
            'alert' => ['group' => 'forms', 'desktop' => 13, 'mobile' => 13, 'selector' => '.alert'],
            'panel_navigation' => ['group' => 'panel', 'desktop' => 13, 'mobile' => 13, 'selector' => '.panel-nav__item'],
            'panel_title' => ['group' => 'panel', 'desktop' => 16, 'mobile' => 16, 'selector' => '.data-row__title'],
            'panel_meta' => ['group' => 'panel', 'desktop' => 12, 'mobile' => 12, 'selector' => '.data-row__meta'],
            'chat_name' => ['group' => 'panel', 'desktop' => 13, 'mobile' => 13, 'selector' => '.thread-preview__name'],
            'chat_preview' => ['group' => 'panel', 'desktop' => 12, 'mobile' => 12, 'selector' => '.thread-preview__text'],
            'chat' => ['group' => 'panel', 'desktop' => 16, 'mobile' => 16, 'selector' => '.thread__text'],
            'chat_time' => ['group' => 'panel', 'desktop' => 11, 'mobile' => 11, 'selector' => '.thread__time'],
            'plan_price' => ['group' => 'plans', 'desktop' => 32, 'mobile' => 26, 'selector' => '.plan-card__price'],
            'plan_features' => ['group' => 'plans', 'desktop' => 13, 'mobile' => 13, 'selector' => '.plan-card__features'],
            'footer_heading' => ['group' => 'footer', 'desktop' => 13, 'mobile' => 13, 'selector' => '.site-footer__heading'],
            'footer_link' => ['group' => 'footer', 'desktop' => 13, 'mobile' => 13, 'selector' => '.site-footer__link'],
            'footer_meta' => ['group' => 'footer', 'desktop' => 12, 'mobile' => 12, 'selector' => '.site-footer__bottom, .locale-list__item'],
        ];
    }

    public static function defaults(): array
    {
        $typography = [];
        foreach (self::fonts() as $key => $font) {
            $typography[$key] = ['desktop' => $font['desktop'], 'mobile' => $font['mobile']];
        }

        return ['colors' => self::colors(), 'typography' => $typography];
    }

    public static function normalize(array $data): array
    {
        $result = self::defaults();
        foreach ($result['colors'] as $key => $default) {
            $value = $data['colors'][$key] ?? null;
            if (is_string($value) && preg_match('/\A#[0-9a-fA-F]{6}\z/', $value)) {
                $result['colors'][$key] = strtolower($value);
            }
        }
        foreach ($result['typography'] as $key => $sizes) {
            foreach ($sizes as $device => $default) {
                $value = $data['typography'][$key][$device] ?? null;
                if (filter_var($value, FILTER_VALIDATE_INT) !== false && $value >= 10 && $value <= 72) {
                    $result['typography'][$key][$device] = (int) $value;
                }
            }
        }

        return $result;
    }

    public static function current(): array
    {
        try {
            return self::normalize(app(AppearanceSettings::class)->toArray());
        } catch (MissingSettings) {
            return self::defaults();
        }
    }

    public static function foreground(string $color): string
    {
        $channels = array_map(static function (string $channel): float {
            $value = hexdec($channel) / 255;

            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, str_split(substr($color, 1), 2));
        $luminance = $channels[0] * 0.2126 + $channels[1] * 0.7152 + $channels[2] * 0.0722;

        return ($luminance + 0.05) / 0.05 >= 1.05 / ($luminance + 0.05) ? '#000000' : '#ffffff';
    }

    public static function variables(array $data, string $device = 'mobile'): string
    {
        $data = self::normalize($data);
        $colors = $data['colors'];
        $tokens = [
            'accent' => 'accent', 'surface-sunken' => 'page', 'surface-page' => 'surface',
            'surface-raised' => 'surface', 'surface-overlay' => 'header', 'ink-900' => 'heading',
            'ink-800' => 'text', 'ink-700' => 'text', 'ink-600' => 'muted', 'ink-500' => 'muted',
            'line-hairline' => 'border', 'line-strong' => 'border', 'positive' => 'success',
            'caution' => 'warning', 'critical' => 'danger',
        ];
        $variables = [];
        foreach ($tokens as $token => $key) {
            $variables[] = '--'.$token.':'.$colors[$key];
        }
        foreach ($colors as $key => $value) {
            $variables[] = '--appearance-'.str_replace('_', '-', $key).':'.$value;
        }
        foreach (['primary', 'accent', 'banner'] as $key) {
            $variables[] = '--appearance-on-'.$key.':'.self::foreground($colors[$key]);
        }
        foreach (['accent' => 'accent', 'positive' => 'success', 'caution' => 'warning', 'critical' => 'danger'] as $token => $key) {
            $variables[] = '--'.$token.'-soft:color-mix(in srgb, '.$colors[$key].' 10%, '.$colors['surface'].')';
        }
        $variables[] = '--accent-strong:color-mix(in srgb, '.$colors['accent'].' 85%, #000000)';
        foreach ($data['typography'] as $key => $sizes) {
            $variables[] = '--font-'.str_replace('_', '-', $key).':'.($sizes[$device === 'desktop' ? 'desktop' : 'mobile'] / 16).'rem';
        }
        $variables[] = '--text-base:var(--font-body)';
        $variables[] = '--focus-ring:0 0 0 3px color-mix(in srgb, '.$colors['accent'].' 35%, transparent)';

        return implode(';', $variables).';';
    }

    public static function css(array $data): string
    {
        $css = 'html:root{'.self::variables($data).'}';
        $css .= '@media(min-width:64rem){html:root{'.self::variables($data, 'desktop').'}}';
        foreach (self::fonts() as $key => $font) {
            $css .= implode(',', array_map(static fn (string $selector): string => ($selector === 'body' ? 'html body.app-shell' : 'html body.app-shell '.$selector), explode(', ', $font['selector'])));
            $css .= '{font-size:var(--font-'.str_replace('_', '-', $key).');}';
        }

        return $css;
    }
}
