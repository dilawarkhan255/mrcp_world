# Admin content uploads and student practice

The application uses Laravel 12 for validation, permissions, imports, data and scoring, with Vue 3 + TypeScript + Inertia for the student interface. The existing Filament v3 admin panel runs inside Laravel.

## Qbanks and exams

A **Qbank** is the product a student subscribes to. Each Qbank groups exams:

| Qbank | Exams | Question bank |
| --- | --- | --- |
| MRCPWorld 1 Qbank | MRCP 1 UK/Irish, IMM | IMM reads the MRCP 1 UK/Irish bank |
| MRCPWorld 2 Qbank | MRCP 2 UK/Irish, FCPS Part 2 | FCPS Part 2 reads the MRCP 2 UK/Irish bank |

## Subscriptions

Each Qbank is sold on its own: **£8 for 6 months** (plans are in **Users and billing → Plans**). Payment is manual until a payment gateway is added:

1. The student opens **Subscription**, picks the Qbank, pays through the method the team gave them and sends the payment reference. The text above the form comes from `SUBSCRIPTION_PAYMENT_INSTRUCTIONS` in `.env` (put your real payment details there; none are invented in the code).
2. The request appears under **Users and billing → Subscriptions** with a badge. Check the payment, then **Activate** (180 days by default, editable). Activating while an earlier subscription is still running starts the new period when the old one ends, so no days are lost.
3. **Deactivate** removes access at once. **Grant access** creates an active subscription directly, for example for a free trial.

Without an active subscription a student sees a "free demo mode" banner and lock icons in the sidebar. They can practise only the questions marked **Free demo question** in the MCQ form. Past papers, mocks, search, reviews, notes, comments, the textbook and the Image Atlas send them to the subscription page; so does a session with non-demo questions after a subscription ends. Editors and admins always have access. One subscription to MRCPWorld 1 Qbank opens both MRCP 1 UK/Irish and IMM.

## Extended textbook

Open **Content → Textbook** and add chapters to an exam (the exam that owns the bank, never IMM or FCPS) and optionally a subject. The chapter body and key points are Markdown with tables and images (put flowcharts and diagrams in as images; files go to `storage/app/public/textbook-media`). Chapters are listed by subject and in **Order in subject**; students reach them from **Textbooks** with previous/next links. Only published chapters are shown, and only to subscribers.

An exam either owns its subjects, papers and questions, or uses another exam's bank (**Exams → Uses the question bank of**). A shared-content exam cannot hold subjects, papers or imports of its own; the admin pickers list only exams that own content. When a student picks IMM in the sidebar, they see the MRCP 1 UK/Irish questions under the IMM name, and their sessions, reviews, bookmarks and performance are stored against the MRCP 1 UK/Irish bank, so switching between the two keeps one history. Subscriptions are tied to the Qbank, so they cover every exam in it.

## Add your content

1. Log in as admin@example.test or editor@example.test using the local seed password in README.md. Open /admin.
2. Open **Categories**. Create categories under the correct exam; note each category slug.
3. For past-paper MCQs, open **Past papers**, create a paper with its exam/year/title/slug, and publish it when ready.
4. To enter individual MCQs, open **Questions → Create**. Choose exam, category and optional paper; provide a unique code, question, 2–5 options (A–E), correct option and explanation. Save as a draft or publish immediately. Edit and Archive are available in the question table.
5. For bulk content, open **Bulk MCQ uploads**, download the CSV template and fill it. CSV must use UTF-8 and quote any values containing commas or newlines. XLSX uses the first sheet and the same header row. Legacy .xls files are not supported.
6. Click **Upload MCQs**, choose the exam and optionally the past paper, select CSV/XLSX, and choose whether to publish imported questions. Submission returns immediately; the queue worker processes the private file.
7. Watch status, accepted/rejected counts, and **Report**. The report retains the first 100 row errors. A completed import can still have rejected rows; fix and reupload those rows.

Only published MCQs are visible to students. MCQs belonging to a paper also require that paper to be published. A paper is an organised set of MCQs; uploading a PDF does not automatically create questions.

### File headers

