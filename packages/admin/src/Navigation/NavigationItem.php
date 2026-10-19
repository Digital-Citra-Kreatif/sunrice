<?php

declare(strict_types=1);

namespace Sunrice\Admin\Navigation;

use Closure;
use Illuminate\Contracts\Auth\Access\Authorizable;
use InvalidArgumentException;

/**
 * A link in the admin sidebar.
 *
 *     NavigationItem::make('shop.orders')
 *         ->label('Orders')->url('shop/orders')->icon('receipt')
 *         ->group('Shop')->permission('shop.orders.view');
 *
 * `url` is relative to the panel's path. An item without a permission is
 * shown to everyone who can open the panel.
 *
 * @api
 */
final class NavigationItem
{
    private string $label;

    private string $url = '';

    private string $icon = 'circle';

    private ?string $group = null;

    private ?string $parent = null;

    private int $sort = 100;

    private ?string $permission = null;

    /** @var (Closure(Authorizable): bool)|null */
    private ?Closure $visible = null;

    private function __construct(public readonly string $key)
    {
        $this->label = $key;
    }

    public static function make(string $key): self
    {
        if (preg_match('/^[a-z0-9][a-z0-9._-]*$/', $key) !== 1) {
            throw new InvalidArgumentException("Navigation item \"{$key}\": use a lowercase key like \"shop.orders\".");
        }

        return new self($key);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function url(string $url): self
    {
        $this->url = trim($url, '/');

        return $this;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function group(?string $group): self
    {
        $this->group = $group;

        return $this;
    }

    /** Nest under another item (by its key) as a submenu entry. */
    public function parent(?string $key): self
    {
        $this->parent = $key;

        return $this;
    }

    /** Lower comes first; 100 by default. */
    public function sort(int $sort): self
    {
        $this->sort = $sort;

        return $this;
    }

    public function permission(?string $permission): self
    {
        $this->permission = $permission;

        return $this;
    }

    /**
     * Show the item only when this returns true for the user (instead of,
     * or as well as, a permission).
     *
     * @param  Closure(Authorizable): bool  $check
     */
    public function visible(Closure $check): self
    {
        $this->visible = $check;

        return $this;
    }

    /** Whether the user may see it: the permission, if any, and the visibility check, if any. */
    public function isVisibleTo(Authorizable $user): bool
    {
        return ($this->permission === null || $user->can($this->permission))
            && ($this->visible === null || ($this->visible)($user));
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getGroup(): ?string
    {
        return $this->group;
    }

    public function getParent(): ?string
    {
        return $this->parent;
    }

    public function getSort(): int
    {
        return $this->sort;
    }

    public function getPermission(): ?string
    {
        return $this->permission;
    }
}
