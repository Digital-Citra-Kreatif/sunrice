<?php

declare(strict_types=1);

namespace Sunrice\Tests\Admin\Fixtures;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Ai\Tool;

#[Description('Change the shop.')]
final class ShopWriteTool extends Tool
{
    protected string $name = 'shop_write';

    public function handle(Request $request): Response
    {
        if (! $this->can('shop.products.update')) {
            return $this->denied('edit products');
        }
        app(ActivityLog::class)->record('updated', 'products', 1, 'Kettle', ['changed' => ['price']]);

        return $this->json(['done' => true]);
    }
}
