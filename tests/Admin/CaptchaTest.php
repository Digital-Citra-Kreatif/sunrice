<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Sunrice\Admin\Security\Captcha;

function captcha(array $config = []): Captcha
{
    config(['sunrice-admin.captcha' => [...['provider' => 'turnstile', 'site_key' => 'site', 'secret_key' => 'secret'], ...$config]]);

    return app(Captcha::class);
}

it('is off until both keys are set, and then lets nothing through unchecked', function () {
    expect(captcha(['site_key' => null])->enabled())->toBeFalse()
        ->and(captcha(['site_key' => null])->widget()->toHtml())->toBe('')
        ->and(captcha(['site_key' => null])->passes(Request::create('/', 'POST')))->toBeTrue()
        ->and(captcha(['provider' => 'nope'])->enabled())->toBeFalse()
        ->and(captcha()->enabled())->toBeTrue()
        ->and(captcha()->passes(Request::create('/', 'POST')))->toBeFalse();
});

it('prints each provider\'s widget, with its script once per page', function () {
    expect(captcha(['provider' => 'hcaptcha'])->widget('id')->toHtml())
        ->toBe('<div class="h-captcha" data-sitekey="site"></div><script src="https://js.hcaptcha.com/1/api.js?hl=id" async defer></script>')
        ->and(captcha(['provider' => 'recaptcha'])->widget('pt_BR')->toHtml())->toBe('<div class="g-recaptcha" data-sitekey="site"></div>')
        ->and(captcha(['provider' => 'recaptcha'])->field())->toBe('g-recaptcha-response');

    request()->attributes->remove('sunrice-admin.captcha.script');
    expect(captcha(['site_key' => '"><script>'])->widget()->toHtml())->toContain('data-sitekey="&quot;&gt;&lt;script&gt;"');
});

it('asks the provider, and refuses when it can\'t', function () {
    Http::fake(['www.google.com/*' => Http::response(['success' => true])]);
    $recaptcha = captcha(['provider' => 'recaptcha']);
    expect($recaptcha->verify('token', '10.0.0.1'))->toBeTrue()
        ->and($recaptcha->verify(''))->toBeFalse()
        ->and($recaptcha->verify(str_repeat('a', 5000)))->toBeFalse();
    Http::assertSent(fn ($request) => $request['secret'] === 'secret' && $request['response'] === 'token' && $request['remoteip'] === '10.0.0.1');

    Log::spy();
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-secret']])]);
    expect(captcha()->verify('token'))->toBeFalse();
    Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'secret key was refused'));

    Http::fake(fn () => throw new ConnectionException('timed out'));
    expect(captcha()->verify('token'))->toBeFalse();
});

it('names its provider for people', function () {
    expect(captcha()->name())->toBe('Cloudflare Turnstile')
        ->and(captcha(['provider' => 'recaptcha'])->name())->toBe('Google reCAPTCHA')
        ->and(captcha(['provider' => 'hcaptcha'])->name())->toBe('hCaptcha')
        ->and(Captcha::ENV_EXAMPLE)->toContain('SUNRICE_CAPTCHA_SITE_KEY=');
});
