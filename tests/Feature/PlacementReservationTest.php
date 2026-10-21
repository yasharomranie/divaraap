<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Advertising\Filament\Admin\Pages\PlacementCalendar;
use Modules\Advertising\Filament\Admin\Resources\PlacementReservationResource;
use Modules\Advertising\Filament\Admin\Resources\PlacementReservationResource\Pages\CreateReservation;
use Modules\Advertising\Filament\Admin\Resources\PlacementReservationResource\Pages\ListReservations;
use Modules\Advertising\Models\AdvertisingOrder;
use Modules\Advertising\Models\AdvertisingPayment;
use Modules\Advertising\Models\BannerPlacement;
use Modules\Advertising\Models\PlacementReservation;
use Modules\User\App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class PlacementReservationTest extends TestCase
{
    private User $admin;

    private BannerPlacement $placement;

    private Carbon $start;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::where('email', 'a@a.com')->firstOrFail();
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->placement = BannerPlacement::where('key', 'home-top')->firstOrFail();
        $this->start = now()->addDay()->startOfDay();
    }

    private function data(array $overrides = []): array
    {
        return array_replace(['request_key' => (string) Str::uuid(), 'placement_id' => $this->placement->id, 'title' => 'Reservation fixture', 'device' => 'both', 'starts_at' => $this->start->toIso8601String(), 'ends_at' => $this->start->copy()->addHours(2)->toIso8601String(), 'note' => 'PRIVATE RESERVATION NOTE'], $overrides);
    }

    private function invalid(callable $action, string $field): void
    {
        try {
            $action();
            $this->fail('Invalid reservation accepted');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public function test_overlaps_are_blocked_and_adjacent_half_open_windows_are_allowed(): void
    {
        $first = PlacementReservation::reserve($this->data());
        $this->invalid(fn () => PlacementReservation::reserve($this->data(['starts_at' => $this->start->copy()->addHour()->toIso8601String()])), 'starts_at');
        $this->invalid(fn () => PlacementReservation::reserve($this->data(['starts_at' => $this->start->copy()->subHour()->toIso8601String(), 'ends_at' => $this->start->copy()->addHours(3)->toIso8601String()])), 'starts_at');
        $next = PlacementReservation::reserve($this->data(['starts_at' => $first->ends_at->toIso8601String(), 'ends_at' => $first->ends_at->copy()->addHour()->toIso8601String()]));
        $this->assertSame($first->ends_at->timestamp, $next->starts_at->timestamp);
        $other = BannerPlacement::where('key', 'listing-details')->firstOrFail();
        $this->assertNotNull(PlacementReservation::reserve($this->data(['placement_id' => $other->id]))->id);
    }

    public function test_mobile_and_desktop_can_coexist_but_both_conflicts_with_either(): void
    {
        PlacementReservation::reserve($this->data(['device' => 'mobile']));
        PlacementReservation::reserve($this->data(['device' => 'desktop']));
        $this->invalid(fn () => PlacementReservation::reserve($this->data()), 'starts_at');
        $this->invalid(fn () => PlacementReservation::reserve($this->data(['device' => 'mobile'])), 'starts_at');
        $this->assertSame(2, PlacementReservation::where('placement_id', $this->placement->id)->count());
    }

    public function test_time_bounds_precision_and_device_support_are_validated(): void
    {
        foreach ([['starts_at' => now()->subMinute()->startOfMinute()->toIso8601String()], ['starts_at' => $this->start->copy()->second(1)->toIso8601String()], ['ends_at' => $this->start->copy()->addDays(91)->toIso8601String()]] as $input) {
            $this->invalid(fn () => PlacementReservation::reserve($this->data($input)), 'starts_at');
        }
        $this->invalid(fn () => PlacementReservation::reserve($this->data(['ends_at' => $this->start->toIso8601String()])), 'ends_at');
        $this->placement->update(['device' => 'mobile']);
        $this->invalid(fn () => PlacementReservation::reserve($this->data()), 'placement_id');
        $this->placement->update(['device' => 'both', 'is_active' => false]);
        $this->invalid(fn () => PlacementReservation::reserve($this->data()), 'placement_id');
    }

    public function test_replay_is_idempotent_and_changed_details_cannot_reuse_identity(): void
    {
        $data = $this->data();
        $first = PlacementReservation::reserve($data);
        $this->assertSame($first->id, PlacementReservation::reserve($data)->id);
        $this->invalid(fn () => PlacementReservation::reserve(array_replace($data, ['title' => 'Different title'])), 'request_key');
        $this->invalid(fn () => $first->update(['title' => 'Bypass']), 'reservation');
        $this->invalid(fn () => $first->delete(), 'reservation');
        $this->invalid(fn () => $first->forceDelete(), 'reservation');
    }

    public function test_cancellation_releases_calendar_window_and_retains_history_and_actor(): void
    {
        $first = PlacementReservation::reserve($this->data());
        $first->cancel('Changed booking');
        $this->assertSame('cancelled', $first->state);
        $this->assertSame($this->admin->id, $first->cancelled_by);
        $this->assertSame('Changed booking', $first->cancel_reason);
        $this->assertNotNull($first->cancelled_at);
        $new = PlacementReservation::reserve($this->data());
        $this->assertNotSame($first->id, $new->id);
        $this->assertSame(2, PlacementReservation::where('placement_id', $this->placement->id)->count());
        $this->travelTo($new->ends_at);
        $this->assertSame('ended', $new->displayState());
        $this->invalid(fn () => $new->cancel('Too late'), 'reservation');
    }

    public function test_tehran_month_boundaries_and_midnight_exclusive_end_are_correct(): void
    {
        $this->travelTo(Carbon::parse('2026-10-30 10:00:00', 'UTC'));
        $first = PlacementReservation::reserve($this->data(['starts_at' => '2026-10-31T20:00:00Z', 'ends_at' => '2026-10-31T21:00:00Z']));
        $oct = PlacementReservation::calendar('2026-10');
        $nov = PlacementReservation::calendar('2026-11');
        $this->assertSame($first->id, $oct['days'][30]['items']->first()->id);
        $this->assertSame($first->id, $nov['days'][0]['items']->first()->id);
        $first->cancel('Boundary test');
        PlacementReservation::reserve($this->data(['starts_at' => '2026-10-31T19:30:00Z', 'ends_at' => '2026-10-31T20:30:00Z']));
        $this->assertCount(0, PlacementReservation::calendar('2026-11')['days'][0]['items']);
    }

    public function test_only_accepted_paid_matching_orders_can_be_linked_and_customer_notes_stay_private(): void
    {
        $customer = User::factory()->create(['status' => 'active']);
        $this->actingAs($customer);
        $order = AdvertisingOrder::submit(['request_key' => (string) Str::uuid(), 'title' => 'Order reservation fixture', 'brief' => 'Brief', 'goal' => 'awareness', 'placement' => 'home-top', 'duration_days' => 7]);
        $this->actingAs($this->admin);
        $this->invalid(fn () => PlacementReservation::reserve($this->data(['order_id' => $order->id])), 'order_id');
        $order->quote('100', 'Terms');
        $this->actingAs($customer);
        $order->customerAction('accept');
        $this->actingAs($this->admin);
        $this->invalid(fn () => PlacementReservation::reserve($this->data(['order_id' => $order->id])), 'order_id');
        AdvertisingPayment::recordReceived($order->invoice, '100', 'RESERVATION-ORDER');
        $reservation = PlacementReservation::reserve($this->data(['order_id' => $order->id, 'title' => '<script>reservation()</script>']));
        $this->actingAs($customer)->get(route('advertising.orders.show', $order))->assertOk()->assertSee('data-order-reservation="'.$reservation->id.'"', false)->assertSee('&lt;script&gt;reservation()&lt;/script&gt;', false)->assertDontSee('PRIVATE RESERVATION NOTE')->assertDontSee('<script>reservation()</script>', false);
        $this->actingAs($this->admin);
        $other = BannerPlacement::where('key', 'listing-details')->firstOrFail();
        $this->invalid(fn () => PlacementReservation::reserve($this->data(['order_id' => $order->id, 'placement_id' => $other->id])), 'order_id');
    }

    public function test_staff_and_customers_cannot_access_calendar_or_mutate_reservations(): void
    {
        $first = PlacementReservation::reserve($this->data());
        $staff = User::factory()->create(['status' => 'active']);
        $staff->givePermissionTo('advertising.manage');
        $this->actingAs($staff);
        $this->assertFalse(PlacementReservationResource::canViewAny());
        $this->assertFalse(PlacementCalendar::canAccess());
        $this->get(PlacementCalendar::getUrl())->assertForbidden();
        foreach ([fn () => PlacementReservation::reserve($this->data()), fn () => $first->cancel('Forbidden')] as $action) {
            try {
                $action();
                $this->fail('Staff reservation mutation accepted');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
    }

    public function test_calendar_filters_and_invalid_month_are_safe(): void
    {
        $first = PlacementReservation::reserve($this->data());
        $data = PlacementReservation::calendar($this->start->format('Y-m'), (string) $this->placement->id);
        $this->assertFalse($data['limited']);
        $this->assertSame($first->id, collect($data['days'])->flatMap(fn ($day) => $day['items'])->first()->id);
        $other = BannerPlacement::where('key', 'listing-details')->firstOrFail();
        $this->assertCount(0, collect(PlacementReservation::calendar($this->start->format('Y-m'), (string) $other->id)['days'])->flatMap(fn ($day) => $day['items']));
        Livewire::test(PlacementCalendar::class)->set('month', '<script>')->assertSee(__('advertising::reservations.invalid_month'))->assertDontSee('<script>', false);
    }

    public function test_filament_creation_converts_tehran_time_and_cancel_action_works(): void
    {
        $start = $this->start->copy()->timezone('Asia/Tehran')->format('Y-m-d H:i:s');
        $end = $this->start->copy()->addHours(2)->timezone('Asia/Tehran')->format('Y-m-d H:i:s');
        Livewire::test(CreateReservation::class)->fillForm(['title' => 'Filament booking', 'placement_id' => $this->placement->id, 'device' => 'both', 'starts_at' => $start, 'ends_at' => $end])->call('create')->assertHasNoFormErrors();
        $reservation = PlacementReservation::where('title', 'Filament booking')->firstOrFail();
        $this->assertSame($this->start->timestamp, $reservation->starts_at->timestamp);
        Livewire::test(ListReservations::class)->callTableAction('cancel', $reservation, ['reason' => 'Panel cancellation'])->assertHasNoActionErrors();
        $this->assertSame('cancelled', $reservation->fresh()->state);
    }
}
