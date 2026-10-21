<?php

declare(strict_types=1);

namespace Sunrice\Admin\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Sunrice\Admin\Actions\Ai\CreateAiToken;
use Sunrice\Admin\Actions\Ai\RevokeAiTokens;
use Sunrice\Admin\Ai\AiToken;
use Sunrice\Admin\Panel;

/**
 * sunrice:ai-token: creates, lists or revokes a user's access tokens for
 * AI agents, like the panel's AI access page.
 *
 * @internal
 */
final class AiTokenCommand extends Command
{
    protected $signature = 'sunrice:ai-token
        {email : The user the agent acts as}
        {--name=AI agent : A name to recognise the token by}
        {--read-only : The agent may only look}
        {--expires=90 : Days until it expires, or "never"}
        {--list : List the user\'s tokens instead}
        {--revoke= : Revoke the user\'s token with this id}';

    protected $description = 'Create (or list, or revoke) an access token for AI agents acting as a user';

    public function handle(Panel $panel, CreateAiToken $create, RevokeAiTokens $revoke): int
    {
        $user = $panel->getUserModel()::query()->where('email', $this->argument('email'))->first();
        if (! $user instanceof Model) {
            $this->components->error('No account has that email.');

            return self::FAILURE;
        }
        $key = $user->getKey();
        $tokens = AiToken::query()->where('user_id', is_scalar($key) ? (string) $key : '');

        if ($this->option('list') === true) {
            $this->table(['ID', 'Name', 'Read-only', 'Expires', 'Last used', 'Created'], $tokens->orderBy('id')->get()->map(fn (AiToken $t): array => [
                $t->id, $t->name, $t->read_only ? 'yes' : 'no', $t->expires_at?->toDateString() ?? 'never',
                $t->last_used_at?->diffForHumans() ?? 'never', $t->created_at?->toDateString(),
            ])->all());

            return self::SUCCESS;
        }

        $revoking = $this->option('revoke');
        if ($revoking !== null) {
            $token = ctype_digit($revoking) ? $tokens->whereKey((int) $revoking)->first() : null;
            if ($token === null) {
                $this->components->error('This account has no token with that id (--list shows them).');

                return self::FAILURE;
            }
            $revoke->one($token, $user);
            $this->components->info("Token \"{$token->name}\" revoked.");

            return self::SUCCESS;
        }

        // Listing and revoking still work, for tokens made before.
        if (! $panel->hasAiAccess()) {
            $this->components->error('AI access is turned off in this panel ($panel->aiAccess(false)): no agent could use a token.');

            return self::FAILURE;
        }
        $expires = strtolower((string) $this->option('expires'));
        if ($expires !== 'never' && (! ctype_digit($expires) || (int) $expires < 1 || (int) $expires > 3650)) {
            $this->components->error('--expires takes a number of days (1 to 3650) or "never".');

            return self::FAILURE;
        }
        $name = trim((string) $this->option('name'));
        if (! $user instanceof Authorizable || ! $user->can(Panel::ACCESS) || ! $user->can('sunrice.ai.connect')) {
            $this->components->warn('This account may not open the panel or connect AI agents (sunrice.access, sunrice.ai.connect): the token won\'t work until a role gives it both.');
        }
        [, $plain] = $create->handle($user, $name !== '' ? $name : 'AI agent', $this->option('read-only') === true, $expires === 'never' ? null : now()->addDays((int) $expires));

        $this->components->info('Token created. It is shown only once:');
        $this->line($plain);
        $this->newLine();
        $path = config('sunrice-admin.ai.path', 'mcp');
        $this->line('MCP endpoint: '.url('/'.trim(is_string($path) ? $path : 'mcp', '/')));
        $this->line("Send it as the header  Authorization: Bearer {$plain}");

        return self::SUCCESS;
    }
}
