<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Sunrice\Admin\Auth\EmailCodes;

use function Pest\Laravel\travel;

function codeRequest(): Request
{
    $request = Request::create('/');
    $request->setLaravelSession(app('session.store'));

    return $request;
}

beforeEach(function () {
    RateLimiter::clear('sunrice-shop-login|7');
});

it('issues a 6-digit code kept only as a hash in the session, with its data', function () {
    $codes = app(EmailCodes::class);
    $request = codeRequest();

    $code = $codes->issue($request, 'shop-login', 7, ['remember' => true]);

    expect($code)->toMatch('/^\d{6}$/')
        ->and(json_encode(session(EmailCodes::sessionKey('shop-login'))))->not->toContain($code)
        ->and($codes->pending($request, 'shop-login'))->toMatchArray(['user_id' => 7, 'data' => ['remember' => true], 'attempts' => 0])
        ->and($codes->pending($request, 'shop-login'))->not->toHaveKey('code')
        ->and(EmailCodes::sessionKey('shop-login'))->toBe('sunrice_shop_login');
});

it('accepts the right code once, spaces and dashes aside', function () {
    $codes = app(EmailCodes::class);
    $request = codeRequest();
    $code = $codes->issue($request, 'shop-login', 7);

    expect($codes->check($request, 'shop-login', substr($code, 0, 3).'-'.substr($code, 3)))->toBe('ok')
        ->and($codes->pending($request, 'shop-login'))->toBeNull()
        ->and($codes->check($request, 'shop-login', $code))->toBe('missing');
});

it('refuses wrong and expired codes, and ends a code after too many guesses', function () {
    $codes = app(EmailCodes::class);
    $request = codeRequest();
    $code = $codes->issue($request, 'shop-login', 7);
    $wrong = $code === '000000' ? '111111' : '000000';

    foreach (range(1, EmailCodes::MAX_ATTEMPTS - 1) as $i) {
        expect($codes->check($request, 'shop-login', $wrong))->toBe('wrong');
    }
    expect($codes->check($request, 'shop-login', $wrong))->toBe('locked')
        ->and($codes->check($request, 'shop-login', $code))->toBe('missing');

    RateLimiter::clear('sunrice-shop-login|7');
    $code = $codes->issue($request, 'shop-login', 7, minutes: 2);
    travel(3)->minutes();
    expect($codes->check($request, 'shop-login', $code))->toBe('expired');
});

it('locks a user\'s codes across fresh ones, per purpose', function () {
    $codes = app(EmailCodes::class);
    $request = codeRequest();
    foreach (range(1, 2) as $round) {
        $codes->issue($request, 'shop-login', 7);
        foreach (range(1, EmailCodes::MAX_ATTEMPTS) as $i) {
            $codes->check($request, 'shop-login', 'x');
        }
    }

    expect($codes->lockedFor('shop-login', 7))->toBeGreaterThan(0)
        ->and($codes->lockedFor('other-login', 7))->toBe(0);
    $code = $codes->issue($request, 'shop-login', 7);
    expect($codes->check($request, 'shop-login', $code))->toBe('locked');
});

it('keeps purposes apart and says when the next code may go', function () {
    $codes = app(EmailCodes::class);
    $request = codeRequest();
    $shop = $codes->issue($request, 'shop-login', 7);
    $codes->issue($request, 'other-login', 7);

    expect($codes->resendIn($request, 'shop-login'))->toBe(EmailCodes::RESEND_SECONDS)
        ->and($codes->resendIn($request, 'nothing'))->toBe(0)
        ->and($codes->check($request, 'other-login', $shop === '000000' ? '111111' : '000000'))->toBe('wrong')
        ->and($codes->check($request, 'shop-login', $shop))->toBe('ok')
        ->and($codes->pending($request, 'other-login'))->not->toBeNull();
    travel(61)->seconds();
    expect($codes->resendIn($request, 'other-login'))->toBe(0);
});

it('wants a lowercase purpose', function () {
    app(EmailCodes::class)->issue(codeRequest(), 'Shop Login', 7);
})->throws(InvalidArgumentException::class);
