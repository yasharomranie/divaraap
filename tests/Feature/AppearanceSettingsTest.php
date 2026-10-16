<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\AppearanceTheme;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Site\App\Settings\AppearanceSettings;
use Modules\Site\Filament\Admin\Pages\ManageAppearanceSettings;
use Modules\User\App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class AppearanceSettingsTest extends TestCase
{
    private function admin(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::where('email', 'a@a.com')->firstOrFail());
    }

    public function test_admin_publishes_colors_and_responsive_typography_to_public_pages(): void
    {
        $this->admin();
        Livewire::test(ManageAppearanceSettings::class)
            ->fillForm(['colors.accent' => '#9146ff', 'colors.primary' => '#ffee00', 'typography.listing_title.mobile' => 20, 'typography.listing_title.desktop' => 28])
            ->call('save')->assertHasNoFormErrors();
        $settings = app(AppearanceSettings::class)->refresh();
        $this->assertSame('#9146ff', $settings->colors['accent']);
        $this->assertSame(28, $settings->typography['listing_title']['desktop']);
        auth()->logout();
        foreach (['/', '/listings', '/login'] as $path) {
            $response = $this->get($path)->assertSuccessful();
            $response->assertSee('--accent:#9146ff', false)
                ->assertSee('--appearance-on-primary:#000000', false)
                ->assertSee('--font-listing-title:1.25rem', false)
                ->assertSee('--font-listing-title:1.75rem', false)
                ->assertSee('@media(min-width:64rem)', false);
        }
    }

    public function test_invalid_css_and_font_values_cannot_be_saved(): void
    {
        $this->admin();
        Livewire::test(ManageAppearanceSettings::class)
            ->fillForm(['colors.accent' => '#fff;}</style><script>alert(1)</script>', 'typography.body.mobile' => 9, 'typography.body.desktop' => 73])
            ->call('save')->assertHasFormErrors(['colors.accent', 'typography.body.mobile', 'typography.body.desktop']);
        $this->assertSame('#0066cc', app(AppearanceSettings::class)->refresh()->colors['accent']);
    }

    public function test_reset_changes_only_draft_until_admin_saves(): void
    {
        $this->admin();
        $page = Livewire::test(ManageAppearanceSettings::class)
            ->fillForm(['colors.accent' => '#994422'])->call('save')->assertHasNoFormErrors()
            ->call('resetDraft')->assertSet('data.colors.accent', '#0066cc');
        $this->assertSame('#994422', app(AppearanceSettings::class)->refresh()->colors['accent']);
        $page->call('save')->assertHasNoFormErrors();
        $this->assertSame(AppearanceTheme::defaults(), AppearanceTheme::current());
    }

    public function test_member_cannot_access_settings_or_reset_draft(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::where('email', 'b@b.com')->firstOrFail());
        $this->get('/admin/manage-appearance-settings')->assertForbidden();
        $this->assertFalse(ManageAppearanceSettings::canAccess());
        $this->assertFalse((new ManageAppearanceSettings)->canEdit());
        $this->expectException(HttpException::class);
        (new ManageAppearanceSettings)->resetDraft();
    }

    public function test_existing_site_keeps_defaults_until_settings_migration_runs(): void
    {
        DB::table('settings')->where('group', 'appearance')->delete();
        app()->forgetInstance(AppearanceSettings::class);
        $this->assertSame(AppearanceTheme::defaults(), AppearanceTheme::current());
        $this->get('/')->assertSuccessful()->assertSee('id="site-appearance"', false);
    }

    public function test_rendering_rejects_tampered_database_values_and_unknown_properties(): void
    {
        $data = AppearanceTheme::defaults();
        $data['colors']['accent'] = '</style><script>alert(1)</script>';
        $data['colors']['injected'] = 'url(https://example.com)';
        $data['typography']['body']['mobile'] = '20; color:red';
        $data['typography']['injected'] = ['mobile' => 100];
        $css = AppearanceTheme::css($data);
        $this->assertStringNotContainsString('</style>', $css);
        $this->assertStringNotContainsString('example.com', $css);
        $this->assertStringNotContainsString('injected', $css);
        $this->assertStringContainsString('--accent:#0066cc', $css);
        $this->assertStringContainsString('--font-body:1rem', $css);
        $this->assertSame('#ffffff', AppearanceTheme::foreground('#000000'));
        $this->assertSame('#000000', AppearanceTheme::foreground('#ffffff'));
    }
}
