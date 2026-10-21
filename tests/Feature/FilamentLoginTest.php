<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Filament\Pages\Auth\Login;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->create(['password' => bcrypt('password')]);
    $this->admin->assignRole('admin');
});

it('redirects admin to /admin after Filament login', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    
    Livewire::test(Login::class)
        ->set('data.email', $this->admin->email)
        ->set('data.password', 'password')
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect('/admin');
});