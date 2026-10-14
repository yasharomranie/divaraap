<?php

declare(strict_types=1);

namespace App\Support\Filament;

use Filament\Forms\Components\Select;
use Spatie\ModelStates\State;

class ModelStateSelect extends Select
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->formatStateUsing(fn (mixed $state): mixed => $state instanceof State ? (string) $state : $state);
    }
}
