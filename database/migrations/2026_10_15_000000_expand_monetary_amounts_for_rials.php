<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['listings' => 'price', 'offers' => 'amount', 'promotion_plans' => 'price', 'promotion_orders' => 'amount'] as $tableName => $amountColumn) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName, $amountColumn): void {
                $column = $table->decimal($amountColumn, 20, 2);
                if ($tableName === 'listings') {
                    $column->nullable();
                } elseif ($tableName !== 'offers') {
                    $column->default(0);
                }
                $column->change();
                $table->string('currency', 3)->default('IRR')->change();
            });
        }
    }

    public function down(): void
    {
        foreach (['listings', 'offers', 'promotion_plans', 'promotion_orders'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('currency', 3)->default('USD')->change();
            });
        }
    }
};
