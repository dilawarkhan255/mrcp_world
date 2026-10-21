# Admin content uploads and student practice

The application uses Laravel 12 for validation, permissions, imports, data and scoring, with Vue 3 + TypeScript + Inertia for the student interface. The existing Filament v3 admin panel runs inside Laravel.

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
| explanation | No | Explanation, maximum 20,000 characters |

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
- Dashboard question, accuracy and session counts reflect saved practice for the selected exam, and the dashboard activity strip shows the real answers of the last 14 days.

Textbooks, flashcards, bookmarks, activity heatmaps and PDF/Word extraction remain separate future work; they are not represented as completed features.

## Large datasets and capacity

- Maximum 20 MB per uploaded file and 50,000 rows; split larger collections into multiple files. XLSX expanded content is capped at 100 MB / 5,000 archive entries.
- CSV is read with a file stream and XLSX with OpenSpout. Neither imports the complete workbook into an in-memory array. At most 100 rows are staged for an upsert transaction.
- Category IDs are resolved once per batch. The database enforces unique exam/question codes, foreign keys and category/paper relationships.
- Composite indexes support published questions by exam, category and paper, plus per-user session history. The admin code search is prefix-based. Student lists use simple pagination with 20 records and no total-count pagination query.
- The review queue reads the newest saved answer per question through one grouped subquery per student and exam, so a large answer history is not scanned per page.
- Performance aggregates run over the saved answers of one student and exam. Days are grouped in UTC; the MySQL connection is pinned to `+00:00` so the database, PHP and SQLite agree on the day boundary.
- Comment visibility is applied in the query, not in the browser. A thread list is one page of top-level comments for the selected exam with their replies loaded in one query.
- Session creation loads at most 100 questions; the practice response contains one question. Correct answers are scored under a session row lock.
- A mock closes on its stored deadline, not on a browser event. The deadline is checked inside the same row lock that scores an answer, so a late submission cannot slip through a concurrent request.
- Imports run in a worker separate from HTTP request processing. Use supervised worker restarts, backups, disk monitoring and database connection limits when deploying. These controls reduce risk; they do not guarantee crash-free operation at arbitrary volume.

Verified locally: a 1,200-row CSV uses 12 question upserts; tests cover real upload-to-worker processing, XLSX, invalid rows, retries, published visibility, access control, saved sessions, answer secrecy and score idempotency. This is a regression check, not a production concurrency or million-row benchmark. Capacity must be load-tested against the target hosting and expected traffic.
