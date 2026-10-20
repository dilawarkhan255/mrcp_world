<?php

namespace Database\Seeders;
use App\Models\User;
use Illuminate\Database\Seeder;

class LocalUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) { return; }
        foreach (['admin' => 'Local Admin', 'editor' => 'Local Editor', 'student' => 'Local Student'] as $role => $name) {
            $user = User::firstOrCreate(['email' => $role.'@example.test'], [
                'name' => $name, 'password' => 'LocalOnly!2026',
                'email_verified_at' => now(), 'country' => 'PK', 'timezone' => 'Asia/Karachi',
            ]);
            $user->assignRole($role);
        }
    }
}
