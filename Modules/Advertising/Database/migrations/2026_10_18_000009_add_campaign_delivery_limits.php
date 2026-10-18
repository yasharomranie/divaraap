<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advertising_campaigns', function (Blueprint $table): void {
            $table->unsignedBigInteger('impression_limit')->nullable();
            $table->unsignedBigInteger('click_limit')->nullable();
            $table->unsignedBigInteger('cost_limit')->nullable();
            $table->unsignedBigInteger('impression_cost')->default(0);
            $table->unsignedBigInteger('click_cost')->default(0);
            $table->unsignedBigInteger('recorded_impressions')->default(0);
            $table->unsignedBigInteger('recorded_clicks')->default(0);
            $table->unsignedBigInteger('recorded_cost')->default(0);
        });
        $counts = DB::table('advertising_events')->whereNotNull('campaign_id')->whereNull('deleted_at')->select('campaign_id')
            ->selectRaw("SUM(CASE WHEN kind = 'impression' THEN 1 ELSE 0 END) AS impressions")
            ->selectRaw("SUM(CASE WHEN kind = 'click' THEN 1 ELSE 0 END) AS clicks")->groupBy('campaign_id')->get();
        foreach ($counts as $row) {
            DB::table('advertising_campaigns')->where('id', $row->campaign_id)->update(['recorded_impressions' => (int) $row->impressions, 'recorded_clicks' => (int) $row->clicks]);
        }
    }

    public function down(): void
    {
        Schema::table('advertising_campaigns', function (Blueprint $table): void {
            $table->dropColumn(['impression_limit', 'click_limit', 'cost_limit', 'impression_cost', 'click_cost', 'recorded_impressions', 'recorded_clicks', 'recorded_cost']);
        });
    }
};
