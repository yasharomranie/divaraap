<?php

declare(strict_types=1);

namespace Modules\User\App\Events;

final readonly class ShopViewed
{
    public function __construct(public int $shopId) {}
}
