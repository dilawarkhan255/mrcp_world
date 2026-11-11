New message from the {{ config('app.name') }} contact page

Name:  {{ $contact->name }}
Email: {{ $contact->email }}
Phone: {{ $contact->phone ?: '—' }}
Sent:  {{ $contact->created_at?->toDayDateTimeString() }}

{{ $contact->message }}

---
Reply to this email to answer {{ $contact->name }}. All messages are in the admin panel: {{ url('/admin/contact-messages') }}
