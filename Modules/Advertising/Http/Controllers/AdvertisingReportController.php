<?php

declare(strict_types=1);

namespace Modules\Advertising\Http\Controllers;

use Modules\Advertising\Http\Requests\AdvertisingReportRequest;
use Modules\Advertising\Models\AdvertisingEvent;
use Modules\Advertising\Models\Campaign;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AdvertisingReportController
{
    public function __invoke(AdvertisingReportRequest $request): StreamedResponse
    {
        $filters = $request->validated();
        $campaignId = filled($filters['campaignId'] ?? null) ? (int) $filters['campaignId'] : null;
        $title = Campaign::reportTitle($campaignId);
        $filename = 'advertising-report-'.$filters['fromDate'].'-to-'.$filters['toDate'].'.csv';

        return response()->streamDownload(function () use ($filters, $campaignId, $title): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, array_map(fn (string $key) => __('advertising::metrics.'.$key), ['csv_campaign', 'from', 'to', 'dimension', 'group_label', 'impressions', 'clicks', 'ctr', 'conversations', 'shop_views', 'service_requests']), ',', '"', '');
            foreach (AdvertisingEvent::reportRows($campaignId, $filters['dimension'], $filters['fromDate'], $filters['toDate']) as $row) {
                $label = in_array($filters['dimension'], ['placement', 'device'], true) ? __('advertising::admin.'.$row['label']) : $row['label'];
                $values = [$title, $filters['fromDate'], $filters['toDate'], __('advertising::metrics.'.$filters['dimension']), $label, $row['impressions'], $row['clicks'], $row['ctr'] ?? '', $row['conversations'], $row['shop_views'], $row['service_requests']];
                fputcsv($output, array_map(self::safeCell(...), $values), ',', '"', '');
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'X-Robots-Tag' => 'noindex, nofollow']);
    }

    private static function safeCell(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[\s\p{Z}\p{Cf}\x00-\x1f]*[=+\-@]/u', $value) ? "'".$value : $value;
    }
}
