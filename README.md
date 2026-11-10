# MRCPWorld — MCQ Qbank for MRCP Part 1, MRCP Part 2, IMM and FCPS Part 2

Laravel 12 with the official Laravel Vue starter kit (Inertia 2, Vue 3, TypeScript, Tailwind CSS, shadcn-vue) and a Filament v3 admin panel. See [Content workflow](docs/CONTENT_WORKFLOW.md) for the full admin-to-student workflow.

## What the app does

**Students** (each Qbank is sold separately: 3 months £8, 5 months £12)

- Main MCQ bank and past paper MCQs by subject; practice sessions with a question grid, mark as read, clear response, labels, notes, comments and "Report MCQ".
- Detailed explanations (Markdown with tables and images), key points, mnemonics, NICE/WHO references, and a right-hand **resources sidebar** per MCQ (Extended Textbook, Image Atlas, references, image links, YouTube), shown after the answer.
- Timed mock exams (deadline enforced by the server), review queue, search, performance, candidate statistics, weekly leaderboard.
- **Extended Textbook** (subject → chapters), **Image Atlas**, **Flashcards** with spaced repetition (Again / Hard / Good / Easy).
- **AI** (Claude, official Anthropic PHP SDK): "Ask AI" under every MCQ (never given the answer key before the answer), the AI Study Plan, and the **Knowledge Tutor** conversation page.
- Copy protection on every page (copy, cut, selection, context menu, printing) and a watermark with the student's email.

**Payments**: card payments with Stripe Checkout (activated automatically by a signed webhook; refunds close access), with a manual "payment reference" request as the fallback when Stripe is not configured.

**Admin** (`/admin`): Qbanks, exams, subjects, MCQs (bulk CSV/XLSX import, "Make flashcards"), past papers with cover images, textbook, Image Atlas, flashcards, plans, subscriptions, users, roles and permissions (spatie), feedback. Every list is a server-side Yajra DataTable with search, filters, View / Edit / Delete row actions and a safe bulk delete (records still in use are skipped).

**Public site**: landing page in the logo's colours, pricing, free demo questions, Terms of use, Privacy notice, Contact and Cookie settings.

## Windows / XAMPP prerequisites

- PHP 8.2+ (8.3 preferred); Composer 2; Node 22+ and npm.
- Enable mbstring, openssl, pdo_mysql, fileinfo, intl, zip and gd in C:/xampp/php/php.ini; restart Apache after changes. Run php -v, php -m, composer -V, node -v and npm -v to verify.
- Start MySQL in the XAMPP Control Panel. The local XAMPP installation bundles MariaDB 10.4, used through Laravel's MySQL driver.
- No Docker, Sail or Redis service is needed.

## First-time setup

Run these commands in PowerShell from C:/xampp/htdocs/mrcp_world:

~~~powershell
composer install
# Only on a fresh checkout without .env:
Copy-Item .env.example .env
php artisan key:generate
C:/xampp/mysql/bin/mysql.exe -u root -h 127.0.0.1 -e "CREATE DATABASE IF NOT EXISTS mrcp_world CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate --seed
php artisan storage:link
npm install
npm run build
~~~

The supplied .env.example uses APP_URL=http://127.0.0.1:8000, DB_CONNECTION=mysql, DB_HOST=127.0.0.1, DB_PORT=3306, DB_DATABASE=mrcp_world, DB_USERNAME=root and an empty DB_PASSWORD. Empty root credentials are for this local environment only. Do not commit .env.

CACHE_STORE=database, SESSION_DRIVER=database and QUEUE_CONNECTION=database use the migrated cache, sessions and jobs tables.

## Settings in .env

| Setting | What it is for | Without it |
| --- | --- | --- |
| `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Verification and password-reset emails (for example Gmail with an App Password, or Brevo). New students must verify their email. | With `MAIL_MAILER=log` the emails only go to storage/logs/laravel.log and nobody can verify. |
| `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` | Card payments with Stripe Checkout. Webhook: `https://<domain>/stripe/webhook` with `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `checkout.session.expired`, `charge.refunded`. Locally: `stripe listen --forward-to http://127.0.0.1:8000/stripe/webhook`. | The manual request with a payment reference is used. |
| `ANTHROPIC_API_KEY`, `ANTHROPIC_MODEL`, `AI_TUTOR_DAILY_LIMIT` | Ask AI, AI Study Plan and Knowledge Tutor (key from console.anthropic.com; a claude.ai subscription does not include API access). | The AI features are hidden or show "not switched on". |
| `LEGAL_OWNER_NAME`, `LEGAL_ADDRESS`, `LEGAL_CONTACT_EMAIL`, `LEGAL_GOVERNING_LAW` | Terms of use, Privacy notice and Contact page. | The pages show bracketed placeholders. |
| `CONTENT_PROTECTION`, `CONTENT_WATERMARK` | Copy protection and the watermark (both on by default). | — |
| `COMMUNITY_WHATSAPP_URL`, `COMMUNITY_PLAY_STORE_URL` | Footer and Contact links. | The links are hidden. |

After changing .env, run `php artisan config:clear` and restart `php artisan serve`.

## Run locally

Use two PowerShell terminals in the project folder (or run composer dev to start PHP, Vite and a queue worker together):

