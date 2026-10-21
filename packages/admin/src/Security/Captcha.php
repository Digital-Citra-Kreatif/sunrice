<?php

declare(strict_types=1);

namespace Sunrice\Admin\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * A captcha for public forms: Cloudflare Turnstile (the default), Google
 * reCAPTCHA v2 or hCaptcha, set in `sunrice-admin.captcha`
 * (SUNRICE_CAPTCHA_PROVIDER, _SITE_KEY, _SECRET_KEY). Off until both keys
 * are set. A plugin prints the widget and checks the visitor's answer:
 *
 *     {!! app(Captcha::class)->widget() !!}         // in the form, with its script
 *     app(Captcha::class)->passes($request)         // when it's sent
 *
 * When the provider can't be reached or refuses the secret key, the
 * answer counts as wrong (and a warning is logged): a form is never let
 * through unchecked.
 *
 * @api
 */
final class Captcha
{
    /** What a developer adds to the app's .env to set it up (for a "For developers" help). */
    public const ENV_EXAMPLE = "SUNRICE_CAPTCHA_PROVIDER=turnstile   # or recaptcha, hcaptcha\nSUNRICE_CAPTCHA_SITE_KEY=…\nSUNRICE_CAPTCHA_SECRET_KEY=…";

    /** The providers' names, for people. */
    private const NAMES = ['turnstile' => 'Cloudflare Turnstile', 'recaptcha' => 'Google reCAPTCHA', 'hcaptcha' => 'hCaptcha'];

    /** @var array<string, array{script: string, verify: string, class: string, field: string}> */
    private const PROVIDERS = [
        'turnstile' => [
            'script' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
            'verify' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            'class' => 'cf-turnstile',
            'field' => 'cf-turnstile-response',
        ],
        'recaptcha' => [
            'script' => 'https://www.google.com/recaptcha/api.js',
            'verify' => 'https://www.google.com/recaptcha/api/siteverify',
            'class' => 'g-recaptcha',
            'field' => 'g-recaptcha-response',
        ],
        'hcaptcha' => [
            'script' => 'https://js.hcaptcha.com/1/api.js',
            'verify' => 'https://api.hcaptcha.com/siteverify',
            'class' => 'h-captcha',
            'field' => 'h-captcha-response',
        ],
    ];

    /** Whether it's set up: a known provider and both keys. */
    public function enabled(): bool
    {
        return isset(self::PROVIDERS[$this->provider()]) && $this->siteKey() !== '' && $this->secretKey() !== '';
    }

    public function provider(): string
    {
        $provider = config('sunrice-admin.captcha.provider', 'turnstile');

        return is_string($provider) ? strtolower($provider) : 'turnstile';
    }

    /** The provider's name, for people: "Cloudflare Turnstile". */
    public function name(): string
    {
        return self::NAMES[$this->provider()] ?? $this->provider();
    }

    /** The form field the widget puts its answer in. */
    public function field(): string
    {
        return (self::PROVIDERS[$this->provider()] ?? self::PROVIDERS['turnstile'])['field'];
    }

    /** The widget and its script (the script once per page), in a language; empty when it's off. */
    public function widget(?string $locale = null): HtmlString
    {
        if (! $this->enabled()) {
            return new HtmlString('');
        }
        $provider = self::PROVIDERS[$this->provider()];
        $locale = str_replace('_', '-', $locale ?? app()->getLocale());
        $script = '';
        if (request()->attributes->get('sunrice-admin.captcha.script') !== true) {
            request()->attributes->set('sunrice-admin.captcha.script', true);
            $src = $provider['script'].($this->provider() === 'turnstile' ? '' : '?hl='.rawurlencode($locale));
            $script = '<script src="'.e($src).'" async defer></script>';
        }
        $language = $this->provider() === 'turnstile' ? ' data-language="'.e($locale).'"' : '';

        return new HtmlString('<div class="'.$provider['class'].'" data-sitekey="'.e($this->siteKey()).'"'.$language.'></div>'.$script);
    }

    /** Whether the visitor's answer in the request is right (always when it's off). */
    public function passes(Request $request): bool
    {
        if (! $this->enabled()) {
            return true;
        }
        $token = $request->input($this->field());

        return is_string($token) && $this->verify($token, $request->ip());
    }

    /** Asks the provider whether an answer is right. */
    public function verify(string $token, ?string $ip = null): bool
    {
        if ($token === '' || strlen($token) > 4096 || ! $this->enabled()) {
            return false;
        }
        try {
            $response = Http::asForm()->timeout(5)->post(self::PROVIDERS[$this->provider()]['verify'],
                array_filter(['secret' => $this->secretKey(), 'response' => $token, 'remoteip' => $ip]));
            $codes = $response->json('error-codes');
            if (is_array($codes) && array_intersect(array_filter($codes, is_string(...)), ['invalid-input-secret', 'missing-input-secret']) !== []) {
                Log::warning('The captcha\'s secret key was refused: check SUNRICE_CAPTCHA_SECRET_KEY.');
            }

            return $response->successful() && $response->json('success') === true;
        } catch (Throwable $e) {
            Log::warning('The captcha couldn\'t be checked: '.$e->getMessage());

            return false;
        }
    }

    private function siteKey(): string
    {
        $key = config('sunrice-admin.captcha.site_key');

        return is_string($key) ? trim($key) : '';
    }

    private function secretKey(): string
    {
        $key = config('sunrice-admin.captcha.secret_key');

        return is_string($key) ? trim($key) : '';
    }
}
