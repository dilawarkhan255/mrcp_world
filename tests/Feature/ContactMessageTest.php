<?php

use App\Filament\Resources\ContactMessageResource\Pages\ListContactMessages;
use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Models\User;
use Database\Seeders\ExamSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([ExamSeeder::class, RoleSeeder::class]);
    config(['legal.email' => 'owner@example.test', 'legal.phone' => '0313 4488339']);
    Mail::fake();
});

it('shows the contact email and phone on the Contact page', function () {
    $this->get('/contact')->assertInertia(fn (Assert $page) => $page
        ->component('Legal/Contact')
        ->where('email', 'owner@example.test')
        ->where('phone', '0313 4488339'));
});

it('saves a message from the contact form, tells the staff and emails the contact address', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->from('/contact')->post('/contact', [
        'name' => 'Ayesha Khan', 'email' => 'ayesha@example.test', 'phone' => '0300 1234567',
        'message' => 'Is the MRCP 2 bank available yet?', 'website' => '',
    ])->assertRedirect('/contact')->assertSessionHas('status');

    $message = ContactMessage::firstOrFail();
    expect($message->name)->toBe('Ayesha Khan')->and($message->status)->toBe(ContactMessage::STATUS_NEW)
        ->and($admin->notifications()->count())->toBe(1);
    Mail::assertQueued(ContactMessageReceived::class, fn (ContactMessageReceived $mail): bool => $mail->hasTo('owner@example.test') && $mail->hasReplyTo('ayesha@example.test'));
});

it('validates the form and refuses bots that fill the hidden field', function () {
    $this->post('/contact', ['name' => 'A', 'email' => 'not-an-email', 'message' => 'short'])->assertSessionHasErrors(['name', 'email', 'message']);
    $this->post('/contact', ['name' => 'Bot', 'email' => 'bot@example.test', 'message' => 'Buy cheap pills now please', 'website' => 'http://spam.test'])
        ->assertSessionHasErrors('website');
    $this->post('/contact', ['name' => 'Ali', 'email' => 'ali@example.test', 'phone' => 'call me', 'message' => 'A real question here.'])
        ->assertSessionHasErrors('phone');

    expect(ContactMessage::count())->toBe(0);
    Mail::assertNothingQueued();
});

it('lets admins read and delete contact messages, and keeps students out', function () {
    $message = ContactMessage::create(['name' => 'Bilal', 'email' => 'bilal@example.test', 'message' => 'Hello, I have a question.']);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $student = User::factory()->create();
    $student->assignRole('student');
    $this->actingAs($student)->get('/admin/contact-messages')->assertForbidden();

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin)->get('/admin/contact-messages')->assertOk();
    expect(yajraHtml(ListContactMessages::class))->toContain('Bilal');

    Livewire::test(ListContactMessages::class)->callTableAction('markReplied', $message);
    expect($message->fresh()->status)->toBe(ContactMessage::STATUS_REPLIED);
    Livewire::test(ListContactMessages::class)->callTableAction('delete', $message);
    expect(ContactMessage::count())->toBe(0);
});
