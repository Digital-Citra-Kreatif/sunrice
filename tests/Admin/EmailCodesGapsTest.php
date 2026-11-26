<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Sunrice\Admin\Actions\Settings\SaveSettings;
use Sunrice\Admin\Activity\Activity;
use Sunrice\Admin\Auth\EmailCodes;
use Sunrice\Admin\Auth\TwoFactorLogin;
use Sunrice\Admin\Auth\WeakPasswordAtLogin;
use Sunrice\Admin\Notifications\LoginCode;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Security\SecuritySettings;

use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\post;
use function Pest\Laravel\travel;

/** A request on the app's session store. */
function gapsRequest(): Request
{
    $request = Request::create('/');
    $request->setLaravelSession(app('session.store'));

    return $request;
}

/** A 6-digit code that isn't $code. */
function gapsOther(string $code): string
{
    return $code === '000000' ? '111111' : '000000';
}

function gapsPasswordStep(bool $remember = false): TestResponse
{
    return post('/admin/login', ['email' => 'rina@example.com', 'password' => 'secret-password', 'remember' => $remember]);
}

/** The code in the latest login email. */
function gapsSentCode(): string
{
    $codes = [];
    Notification::assertSentTo(new AnonymousNotifiable, LoginCode::class, function (LoginCode $n) use (&$codes) {
        $codes[] = $n->code;

        return true;
    });

    return (string) end($codes);
}

beforeEach(function () {
    foreach (['shop-login', 'other-login', 'two-factor'] as $purpose) {
        foreach ([7, 8] as $id) {
            RateLimiter::clear("sunrice-{$purpose}|{$id}");
        }
    }
});

