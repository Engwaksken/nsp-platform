<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Audit;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'nsp:create-admin {email} {--name=} {--role=super_admin}';

    protected $description = 'Create (or promote) an admin account. The password is asked for interactively and never stored in shell history.';

    public function handle(): int
    {
        $email = Str::lower((string) $this->argument('email'));
        $role = (string) $this->option('role');
        if (! array_key_exists($role, RolesAndPermissionsSeeder::ROLES)) {
            $this->error("Unknown role {$role}.");

            return self::FAILURE;
        }
        $this->callSilently('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);

        $user = User::query()->where('email', $email)->first();
        if (! $user) {
            $password = (string) $this->secret('Password (at least 12 characters, letters and numbers)');
            $validator = Validator::make(
                ['email' => $email, 'password' => $password],
                ['email' => ['required', 'email'], 'password' => ['required', Password::min(12)->letters()->numbers()]]
            );
            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $this->error($message);
                }

                return self::FAILURE;
            }
            $user = new User;
            $user->forceFill([
                'name' => $this->option('name') ?: Str::before($email, '@'),
                'email' => $email,
                'password' => $password,
                'is_active' => true,
                'email_verified_at' => now(),
            ])->save();
            $this->info("Created {$email}.");
        }

        $user->assignRole($role);
        Audit::log('user.role_granted_cli', $user, ['role' => $role]);
        $this->info("{$email} now has the {$role} role.");

        return self::SUCCESS;
    }
}
