# CLAUDE.md

Guidance for Claude Code working in this repository. Read this before making changes.

## Product

MRCP World is a question bank (Qbank) for the MRCP Part 1 and MRCP Part 2 Written exams (UK/Irish).

- Each exam has a **Main MCQ Bank** and **Past Paper MCQs**. Since 2026-11-11 the Main MCQ Bank shows **every** published MCQ, past paper ones included (`PracticeService::SOURCE_MAIN` no longer filters); Past Papers show only MCQs that belong to a paper.
- Questions are organised by **subject**, stored as categories that belong to an exam (unique slug per exam).
- Past papers are organised sets of MCQs (exam, year, title, slug). Uploading a PDF does not create questions.
- Only **published** questions are visible to students. A question tied to a paper also requires that paper to be published.
- **Explanations must be detailed and reference NICE and WHO guidelines** wherever the subject has relevant guidance. Do not invent guideline titles, years or URLs; if unsure of a citation, say so and flag it for human review.
- Implemented: the extended textbook (chapters by subject), and the **Knowledge Tutor** (`App\Services\KnowledgeTutorService`): facts are the flashcards (SM-2 in `FlashcardService`) with a high-yield rating and a difficulty refreshed by `stats:refresh`; `/tutor` (filters, progress, last activity), `/tutor/facts` (Review facts) and `/tutor/ai` (the AI tutor, conversations saved in `tutor_conversations`/`tutor_messages`, counted in the AI daily limit). Admins make facts from MCQs in **Knowledge Tutor facts**.
- **Subscriptions:** each Qbank is sold separately: 3 months for £8 or 5 months for £12 (plans in **Users and billing → Plans**). Payment by card through Stripe Checkout (`App\Services\StripeCheckout`, on when `STRIPE_SECRET` is set): the row waits in `checkout` until the signed webhook (`/stripe/webhook`) or the success page confirms it, then it is activated once; amount and currency must match. `charge.refunded` closes access on a full refund (recorded in `stripe_refunds`, so a refund that arrives before the confirmation is applied too). Otherwise manual: students request, admins activate in **Subscriptions**. Students buy a package before using the app: the whole student route group uses the `subscribed` middleware (`App\Http\Middleware\EnsureSubscribed`), which sends a student with no running subscription to `/subscription` (staff and exams outside every Qbank pass), and login sends such a student there too. A subscriber who selects an exam of a Qbank they did not buy only gets its demo questions (`is_demo`); those pages use the `qbank` route middleware. Logout (student and admin) returns to the home page. The free public demo is `/exams/{slug}/demo`.

## Stack

- **Backend:** Laravel 12 (PHP 8.2+, 8.3 preferred)
- **Frontend:** Inertia 2 + Vue 3 + TypeScript, Vite 6, Tailwind CSS 3, shadcn-vue (starter-kit components); data tables use Yajra DataTables (see Other packages)
- **Admin:** Filament v3, mounted inside Laravel at `/admin`
- **Permissions:** spatie/laravel-permission 6
- **Tests:** Pest 3 (with PHPUnit 11 underneath). Tests run on an isolated in-memory SQLite database.
- **Static analysis / style:** Larastan level 6 (no baseline), Laravel Pint, ESLint, Prettier
- **Imports:** CSV via streamed reads, XLSX via OpenSpout 4
- **AI tutor:** the official Anthropic PHP SDK (`anthropic-ai/sdk`) in `App\Services\AiTutor`, model `claude-opus-5-5` (`ANTHROPIC_MODEL`), off without `ANTHROPIC_API_KEY`. Answers are cached and limited per student per day. A small "Ask AI" box (`components/practice/AiQuickAsk.vue`) sits in the right column of every MCQ (practice, past papers, review, bookmarks, search, mocks), textbook chapter, flashcard and Image Atlas picture. Claude gets the answer key only after the answer is revealed (a flashcard back only once the card is turned), and the box is locked during an open mock.
- **Other packages:** Ziggy, predis (no phpredis extension needed). yajra/laravel-datatables-oracle + datatables.net-dt power every data table. Student side: `StudentTableController` + `resources/js/components/YajraTable.vue`. Admin side: each resource list page uses `App\Filament\Concerns\UsesYajraTable` (its view also renders the action modals, which Filament otherwise leaves to the table markup); row actions View/Edit/Delete and the safe bulk delete come from `App\Filament\Support\CrudActions` (MCQs, past papers, textbook chapters, users and atlas images open a View **page** with an infolist via `CrudActions::viewPage()`; the rest use a centred modal) with a per-resource `deletable()` rule (view `filament/pages/yajra-list-records`, JS `resources/js/admin-yajra.ts`); the Filament `table()` definition stays the source of columns, filters and actions, and rows come from the page's `yajraRows()` Livewire call. In tests, check admin rows with the `yajraRows()`/`yajraHtml()` helpers in `tests/Pest.php`, not `assertSee` on the page.

## Local environment