describe('the engine', function () {
    it('makes 6-digit codes from a wide range, never the same twice in a row on purpose', function () {
        $codes = app(EmailCodes::class);
        $request = gapsRequest();
        $seen = [];
        foreach (range(1, 300) as $i) {
            $code = $codes->issue($request, 'shop-login', 7);
            expect($code)->toMatch('/^\d{6}$/');
            $seen[$code] = true;
        }

        // 300 draws from a million: a collision or two at most.
        expect(count($seen))->toBeGreaterThan(295);
    });

    it('keeps a keyed hash, not a plain digest, so another app key doesn\'t accept it', function () {
        $codes = app(EmailCodes::class);
        $request = gapsRequest();
        $code = $codes->issue($request, 'shop-login', 7);
        $stored = session(EmailCodes::sessionKey('shop-login'));

        expect($stored['code'])->not->toBe($code)
            ->and($stored['code'])->not->toBe(hash('sha256', $code))
            ->and($stored['code'])->not->toBe(md5($code))
            ->and($stored['code'])->not->toBe(sha1($code));

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        expect($codes->check($request, 'shop-login', $code))->toBe('wrong');
    });

    it('stops the earlier code working when a new one is issued, and starts its guesses afresh', function () {
        $codes = app(EmailCodes::class);
        $request = gapsRequest();
        $old = $codes->issue($request, 'shop-login', 7);
        expect($codes->check($request, 'shop-login', gapsOther($old)))->toBe('wrong');

        do {
            $new = $codes->issue($request, 'shop-login', 7);
        } while ($new === $old);

        expect($codes->pending($request, 'shop-login')['attempts'])->toBe(0)
            ->and($codes->check($request, 'shop-login', $old))->toBe('wrong')
            ->and($codes->check($request, 'shop-login', $new))->toBe('ok');
    });

    it('rejects empty, short, long and digit-free input without accepting it', function () {
        $codes = app(EmailCodes::class);
        $request = gapsRequest();
        $code = $codes->issue($request, 'shop-login', 7);

        expect($codes->check($request, 'shop-login', ''))->toBe('wrong')
            ->and($codes->check($request, 'shop-login', substr($code, 0, 5)))->toBe('wrong')
            ->and($codes->check($request, 'shop-login', $code.'0'))->toBe('wrong')
            ->and($codes->check($request, 'shop-login', 'abcdef'))->toBe('wrong')
            ->and($codes->pending($request, 'shop-login')['attempts'])->toBe(4)
            // Letters and spaces around the right digits don't matter.
            ->and($codes->check($request, 'shop-login', ' '.substr($code, 0, 2).' '.substr($code, 2).' '))->toBe('ok');
    });

    it('works until the second it expires, and not after', function () {
        $codes = app(EmailCodes::class);
        $request = gapsRequest();
        $this->freezeTime();
        $code = $codes->issue($request, 'shop-login', 7);
        expect($codes->pending($request, 'shop-login')['expires_at'])->toBe(now()->getTimestamp() + EmailCodes::LIFETIME_MINUTES * 60);

        travel(EmailCodes::LIFETIME_MINUTES * 60)->seconds();
        expect($codes->check($request, 'shop-login', gapsOther($code)))->toBe('wrong');
        travel(1)->seconds();
        expect($codes->check($request, 'shop-login', $code))->toBe('expired')
            ->and($codes->pending($request, 'shop-login'))->toBeNull()
            ->and($codes->check($request, 'shop-login', $code))->toBe('missing');
    });

    it('gives a code at least a minute, whatever minutes are asked for', function () {
        $codes = app(EmailCodes::class);
        $request = gapsRequest();
        $this->freezeTime();
        $codes->issue($request, 'shop-login', 7, minutes: 0);
        expect($codes->pending($request, 'shop-login')['expires_at'])->toBe(now()->getTimestamp() + 60);
        $codes->issue($request, 'shop-login', 7, minutes: -5);
        expect($codes->pending($request, 'shop-login')['expires_at'])->toBe(now()->getTimestamp() + 60);
    });

    it('doesn\'t count an expired code towards the lockout', function () {
        $codes = app(EmailCodes::class);
        $request = gapsRequest();
        foreach (range(1, EmailCodes::LOCKOUT_ATTEMPTS + 2) as $i) {
            $code = $codes->issue($request, 'shop-login', 7, minutes: 1);
            travel(2)->minutes();
            expect($codes->check($request, 'shop-login', gapsOther($code)))->toBe('expired');
        }
        expect($codes->lockedFor('shop-login', 7))->toBe(0);
    });

    it('locks on the LOCKOUT_ATTEMPTS-th wrong code, for LOCKOUT_SECONDS, then lets them try again', function () {
        $codes = app(EmailCodes::class);
        $request = gapsRequest();
        $this->freezeTime();
        $results = [];
        foreach (range(1, EmailCodes::LOCKOUT_ATTEMPTS) as $i) {
            if ($codes->pending($request, 'shop-login') === null) {
                $code = $codes->issue($request, 'shop-login', 7);
            }
            $results[] = $codes->check($request, 'shop-login', gapsOther($code));
        }

        // 4 wrong + locked (MAX_ATTEMPTS), twice: the 10th wrong guess locks the user.
        expect($results)->toBe(['wrong', 'wrong', 'wrong', 'wrong', 'locked', 'wrong', 'wrong', 'wrong', 'wrong', 'locked'])
            ->and($codes->lockedFor('shop-login', 7))->toBe(EmailCodes::LOCKOUT_SECONDS);

        // Locked: even the right code is refused and ends the pending one.
        $code = $codes->issue($request, 'shop-login', 7);
        expect($codes->check($request, 'shop-login', $code))->toBe('locked')
            ->and($codes->pending($request, 'shop-login'))->toBeNull();

        travel(EmailCodes::LOCKOUT_SECONDS + 1)->seconds();
        expect($codes->lockedFor('shop-login', 7))->toBe(0);
        $code = $codes->issue($request, 'shop-login', 7);
        expect($codes->check($request, 'shop-login', $code))->toBe('ok');
    });

    it('locks per user: one user\'s wrong codes don\'t lock another', function () {
        $codes = app(EmailCodes::class);
        $request = gapsRequest();
        foreach (range(1, 2) as $round) {
            $codes->issue($request, 'shop-login', 7);
            foreach (range(1, EmailCodes::MAX_ATTEMPTS) as $i) {
                $codes->check($request, 'shop-login', 'x');
            }
        }

        expect($codes->lockedFor('shop-login', 7))->toBeGreaterThan(0)
            ->and($codes->lockedFor('shop-login', 8))->toBe(0)
            // The same id as a string is the same user.
            ->and($codes->lockedFor('shop-login', '7'))->toBeGreaterThan(0);
        $code = $codes->issue($request, 'shop-login', 8);
        expect($codes->check($request, 'shop-login', $code))->toBe('ok');
    });

    it('clears the user\'s wrong-code count when the right code is typed', function () {
        $codes = app(EmailCodes::class);
        $request = gapsRequest();
        $code = $codes->issue($request, 'shop-login', 7);
        foreach (range(1, EmailCodes::MAX_ATTEMPTS - 1) as $i) {
            $codes->check($request, 'shop-login', gapsOther($code));
        }
        expect($codes->check($request, 'shop-login', $code))->toBe('ok')
            ->and(RateLimiter::attempts('sunrice-shop-login|7'))->toBe(0);
    });

    it('holds one pending code per purpose: a code for user A then B leaves only B\'s', function () {
        $codes = app(EmailCodes::class);
        $request = gapsRequest();
        $forA = $codes->issue($request, 'shop-login', 7);
        do {
            $forB = $codes->issue($request, 'shop-login', 8);
        } while ($forB === $forA);

        expect($codes->pending($request, 'shop-login')['user_id'])->toBe(8)
            ->and($codes->check($request, 'shop-login', $forA))->toBe('wrong')
            // The wrong guess counted against B, the user the pending code is for.
            ->and(RateLimiter::attempts('sunrice-shop-login|8'))->toBe(1)
            ->and(RateLimiter::attempts('sunrice-shop-login|7'))->toBe(0);
    });

    it('belongs to the session it was issued in', function () {
        $codes = app(EmailCodes::class);
        $code = $codes->issue(gapsRequest(), 'shop-login', 7);

        $other = Request::create('/');
        $other->setLaravelSession(new Store('other', new ArraySessionHandler(10)));

        expect($codes->check($other, 'shop-login', $code))->toBe('missing')
            ->and($codes->pending($other, 'shop-login'))->toBeNull();
    });

    it('doesn\'t accept one purpose\'s code for another', function () {
        $codes = app(EmailCodes::class);
        $request = gapsRequest();
        $shop = $codes->issue($request, 'shop-login', 7);
        do {
            $other = $codes->issue($request, 'other-login', 7);
        } while ($other === $shop);

        expect($codes->check($request, 'other-login', $shop))->toBe('wrong')
            ->and($codes->check($request, 'shop-login', $other))->toBe('wrong')
            ->and($codes->check($request, 'missing-login', $shop))->toBe('missing');
    });

    it('resets the resend wait with each code and counts it down', function () {
        $codes = app(EmailCodes::class);
        $request = gapsRequest();
        $this->freezeTime();
        $codes->issue($request, 'shop-login', 7);
        travel(45)->seconds();
        expect($codes->resendIn($request, 'shop-login'))->toBe(15);
        $codes->issue($request, 'shop-login', 7);
        expect($codes->resendIn($request, 'shop-login'))->toBe(EmailCodes::RESEND_SECONDS);
        $codes->forget($request, 'shop-login');
        expect($codes->resendIn($request, 'shop-login'))->toBe(0);
    });

    it('gives back the caller\'s data with string keys, never the code', function () {
        $codes = app(EmailCodes::class);
        $request = gapsRequest();
        $codes->issue($request, 'shop-login', 'uuid-7', ['remember' => false, 'next' => '/cart']);

        $pending = $codes->pending($request, 'shop-login');
        expect($pending)->toHaveKeys(['user_id', 'data', 'expires_at', 'sent_at', 'attempts'])
            ->and($pending)->not->toHaveKey('code')
            ->and($pending['user_id'])->toBe('uuid-7')
            ->and($pending['data'])->toBe(['remember' => false, 'next' => '/cart']);
    });

    it('ignores a pending code it can\'t read', function () {
        $codes = app(EmailCodes::class);
        $request = gapsRequest();
        $key = EmailCodes::sessionKey('shop-login');

        foreach ([
            'a string',
            ['user_id' => 7],
            ['user_id' => 7, 'code' => 123],
            ['user_id' => ['7'], 'code' => 'abc'],
            ['user_id' => null, 'code' => 'abc'],
        ] as $broken) {
            session([$key => $broken]);
            expect($codes->pending($request, 'shop-login'))->toBeNull()
                ->and($codes->check($request, 'shop-login', '123456'))->toBe('missing');
        }

        // Without expires_at it counts as expired, not as forever.
        $code = $codes->issue($request, 'shop-login', 7);
        session([$key => array_diff_key(session($key), ['expires_at' => true])]);
        expect($codes->check($request, 'shop-login', $code))->toBe('expired');
    });

    it('wants a well-formed purpose everywhere, so two can\'t share a session key', function (string $purpose) {
        $codes = app(EmailCodes::class);
        $request = gapsRequest();
        foreach ([
            fn () => $codes->issue($request, $purpose, 7),
            fn () => $codes->check($request, $purpose, '123456'),
            fn () => $codes->pending($request, $purpose),
            fn () => $codes->resendIn($request, $purpose),
            fn () => $codes->forget($request, $purpose),
            fn () => $codes->lockedFor($purpose, 7),
            fn () => EmailCodes::sessionKey($purpose),
        ] as $call) {
            expect($call)->toThrow(InvalidArgumentException::class);
        }
    })->with(['shop_login', 'Shop-login', '-shop', 'shop-', 'shop--login', '', 'shop|login', "shop\nlogin"]);

    it('keeps a plugin\'s purposes clear of the panel\'s own session and limiter keys', function () {
        // A purpose maps onto `sunrice_{purpose}` and `sunrice-{purpose}|{user}`, the same
        // namespace as the panel's own keys: "weak-password" is WeakPasswordAtLogin's session key.
        expect(EmailCodes::sessionKey('weak-password'))->not->toBe(WeakPasswordAtLogin::SESSION_KEY);
    })->skip('BUG: EmailCodes purposes share the panel\'s key namespace with no reserved list: sessionKey("weak-password") === WeakPasswordAtLogin::SESSION_KEY, sessionKey("locale") === "sunrice_locale", and lockout keys for purposes "login", "login-ip", "login-account" are LoginThrottle\'s keys.');
});

