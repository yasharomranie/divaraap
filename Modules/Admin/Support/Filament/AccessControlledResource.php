<?php

declare(strict_types=1);

namespace Modules\Admin\Support\Filament;

use Filament\Resources\Resource;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Modules\Admin\Support\StaffAccess;
use UnitEnum;

abstract class AccessControlledResource extends Resource
{
    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $user = auth()->user();
        if (! $user || (string) $user->status !== 'active') {
            return Response::deny();
        }
        if ($user?->hasRole('admin')) {
            return parent::getAuthorizationResponse($action, $record);
        }
        $permission = StaffAccess::permissionFor(static::getModel());

        return $permission && $user?->can($permission) ? Response::allow() : Response::deny();
    }
}
