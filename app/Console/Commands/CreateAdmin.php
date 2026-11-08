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
    protected $signature = 'nsp:create-admin {email} {--name=} {--role=super_admin} {--reset-password : Set a new password for an existing account}';

    protected $description = 'Create (or promote) an admin account, or reset its password. The password is asked for interactively (twice) and never stored in shell history.';

    /** Ask for the password twice and validate it; null when it is rejected. */
    private function askPassword(string $email): ?string
    {
        $password = (string) $this->secret('Password (at least 12 characters, letters and numbers; typing is hidden)');
        $repeat = (string) $this->secret('Repeat the password');
        if (! hash_equals($password, $repeat)) {
            $this->error('The two passwords do not match. Nothing was changed.');

            return null;
        }
        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email'], 'password' => ['required', Password::min(12)->letters()->numbers()]]
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return null;
        }

        return $password;
    }

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
        if (! $user || $this->option('reset-password')) {
            $password = $this->askPassword($email);
            if ($password === null) {
                return self::FAILURE;
            }
            if (! $user) {
                $user = new User;
                $user->forceFill([
                    'name' => $this->option('name') ?: Str::before($email, '@'),
                    'email' => $email,
                    'email_verified_at' => now(),
                ]);
            }
            $created = ! $user->exists;
            // Re-activate on reset too, so a locked-out admin can always get back in from the server.
            $user->forceFill(['password' => $password, 'is_active' => true])->save();
            $this->info($created ? "Created {$email}." : "Password reset for {$email}.");
            if (! $created) {
                Audit::log('user.password_reset_cli', $user);
            }
        } else {
            $this->line("{$email} already exists; its password was not changed (use --reset-password to set a new one).");
        }

        $user->assignRole($role);
        Audit::log('user.role_granted_cli', $user, ['role' => $role]);
        $this->info("{$email} now has the {$role} role.");

        return self::SUCCESS;
    }
}
