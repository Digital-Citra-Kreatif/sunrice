<?php

declare(strict_types=1);

namespace Sunrice\Tests\Admin\Fixtures;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Sunrice\Admin\Ai\Tool;

#[IsReadOnly]
#[Description('Read the shop.')]
final class ShopReadTool extends Tool
{
    protected string $name = 'shop_read';

    public function handle(Request $request): Response
    {
        return $this->json(['ok' => true]);
    }
}
