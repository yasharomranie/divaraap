<?php

declare(strict_types=1);

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class MobileNavigationTest extends TestCase
{
    private function navigation(string $path): DOMXPath
    {
        $response = $this->get($path)->assertSuccessful();
        $document = new DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);

        return new DOMXPath($document);
    }

    public function test_guest_navigation_has_five_destinations_and_requires_login_to_post(): void
    {
        $xpath = $this->navigation('/');
        $this->assertCount(5, $xpath->query('//nav[@data-mobile-navigation]/a'));
        $selected = $xpath->query('//nav[@data-mobile-navigation]/a[@aria-current="page"]');
        $this->assertCount(1, $selected);
        $this->assertSame(route('home'), $selected->item(0)->getAttribute('href'));
        $post = $xpath->query('//nav[@data-mobile-navigation]/a[contains(@class,"mobile-tabs__item--create")]')->item(0);
        $this->assertSame(route('login'), $post->getAttribute('href'));
        $this->get(route('panel.listings.create'))->assertRedirect();
    }

    public function test_member_navigation_opens_the_real_create_and_inbox_routes(): void
    {
        $this->actingAs(User::where('email', 'b@b.com')->firstOrFail());
        $xpath = $this->navigation('/panel/inbox');
        $selected = $xpath->query('//nav[@data-mobile-navigation]/a[@aria-current="page"]');
        $this->assertCount(1, $selected);
        $this->assertSame(route('panel.inbox.index'), $selected->item(0)->getAttribute('href'));
        $post = $xpath->query('//nav[@data-mobile-navigation]/a[contains(@class,"mobile-tabs__item--create")]')->item(0);
        $this->assertSame(route('panel.listings.create'), $post->getAttribute('href'));
    }

    public function test_authentication_screen_is_not_obscured_by_site_navigation(): void
    {
        $xpath = $this->navigation('/login');
        $this->assertCount(0, $xpath->query('//nav[@data-mobile-navigation]'));
    }

    public function test_admin_gets_settings_navigation_while_members_are_denied(): void
    {
        $this->actingAs(User::where('email', 'a@a.com')->firstOrFail());
        $xpath = $this->navigation('/admin/manage-general-settings');
        $selected = $xpath->query('//nav[@class="admin-mobile-tabs"]/a[@aria-current="page"]');
        $this->assertCount(1, $selected);
        $this->assertSame(url('/admin/manage-general-settings'), $selected->item(0)->getAttribute('href'));
        $this->actingAs(User::where('email', 'b@b.com')->firstOrFail());
        $this->get('/admin/manage-general-settings')->assertForbidden();
    }
}
