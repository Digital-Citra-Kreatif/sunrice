<?php

declare(strict_types=1);

namespace Sunrice\Admin\Ai;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\ServerContext;
use Sunrice\Admin\Panel;

/**
 * The panel's MCP server: the panel's own tools and the ones plugins and
 * the app register (Panel::tools()), with their instructions
 * (Panel::aiInstructions()).
 *
 * @internal
 */
final class PanelServer extends Server
{
    public const INSTRUCTIONS = <<<'MD'
You work in an admin panel as the user who owns your access token, with that user's permissions; a read-only token can only read.

Start with get_panel_info: who you are, what you may do, and the panel's screens (resources), settings sections and tools.

Everything you change is recorded in the activity log under your user, with the source "AI agent" and your access token's name. Ask the user before deleting anything or making large changes.
MD;

    /** All tools in one list: clients see everything on the first page. */
    public int $defaultPaginationLength = 50;

    protected string $version = '1.0.0';

    protected function boot(): void
    {
        $this->tools = array_values(array_unique([Tools\GetPanelInfo::class, ...app(Panel::class)->getTools()]));
    }

    public function createContext(): ServerContext
    {
        $context = parent::createContext();
        $panel = app(Panel::class);
        $context->instructions = implode("\n\n", [self::INSTRUCTIONS, ...$panel->getAiInstructions()]);
        $context->implementation->name = $panel->getBrand();

        return $context;
    }
}
