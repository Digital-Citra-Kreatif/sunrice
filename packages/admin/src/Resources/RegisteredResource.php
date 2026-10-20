<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources;

use Closure;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Tables\Table;

/**
 * A resource on the panel, with its permission prefix, and its form and
 * table built once per request.
 *
 * @internal
 */
final class RegisteredResource
{
    private ?Form $form = null;

    private ?Table $table = null;

    /**
     * @param  class-string<\Sunrice\Admin\Resources\Resource>  $class
     * @param  string|null  $path  its address under the panel: a plugin's under the plugin's id ("cms/forms"), the app's at its key
     */
    public function __construct(public readonly string $class, public readonly string $permissionPrefix, private readonly ?string $path = null) {}

    /** Its address under the panel: "products", or "shop/products" for a plugin's. */
    public function path(): string
    {
        return $this->path ?? $this->key();
    }

    public function key(): string
    {
        return $this->class::key();
    }

    public function form(): Form
    {
        return $this->form ??= $this->class::form(new Form);
    }

    public function table(): Table
    {
        return $this->table ??= $this->class::table(new Table);
    }

    public function gate(): ResourceGate
    {
        return new ResourceGate($this->class, $this->permissionPrefix);
    }

    public function allows(Authorizable $user, string $ability, ?Model $record = null): bool
    {
        return $this->gate()->allows($user, $ability, $record);
    }

    /** @return Closure(Authorizable): bool */
    public function visibleTo(): Closure
    {
        return fn (Authorizable $user): bool => $this->allows($user, 'view');
    }
}
