<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Modules\Advertising\Filament\Admin\Pages\AdvertisingStatistics;
use Modules\Advertising\Models\AdvertisingEvent;
use Modules\Advertising\Models\Banner;
use Modules\Advertising\Models\Campaign;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class AdvertisingReportTest extends TestCase
{
    private function admin(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::where('email', 'a@a.com')->firstOrFail());
    }

    private function banner(string $title = 'Report banner', ?Campaign $campaign = null): Banner
    {
        return Banner::create(['title' => $title, 'image' => 'reports.jpg', 'alt' => 'Banner', 'device' => 'both', 'is_active' => false, 'campaign_id' => $campaign?->id]);
    }

    private function event(Banner $banner, string $kind, ?string $date = null): void
    {
        AdvertisingEvent::create(['banner_id' => $banner->id, 'campaign_id' => $banner->campaign_id, 'placement' => 'home-feed', 'device' => 'mobile', 'kind' => $kind, 'receipt' => bin2hex(random_bytes(32)), 'event_date' => $date ?? now()->toDateString()]);
    }

    private function filters(array $data = []): array
    {
        return array_replace(['dimension' => 'banner_id', 'fromDate' => now()->subDays(29)->toDateString(), 'toDate' => now()->toDateString()], $data);
    }

    private function download(array $filters = []): TestResponse
    {
        return $this->get(route('advertising.report-download', $this->filters($filters)));
    }

    public function test_export_matches_filtered_screen_report_and_contains_no_private_identifiers(): void
    {
        $campaign = Campaign::create(['title' => 'Report campaign']);
        $banner = $this->banner(campaign: $campaign);
        $other = $this->banner('Other campaign banner');
        $this->event($banner, 'impression');
        $this->event($banner, 'click');
        $this->event($banner, 'conversion');
        $this->event($banner, 'shop_view');
        $this->event($banner, 'service');
        $this->event($other, 'impression');
        $this->admin();
        $response = $this->download(['campaignId' => $campaign->id])->assertSuccessful();
        $response->assertDownload('advertising-report-'.now()->subDays(29)->toDateString().'-to-'.now()->toDateString().'.csv');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Report campaign', $csv);
        $this->assertStringContainsString('Report banner', $csv);
        $this->assertStringNotContainsString('Other campaign banner', $csv);
        foreach (['receipt', 'visitor', 'user_id', 'target_listing_id', 'target_shop_id', 'source_click_id'] as $private) {
            $this->assertStringNotContainsString($private, $csv);
        }
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $csv);
        rewind($stream);
        fgetcsv($stream, escape: '');
        $row = fgetcsv($stream, escape: '');
        fclose($stream);
        $this->assertSame(['1', '1', '100', '1', '1', '1'], array_slice($row, 5));
        $this->assertSame(1, AdvertisingEvent::report($campaign->id, 'banner_id')[0]['conversations']);
    }

    public function test_date_boundaries_and_archived_campaigns_are_supported(): void
    {
        $campaign = Campaign::create(['title' => 'Archived campaign']);
        $banner = $this->banner('Archived creative', $campaign);
        $today = now()->toDateString();
        $this->event($banner, 'impression', $today);
        $this->event($banner, 'click', now()->subDay()->toDateString());
        $campaign->delete();
        $banner->delete();
        $this->admin();
        $response = $this->download(['campaignId' => $campaign->id, 'fromDate' => $today, 'toDate' => $today])->assertSuccessful();
        $this->assertStringContainsString('Archived creative', $response->streamedContent());
        $this->assertSame(1, AdvertisingEvent::report($campaign->id, 'banner_id', $today, $today)[0]['impressions']);
        $this->assertSame(0, AdvertisingEvent::report($campaign->id, 'banner_id', $today, $today)[0]['clicks']);
        $this->assertArrayHasKey($campaign->id, Campaign::reportOptions());
        $this->assertArrayNotHasKey($campaign->id, Campaign::options());
    }

    public function test_csv_neutralizes_spreadsheet_formulas_in_titles(): void
    {
        $campaign = Campaign::create(['title' => '=HYPERLINK("https://example.com")']);
        foreach (['=1+1', '+1+1', '-1+1', '@SUM(1)', "\t=1+1", "\r\n+1+1"] as $title) {
            $this->event($this->banner($title, $campaign), 'impression');
        }
        $this->admin();
        $csv = $this->download(['campaignId' => $campaign->id])->assertSuccessful()->streamedContent();
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $csv);
        rewind($stream);
        fgetcsv($stream, escape: '');
        $count = 0;
        while (($row = fgetcsv($stream, escape: '')) !== false) {
            $this->assertStringStartsWith("'", $row[0]);
            $this->assertStringStartsWith("'", $row[4]);
            $count++;
        }
        fclose($stream);
        $this->assertSame(6, $count);
    }

    public function test_download_includes_all_groups_beyond_the_ui_limit_and_stream_chunk_boundary(): void
    {
        for ($i = 0; $i < 205; $i++) {
            $this->event($this->banner('Export creative '.$i), 'impression');
        }
        $this->admin();
        $this->assertCount(100, AdvertisingEvent::report(null, 'banner_id'));
        $csv = $this->download()->assertSuccessful()->streamedContent();
        $this->assertSame(206, count(array_filter(explode("\n", trim($csv)))));
        $this->assertStringContainsString('Export creative 0', $csv);
        $this->assertStringContainsString('Export creative 204', $csv);
    }

    public function test_invalid_dimensions_ranges_and_unknown_campaigns_are_rejected(): void
    {
        $this->admin();
        foreach ([['dimension' => 'receipt'], ['fromDate' => now()->subDays(90)->toDateString()], ['toDate' => now()->addDay()->toDateString()], ['fromDate' => 'not-a-date'], ['campaignId' => 999999999], ['fromDate' => now()->toDateString(), 'toDate' => now()->subDay()->toDateString()]] as $invalid) {
            $this->getJson(route('advertising.report-download', $this->filters($invalid)))->assertUnprocessable();
        }
        Livewire::test(AdvertisingStatistics::class)->set('fromDate', now()->addDay()->toDateString())->assertHasErrors(['fromDate']);
    }

    public function test_only_active_advertising_staff_can_download_reports_and_empty_reports_have_headers(): void
    {
        $this->get(route('advertising.report-download', $this->filters()))->assertRedirect();
        $user = User::factory()->create(['status' => 'active']);
        $this->actingAs($user);
        $this->download()->assertForbidden();
        $user->assignRole('advertising-manager');
        $csv = $this->download()->assertSuccessful()->streamedContent();
        $this->assertCount(1, array_filter(explode("\n", trim($csv))));
        $user->update(['status' => 'suspended']);
        $this->download()->assertForbidden();
    }
}
