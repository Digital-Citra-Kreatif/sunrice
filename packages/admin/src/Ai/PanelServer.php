<?php

declare(strict_types=1);

namespace Sunrice\Admin\Ai;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\StdioTransport;
use RuntimeException;
use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Security\AccountGuard;

/**
 * The panel's MCP server: the panel's own tools and the ones plugins and
 * the app register (Panel::tools()), with their instructions
 * (Panel::aiInstructions()). Over HTTP the token's user works
 * (AuthenticateAiToken); over stdio (`php artisan mcp:start sunrice`, an
 * agent on the server itself) sunrice-admin.ai.local_user does.
 *
 * @internal
 */
final class PanelServer extends Server
{
    public const INSTRUCTIONS = <<<'MD'
You work in an admin panel as the user who owns your access token, with that user's permissions; a read-only token can only read.

Start with get_panel_info: who you are, what you may do, and the panel's screens (resources), settings sections and tools. read_docs has the developer documentation (how resources, settings, fields and templates work): read the relevant page before structural work.

- Records: list_records (search, filters, sort), get_record (its fields, values and actions), save_record (only the fields you send change), delete_record, run_action.
- Settings: get_settings, then update_settings (only the settings you send change).
- Media: list_media finds files and their ids for media fields; upload_media adds one from a public URL or base64 (always give images alt text); update_media changes title, alt text and caption; manage_media handles folders, moving, the trash and deleting.
- The activity log: get_activity answers "what changed recently?" and "what did you do?" (mine: true).
- When the user reports an error or a broken page: read_error_log shows recent errors (secrets masked). Explain the likely cause and recommend a fix; don't change code or settings to fix it without asking.
- run_command runs the artisan commands this site allows ("list" shows them).

Everything you change is recorded in the activity log under your user, with the source "AI agent" and your access token's name. Ask the user before deleting anything, publishing, or making large changes.
MD;

    /** The panel's own tools. */
    public const TOOLS = [
        Tools\GetPanelInfo::class,
        Tools\ReadDocs::class,
        Tools\ListRecords::class,
        Tools\GetRecord::class,
        Tools\SaveRecord::class,
        Tools\DeleteRecord::class,
        Tools\RunAction::class,
        Tools\GetSettings::class,
        Tools\UpdateSettings::class,
        Tools\ListMedia::class,
        Tools\UploadMedia::class,
        Tools\UpdateMedia::class,
        Tools\ManageMedia::class,
        Tools\GetActivity::class,
        Tools\ReadErrorLog::class,
        Tools\RunCommand::class,
    ];

    /** How a local agent shows in the activity log. */
    public const LOCAL = 'local (mcp:start)';

    /** All tools in one list: clients see everything on the first page. */
    public int $defaultPaginationLength = 50;

    protected string $version = '1.0.0';

    private bool $local = false;

    protected function boot(): void
    {
        $this->tools = array_values(array_unique([...self::TOOLS, ...app(Panel::class)->getTools()]));
        if ($this->transport instanceof StdioTransport) {
            self::actAsLocalUser();
            $this->local = true;
        }
    }

    public function handle(string $rawMessage): void
    {
        if (! $this->local) {
            parent::handle($rawMessage);

            return;
        }
        app(ActivityLog::class)->withSource('ai', self::LOCAL, function () use ($rawMessage): void {
            parent::handle($rawMessage);
        });
    }

    /**
     * Signs in sunrice-admin.ai.local_user for an agent over stdio, with the
     * same checks as a token's user; refuses (throws) when it isn't set or
     * may not connect agents.
     *
     * @throws RuntimeException
     */
    public static function actAsLocalUser(): Authenticatable
    {
        $panel = app(Panel::class);
        $email = config('sunrice-admin.ai.local_user');
        if (! is_string($email) || trim($email) === '') {
            throw new RuntimeException('A local AI agent needs a user to act as: set sunrice-admin.ai.local_user (SUNRICE_AI_USER) to their email.');
        }
        $user = $panel->getUserModel()::query()->where('email', trim($email))->first();
        if (! $user instanceof Authenticatable || ! $user instanceof Authorizable) {
            throw new RuntimeException("No account has the email in sunrice-admin.ai.local_user ({$email}).");
        }
        if (! $user->can(Panel::ACCESS) || ! $user->can('sunrice.ai.connect')) {
            throw new RuntimeException("{$email} may not open the panel or connect AI agents (sunrice.access, sunrice.ai.connect).");
        }
        if (app(AccountGuard::class)->isLocked($user)) {
            throw new RuntimeException("{$email} is locked. Unlock it first (sunrice:unlock).");
        }
        Auth::guard($panel->getGuard())->setUser($user);
        Auth::shouldUse($panel->getGuard());

        return $user;
    }

    public function createContext(): ServerContext
    {
        $context = parent::createContext();
        $panel = app(Panel::class);
        $name = $panel->brandName();
        $context->instructions = implode("\n\n", ["The panel is called {$name}.", self::INSTRUCTIONS, ...$panel->getAiInstructions()]);
        $context->implementation->name = $name;

        return $context;
    }
}
