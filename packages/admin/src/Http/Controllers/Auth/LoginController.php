<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Controllers\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Admin\Actions\Auth\AttemptLogin;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Security\SecurityLog;

/** @internal */
final class LoginController
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request, AttemptLogin $login): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:1000'],
            'remember' => ['boolean'],
        ]);

        $login->handle(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->boolean('remember'),
            (string) $request->ip(),
        );
        $request->session()->regenerate();

        return redirect()->intended(route('sunrice.admin.dashboard'));
    }

    public function destroy(Request $request, Panel $panel, SecurityLog $security): RedirectResponse
    {
        $user = Auth::guard($panel->getGuard())->user();
        if ($user instanceof Model) {
            $security->record('logged out', $user);
        }
        Auth::guard($panel->getGuard())->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('sunrice.admin.login');
    }
}
