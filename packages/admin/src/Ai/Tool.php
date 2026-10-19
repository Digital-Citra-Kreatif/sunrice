<?php

declare(strict_types=1);

namespace Sunrice\Admin\Ai;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool as McpTool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use ReflectionClass;
use Sunrice\Admin\Panel;

/**
 * A tool AI agents can use through the panel's MCP endpoint, as the user
 * who owns their access token, with that user's permissions. Register
 * yours with `$panel->tools(ListOrders::class)`.
 *
 *     #[IsReadOnly]
 *     #[Description('List the shop\'s orders, newest first.')]
 *     final class ListOrders extends Tool
 *     {
 *         protected string $name = 'shop_list_orders';
 *
 *         public function handle(Request $request): Response
 *         {
 *             if (! $this->can('shop.orders.view')) {
 *                 return $this->denied('View orders');
 *             }
 *
 *             return $this->json(Order::latest()->limit(20)->get(['id', 'number', 'total']));
 *         }
 *     }
 *
 * Mark tools that only read with #[IsReadOnly]: read-only tokens see only
 * those. Check permissions in every tool; the user's own checks apply.
 *
 * @api
 */
abstract class Tool extends McpTool
{
    /** Read-only tokens get only tools marked #[IsReadOnly]. */
    public function shouldRegister(): bool
    {
        return ! app(TokenScope::class)->readOnly() || $this->isReadOnly();
    }

    public function isReadOnly(): bool
    {
        return (new ReflectionClass($this))->getAttributes(IsReadOnly::class) !== [];
    }

    /** Whether the token's user has this permission (or passes this gate). */
    protected function can(string $ability, mixed $arguments = []): bool
    {
        $user = auth(app(Panel::class)->getGuard())->user();

        return $user instanceof Authorizable && $user->can($ability, $arguments);
    }

    protected function denied(string $what): Response
    {
        return Response::error("You don't have permission to {$what}.".(app(TokenScope::class)->readOnly() ? ' This access token is read-only.' : ''));
    }

    protected function json(mixed $content): Response
    {
        return Response::json($content);
    }
}
