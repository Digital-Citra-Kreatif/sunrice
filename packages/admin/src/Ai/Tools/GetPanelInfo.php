<?php

declare(strict_types=1);

namespace Sunrice\Admin\Ai\Tools;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Sunrice\Admin\Ai\TokenScope;
use Sunrice\Admin\Ai\Tool;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Permissions\Permission;
use Sunrice\Admin\Resources\RegisteredResource;
use Sunrice\Admin\Settings\RegisteredSettings;

#[IsReadOnly]
#[Description('Start here: who you are (the token\'s user), whether your token is read-only, your permissions, and the panel\'s screens (resources with what you may do on each), settings sections and plugins.')]
final class GetPanelInfo extends Tool
{
    protected string $name = 'get_panel_info';

    public function handle(Request $request): Response
    {
        $panel = app(Panel::class);
        $user = auth($panel->getGuard())->user();
        if (! $user instanceof Authorizable) {
            return Response::error('Not signed in.');
        }
        $permissions = array_values(array_filter($panel->getPermissions(), fn (Permission $p) => $user->can($p->name)));

        return $this->json([
            'panel' => $panel->getBrand(),
            'you' => ['name' => data_get($user, 'name'), 'email' => data_get($user, 'email')],
            'read_only_token' => app(TokenScope::class)->readOnly(),
            'permissions' => array_map(fn (Permission $p) => ['name' => $p->name, 'label' => $p->label, 'group' => $p->group], $permissions),
            'resources' => array_values(array_map(fn (RegisteredResource $r) => [
                'key' => $r->key(),
                'label' => $r->class::label(),
                'can' => array_values(array_filter(['view', 'create', 'update', 'delete'], fn (string $ability) => $r->allows($user, $ability))),
            ], array_filter($panel->getResources(), fn (RegisteredResource $r) => $r->allows($user, 'view')))),
            'settings' => array_values(array_map(fn (RegisteredSettings $s) => ['key' => $s->key(), 'label' => $s->class::label()],
                array_filter($panel->getSettings(), fn (RegisteredSettings $s) => $s->allows($user)))),
            'plugins' => array_keys($panel->getPlugins()),
        ]);
    }
}
