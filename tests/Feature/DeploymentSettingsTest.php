<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Admin\Filament\Pages\ManageDeployment;
use Modules\Site\App\Settings\DeploymentSettings;
use Modules\User\App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

final class DeploymentSettingsTest extends TestCase
{
    public function test_admin_saves_encrypted_token_and_blank_edit_preserves_it(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::where('email', 'a@a.com')->firstOrFail());
        Livewire::test(ManageDeployment::class)->fillForm(['repository' => 'owner/project', 'ref' => 'main', 'github_token' => 'secret-test-token'])->call('save')->assertHasNoFormErrors()->assertSet('data.github_token', '');
        $raw = DB::table('settings')->where('group', 'deployment')->where('name', 'github_token')->value('payload');
        $this->assertStringNotContainsString('secret-test-token', $raw);
        $this->assertSame('secret-test-token', app(DeploymentSettings::class)->github_token);
        Livewire::test(ManageDeployment::class)->assertSet('data.github_token', '')->fillForm(['github_token' => '', 'ref' => 'stable'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('secret-test-token', app(DeploymentSettings::class)->github_token);
    }

    public function test_phone_input_uses_local_country_without_remote_ip_lookup(): void
    {
        $input = PhoneInput::make('phone')->defaultCountry('IR');
        $this->assertFalse($input->canPerformIpLookup());
        $this->assertSame('ir', $input->getInitialCountry());
    }

    public function test_member_cannot_access_or_mutate_deployment(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::where('email', 'b@b.com')->firstOrFail())->get('/admin/manage-deployment')->assertForbidden();
        $page = new ManageDeployment;
        $this->assertFalse($page::canAccess());
        $this->expectException(HttpException::class);
        $page->deploy();
    }

    public function test_shared_pages_reject_external_scripts_styles_and_fonts(): void
    {
        config(['hosting.profile' => 'shared']);
        $response = $this->get('/');
        $response->assertSuccessful();
        $policy = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self'", $policy);
        $this->assertStringContainsString("font-src 'self' data:", $policy);
        $this->assertStringNotContainsString('https:', $policy);
    }
}
