<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Whether a user may view, create, update or delete through a resource:
 * through the model's policy when it has one, otherwise through the
 * resource's permissions ("<prefix>.view" …), then the resource's can().
 *
 * @internal
 */
final class ResourceGate
{
    public const ABILITIES = ['view', 'create', 'update', 'delete'];

    /** @param class-string<\Sunrice\Admin\Resources\Resource> $resource */
    public function __construct(private string $resource, private string $permissionPrefix) {}

    public function allows(Authorizable $user, string $ability, ?Model $record = null): bool
    {
        return $this->baseAllows($user, $ability, $record) && $this->resource::can($user, $ability, $record);
    }

    private function baseAllows(Authorizable $user, string $ability, ?Model $record): bool
    {
        $model = $this->resource::model();

        $policy = Gate::getPolicyFor($model);
        if (is_object($policy) || is_string($policy)) {
            return match (true) {
                $ability === 'view' => $record === null ? $user->can('viewAny', $model) : $user->can('view', $record),
                $ability === 'create' => $user->can('create', $model),
                $record !== null => $user->can($ability, $record),
                // Without a record (whether to offer bulk delete): the policy's
                // updateAny/deleteAny if it has one; each record is checked anyway.
                method_exists($policy, "{$ability}Any") => $user->can("{$ability}Any", $model),
                default => true,
            };
        }

        return $user->can("{$this->permissionPrefix}.{$ability}");
    }
}
