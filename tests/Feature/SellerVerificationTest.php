<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\User\App\Models\Profile;
use Modules\User\App\Models\User;
use Modules\User\Filament\Admin\Resources\UserResource\Pages\EditUser;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class SellerVerificationTest extends TestCase
{
    private function admin(): User
    {
        return User::where('email', 'a@a.com')->firstOrFail();
    }

    private function seller(): User
    {
        return User::where('email', 'b@b.com')->firstOrFail();
    }

    private function grant(User $seller): void
    {
        Profile::setVerificationFor($this->admin(), $seller, true, 'Reviewed seller information', Profile::verificationVersion($seller));
    }

    public function test_admin_grant_revoke_and_private_audit_are_recorded(): void
    {
        $seller = $this->seller();
        $this->grant($seller);
        $state = Profile::publicVerification($seller->id);
        $this->assertTrue($state['reviewed']);
        $this->assertTrue($state['reviewed_at']->isToday());
        $this->assertSame($this->admin()->id, Profile::detailsForUser($seller)->verified_by);
        $audit = Activity::where('log_name', 'seller-verification')->where('subject_id', $seller->id)->latest('id')->firstOrFail();
        $this->assertSame($this->admin()->id, $audit->causer_id);
        $this->assertSame('Reviewed seller information', $audit->properties['reason']);
        $this->get(route('sellers.show', $seller))->assertOk()->assertSee('data-verification="staff"', false)->assertDontSee('Reviewed seller information')->assertDontSee($this->admin()->email)->assertDontSee('verification_fingerprint');
        Profile::setVerificationFor($this->admin(), $seller, false, 'Review revoked', Profile::verificationVersion($seller));
        $this->assertFalse(Profile::publicVerification($seller->id)['reviewed']);
        $this->assertNull(Profile::detailsForUser($seller)->verified_at);
        $this->get(route('sellers.show', $seller))->assertDontSee('data-verification="staff"', false);
    }

    public function test_legacy_boolean_alone_is_not_a_public_review_badge_and_email_is_independent(): void
    {
        $seller = $this->seller();
        $seller->forceFill(['email_verified_at' => now()])->save();
        $profile = Profile::firstOrNew(['user_id' => $seller->id]);
        $profile->forceFill(['is_verified' => true, 'verified_at' => null, 'verification_fingerprint' => null])->save();
        $state = Profile::publicVerification($seller->id);
        $this->assertTrue($state['email']);
        $this->assertFalse($state['reviewed']);
        $this->grant($seller);
        $seller->forceFill(['email_verified_at' => null])->save();
        $state = Profile::publicVerification($seller->id);
        $this->assertFalse($state['email']);
        $this->assertTrue($state['reviewed']);
    }

    public function test_identity_changes_hide_the_badge_until_staff_reviews_again(): void
    {
        $seller = $this->seller();
        $this->grant($seller);
        $seller->forceFill(['name' => 'Changed identity'])->save();
        $this->assertFalse(Profile::publicVerification($seller->id)['reviewed']);
        $this->grant($seller->fresh());
        Profile::savePhoneForUser($seller, '09123456789');
        $this->assertFalse(Profile::publicVerification($seller->id)['reviewed']);
        $this->grant($seller->fresh());
        Profile::saveShopFor($seller, ['shop_enabled' => true, 'shop_name' => 'New shop'], Profile::shopVersion($seller));
        $this->assertFalse(Profile::publicVerification($seller->id)['reviewed']);
        $this->grant($seller->fresh());
        $seller->forceFill(['email' => 'changed-seller@example.test'])->save();
        $this->assertFalse(Profile::publicVerification($seller->id)['reviewed']);
    }

    public function test_inactive_deleted_sellers_never_have_public_badges(): void
    {
        $seller = $this->seller();
        $this->grant($seller);
        $seller->forceFill(['status' => 'suspended'])->save();
        $state = Profile::publicVerification($seller->id);
        $this->assertFalse($state['email']);
        $this->assertFalse($state['reviewed']);
        $seller->delete();
        $this->assertSame(['email' => false, 'reviewed' => false, 'reviewed_at' => null], Profile::publicVerification($seller->id));
    }

    public function test_ordinary_seller_cannot_approve_anyone_or_self_approve(): void
    {
        $seller = $this->seller();
        $seller->syncRoles([]);
        $this->actingAs($seller)->get('/admin/users/'.$seller->id.'/edit')->assertForbidden();
        $this->expectException(HttpException::class);
        Profile::setVerificationFor($seller, $this->admin(), true, 'Unauthorized approval', Profile::verificationVersion($this->admin()));
    }

    public function test_admin_cannot_self_approve_and_unprivileged_public_payload_is_ignored(): void
    {
        $admin = $this->admin();
        $seller = $this->seller();
        $this->actingAs($seller)->put(route('shop.update'), ['shop_enabled' => '0', 'version' => Profile::shopVersion($seller), 'is_verified' => true, 'verified_by' => $admin->id, 'verified_at' => now()->toIso8601String()])->assertRedirect();
        $this->assertFalse(Profile::publicVerification($seller->id)['reviewed']);
        $this->assertFalse((new Profile)->isFillable('is_verified'));
        $this->expectException(HttpException::class);
        Profile::setVerificationFor($admin, $admin, true, 'Self approval', Profile::verificationVersion($admin));
    }

    public function test_stale_approval_is_rejected_after_profile_changes(): void
    {
        $seller = $this->seller();
        $version = Profile::verificationVersion($seller);
        Profile::savePhoneForUser($seller, '09123456789');
        try {
            Profile::setVerificationFor($this->admin(), $seller, true, 'Stale approval', $version);
            $this->fail('Expected stale approval rejection');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('version', $exception->errors());
        }
        $this->assertFalse(Profile::publicVerification($seller->id)['reviewed']);
    }

    public function test_filament_action_grants_and_revokes_with_reason_and_never_for_self(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin());
        $seller = $this->seller();
        Livewire::test(EditUser::class, ['record' => $seller->id])
            ->callAction('manageVerification', data: ['decision' => 'grant', 'reason' => 'Staff reviewed current data', 'version' => Profile::verificationVersion($seller)])
            ->assertHasNoActionErrors();
        $this->assertTrue(Profile::publicVerification($seller->id)['reviewed']);
        Livewire::test(EditUser::class, ['record' => $seller->id])
            ->callAction('manageVerification', data: ['decision' => 'revoke', 'reason' => 'Review withdrawn by staff', 'version' => Profile::verificationVersion($seller)])
            ->assertHasNoActionErrors();
        $this->assertFalse(Profile::publicVerification($seller->id)['reviewed']);
        Livewire::test(EditUser::class, ['record' => $this->admin()->id])->assertActionHidden('manageVerification');
    }
}
