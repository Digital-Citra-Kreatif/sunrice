<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Admin\Actions\Ai\CreateAiToken;
use Sunrice\Admin\Actions\Ai\RevokeAiTokens;
use Sunrice\Admin\Ai\AiToken;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Users\PermissionRank;

/**
 * The AI access page: a user's own tokens, and how to connect an agent.
 * Needs "sunrice.ai.connect".
 *
 * @internal
 */
final class AiAccessController
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $path = config('sunrice-admin.ai.path', 'mcp');

        return Inertia::render('Ai/Index', [
            'endpoint' => url(is_string($path) ? $path : 'mcp'),
            'tokens' => AiToken::query()->where('user_id', self::key($user))->latest('id')->get()->map(fn (AiToken $token) => [
                'id' => $token->id,
                'name' => $token->name,
                'readOnly' => $token->read_only,
                'createdAt' => $token->created_at?->toIso8601String(),
                'lastUsedAt' => $token->last_used_at?->toIso8601String(),
                'lastUsedIp' => $token->last_used_ip,
                'expiresAt' => $token->expires_at?->toIso8601String(),
                'expired' => $token->expires_at !== null && $token->expires_at->isPast(),
            ])->all(),
            // The new token, once, right after it's created.
            'created' => $request->session()->get('sunrice.ai.created'),
            // What a full token could do as this person, to choose read-only or a short expiry.
            'risks' => self::risks($user),
        ]);
    }

    /**
     * Whether the user is a super admin, and the labels of the privileged
     * permissions they have ($panel->privilegedPermissions()).
     *
     * @return array{superAdmin: bool, privileged: list<string>}
     */
    private static function risks(Model $user): array
    {
        $superAdmin = app(PermissionRank::class)->isSuperAdmin($user);
        $privileged = [];
        $panel = app(Panel::class);
        foreach ($panel->getPermissions() as $name => $permission) {
            // Everyone here may connect agents: not worth saying.
            if ($name !== 'sunrice.ai.connect' && $panel->isPrivilegedPermission($name) && ($superAdmin || ($user instanceof Authorizable && $user->can($name)))) {
                $privileged[] = $permission->saidLabel();
            }
        }

        return ['superAdmin' => $superAdmin, 'privileged' => $privileged];
    }

    public function store(Request $request, CreateAiToken $create): RedirectResponse
    {
        $user = $this->user($request);
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'read_only' => ['boolean'],
            'expires_in_days' => ['nullable', Rule::in([30, 90, 365])],
        ]);
        // 90 days unless asked otherwise; null (sent on purpose) is never.
        $days = $request->has('expires_in_days') ? $request->integer('expires_in_days') : 90;
        [$token, $plain] = $create->handle($user, $request->string('name')->toString(), $request->boolean('read_only'), $days > 0 ? now()->addDays($days) : null);

        return back()->with('sunrice.ai.created', ['name' => $token->name, 'token' => $plain]);
    }

    public function destroy(Request $request, int $token, RevokeAiTokens $revoke): RedirectResponse
    {
        $user = $this->user($request);
        // Only your own tokens; others' are revoked from the Users screen.
        $model = AiToken::query()->where('user_id', self::key($user))->findOrFail($token);
        $revoke->one($model, $user);

        return back()->with('success', __('":name" revoked: agents using it can no longer connect.', ['name' => $model->name]));
    }

    private function user(Request $request): Model
    {
        $user = self::model($request->user());
        abort_unless($user instanceof Authorizable && $user->can('sunrice.ai.connect'), 403);

        return $user;
    }

    /** The signed-in user as a model (any user model). */
    private static function model(mixed $user): ?Model
    {
        return $user instanceof Model ? $user : null;
    }

    private static function key(Model $user): string
    {
        $key = $user->getKey();

        return is_scalar($key) ? (string) $key : '';
    }
}
