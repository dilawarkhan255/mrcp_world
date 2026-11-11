<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Filament\Notifications\Auth\ResetPassword as FilamentResetPassword;
use Filament\Pages\Auth\EditProfile;
use Filament\Pages\Auth\PasswordReset\RequestPasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->create(['email' => 'boss@example.test']);
    $this->admin->assignRole('admin');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('offers "Forgot password?" on the admin login and emails a reset link', function () {
    Notification::fake();

    $this->get('/admin/login')->assertOk()->assertSee('/admin/password-reset/request', false);
    $this->get('/admin/password-reset/request')->assertOk();

    Livewire::test(RequestPasswordReset::class)
        ->set('data.email', 'boss@example.test')
        ->call('request')
        ->assertHasNoErrors();

    Notification::assertSentTo($this->admin, FilamentResetPassword::class);
});

it('lets an admin change their own name and password on the Profile page', function () {
    $this->actingAs($this->admin)->get('/admin/profile')->assertOk();

    Livewire::actingAs($this->admin)->test(EditProfile::class)
        ->set('data.name', 'New Name')
        ->set('data.password', 'A-new-password-2026')
        ->set('data.passwordConfirmation', 'A-new-password-2026')
        ->call('save')
        ->assertHasNoErrors();

    $this->admin->refresh();
    expect($this->admin->name)->toBe('New Name')
        ->and(Hash::check('A-new-password-2026', $this->admin->password))->toBeTrue();
});

it('keeps the student "Forgot password?" flow on the student login', function () {
    $this->get('/login')->assertOk();
    $this->get('/forgot-password')->assertOk();
});
