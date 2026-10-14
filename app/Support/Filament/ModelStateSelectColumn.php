<?php

declare(strict_types=1);

namespace App\Support\Filament;

use Filament\Tables\Columns\SelectColumn;
use Illuminate\Database\Eloquent\Model;

class ModelStateSelectColumn extends SelectColumn
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->getStateUsing(fn (Model $record): ?string => ($state = $record->getAttribute($this->getName())) === null ? null : (string) $state);
        $this->updateStateUsing(function (Model $record, string $state): string {
            $record->getAttribute($this->getName())->transitionTo($state);

            return $state;
        });
    }
}
