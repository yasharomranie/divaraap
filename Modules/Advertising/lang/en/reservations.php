<?php

declare(strict_types=1);

return [
    'starts_at' => 'Reservation start',
    'ends_at' => 'Reservation end',
    'reservation' => 'Placement reservation',
    'reservations' => 'Placement reservations',
    'calendar' => 'Placement reservation calendar',
    'title' => 'Reservation title (visible to customer)',
    'month' => 'Gregorian month, Tehran time',
    'order_hint' => 'Optional: an accepted order with a fully received valid invoice, for this same placement. Final eligibility is checked when saving.',
    'time_hint' => 'Tehran time. Future minute boundaries, start within one year, duration up to 90 days. The end instant is excluded.',
    'scope' => 'This calendar coordinates whole-placement/device bookings, not page rendering. Existing campaigns and general ads are not paused or automatically published; an empty day is not proof of available inventory. Coordinate the agreed delivery separately.',
    'cancel' => 'Cancel reservation',
    'cancel_hint' => 'Release this calendar interval. This does not stop a campaign, cancel an order or refund money.',
    'cancel_reason' => 'Reservation cancellation reason',
    'upcoming' => 'Upcoming',
    'current' => 'Within booked interval',
    'ended' => 'Ended',
    'cancelled' => 'Cancelled',
    'immutable' => 'Reservation history cannot be edited or deleted. Cancel and create a new reservation to change its interval.',
    'invalid_window' => 'Choose future whole-minute times, starting within one year and lasting no more than 90 days.',
    'invalid_placement' => 'Choose an active placement supporting the selected device.',
    'invalid_order' => 'The order must be accepted, fully settled and for this placement.',
    'conflict' => 'This placement already has an overlapping confirmed reservation for the same device.',
    'cannot_cancel' => 'Only a confirmed reservation that has not ended can be cancelled.',
    'empty_day' => 'No recorded reservation; inventory availability is not guaranteed.',
    'limited' => 'Only the first 1,000 matching reservations are shown. Narrow the placement filter or use the reservation list.',
    'invalid_month' => 'Choose a valid Gregorian month between 2000 and 2099.',
];
