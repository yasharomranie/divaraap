<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Modules\Site\App\Settings\GeneralSettings;
use Modules\User\App\Models\User;
use Tests\TestCase;

class PersianLocaleTest extends TestCase
{
    public function test_persian_home_uses_rtl_and_local_fonts(): void
    {
        $this->withSession(['locale' => 'fa'])->get('/')
            ->assertSuccessful()
            ->assertSee('lang="fa"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee('چیزهای خوب،')->assertSee('دوباره پیدا می‌شوند.')
            ->assertSee('فارسی')
            ->assertSee('assets/fonts/vazirmatn/font.css', false);
    }

    public function test_language_switch_persists_persian_for_auth_pages(): void
    {
        $this->from('/')->get('/lang/fa')
            ->assertRedirect('/')
            ->assertSessionHas('locale', 'fa');

        $this->get('/login')
            ->assertSuccessful()
            ->assertSee('lang="fa"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee('رمز عبور')
            ->assertSee('ایمیل');
    }

    public function test_english_remains_selectable_and_ltr(): void
    {
        $this->withSession(['locale' => 'fa'])->from('/')->get('/lang/en')
            ->assertSessionHas('locale', 'en');

        $this->get('/')
            ->assertSuccessful()
            ->assertSee('lang="en"', false)
            ->assertSee('dir="ltr"', false)
            ->assertSee('Good things,')->assertSee('found again.');
    }

    public function test_unsupported_language_does_not_replace_the_session(): void
    {
        $this->withSession(['locale' => 'fa'])->from('/')->get('/lang/unknown')
            ->assertRedirect('/')
            ->assertSessionHas('locale', 'fa');
    }

    public function test_admin_uses_persian_and_a_local_font_provider(): void
    {
        $admin = User::query()->where('email', 'a@a.com')->firstOrFail();

        $this->actingAs($admin)->withSession(['locale' => 'fa'])->get('/admin/users')
            ->assertSuccessful()
            ->assertSee('lang="fa"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee('assets/fonts/vazirmatn/font.css', false)
            ->assertDontSee('fonts.bunny.net', false);
    }

    public function test_persian_translations_keep_keys_and_placeholders(): void
    {
        foreach (['Site', 'User'] as $module) {
            $english = require base_path("Modules/{$module}/lang/en/messages.php");
            $persian = require base_path("Modules/{$module}/lang/fa/messages.php");
            $this->assertEqualsCanonicalizing(array_keys($english), array_keys($persian));

            foreach ($english as $key => $value) {
                preg_match_all('/:([a-z_]+)/i', $value, $englishTokens);
                preg_match_all('/:([a-z_]+)/i', $persian[$key], $persianTokens);
                $this->assertEqualsCanonicalizing($englishTokens[1], $persianTokens[1], "{$module}:{$key}");
            }
        }

        app()->setLocale('fa');
        $this->assertSame('2 نتیجه', trans_choice('site::messages.results_count', 2));
    }

    public function test_validation_errors_use_persian_attribute_names(): void
    {
        app()->setLocale('fa');
        $message = Validator::make(['email' => 'invalid'], ['email' => 'required|email'])->errors()->first('email');

        $this->assertStringContainsString('ایمیل', $message);
        $this->assertStringNotContainsString('The email', $message);
    }

    public function test_font_files_and_license_are_bundled_with_the_project(): void
    {
        $this->assertFileExists(public_path('assets/fonts/vazirmatn/font.css'));
        $this->assertStringContainsString('SIL OPEN FONT LICENSE', file_get_contents(public_path('assets/fonts/vazirmatn/LICENSE')));

        foreach (['arabic', 'latin', 'latin-ext'] as $subset) {
            $path = public_path("assets/fonts/vazirmatn/files/vazirmatn-{$subset}-wght-normal.woff2");
            $this->assertSame('wOF2', file_get_contents($path, false, null, 0, 4));
        }
    }

    public function test_admin_default_uses_site_language_before_browser_preference(): void
    {
        $settings = app(GeneralSettings::class);
        $settings->default_language = 'fa';
        $settings->save();

        $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])->get('/admin/login')
            ->assertSuccessful()
            ->assertSee('lang="fa"', false)
            ->assertSee('dir="rtl"', false);
    }

    public function test_admin_preserves_an_explicit_english_selection(): void
    {
        $this->withSession(['locale' => 'en'])->get('/admin/login')
            ->assertSuccessful()
            ->assertSee('lang="en"', false)
            ->assertSee('dir="ltr"', false);
    }
}
