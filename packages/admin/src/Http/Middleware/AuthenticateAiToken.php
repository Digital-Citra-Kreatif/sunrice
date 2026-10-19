<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Ai\AiToken;
use Sunrice\Admin\Ai\TokenScope;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Security\AccountGuard;
use Sunrice\Admin\Security\SecurityLog;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs an MCP request in as the owner of its bearer token, so every tool
 * runs with that user's permissions (and a read-only token's limits). The
 * owner needs Panel::ACCESS, "sunrice.ai.connect" and an unlocked account
 * ("Sign out everywhere" and locking revoke the tokens, see AccountGuard).
 * Their changes show in the activity log as the AI agent's, by token name.
 *
 * @internal
 */
final class AuthenticateAiToken
{
    public function __construct(private Panel $panel, private TokenScope $scope, private ActivityLog $activity, private SecurityLog $security) {}

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $token = AiToken::findValid((string) $request->bearerToken());
        $user = $token === null ? null : $this->panel->getUserModel()::query()->whereKey($token->user_id)->first();
        if ($token === null || ! $user instanceof Authenticatable) {
            return response()->json(['error' => 'Missing, expired or revoked access token. Create one in the admin panel under AI access.'], 401)
                ->header('WWW-Authenticate', 'Bearer');
        }
        if (! $user instanceof Authorizable || ! $user->can(Panel::ACCESS)) {
            return response()->json(['error' => 'This user may not open the admin panel (the "Access the admin panel" permission), so its AI agents may not either.'], 403);
        }
        if (! $user->can('sunrice.ai.connect')) {
            return response()->json(['error' => 'This user may not connect AI agents (the "Connect AI agents" permission).'], 403);
        }
        if (app(AccountGuard::class)->isLocked($user)) {
            return response()->json(['error' => 'This account is locked. Ask an administrator to unlock it.'], 403);
        }

        Auth::guard($this->panel->getGuard())->setUser($user);
        Auth::shouldUse($this->panel->getGuard());
        $this->scope->token = $token;

        // A token used from a new address may have been copied.
        $ip = (string) $request->ip();
        if ($token->last_used_ip !== null && $token->last_used_ip !== $ip) {
            $this->security->record('token used from new IP', $user, null, ['note' => "\"{$token->name}\" from {$ip} (before: {$token->last_used_ip})"]);
        }
        if ($token->last_used_at === null || $token->last_used_at->lt(now()->subMinute()) || $token->last_used_ip !== $ip) {
            $token->forceFill(['last_used_at' => now(), 'last_used_ip' => $ip])->saveQuietly();
        }

        return $this->activity->withSource('ai', $token->name, fn () => $next($request));
    }
}