| Header | Required | Meaning |
| --- | --- | --- |
| code | Yes | Stable question reference, unique within an exam; letters, digits, dot, underscore and dash, maximum 100 characters |
| category_slug | Yes | Existing category slug in the selected exam |
| question | Yes | Question text, maximum 20,000 characters |
| option_a, option_b | Yes | First two answer choices, maximum 4,000 characters each |
| option_c, option_d, option_e | No | Up to three additional choices, maximum 4,000 characters each |
| correct_option | Yes | A, B, C, D or E; must refer to a populated option |
| explanation | No | Explanation (Markdown), maximum 20,000 characters |
| key_points | No | Key points (Markdown), maximum 8,000 characters |
| mnemonics | No | Mnemonic (Markdown), maximum 4,000 characters |
| system | No | Body system label shown on the question, e.g. Cardiovascular |
| repeat_count | No | Times the topic appeared in past exams (0–999) |
| source_reference | No | Source label shown on the question, e.g. Sept 2023 recall |
| nice_reference, nice_url | No | NICE citation text and link |
| who_reference, who_url | No | WHO citation text and link |
| other_reference, other_url | No | Any other guideline (BNF, BTS, ESC…) |
| youtube_url | No | A YouTube watch, share or embed link; other links reject the row |
| image_links | No | Searchable image links: `label \| url` pairs separated by `;` or new lines |
| image_search | No | Live image search term, e.g. `erythema nodosum` |
| image_search_sites | No | Sites for the search: any of `radiopaedia`, `dermnet`, `litfl`, `eyewiki` separated by commas; empty means all four |
| needs_review | No | `yes`/`1` flags the MCQ for human review |
| review_note | No | What the reviewer must check |

Unknown headers fail the upload, so a misspelt column is never silently ignored.

**Citations.** Write the citation exactly as published. Nothing in the application generates, completes or corrects a citation. A row whose reference link is not a valid http(s) address keeps the citation text, drops the link and is flagged for review with a note. Flagged MCQs show a badge in the admin menu, a card on the dashboard and a **Flagged for review** filter; **Mark reviewed** clears the flag after a person checks it.

### Rich content

Question stems, explanations, key points and mnemonics are Markdown. Use the editor toolbar for tables and to attach images (stored on the public disk under `storage/app/public/question-media`; run `php artisan storage:link` once). Raw HTML is stripped and `javascript:`/`data:` links are removed when the content is rendered, so pasted HTML never runs in a student's browser. Plain text from older imports still displays correctly.

A matching exam + code updates the existing question rather than creating a duplicate. This also restores an archived question; check the publish switch before uploading. Accepted counts refer to rows, not distinct question codes. Existing practice sessions keep snapshots of the original question/options/answer/explanation, so later edits do not change an old result.

## Queue worker

Run from the project folder:

~~~powershell
php artisan queue:listen --queue=imports,default --timeout=1200 --tries=3 --memory=256
~~~

On Windows, queue:listen launches a fresh worker process for each job and enforces the child-process timeout without requiring pcntl. composer dev starts this listener with the imports queue. DB_QUEUE_RETRY_AFTER=1260 is intentionally longer than the 1200-second import timeout, so a still-running job is not offered to another worker. If switching to Redis, set REDIS_QUEUE_RETRY_AFTER=1260 as well. Restart workers after changing code/config: php artisan queue:restart.

A pending upload means it is waiting for a worker. Files are stored privately under storage/app/private/question-imports. Their paths are not public URLs. Keep this disk under operational retention/storage monitoring when deploying.

Validation failures show a readable failed status. Transient infrastructure failures retry up to three times, then appear in failed_jobs; inspect with php artisan queue:failed. Earlier committed batches remain saved. The checkpoint is committed in the same transaction as each upsert batch, making retries resumable without double-counting.

## Student flow

- Choose the exam in the sidebar.
- **Question bank** lists categories and published question counts, with mixed practice or category practice.
- **Past papers** lists published papers; start a session from a paper.
- Select 1–100 questions per session. If fewer match, the session contains the available questions. Session creation uses an indexed random starting point with wraparound, not a full-table random sort; it is not a statistically uniform sample.
- Choose one answer and submit. The server saves and scores it, then returns the correct option and explanation. These answers are absent from the unanswered question response.
- **Previous sessions** lets the student resume unfinished sessions or review completed answers. Only submitted choices are saved. An answer cannot be changed after submission. Repeated identical submissions do not increment the score twice.
- **Mock exams** are timed papers from the same published bank. A preset or custom question count and duration sets a server-side deadline; the countdown in the browser only mirrors it. Answers submitted after the deadline are rejected, the mock closes on the next request, and unanswered questions stay unanswered and unscored. Correct answers are hidden until the mock is closed, so a mock behaves like an exam rather than a practice set.
- **Review questions** lists the questions whose most recent saved answer was wrong, with the chosen answer, the correct answer and the explanation. A question leaves the queue as soon as it is answered correctly in a later session. The queue can be practised as a session of up to 50 questions.
- **Performance** reports the answered and correct totals, the accuracy, the session and mock counts, the study streak, a 14-day answer trend, the accuracy per category and the most missed questions. All figures are read from the saved answers of the selected exam.
- **Comment threads** lets any verified student ask about a published question and reply to other students. A thread is created from a question found by code or wording; only published questions of the selected exam can be discussed. Editors and admins can hide or pin a thread. A hidden thread stays visible to its author and to moderators and is removed from every other student's list.
- **Bookmarks and notes** saves a question from a practice or mock session with a personal note. One bookmark per question is kept, the note can be edited or removed, and the bookmarked questions can be practised as one session.
- Dashboard question, accuracy and session counts reflect saved practice for the selected exam, and the dashboard activity strip shows the real answers of the last 14 days.

