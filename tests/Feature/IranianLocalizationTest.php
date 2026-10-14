<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\NormalizeLocalizedNumbers;
use App\Rules\IranianMobile;
use App\Support\IranianPhone;
use App\Support\PersianLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\Location\Models\City;
use Modules\Location\Models\Country;
use Modules\Location\Support\IranianCities;
use Modules\User\App\Models\Profile;
use Modules\User\App\Models\User;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class IranianLocalizationTest extends TestCase
{
    public function test_jalali_new_year_leap_day_and_tehran_midnight_preserve_source(): void
    {
        app()->setLocale('fa');
        $this->assertSame('۱ فروردین ۱۴۰۴', PersianLocale::date('2025-03-21T00:00:00Z'));
        $this->assertSame('۳۰ اسفند ۱۴۰۳', PersianLocale::date('2025-03-20T00:00:00Z'));
        $this->assertSame('۱ فروردین ۱۴۰۴', PersianLocale::date('2025-03-20T21:00:00Z'));
        $this->assertSame('۰۰:۳۰', PersianLocale::time('2025-03-20T21:00:00Z'));
        $source = new \DateTimeImmutable('2025-03-20T21:00:00Z');
        PersianLocale::date($source, true);
        $this->assertSame('2025-03-20T21:00:00+00:00', $source->format('c'));
        app()->setLocale('en');
        $this->assertSame('March 21, 2025', PersianLocale::date('2025-03-21T00:00:00Z'));
        $this->assertSame('1,234.50', PersianLocale::number('1234.5', 2));
    }

    public function test_numbers_are_normalized_without_changing_prose_or_passwords(): void
    {
        app()->setLocale('fa');
        $this->assertSame('۱۲٬۳۴۵٫۶۷', PersianLocale::number('12345.67', 2));
        $this->assertSame('1234567890.5', PersianLocale::normalize('۱۲۳٤٥٦۷۸۹۰٫۵'));
        $request = Request::create('/example', 'POST', ['price' => '۱۲۳٫۵', 'phone' => '۰۹۱۲۳۴۵۶۷۸۹', 'title' => 'مدل ۱۲۳', 'password' => 'رمز۱۲۳']);
        (new NormalizeLocalizedNumbers)->handle($request, fn () => new Response);
        $this->assertSame('123.5', $request->input('price'));
        $this->assertSame('+989123456789', $request->input('phone'));
        $this->assertSame('مدل ۱۲۳', $request->input('title'));
        $this->assertSame('رمز۱۲۳', $request->input('password'));
    }

    public function test_iranian_mobile_formats_and_invalid_numbers(): void
    {
        foreach (['۰۹۱۲۳۴۵۶۷۸۹', '+98 912-345-6789', '00989123456789', '٠٩١٢٣٤٥٦٧٨٩'] as $value) {
            $this->assertSame('+989123456789', IranianPhone::normalize($value));
            $this->assertTrue(Validator::make(['phone' => $value], ['phone' => [new IranianMobile]])->passes());
        }
        foreach (['09123', '+9891234567890', '0098123456789'] as $value) {
            $this->assertFalse(Validator::make(['phone' => $value], ['phone' => [new IranianMobile]])->passes());
        }
        $this->assertSame('+905551112233', IranianPhone::normalize('+905551112233'));
    }

    public function test_iranian_cities_are_idempotent_and_preserve_existing_data(): void
    {
        app()->setLocale('fa');
        City::ensureIranianCapitals();
        $country = Country::query()->where('code', 'IR')->firstOrFail();
        $before = City::query()->where('country_id', $country->id)->pluck('name', 'id')->all();
        City::ensureIranianCapitals();
        $this->assertSame($before, City::query()->where('country_id', $country->id)->pluck('name', 'id')->all());
        $this->assertCount(31, IranianCities::CAPITALS);
        $city = City::query()->where('country_id', $country->id)->where('name', 'Tehran')->firstOrFail();
        $this->assertSame('تهران', $city->displayName());
        $this->assertSame('Tehran', $city->name);
        $this->assertSame('نام سفارشی', IranianCities::label('نام سفارشی'));
        app()->setLocale('en');
        $this->assertSame('Tehran', $city->displayName());
    }

    public function test_profile_saves_normalized_phone(): void
    {
        $user = User::query()->where('email', 'b@b.com')->firstOrFail();
        $this->actingAs($user)->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'phone' => '۰۹۱۲۳۴۵۶۷۸۹'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('+989123456789', Profile::phoneForUser($user));
        $this->actingAs($user)->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email])->assertSessionHasNoErrors();
        $this->assertSame('+989123456789', Profile::phoneForUser($user));
        $this->actingAs($user)->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'phone' => '۰۹۱۲'])->assertSessionHasErrorsIn('updateProfile', 'phone');
        $this->assertSame('+989123456789', Profile::phoneForUser($user));
    }
}
