<?php

/*
|--------------------------------------------------------------------------
| Legal details
|--------------------------------------------------------------------------
|
| The owner details used by the Terms of use and the Privacy notice. Set
| them in .env before the site goes live; until then the pages show the
| bracketed placeholders, so nothing is published with invented details.
| Both texts are drafts and should be reviewed by a lawyer.
|
*/

return [
    'service' => env('APP_NAME', 'MRCP World'),
    'owner' => env('LEGAL_OWNER_NAME') ?: '[OWNER OR COMPANY NAME]',
    'address' => env('LEGAL_ADDRESS') ?: '[POSTAL ADDRESS]',
    'email' => env('LEGAL_CONTACT_EMAIL') ?: '[CONTACT EMAIL]',
    'governing_law' => env('LEGAL_GOVERNING_LAW') ?: '[COUNTRY WHOSE LAW APPLIES]',
    'updated' => env('LEGAL_UPDATED_AT', '10 November 2026'),
];
