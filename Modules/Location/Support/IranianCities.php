<?php

declare(strict_types=1);

namespace Modules\Location\Support;

final class IranianCities
{
    public const CAPITALS = [
        'Ardabil' => 'اردبیل',
        'Urmia' => 'ارومیه',
        'Tabriz' => 'تبریز',
        'Isfahan' => 'اصفهان',
        'Karaj' => 'کرج',
        'Ilam' => 'ایلام',
        'Bushehr' => 'بوشهر',
        'Tehran' => 'تهران',
        'Shahrekord' => 'شهرکرد',
        'Birjand' => 'بیرجند',
        'Mashhad' => 'مشهد',
        'Bojnurd' => 'بجنورد',
        'Ahvaz' => 'اهواز',
        'Zanjan' => 'زنجان',
        'Semnan' => 'سمنان',
        'Zahedan' => 'زاهدان',
        'Shiraz' => 'شیراز',
        'Qazvin' => 'قزوین',
        'Qom' => 'قم',
        'Sanandaj' => 'سنندج',
        'Kerman' => 'کرمان',
        'Kermanshah' => 'کرمانشاه',
        'Yasuj' => 'یاسوج',
        'Gorgan' => 'گرگان',
        'Rasht' => 'رشت',
        'Khorramabad' => 'خرم‌آباد',
        'Sari' => 'ساری',
        'Arak' => 'اراک',
        'Bandar Abbas' => 'بندرعباس',
        'Hamadan' => 'همدان',
        'Yazd' => 'یزد',
    ];

    public static function label(string $name): string
    {
        return app()->getLocale() === 'fa' ? (self::CAPITALS[$name] ?? $name) : $name;
    }
}
