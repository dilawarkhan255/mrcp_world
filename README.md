# MRCP World — Phase 1 Foundation

Laravel 12 with the official Laravel Vue starter kit: Inertia 2, Vue 3, TypeScript, Tailwind CSS and shadcn-vue. Filament v3 provides the editor/admin panel. Phase 2 has not been started; learning content, practice sessions, statistics and the activity heatmap remain placeholders.

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
npm install
npm run build
~~~

The supplied .env.example uses APP_NAME="MRCP World", APP_URL=http://127.0.0.1:8000, DB_CONNECTION=mysql, DB_HOST=127.0.0.1, DB_PORT=3306, DB_DATABASE=mrcp_world, DB_USERNAME=root and an empty DB_PASSWORD. Empty root credentials are for this local environment only. Do not commit .env.

CACHE_STORE=database, SESSION_DRIVER=database and QUEUE_CONNECTION=database use the migrated cache, sessions and jobs tables. MAIL_MAILER=log writes verification and password-reset emails to storage/logs/laravel.log. Open their local URLs to complete verification or reset your password.

## Run locally

Use two PowerShell terminals in the project folder:

~~~powershell
php artisan serve --host=127.0.0.1 --port=8000
~~~

~~~powershell
npm run dev -- --host 127.0.0.1
~~~

Open http://127.0.0.1:8000. The student home is /dashboard; the admin panel is /admin. Vite serves development assets at http://127.0.0.1:5173. For queued jobs, run php artisan queue:work in another terminal. The Windows setup intentionally avoids Pail (which requires pcntl) and CLI server workers.

For built assets without Vite, stop the dev server, remove a stale public/hot file if one remains, and run npm run build. The PHP server is still required.

## Local seed accounts

These accounts are created only when APP_ENV=local. They are preverified. Existing account passwords are never overwritten by rerunning the seeder.

| Role | Email | Password |
| --- | --- | --- |
| Admin | admin@example.test | LocalOnly!2026 |
| Editor | editor@example.test | LocalOnly!2026 |
| Student | student@example.test | LocalOnly!2026 |

New registrations receive the student role on the server and must verify their email before accessing the student app. Login locks out a given email/IP after five failed attempts for 60 seconds. The Filament login also has its built-in rate limiting. Only verified editors and admins may access the panel or its Exam/Category resources; model policies enforce resource access too.

Seeded exams: MRCP Part 1 and MRCP Part 2 Written. Categories belong to an exam and have a unique slug within that exam. Exams with categories cannot be deleted. The exam switcher queries active exams and stores selection in the session, falling back to the first active exam if the previous choice becomes unavailable. No category/sample learning data is seeded.

## Quality checks

~~~powershell
composer lint
composer analyse
composer test
npm run typecheck
npm run build
npm run format:check
~~~

composer lint checks Pint formatting; to apply formatting use php vendor/laravel/pint/builds/pint. composer analyse runs Larastan at level 6, without a baseline. composer test runs Pest and the starter kit's existing PHPUnit tests. Tests use an isolated SQLite in-memory database, not the local MySQL database. Coverage includes auth, verification, resets, login lockout, exam selection, panel access, resource policies and editor CRUD. GitHub Actions runs the PHP checks, frontend build/type checking and formatting checks.

## Configurable name and design

Change APP_NAME in .env and run php artisan config:clear. Laravel reads config('app.name'); Vue uses the single APP_NAME constant in resources/js/lib/brand.ts, supplied by the server's HTML data-app-name attribute at runtime. The admin panel uses the same config value.

Design tokens: ink #0F1B2D, teal #0B6E78, light teal #DDF0F1, amber #F2A93B, background #F4F7FA and border #D9E2EC. Sora is used for headings and Manrope for body text, loaded via Bunny Fonts with system fallbacks. The logo is our simple plus mark. Light, dark and system appearances persist in local storage.

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

Ensure Apache loads its vhost configuration and mod_rewrite, restart Apache, set APP_URL=http://mrcp_world.test and run php artisan config:clear. Point the virtual host to public, not the repository root. Keep Vite running for development or use npm run build for compiled assets.

## Redis later

Predis is installed so PHP does not need the phpredis extension. When a Redis server is available, set CACHE_STORE=redis, SESSION_DRIVER=redis, QUEUE_CONNECTION=redis, REDIS_CLIENT=predis and the REDIS_HOST/REDIS_PORT/REDIS_PASSWORD values in .env. Run php artisan config:clear and restart long-running queue workers. No application code change is needed; existing database sessions/cache/jobs do not transfer automatically.

## Template adaptations and attribution

Reference: https://github.com/Whbbit1999/shadcn-vue-admin, revision db05525b4ddc92006ee28c4327678e12917f2646. It was cloned outside this project at C:/Windows/Temp/mrcp-shadcn-vue-admin.

- Collapsible sidebar shell and navigation: AppSidebar.vue, using starter-kit sidebar primitives, Inertia Link, and server-provided navigation. The original team switcher was replaced with database exams.
- Dashboard stat-card structure: StatCard.vue. Values are honest placeholders with no imported statistics.
- Reusable data table: DataTable.vue. Adapted rendering, empty/loading states and pagination to TanStack Vue Table v8, with sorting/filtering and toolbar/empty slots. Previous sessions shows its empty state.
- Light/dark/system theme menu: ThemeToggle.vue, integrated with the starter kit's appearance persistence.

No vue-router, mock auth, AI talk, apps, i18n, sample data or unrelated template features were imported. The complete upstream MIT notice is retained in THIRD_PARTY_LICENSES.md. No PassMedicine assets or content are used.
