<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Support\Notify;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * The small form on the public Contact page. The message is stored (so it is
 * never lost), the staff are told in the panel bell, and a copy goes to the
 * contact email. "website" is a hidden field that only bots fill in.
 */
class ContactMessageController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\-\s]{6,40}$/'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
            'website' => ['nullable', 'string', 'max:0'],
        ], ['website.max' => 'Your message could not be sent.', 'phone.regex' => 'Enter a phone number with digits only.']);

        $contact = ContactMessage::create([
            'name' => trim($data['name']),
            'email' => trim($data['email']),
            'phone' => isset($data['phone']) ? trim($data['phone']) : null,
            'message' => trim($data['message']),
            'ip' => $request->ip(),
        ]);

        Notify::staffWith('community.comments.moderate', 'New contact message', $contact->name.': '.$contact->message, '/admin/contact-messages', 'heroicon-o-envelope', 'info');
        $to = (string) config('legal.email');
        if (str_contains($to, '@')) {
            try {
                Mail::to($to)->queue(new ContactMessageReceived($contact));
            } catch (Throwable $exception) {
                // The message is saved and in the panel; a mail problem must not lose it or show an error.
                Log::warning('Contact message email failed', ['id' => $contact->id, 'error' => $exception->getMessage()]);
            }
        }

        return back()->with('status', 'Thank you, '.$contact->name.'. Your message has been sent; we will reply to '.$contact->email.' as soon as we can.');
    }
}
