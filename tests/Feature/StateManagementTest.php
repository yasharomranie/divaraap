<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Modules\Listing\Filament\Admin\Resources\ListingResource\Pages\EditListing;
use Modules\Listing\Filament\Admin\Resources\ListingResource\Pages\ListListings;
use Modules\Listing\Models\Listing;
use Modules\Listing\Support\ListingPanelHelper;
use Modules\User\App\Models\User;
use Modules\User\Filament\Admin\Resources\UserResource\Pages\EditUser;
use Modules\User\Filament\Admin\Resources\UserResource\Pages\ListUsers;
use Spatie\ModelStates\Events\StateChanged;
use Tests\TestCase;

class StateManagementTest extends TestCase
{
    public function test_user_status_column_preserves_state_transitions(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::query()->where('email', 'a@a.com')->firstOrFail());
        $user = User::query()->where('email', 'b@b.com')->firstOrFail();
        Event::fake([StateChanged::class]);
        $component = Livewire::test(ListUsers::class);
        $column = $component->instance()->getTable()->getColumn('status')->record($user);

        $this->assertSame('active', $column->getState());
        $component->call('updateTableColumnState', 'status', (string) $user->getKey(), 'suspended');

        $this->assertSame('suspended', (string) $user->fresh()->status);
        Event::assertDispatched(StateChanged::class);
    }

    public function test_listing_status_column_preserves_state_transitions(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::query()->where('email', 'a@a.com')->firstOrFail());
        $listing = Listing::query()->active()->firstOrFail();
        Event::fake([StateChanged::class]);
        $component = Livewire::test(ListListings::class);
        $column = $component->instance()->getTable()->getColumn('status')->record($listing);

        $this->assertSame('active', $column->getState());
        $component->call('updateTableColumnState', 'status', (string) $listing->getKey(), 'sold');

        $this->assertSame('sold', (string) $listing->fresh()->status);
        Event::assertDispatched(StateChanged::class);
    }

    public function test_admin_can_hydrate_and_save_a_user_status(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::query()->where('email', 'a@a.com')->firstOrFail());
        $user = User::query()->where('email', 'b@b.com')->firstOrFail();

        Livewire::test(EditUser::class, ['record' => $user->getKey()])
            ->assertFormSet(['status' => 'active'])
            ->fillForm(['status' => 'suspended'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('suspended', (string) $user->fresh()->status);
    }

    public function test_admin_status_filter_uses_the_persisted_state_value(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = User::query()->where('email', 'a@a.com')->firstOrFail();
        $this->actingAs($admin);
        $user = User::query()->where('email', 'b@b.com')->firstOrFail();
        $user->status->transitionTo('suspended');

        Livewire::test(ListUsers::class)
            ->filterTable('status', 'suspended')
            ->assertCanSeeTableRecords([$user])
            ->assertCanNotSeeTableRecords([$admin]);
    }

    public function test_status_form_rejects_unknown_values(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::query()->where('email', 'a@a.com')->firstOrFail());
        $user = User::query()->where('email', 'b@b.com')->firstOrFail();

        Livewire::test(EditUser::class, ['record' => $user->getKey()])
            ->fillForm(['status' => 'unknown'])
            ->call('save')
            ->assertHasFormErrors(['status']);

        $this->assertSame('active', (string) $user->fresh()->status);
    }

    public function test_admin_can_hydrate_and_save_a_listing_status(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::query()->where('email', 'a@a.com')->firstOrFail());
        $listing = Listing::query()->active()->doesntHave('videos')->firstOrFail();
        $listing->update(['currency' => ListingPanelHelper::defaultCurrency()]);

        Livewire::test(EditListing::class, ['record' => $listing->getKey()])
            ->assertFormSet(['status' => 'active'])
            ->fillForm(['status' => 'sold'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('sold', (string) $listing->fresh()->status);
    }

    public function test_status_column_rejects_unknown_values(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::query()->where('email', 'a@a.com')->firstOrFail());
        $user = User::query()->where('email', 'b@b.com')->firstOrFail();

        Livewire::test(ListUsers::class)
            ->call('updateTableColumnState', 'status', (string) $user->getKey(), 'unknown');

        $this->assertSame('active', (string) $user->fresh()->status);
    }
}
