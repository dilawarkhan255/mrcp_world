# CLAUDE.md

Guidance for Claude Code working in this repository. Read this before making changes.

## Product

MRCP World is a question bank (Qbank) for the MRCP Part 1 and MRCP Part 2 Written exams (UK/Irish).

- Each exam has a **Main MCQ Bank** and **Past Paper MCQs**.
- Questions are organised by **subject**, stored as categories that belong to an exam (unique slug per exam).
- Past papers are organised sets of MCQs (exam, year, title, slug). Uploading a PDF does not create questions.
- Only **published** questions are visible to students. A question tied to a paper also requires that paper to be published.
- **Explanations must be detailed and reference NICE and WHO guidelines** wherever the subject has relevant guidance. Do not invent guideline titles, years or URLs; if unsure of a citation, say so and flag it for human review.
- The extended textbook (chapters by subject) is implemented. Flashcards and the knowledge tutor are not implemented; do not describe them as working.
- **Subscriptions:** each Qbank is sold separately (£8 for 6 months). Payment is manual: students request, admins activate in **Subscriptions**. Without access a student only gets demo questions (`is_demo`); subscription pages use the `qbank` route middleware.

## Stack

- **Backend:** Laravel 12 (PHP 8.2+, 8.3 preferred)
- **Frontend:** Inertia 2 + Vue 3 + TypeScript, Vite 6, Tailwind CSS 3, shadcn-vue (starter-kit components), TanStack Vue Table for the reusable DataTable
- **Admin:** Filament v3, mounted inside Laravel at `/admin`
- **Permissions:** spatie/laravel-permission 6
- **Tests:** Pest 3 (with PHPUnit 11 underneath). Tests run on an isolated in-memory SQLite database.
- **Static analysis / style:** Larastan level 6 (no baseline), Laravel Pint, ESLint, Prettier
- **Imports:** CSV via streamed reads, XLSX via OpenSpout 4
- **AI tutor:** the official Anthropic PHP SDK (`anthropic-ai/sdk`) in `App\Services\AiTutor`, model `claude-opus-5-5` (`ANTHROPIC_MODEL`), off without `ANTHROPIC_API_KEY`. Answers are cached and limited per student per day. "Ask AI" works on every question, but Claude gets the answer key only after the answer is revealed, and never in an open mock.
- **Other packages:** Ziggy, predis (no phpredis extension needed). yajra/laravel-datatables-oracle + datatables.net-dt power the student data tables (`StudentTableController`, `resources/js/components/YajraTable.vue`); admin tables stay Filament.

## Local environment

- **OS / shell:** Windows 11, **PowerShell**. Use PowerShell syntax in commands given to the user. Paths: `C:/xampp/htdocs/mrcp_world`.
- **Database:** XAMPP's **MariaDB 10.4**, through Laravel's `mysql` driver at `127.0.0.1:3306`, database `mrcp_world`, user `root`. Start MySQL in the XAMPP Control Panel.
- **Not used:** Docker, Laravel Sail, Redis. Cache, session and queue all use the database driver locally.
- **Server:** `php artisan serve --host=127.0.0.1 --port=8000`, plus `npm run dev -- --host 127.0.0.1` for Vite. `composer dev` starts both with a queue listener.
- **Queue:** imports need a worker running: `php artisan queue:listen --queue=imports,default --timeout=1200 --tries=3 --memory=256`. Do not use Pail or `queue:work` with pcntl on Windows.
- **Database caveat:** MariaDB runs with `ONLY_FULL_GROUP_BY`. SQLite tests accept SQL that MariaDB rejects, so check student pages against local MySQL before calling a change done.
- Seed accounts and passwords are in README.md. Do not copy them elsewhere.

## Verification

Run the checks that match the change before reporting it done:

1. `php artisan test`. Run the full suite, not only the new tests.
2. `vendor/bin/pint` on **changed PHP files only**, e.g. `vendor/bin/pint app/Http/Controllers/PracticeController.php`. Never run Pint on the whole repo. If `vendor/bin/pint` does not work in PowerShell, use `php vendor/laravel/pint/builds/pint <files>`.
3. `npx vue-tsc --noEmit` (same as `npm run typecheck`).
4. `npx eslint <changed files>`. Do not use `npm run lint` because it runs `eslint . --fix` across the project.
5. `npm run build`.

