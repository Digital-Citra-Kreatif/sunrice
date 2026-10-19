<?php

declare(strict_types=1);

namespace Sunrice\Admin\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Sunrice\Admin\Actions\Ai\CreateAiToken;
use Sunrice\Admin\Actions\Ai\RevokeAiTokens;
use Sunrice\Admin\Ai\AiToken;
use Sunrice\Admin\Panel;

/**
 * sunrice:ai-token — AI access tokens from the command line, for setting
 * up an agent on a server: create one for a user, list or revoke theirs.
 *
 * @internal
 */
final class AiTokenCommand extends Command
{
    protected $signature = 'sunrice:ai-token
        {email : The user the agent works as}
        {--name=AI agent : What the token is for}
        {--read-only : The agent may only read}
        {--days= : The token expires after this many days}
        {--list : List the user\'s tokens instead}
        {--revoke : Revoke all of the user\'s tokens instead}';

    protected $description = 'Create, list or revoke a user\'s AI access tokens';

    public function handle(Panel $panel, CreateAiToken $create, RevokeAiTokens $revoke): int
    {
        if (! $panel->hasAiAccess()) {
            $this->components->error('AI access is off in this panel.');

            return self::FAILURE;
        }
        $user = $panel->getUserModel()::query()->where('email', (string) $this->argument('email'))->first();
        if ($user === null) {
            $this->components->error('There\'s no user with that email.');

            return self::FAILURE;
        }
        $id = $user->getKey();
        $id = is_scalar($id) ? (string) $id : '';

        if ($this->option('list')) {
            $tokens = AiToken::query()->where('user_id', $id)->latest('id')->get();
            $this->table(['Name', 'Read only', 'Last used', 'Expires'], $tokens->map(fn (AiToken $token): array => [
                $token->name, $token->read_only ? 'yes' : 'no', $token->last_used_at?->diffForHumans() ?? 'never', $token->expires_at?->toDateString() ?? 'never',
            ])->all());

            return self::SUCCESS;
        }
        if ($this->option('revoke')) {
            $count = $revoke->all($user);
            $this->components->info("Revoked {$count} ".($count === 1 ? 'token' : 'tokens').'.');

            return self::SUCCESS;
        }

        if (! $user instanceof Authorizable || ! $user->can('sunrice.ai.connect')) {
            $this->components->warn('This user may not connect AI agents (sunrice.ai.connect): the token won\'t work until they may.');
        }
        $days = $this->option('days');
        [, $plain] = $create->handle($user, (string) $this->option('name'), (bool) $this->option('read-only'), is_numeric($days) ? now()->addDays((int) $days) : null);
        $path = config('sunrice-admin.ai.path', 'mcp');
        $this->components->info('Created the token. It is shown only now:');
        $this->line("  {$plain}");
        $this->newLine();
        $this->line('  Endpoint: '.url(is_string($path) ? $path : 'mcp'));

        return self::SUCCESS;
    }
}
