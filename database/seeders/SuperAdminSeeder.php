<?php

namespace Database\Seeders;

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Creates the first super admin from SUPER_ADMIN_EMAIL with a random password,
 * then prints a password-reset link (LLD 11 §5).
 */
class SuperAdminSeeder extends Seeder
{
    public function run(WorkspaceContext $context): void
    {
        $email = config('platform.super_admin.email');

        if (blank($email)) {
            $this->command?->warn('SUPER_ADMIN_EMAIL is not set; no super admin was created.');

            return;
        }

        $user = User::query()->firstOrCreate(['email' => $email], [
            'name_en' => config('platform.super_admin.name'),
            'password' => Str::password(32),
            'email_verified_at' => now(),
        ]);

        $context->within(Workspace::platform(), fn () => $user->assignRole(User::SUPER_ADMIN));

        if ($user->wasRecentlyCreated && $this->command !== null) {
            $token = Password::broker()->createToken($user);
            $this->command->info('Super admin created: '.$email);
            $this->command->line('Set the password here: '.url(route('password.reset', ['token' => $token, 'email' => $email], false)));
        }
    }
}
