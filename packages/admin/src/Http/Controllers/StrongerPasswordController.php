<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Admin\Actions\Users\ChooseStrongerPassword;
use Sunrice\Admin\Auth\PasswordPolicy;
use Sunrice\Admin\Auth\WeakPasswordAtLogin;

/**
 * "Choose a stronger password": where someone who signed in with a weak
 * password is sent until they choose one (RequireStrongPassword).
 *
 * @internal
 */
final class StrongerPasswordController
{
    public function __construct(private readonly WeakPasswordAtLogin $weak) {}

    public function edit(Request $request): Response|RedirectResponse
    {
        $user = $this->user($request);
        if (! $this->weak->flagged($request, $user)) {
            return redirect()->route('sunrice.admin.dashboard');
        }

        return Inertia::render('Auth/StrongerPassword', [
            'email' => $user->getAttribute('email'),
            'passwordRules' => PasswordPolicy::checklist(),
        ]);
    }

    public function update(Request $request, ChooseStrongerPassword $choose): RedirectResponse
    {
        $user = $this->user($request);
        if (! $this->weak->flagged($request, $user)) {
            return redirect()->route('sunrice.admin.dashboard');
        }
        $choose->handle($user, $request->only('password', 'password_confirmation'));
        $this->weak->clear($request);

        return redirect()->intended(route('sunrice.admin.dashboard'))->with('success', __('Your new password is saved.'));
    }

    private function user(Request $request): Model&Authenticatable
    {
        $user = $request->user();
        abort_unless($user instanceof Model, 403);

        return $user;
    }
}