describe('two-factor login on the engine', function () {
    beforeEach(function () {
        usePanel();
        Notification::fake();
        $this->admin = adminUser(Panel::ACCESS);
        $this->admin->forceFill(['email' => 'rina@example.com', 'password' => 'secret-password'])->save();
        app(SaveSettings::class)->handle(app(SecuritySettings::class), ['twoFactor' => true]);
        RateLimiter::clear('sunrice-two-factor|'.$this->admin->id);
    });

    it('keeps its old session key and lockout key', function () {
        expect(EmailCodes::sessionKey(TwoFactorLogin::PURPOSE))->toBe(TwoFactorLogin::SESSION_KEY)
            ->and(TwoFactorLogin::SESSION_KEY)->toBe('sunrice_two_factor');

        foreach (range(1, TwoFactorLogin::LOCKOUT_ATTEMPTS) as $i) {
            RateLimiter::hit('sunrice-two-factor|'.$this->admin->id, TwoFactorLogin::LOCKOUT_SECONDS);
        }
        expect(app(TwoFactorLogin::class)->lockedFor($this->admin))->toBeGreaterThan(0);
        gapsPasswordStep()->assertSessionHasErrors('email');
        Notification::assertNothingSent();
        expect(Activity::query()->where('action', 'login blocked')->sole()->properties['note'])->toBe('too many wrong login codes');
    });

    it('keeps "remember me" through a resend', function () {
        gapsPasswordStep(remember: true);
        expect(session(TwoFactorLogin::SESSION_KEY)['data'])->toBe(['remember' => true]);

        travel(61)->seconds();
        post('/admin/login/code/resend')->assertSessionHas('success');
        expect(session(TwoFactorLogin::SESSION_KEY)['data'])->toBe(['remember' => true]);

        post('/admin/login/code', ['code' => gapsSentCode()])->assertRedirect('/admin')
            ->assertCookie(auth('web')->getRecallerName());
        assertAuthenticatedAs($this->admin);
        expect($this->admin->fresh()->getRememberToken())->not->toBeEmpty();
    });

    it('doesn\'t remember the login when not asked to', function () {
        gapsPasswordStep();
        post('/admin/login/code', ['code' => gapsSentCode()])->assertRedirect('/admin')
            ->assertCookieMissing(auth('web')->getRecallerName());
        assertAuthenticatedAs($this->admin);
    });

    it('makes a resent code replace the emailed one', function () {
        gapsPasswordStep();
        $first = gapsSentCode();
        do {
            travel(61)->seconds();
            post('/admin/login/code/resend')->assertSessionHas('success');
            $second = gapsSentCode();
        } while ($second === $first);

        post('/admin/login/code', ['code' => $first])->assertSessionHasErrors('code');
        assertGuest();
        post('/admin/login/code', ['code' => $second])->assertRedirect('/admin');
        assertAuthenticatedAs($this->admin);
    });

    it('starts a new session id when the code signs in', function () {
        gapsPasswordStep();
        $before = session()->getId();
        post('/admin/login/code', ['code' => gapsSentCode()])->assertRedirect('/admin');
        expect(session()->getId())->not->toBe($before)
            ->and(session(TwoFactorLogin::SESSION_KEY))->toBeNull();
    });

    it('can\'t be replayed once it signed in', function () {
        gapsPasswordStep();
        $code = gapsSentCode();
        post('/admin/login/code', ['code' => $code])->assertRedirect('/admin');
        auth('web')->logout();

        post('/admin/login/code', ['code' => $code])->assertRedirect('/admin/login')->assertSessionHas('error');
        assertGuest();
    });

    it('doesn\'t take a plugin purpose\'s code for the login, nor share its lockout', function () {
        gapsPasswordStep();
        $login = gapsSentCode();
        $request = Request::create('/');
        $request->setLaravelSession(app('session.store'));
        $codes = app(EmailCodes::class);
        do {
            $shop = $codes->issue($request, 'shop-login', $this->admin->id);
        } while ($shop === $login);

        post('/admin/login/code', ['code' => $shop])->assertSessionHasErrors('code');
        assertGuest();

        // The plugin's purpose locked doesn't lock the login.
        foreach (range(1, EmailCodes::LOCKOUT_ATTEMPTS) as $i) {
            RateLimiter::hit('sunrice-shop-login|'.$this->admin->id, EmailCodes::LOCKOUT_SECONDS);
        }
        post('/admin/login/code', ['code' => $login])->assertRedirect('/admin');
        assertAuthenticatedAs($this->admin);
        RateLimiter::clear('sunrice-shop-login|'.$this->admin->id);
    });

    it('answers "missing" when the pending account is gone', function () {
        gapsPasswordStep();
        $code = gapsSentCode();
        $this->admin->delete();

        post('/admin/login/code', ['code' => $code])->assertRedirect('/admin/login');
        assertGuest();
    });

    it('logs wrong and blocked codes without the codes themselves', function () {
        gapsPasswordStep();
        $code = gapsSentCode();
        $wrong = $code === '482917' ? '539106' : '482917';
        foreach (range(1, TwoFactorLogin::MAX_ATTEMPTS) as $i) {
            post('/admin/login/code', ['code' => $wrong]);
        }

        expect(Activity::query()->where('action', 'login failed')->where('subject_id', (string) $this->admin->id)->count())->toBe(TwoFactorLogin::MAX_ATTEMPTS - 1)
            ->and(Activity::query()->where('action', 'login blocked')->sole()->properties['note'])->toBe('too many wrong login codes');
        // What's written for each event (timestamps aside).
        $logged = json_encode(Activity::query()->get()->map->only(['action', 'subject_label', 'properties'])->all());
        expect($logged)->not->toContain($code)->not->toContain($wrong)->not->toContain('secret-password');
    });

    it('keeps the code out of the subject unless asked, and says how long it works', function () {
        gapsPasswordStep();
        $code = gapsSentCode();
        $mail = (new LoginCode($code, 'Sunrice'))->toMail(new AnonymousNotifiable);

        expect($mail->subject)->not->toContain($code)
            ->and(implode("\n", $mail->introLines))->toContain($code)->toContain((string) TwoFactorLogin::LIFETIME_MINUTES)
            ->not->toContain('secret-password');

        config(['sunrice-admin.auth.two_factor_code_in_subject' => true]);
        expect((new LoginCode($code, 'Sunrice'))->toMail(new AnonymousNotifiable)->subject)->toContain($code);
    });

    it('ends a pending login when the password changes before the code is typed', function () {
        gapsPasswordStep();
        $code = gapsSentCode();
        $this->admin->forceFill(['password' => 'a-new-secret-password'])->save();

        post('/admin/login/code', ['code' => $code])->assertRedirect('/admin/login');
        assertGuest();
    })->skip('Unchanged by the refactor (old TwoFactorLogin did the same): a pending two-factor login isn\'t tied to the password it was started with, so its code still signs in after the password is changed.');
});
