<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Admin\Actions\Users\UpdateAccount;
use Sunrice\Admin\Auth\PasswordPolicy;

/**
 * Your account: the signed-in user's own name, email address and
 * password, for everyone who may open the panel (nobody else's).
 *
 * @internal
 */
final class AccountController
{
    public function edit(Request $request): Response
    {
        $user = $this->user($request);

        return Inertia::render('Account/Edit', [
            'values' => ['name' => $user->getAttribute('name'), 'email' => $user->getAttribute('email')],
            'passwordRules' => PasswordPolicy::checklist(),
        ]);
    }

    public function update(Request $request, UpdateAccount $update): RedirectResponse
    {
        $changed = $update->handle($this->user($request), $request->all());

        return back()->with('success', $changed === [] ? 'Nothing changed.' : 'Your account is saved.');
    }

    private function user(Request $request): Model
    {
        $user = $request->user();
        abort_unless($user instanceof Model, 403);

        return $user;
    }
}
