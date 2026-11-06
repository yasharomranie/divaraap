<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class StaffSecurityEvent extends Model
{
    use SoftDeletes;

    protected $guarded = ['*'];
}
