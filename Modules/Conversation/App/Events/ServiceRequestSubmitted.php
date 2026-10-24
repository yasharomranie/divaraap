<?php

declare(strict_types=1);

namespace Modules\Conversation\App\Events;

final readonly class ServiceRequestSubmitted
{
    public function __construct(public int $listingId) {}
}