Also available: `composer analyse` (Larastan) and `npm run format:check` (Prettier). Run them when relevant to the change.

Report results faithfully. If a check fails or was not run, say so and include the relevant output.

## Rules

- **Never commit `.env`.** It is listed in `.gitignore`; keep it that way.
- **Never run `migrate:fresh`, `migrate:refresh`, `db:wipe`, `migrate:reset`, or any command or query that drops or truncates data** without asking first. Explain what would be lost and wait for confirmation.
- **Ask before adding any Composer or npm package.** Say what it is for and whether an existing package already covers the need.
- **Keep existing tests passing.** Add tests for new behaviour. Bug fixes need a regression test.
- **Do not overwrite existing seeded account passwords.** The seeder is designed to skip existing accounts.
- **Do not edit `public/hot` or commit build output** unless asked. Remove a stale `public/hot` before using `npm run build` for production-style assets.
- **Do not add an `@anthropic` or other attribution line to commit messages unless the user's instructions require it.**
- **Commit or push only when asked.** Work on a branch if the current branch is `main`.

## Architecture notes

- **Student routes** are in `routes/web.php` under `auth` + `verified`. Placeholder pages come from `config/student.php`; navigation groups are defined there too.
- **Question pages:** practice sessions, mock exams, review queue, performance, comment threads and bookmarks.
- **Scoring:** correct answers are scored under a session row lock. Repeated submissions do not increment the score twice. Sessions store snapshots of the question, options, answer and explanation, so later admin edits do not change old results.
- **Mock exams:** the server enforces the deadline inside the same row lock as scoring. Correct answers are hidden until the mock closes. The browser countdown only mirrors the server.
- **Answer secrecy:** the answer and explanation are absent from the unanswered-question response. Keep it that way.
- **Comment visibility** is applied in the query, not in the browser. Hidden threads remain visible only to their author and moderators.
- **Imports** run in a queue worker, not in the HTTP request. Files are stored privately in `storage/app/private/question-imports`. Matching exam + code updates the existing question. Limits: 20 MB per file, 50,000 rows, 100 MB expanded XLSX. Legacy `.xls` is not supported.
- **Performance and review queries** group by UTC day. Every selected column must appear in `GROUP BY`.
- **Qbanks:** a Qbank groups exams; an exam may read another exam's bank via `content_exam_id` (IMM → MRCP 1, FCPS Part 2 → MRCP 2). Student controllers resolve the exam through `App\Support\CurrentExam`: `content()` for queries and stored rows, `selected()`/`props()` for the name shown. Never query questions by the selected exam's id.
- **Rich content:** MCQ text fields are Markdown rendered by `App\Support\QuestionContent` with raw HTML stripped. Everything that reveals the answer (explanation, key points, mnemonics, references, video, image links) is in `Question::$hidden` and only sent inside `result`.
- **Candidate stats** are read from `question_stats`/`category_stats`, refreshed by `stats:refresh`; do not aggregate all students' answers in a request.
- **Session creation** uses an indexed random starting point with wraparound. It is not a uniform random sample. Do not replace it with a full-table `ORDER BY RAND()`.
- **Roles and permissions** (spatie): permissions are defined in `config/permissions.php` (with labels); admins manage roles, role permissions and user accounts in **Users and billing → Users / Roles & permissions**. `admin`, `editor`, `student` are protected names. Panel access is any user with at least one permission (`User::isStaff()`). `RoleSeeder` never undoes panel changes; it only adds new permissions.
- **Brand:** the name comes from `APP_NAME`. Vue reads it from `resources/js/lib/brand.ts`. Design tokens are in README.md.

## Working conventions

- Read the relevant code before editing it. Keep changes scoped to the request.
- Follow existing patterns: Form Requests for validation, policies for resource access, Inertia pages under `resources/js/pages`.
- For UI, reuse starter-kit and shadcn-vue components before adding new ones.
- Keep Filament resources consistent with the existing Exam and Category resources.
- When a change touches admin content flows, check [docs/CONTENT_WORKFLOW.md](docs/CONTENT_WORKFLOW.md), which describes the admin-to-student flow.