- **OS / shell:** Windows 11, **PowerShell**. Use PowerShell syntax in commands given to the user. Paths: `C:/xampp/htdocs/mrcp_world`.
- **Database:** XAMPP's **MariaDB 10.4**, through Laravel's `mysql` driver at `127.0.0.1:3306`, database `mrcp_world`, user `root`. Start MySQL in the XAMPP Control Panel.
- **Not used:** Docker, Laravel Sail. Cache and session use the database driver locally; the **queue uses Redis** (`QUEUE_CONNECTION=redis`, local Redis 3.0 on 127.0.0.1:6379 through predis, `REDIS_QUEUE_RETRY_AFTER=1260`).
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
- **Imports** run in a queue worker, not in the HTTP request. Besides CSV/XLSX, admins can upload a **PDF** (`Upload PDF` in Bulk MCQ uploads), read **without AI**: `App\Jobs\ImportQuestionsFromPdf` + `App\Services\PdfQuestionParser` (smalot/pdfparser) take numbered questions, A–E options, `Answer: B` (or an answer key), optional `Explanation:` and `Subject:` lines, and also the long layout (`Question 12 of 213 <topic>`, Clinical scenario, Investigations, glued options like `AVerapamil`, Key point, Mnemonic and other sections, mapped to key points, mnemonics and the explanation). The upload can name a subject for the whole PDF. Codes are `PDF-<file hash>-<number>`, so re-uploading updates. Scanned PDFs cannot be read. PDFs only create questions; they are never shown to students. Files are stored privately in `storage/app/private/question-imports`. Matching exam + code updates the existing question. Limits: 20 MB per file, 50,000 rows, 100 MB expanded XLSX. Legacy `.xls` is not supported.
- **Performance and review queries** group by UTC day. Every selected column must appear in `GROUP BY`.
- **Qbanks:** a Qbank groups exams; Qbank 1 = MRCP 1, Qbank 2 = MRCP 2 (IMM, FCPS Part 2 and a short-lived MRCPWorld PACES exam were removed on 2026-11-11). An exam may still read another exam's bank via `content_exam_id`, though no seeded exam does. Student controllers resolve the exam through `App\Support\CurrentExam`: `content()` for queries and stored rows, `selected()`/`props()` for the name shown. Never query questions by the selected exam's id.
- **Rich content:** MCQ text fields are Markdown rendered by `App\Support\QuestionContent` with raw HTML stripped. Everything that reveals the answer (explanation, key points, mnemonics, references, video, image links) is in `Question::$hidden` and only sent inside `result`.
- **MCQ resources sidebar:** the right column of the practice and closed-mock screens (`components/practice/resources/ResourcesPanel.vue`) shows Extended Textbook, Image Atlas, References, Image links and YouTube for the current MCQ. Data comes from `App\Services\QuestionResources` inside `result` only (after the answer), because a chapter or image can name the diagnosis. Editors link chapters and atlas images per MCQ (pivots `question_textbook_chapter`, `atlas_image_question`); otherwise it falls back to the subject's chapters and atlas images matching the search term. Google Images and YouTube links are built only from the editor's search term.
- **Candidate stats** are read from `question_stats`/`category_stats`, refreshed by `stats:refresh`; do not aggregate all students' answers in a request.
- **Session creation** uses an indexed random starting point with wraparound. It is not a uniform random sample. Do not replace it with a full-table `ORDER BY RAND()`.
- **Deleting:** normal deletes refuse a record still in use (each resource's `deletable()`). The `admin` role also gets **Delete with contents** (row: type the record's name; bulk: type DELETE), which `App\Services\AdminDeleter` runs in one transaction (exam → subjects, papers, MCQs, chapters, flashcards, student sessions, uploads; Qbank → its exams, plans, subscriptions; subject/paper → its MCQs). MCQs are archived (soft delete) by default; the Archived filter shows them to Restore or **Delete permanently**. In the student app admins see small Delete buttons (`components/AdminDelete.vue`, routes `/staff/...`, `AdminContentDeleteController`, shared prop `auth.isAdmin`); an MCQ deleted there is archived.
- **Auth:** the student side is the Laravel Vue starter kit (login, register with a package, forgot/reset password, email verification, confirm password, settings). The admin panel has Filament's own `passwordReset()` ("Forgot password?" on /admin/login) and `profile()` (/admin/profile). Do not install Breeze on top: it would overwrite these files. Reset and verification emails go through the SMTP settings in .env.
- **Roles and permissions** (spatie): permissions are defined in `config/permissions.php` (with labels); admins manage roles, role permissions and user accounts in **Users and billing → Users / Roles & permissions**. `admin`, `editor`, `student` are protected names. Panel access is any user with at least one permission (`User::isStaff()`). `RoleSeeder` never undoes panel changes; it only adds new permissions.
- **Contact page:** shows `LEGAL_CONTACT_EMAIL` and `LEGAL_PHONE` (config/legal.php) and has a small form (`ContactMessageController`, throttled, hidden honeypot field `website`): messages are stored in `contact_messages`, the staff get a bell notification and a copy is queued to the contact email; admins read them in **Community → Contact messages**.
- **Content protection** (`composables/useContentProtection.ts`, students and guests, not staff): no copy/cut/select/drag/right-click/print/dev-tools shortcuts, a watermark with the student's email, and a screenshot shield that blurs and covers the page on Print Screen (also clearing the clipboard), on the Windows/Command key, and while the window is not in front. A browser cannot fully stop screenshots (phone camera, OS tools); the watermark makes leaks traceable.
- **Demo content** (`DemoContentSeeder`) is seeded only in tests, or locally with `SEED_DEMO_CONTENT=true`; the owner removed it on 2026-11-11.
- **Brand:** the name comes from `APP_NAME`. Vue reads it from `resources/js/lib/brand.ts`. Design tokens are in README.md.

## Working conventions

- Read the relevant code before editing it. Keep changes scoped to the request.
- Follow existing patterns: Form Requests for validation, policies for resource access, Inertia pages under `resources/js/pages`.
- For UI, reuse starter-kit and shadcn-vue components before adding new ones.
- Keep Filament resources consistent with the existing Exam and Category resources.
- When a change touches admin content flows, check [docs/CONTENT_WORKFLOW.md](docs/CONTENT_WORKFLOW.md), which describes the admin-to-student flow.
