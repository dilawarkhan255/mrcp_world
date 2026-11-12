<?php

use App\Models\User;

it('sends the security headers on public pages', function () {
    $response = $this->get('/');

    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'; object-src 'none'; base-uri 'self'")
        ->assertHeader('Permissions-Policy')
        // HSTS only over HTTPS: on plain HTTP it would be ignored or lock out a local server.
        ->assertHeaderMissing('Strict-Transport-Security');
});

it('sends them on the admin panel, JSON answers and redirects too', function () {
    $this->get('/admin/login')->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $this->get('/dashboard')->assertRedirect()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->actingAs(User::factory()->create())->getJson('/search/quick?q=ab')->assertHeader('X-Frame-Options', 'SAMEORIGIN');
});

it('asks browsers to stay on HTTPS when the site is served over HTTPS', function () {
    $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});
