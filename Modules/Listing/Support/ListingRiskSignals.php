<?php

declare(strict_types=1);

namespace Modules\Listing\Support;

use App\Support\PersianLocale;
use Illuminate\Support\Collection;

final class ListingRiskSignals
{
    public static function normalize(string $value): string
    {
        $value = mb_strtolower(PersianLocale::normalize($value));
        $value = strtr($value, ['ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک', '‌' => ' ']);

        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? '');
    }

    public static function analyze(Collection $records): array
    {
        $prepared = [];
        $groups = [];
        foreach ($records as $record) {
            $id = (int) $record->id;
            $title = self::normalize(mb_substr((string) $record->title, 0, 255));
            $body = self::normalize(mb_substr((string) $record->description, 0, 8000));
            $prepared[$id] = ['record' => $record, 'title' => $title, 'body' => $body];
            if ($record->category_id && mb_strlen($title) >= 8 && str_contains($title, ' ')) {
                $groups[$record->category_id.':'.hash('sha256', $title)][] = $id;
            }
        }
        $result = [];
        foreach ($prepared as $id => $data) {
            $record = $data['record'];
            $signals = [];
            foreach ($groups[$record->category_id.':'.hash('sha256', $data['title'])] ?? [] as $peerId) {
                if ($peerId === $id) {
                    continue;
                }
                $peer = $prepared[$peerId];
                if ($record->user_id && (int) $record->user_id === (int) $peer['record']->user_id) {
                    $signals['repeated_title'][] = $peerId;
                } elseif (mb_strlen($data['body']) >= 80 && hash_equals($data['body'], $peer['body'])) {
                    $signals['copied_content'][] = $peerId;
                }
            }
            foreach (self::textSignals((string) $record->title."\n".mb_substr((string) $record->description, 0, 8000)) as $signal) {
                $signals[$signal] = [];
            }
            if ($signals !== []) {
                foreach ($signals as $key => $peers) {
                    $signals[$key] = array_slice(array_values(array_unique($peers)), 0, 5);
                }
                $result[$id] = ['id' => $id, 'title' => (string) $record->title, 'status' => $record->statusValue(), 'description' => mb_substr((string) $record->description, 0, 8000), 'signals' => $signals];
            }
        }

        return $result;
    }

    private static function textSignals(string $value): array
    {
        $signals = [];
        foreach (preg_split('/[\n.!?؟؛]+/u', $value) ?: [] as $sentence) {
            $sentence = self::normalize($sentence);
            if (preg_match('/\b(?:never|not|don t|without)\b|هرگز|نفرست|نکن|نخواه|بدون بیعانه|بدون پیش پرداخت/u', $sentence)) {
                continue;
            }
            $secret = '(?:رمز پویا|رمز یکبار مصرف|کد تایید|کد تأیید|کد ورود|one time password|verification code|\botp\b)';
            $request = '(?:ارسال|بفرست|بدهید|اعلام کنید|\b(?:send|share|provide)\b)';
            if (preg_match('/(?:'.$request.'.{0,60}'.$secret.'|'.$secret.'.{0,60}'.$request.')/u', $sentence)) {
                $signals['sensitive_request'] = true;
            }
            $payment = '(?:بیعانه|پیش پرداخت|\b(?:deposit|advance payment)\b)';
            $before = '(?:قبل از بازدید|پیش از بازدید|برای رزرو|before viewing|before inspection|to reserve)';
            if (preg_match('/(?:'.$payment.'.{0,80}'.$before.'|'.$before.'.{0,80}'.$payment.')/u', $sentence)) {
                $signals['advance_request'] = true;
            }
        }

        return array_keys($signals);
    }
}