- **Practice screen.** A question grid (up to 250 questions per session) shows answered, correct, incorrect and read questions; a mock's grid hides correctness until it closes. Students can **Mark as read**, **Clear response** before submitting, **Report MCQ** (a feedback message tied to the question, listed under Student feedback with an MCQ filter) and keep a **private note** on the page (saved with the question's bookmark). Labels for subject, system, repeats and source appear above the stem.
- **Candidate statistics.** After answering, the student sees the candidates' correct %, how candidates split across the options, the average time and the subject average. These come from `question_stats` and `category_stats`, refreshed by `php artisan stats:refresh` (scheduled every ten minutes; run `php artisan schedule:work` locally, or the command by hand). Figures appear once at least five answers exist. The answer time is measured in the browser and capped at one hour.
- **Discussion.** The comment threads of a question appear under its explanation once the student has answered it (or once the mock closes), because a discussion can give the answer away. Students post and reply there; editors and admins also hide and pin. The Comment threads page still lists every thread of the exam.
- **Live image searches.** When a question has an image search term, the answer shows search links for it on Radiopaedia, DermNet, Life in the Fast Lane and EyeWiki (or the sites the editor ticked). The search addresses are in `config/student.php` under `image_search`; change one there if a site moves its search page.
- **Leaderboard** (`/leaderboard`, subscribers). Off by default: a student opts in under **Settings → Profile** with a display name (an email address is refused), and only that name is shown. Students are ranked per exam by correct *first* answers, then accuracy, with at least 20 first answers; repeating a question cannot raise a rank. Practice answers and closed mocks count; review sessions and open mocks do not (a moving rank would reveal mock answers). **This week** starts on Monday 00:00 UK time. The top 50 are shown plus the student's own rank; the board is cached for ten minutes. An admin can remove an unsuitable name from **Users → Remove from leaderboard**.
- **AI Study Plan** (`/study-plan`, subscribers). Built with fixed rules from the student's own answers, with no AI service: the three weakest subjects (lowest accuracy with at least 10 answers, each with its reason, e.g. "62% correct across 40 answers"), the subjects not practised yet, and a 7-day plan of 30 questions a day (three subjects of 10; two weak, one new). Each item starts a main bank session in one click. The numbers are in `config/student.php` under `study_plan` and `leaderboard`.
- **Image Atlas** (`/image-atlas`) lists published images of every Qbank or of the student's Qbank, searchable by title or diagnosis and filtered by subject.

Flashcards, activity heatmaps and PDF/Word extraction remain separate future work; they are not represented as completed features.

## Image Atlas

Open **Media → Image Atlas**. **Add image** uploads one image (JPEG, PNG, WebP or GIF, up to 5 MB) with its title, diagnosis, subject, findings and copyright details. **Bulk upload** adds up to 50 images per batch as unpublished drafts titled from their file names, so a large collection (for example 2,105 images) goes in over several batches.

Images may come from other websites, so every image records its source, licence and attribution. An image can only be published when a person ticks **I confirm we have the right to use this image** and the licence is not *Unknown*; the server enforces this, and bulk **Publish** skips images that do not qualify. The menu badge counts images still waiting for a copyright check. Confirming records who confirmed and when.

## Large datasets and capacity