~~~powershell
php artisan serve --host=127.0.0.1 --port=8000
~~~

~~~powershell
npm run dev -- --host 127.0.0.1
~~~

Open http://127.0.0.1:8000. The student home is /dashboard; the admin panel is /admin. Normal login sends verified admins/editors to /admin; their student sidebar also has an "Admin panel" link, and the admin panel has a "Student app" link. For uploads and queued jobs, run php artisan queue:listen --queue=imports,default --timeout=1200 --tries=3 --memory=256 in another terminal. The Windows setup intentionally avoids Pail (which requires pcntl) and CLI server workers.

For built assets without Vite, stop the dev server, remove a stale public/hot file if one remains, and run npm run build. The PHP server is still required.

## Local seed accounts

These accounts are created only when APP_ENV=local. They are preverified. Existing account passwords are never overwritten by rerunning the seeder.

| Role | Email | Password |
| --- | --- | --- |
| Admin | admin@example.test | LocalOnly!2026 |
| Editor | editor@example.test | LocalOnly!2026 |
| Student | student@example.test | LocalOnly!2026 |

Seeded exams: MRCP 1 and IMM (MRCPWorld 1 Qbank), MRCP 2 and FCPS Part 2 (MRCPWorld 2 Qbank). IMM and FCPS Part 2 read the MRCP 1 and MRCP 2 banks. Locally, `DemoContentSeeder` adds sample MCQs (codes `SAMPLE-…`), textbook chapters, flashcards made from the sample MCQs and demo student activity, so the app is not empty; remove them before going live.

New registrations get the student role on the server and must verify their email before using the student app. Login locks out an email/IP after five failed attempts for 60 seconds. Panel access is any user with at least one permission; model policies enforce resource access.

## Going live checklist

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://<domain>`, HTTPS.
- Real SMTP settings, live Stripe keys and webhook, the Anthropic key (optional), the legal details.
- `php artisan storage:link`, a queue worker (`php artisan queue:work --queue=imports,default`) and the scheduler (`php artisan schedule:run` every minute) kept running.
- Database backups. Replace the sample content with real MCQs.

## Quality checks

~~~powershell
php artisan test
composer analyse
npx vue-tsc --noEmit
npm run build
npm run format:check
~~~

Format changed PHP files only with `php vendor/laravel/pint/builds/pint <files>`, and lint changed frontend files with `npx eslint <files>`. composer analyse runs Larastan at level 6, without a baseline. Tests use an isolated SQLite in-memory database, not the local MySQL database, so check new student pages against local MariaDB (it runs with ONLY_FULL_GROUP_BY).

## Name and design

Change APP_NAME in .env and run php artisan config:clear. Laravel reads config('app.name'); Vue uses the APP_NAME constant in resources/js/lib/brand.ts, supplied by the server's HTML data-app-name attribute at runtime. The admin panel uses the same value.

Colours come from the logo: navy `#0B2A6F` for text (the word "World"), the rainbow of the "M" (orange `#F28C28`, yellow `#F5C518`, green `#2DBE4E`, cyan `#1BB8D6`, blue `#1E6FD9`, purple `#5B3FC4`) for accents and sidebar icons, and green `#15803D` for the buttons of the public site. Sora is used for headings and Manrope for body text (local font packages). The logo files are in public/brand. Light, dark and system appearances persist in local storage and are shared with the admin panel.

## Apache virtual host (optional, manual)

No Apache configuration was changed. If you prefer http://mrcp_world.test, add this virtual host yourself to XAMPP's Apache vhost configuration and add 127.0.0.1 mrcp_world.test to the Windows hosts file with administrator access:

~~~apache
<VirtualHost *:80>
    ServerName mrcp_world.test
    DocumentRoot "C:/xampp/htdocs/mrcp_world/public"
    <Directory "C:/xampp/htdocs/mrcp_world/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
~~~

Ensure Apache loads its vhost configuration and mod_rewrite, restart Apache, set APP_URL=http://mrcp_world.test and run php artisan config:clear. Point the virtual host to public, not the repository root.

## Redis later

Predis is installed so PHP does not need the phpredis extension. When a Redis server is available, set CACHE_STORE=redis, SESSION_DRIVER=redis, QUEUE_CONNECTION=redis, REDIS_CLIENT=predis and the REDIS_HOST/REDIS_PORT/REDIS_PASSWORD values in .env. Run php artisan config:clear and restart long-running queue workers. Set REDIS_QUEUE_RETRY_AFTER=1260 for long-running imports.

## Template adaptations and attribution

Reference: https://github.com/Whbbit1999/shadcn-vue-admin, revision db05525b4ddc92006ee28c4327678e12917f2646.

- Collapsible sidebar shell and navigation: AppSidebar.vue, using starter-kit sidebar primitives, Inertia Link and server-provided navigation.
- Dashboard stat-card structure: StatCard.vue, with the signed-in student's own statistics.
- Light/dark/system theme menu: ThemeToggle.vue, integrated with the starter kit's appearance persistence.

No vue-router, mock auth, apps, i18n or sample data were imported from the template. The complete upstream MIT notice is retained in THIRD_PARTY_LICENSES.md. No PassMedicine assets or content are used.
