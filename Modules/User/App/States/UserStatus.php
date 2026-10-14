<?php

declare(strict_types=1);

namespace Modules\User\App\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class UserStatus extends State
{
    public static function options(): array
    {
        return [
            'active' => __('user::admin.active'),
            'suspended' => __('user::admin.suspended'),
            'banned' => __('user::admin.banned'),
        ];
    }

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(ActiveUserStatus::class)
            ->allowAllTransitions()
            ->ignoreSameState();
    }
}
