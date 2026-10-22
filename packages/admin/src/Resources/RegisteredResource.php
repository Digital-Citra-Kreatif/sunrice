<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources;

use Closure;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
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

    private ?string $recordKey = null;

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

    /** Its route names' area: "products", or "shop.products" for a plugin's (sunrice.admin.shop.products.index). */
    public function routeArea(): string
    {
        return str_replace('/', '.', $this->path());
    }

    /** The full name of one of its routes: "sunrice.admin.shop.products.edit". */
    public function routeName(string $action): string
    {
        return "sunrice.admin.{$this->routeArea()}.{$action}";
    }

    /** The attribute in its records' addresses: Resource::recordKey(), else the primary key. */
    public function recordKey(): string
    {
        // A plain instance, not newRecord(): that prepares a record to create
        // (a new user's random password is hashed there), and this runs per row.
        return $this->recordKey ??= $this->class::recordKey() ?? $this->blankModel()->getKeyName();
    }

    /** An unsaved instance of its model, as the model makes it: for its key name or relations, never to save. */
    public function blankModel(): Model
    {
        $model = $this->class::model();

        return new $model;
    }

    /** A record's value in its addresses. */
    public function recordKeyOf(Model $record): string
    {
        $value = $record->getAttribute($this->recordKey());

        return is_scalar($value) ? (string) $value : '';
    }

    /** Whether its model goes to the trash when deleted (SoftDeletes): the list then has a trash. */
    public function softDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($this->class::model()), true);
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
