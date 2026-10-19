<?php

declare(strict_types=1);

namespace Sunrice\Admin\Ai\Tools\Developer;

use Sunrice\Admin\Ai\DeveloperTools;
use Sunrice\Admin\Ai\Tool;

/**
 * A tool for working on the app itself, offered only when the developer
 * tools are turned on (`sunrice-admin.ai.developer_tools.enabled`).
 *
 * @internal
 */
abstract class DeveloperTool extends Tool
{
    public function shouldRegister(): bool
    {
        return DeveloperTools::enabled() && parent::shouldRegister();
    }
}