- Maximum 20 MB per uploaded file and 50,000 rows; split larger collections into multiple files. XLSX expanded content is capped at 100 MB / 5,000 archive entries.
- CSV is read with a file stream and XLSX with OpenSpout. Neither imports the complete workbook into an in-memory array. At most 100 rows are staged for an upsert transaction.
- Category IDs are resolved once per batch. The database enforces unique exam/question codes, foreign keys and category/paper relationships.
- Composite indexes support published questions by exam, category and paper, plus per-user session history. The admin code search is prefix-based. Student lists use simple pagination with 20 records and no total-count pagination query.
- The review queue reads the newest saved answer per question through one grouped subquery per student and exam, so a large answer history is not scanned per page.
- Performance aggregates run over the saved answers of one student and exam. Days are grouped in UTC; the MySQL connection is pinned to `+00:00` so the database, PHP and SQLite agree on the day boundary.
- Grouped queries list every selected column in the GROUP BY clause. SQLite accepts a looser form, but the MySQL/MariaDB server used here runs with `ONLY_FULL_GROUP_BY` and rejects it.
- The automated tests run on an isolated SQLite database. Check the student pages against the local MySQL database as well before a release, because the two servers do not accept the same SQL.
- Comment visibility is applied in the query, not in the browser. A thread list is one page of top-level comments for the selected exam with their replies loaded in one query.
- Session creation loads at most 100 questions; the practice response contains one question. Correct answers are scored under a session row lock.
- A mock closes on its stored deadline, not on a browser event. The deadline is checked inside the same row lock that scores an answer, so a late submission cannot slip through a concurrent request.
- Candidate statistics are recomputed per question from the answers of all students, only for questions answered since the previous run (indexed by `practice_items.answered_at`, with a five-minute overlap). The totals are recomputed rather than incremented, so a repeated or overlapping run never double counts. The practice screen reads one row per question and one per subject.
- The admin dashboard reads one aggregate per table; student activity figures are cached for five minutes.
- Imports run in a worker separate from HTTP request processing. Use supervised worker restarts, backups, disk monitoring and database connection limits when deploying. These controls reduce risk; they do not guarantee crash-free operation at arbitrary volume.

Verified locally: a 1,200-row CSV uses 12 question upserts; tests cover real upload-to-worker processing, XLSX, invalid rows, retries, published visibility, access control, saved sessions, answer secrecy and score idempotency. This is a regression check, not a production concurrency or million-row benchmark. Capacity must be load-tested against the target hosting and expected traffic.

## Choosing a plan and copy protection

- **Choosing a plan on the landing page** opens `/subscription?plan=ID`. A visitor who is not logged in is sent to the login page (with a note that the plan opens afterwards); after logging in, or registering and verifying the email, they land on the subscription page with that plan's request form open.
- **Copy protection** (`CONTENT_PROTECTION`, on by default) blocks copying, cutting, text selection, the context menu, image dragging, printing and the copy/select-all/save/print/view-source/developer-tools shortcuts on every student page and on the public demo. Typing and pasting into notes, comments and search still work. A faint watermark with the student's own email and the date (`CONTENT_WATERMARK`) covers the screen, so a shared screenshot can be traced to the account. Editors and admins are never protected. This deters copying; no website can fully stop screenshots, a phone camera or the browser's developer tools.

## Users, roles and permissions

Admins log in at `/login` (or `/admin/login`) and land in the admin panel. Under **Users and billing**:

- **Users** lists every account with roles, verification and active Qbanks. **Add user** creates an account with a password, a verified email and roles; editing changes roles, resets the password (leave it empty to keep it) or adds a single extra permission. A user added without a role becomes a student. Email addresses are masked for panel users who may not manage accounts.
- **Roles & permissions** lists the roles with their users and permissions. **New role** creates a role (for example `content-reviewer`) and ticks its permissions; changes apply at once. The permissions themselves are fixed in `config/permissions.php`, because each one guards a feature in the code.
- Safety rules: the `admin` role always has every permission; `admin`, `editor` and `student` cannot be renamed or deleted; a role with users cannot be deleted; nobody can remove their own admin role, and the last admin keeps it. Running `RoleSeeder` again keeps every change made in the panel and only adds permissions that are new.
- Anyone with at least one permission can open the admin panel, so a custom role works without code changes. Students have no permissions.

## Demo content (local only)

`php artisan db:seed --class=DemoContentSeeder` fills a local site so it does not look empty: subjects for MRCP 1 UK/Irish and MRCP 2 UK/Irish, 36 sample MCQs (main bank and a "Sample paper A (demo content)"), three textbook chapters, ten demo students with subscriptions, two weeks of practice history, leaderboard entries, comments and candidate statistics. The local student account gets an MRCPWorld 1 Qbank subscription so both the subscribed and the demo-only states can be tried. `php artisan db:seed` runs it automatically in the local environment.

- It runs only in the `local` and `testing` environments and refuses to run in production.
- Every sample MCQ is coded `SAMPLE-MP1-…` / `SAMPLE-MP2-…`, marked as sample and **flagged for review**: a clinician must check the facts and citations before real students rely on them. Only well-established NICE guideline numbers are cited; no citation was invented.
- It can run again safely: it only adds what is missing and never overwrites an edit made in the admin panel.
- Demo student accounts (`demo.student1@example.test` …) have random passwords and are not meant for logging in.

Admins move between the two sides with **Student app** in the admin sidebar (and the user menu); the student sidebar has **Admin panel** to return.
