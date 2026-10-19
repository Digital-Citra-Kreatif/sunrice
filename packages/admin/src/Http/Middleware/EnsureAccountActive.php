<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Security\AccountGuard;
use Sunrice\Admin\Security\SecurityLog;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session of an account that was locked, or signed out
 * everywhere after this session signed in (see AccountGuard).
 *
 * @internal
 */
final class EnsureAccountActive
{
    public function __construct(private AccountGuard $accounts, private SecurityLog $security, private Panel $panel) {}

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof Authenticatable || $this->accounts->sessionAllowed($request, $user)) {
            return $next($request);
        }

        $locked = $this->accounts->isLocked($user);
        if ($locked) {
            $this->security->record('login blocked', $user, null, ['note' => 'the account is locked']);
        }
        Auth::guard($this->panel->getGuard())->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('sunrice.admin.login')->with('error', $locked
            ? __('This account is locked. Ask an administrator to unlock it.')
            : __('You were signed out. Please log in again.'));
    }
}
