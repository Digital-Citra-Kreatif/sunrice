<?php

declare(strict_types=1);

namespace Sunrice\Admin\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Traits\HasRoles;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Panel;

/**
 * sunrice:make-admin — create a super admin (or make an existing user
 * one). The way in on a fresh install.
 *
 * @internal
 */
final class MakeAdminCommand extends Command
{
    protected $signature = 'sunrice:make-admin {--name= : The user\'s name} {--email= : The login email} {--password= : Leave out to be asked (not stored in your shell history)}';

    protected $description = 'Create a super admin for the admin panel, or make an existing user one';

    public function handle(Panel $panel, SyncPermissions $sync): int
    {
        $model = $panel->getUserModel();
        if (! in_array(HasRoles::class, class_uses_recursive($model), true)) {
            $this->components->error("{$model} needs the Spatie\\Permission\\Traits\\HasRoles trait.");

            return self::FAILURE;
        }

        $email = $this->text('email', 'Email');
        $user = $model::query()->where('email', $email)->first();

        $data = ['email' => $email];
        if ($user === null) {
            $data['name'] = $this->text('name', 'Name');
            $data['password'] = $this->text('password', 'Password', secret: true);
        }
        $validator = Validator::make($data, [
            'email' => ['required', 'email', 'max:255'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'password' => ['sometimes', 'required', 'string', 'max:1000', Password::defaults()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        if ($user === null) {
            $user = new $model;
            $user->forceFill(['name' => $data['name'], 'email' => $email, 'password' => Hash::make($data['password'])])->save();
        }

        $sync->handle();
        $user->assignRole(SyncPermissions::superAdminRole()); // @phpstan-ignore method.notFound (HasRoles is checked above)
        $this->components->info("{$email} is a super admin. Sign in at ".url($panel->getPath()).'.');

        return self::SUCCESS;
    }

    /** An option's value, or the answer to a question when it wasn't given. */
    private function text(string $option, string $question, bool $secret = false): string
    {
        $value = $this->option($option);
        if (! is_string($value) || $value === '') {
            $value = $secret ? $this->secret($question) : $this->ask($question);
        }

        return is_string($value) ? $value : '';
    }
}
