<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

it('registers students and sends a verification notification', function () {
    Notification::fake();
    $this->post('/register', [
        'name' => 'New Student', 'email' => 'new@example.test',
        'password' => 'secure-password', 'password_confirmation' => 'secure-password',
        'role' => 'admin',
    ])->assertRedirect('/dashboard');
    $user = User::where('email', 'new@example.test')->firstOrFail();
    expect($user->hasRole('student'))->toBeTrue()->and($user->hasRole('admin'))->toBeFalse();
    Notification::assertSentTo($user, VerifyEmail::class);
    $this->get('/dashboard')->assertRedirect('/verify-email');
});

it('blocks login after five failed attempts for the same email and IP', function () {
    $user = User::factory()->create();
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post('/login', ['email' => $user->email, 'password' => 'incorrect'])->assertSessionHasErrors('email');
    }
    $key = strtolower($user->email).'|127.0.0.1';
    expect(RateLimiter::tooManyAttempts($key, 5))->toBeTrue();
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();
    RateLimiter::clear($key);
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
});
