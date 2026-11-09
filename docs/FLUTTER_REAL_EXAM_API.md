# Flutter real-examination API: implemented contract

This is the handoff document for the Flutter agent. It supersedes `FLUTTER_REAL_EXAM_API_REVIEW.md` and the earlier contract inferred from Tauri. The Laravel implementation and feature tests are in this repository. Deployment, migration and LAN end-to-end acceptance still need to happen before a real exam.

## Timing rule agreed with the school

- Staff configure a batch **admission window**, such as 08:00–12:00, and a positive whole-number **duration in minutes**, such as 40.
- New login is allowed from the scheduled start, inclusive, until the scheduled end, exclusive. Eligibility uses the student's department, set, level and assigned paper.
- Admission/login does not start the answering clock. First successful `load-questions` starts it on the server and persists `expires_at = started_at + duration`.
- A student starting at 11:50 receives the full 40 minutes, until 12:30. The admission end does not shorten an admitted student's personal time.
- Reload/resume does not reset the clock. Subsequent staff edits to the duration do not move an already-started attempt's deadline.
- At personal expiry, freeze editing and ordinary autosaves. The server allows **30 additional seconds for delivery of frozen final answers**, not additional answering time.
- The server accepts a changed final snapshot through `final_delivery_deadline`, inclusive, using server time captured when the endpoint begins handling the request (before waiting on its attempt lock). After that, it rejects changed answers; previously saved answers can still be finalized explicitly.
- A client timestamp cannot prove when an answer was selected. This policy permits delivery within the grace period but does not cryptographically prove that the answers were frozen at expiry.

## Base URL, transport and compatibility

Use the deployment's configured API base ending in `/api/`. The LAN hosts/ports in the Flutter agent's earlier document have not been verified by this change. Send `Accept: application/json` on every request and `Content-Type: application/json` for JSON bodies. Laravel's API exceptions now render JSON even when Accept is omitted.

**Flutter must opt in with `client: "flutter"`.** Omitting it retains legacy registration-only enrollment, which does not provide the protected resume/status contract. Do not silently fall back to legacy mode if the server is not upgraded.

For Flutter attempts, send `Authorization: Bearer <attempt_token>` on load, save, submit, resume and status requests. Changing request fields to `client: legacy` cannot bypass protection on an existing protected attempt.

Two credentials have separate purposes:

| Credential | Purpose |
| --- | --- |
| `attempt_token` | Exam paper, answers, resume/status and submission for one Flutter attempt. |
| `hall_token` | Only live-hall heartbeat/progress reporting. It is not an exam API token. |

Never put these credentials in URLs or logs. Keep them in platform-appropriate secure storage. The original registration-only identification at first admission still does not prove the person's identity; this change protects the resulting attempt and its recovery credentials. Preserve the school's identity-verification process. Legacy attempts remain less protected during the controlled rollout.

## 1. Enroll, including recovery from a lost login response

Before the first request, generate and durably store a **cryptographically random UUID v4 `login_id`**, scoped to this enrollment/server/student. Treat it as a secret recovery credential, not an analytics or tracing ID. Do not log it. Use a fresh ID for the next examination after the current session has been safely completed and archived.

```http
POST /api/student-login
```

```json
{
  "reg_no": "CH/2026/001",
  "client": "flutter",
  "login_id": "03f10ea9-2d03-4690-95b1-71717da7f9cb"
}
```

Successful response, abbreviated:

```json
{
  "type": "start",
  "student": {"id": 17, "department_id": 3, "set": "2026/2027", "first_name": "Ada", "last_name": "Student", "photo": "students/photo.jpg"},
  "attempt_id": 123,
  "attempt_token": "<opaque attempt credential>",
  "attempt_status": "in_progress",
  "hall_token": "<separate monitoring credential>",
  "heartbeat_interval_seconds": 20,
  "started_at": null,
  "expires_at": null,
  "remaining_seconds": null,
  "server_now": "2026-11-09T10:50:00.000000Z",
  "final_delivery_grace_seconds": 30,
  "final_delivery_deadline": null,
  "receipt": null
}
```

Persist the returned attempt credentials before continuing. Resolve the relative student `photo` path against the server's `/storage/` location; handle null/missing photos.

If the server created the attempt but the response was lost, retry **the same registration number, `client: flutter`, and original `login_id`**. This recovers the same attempt/token, including after admission closes. UUID casing is normalized. A different login ID does not grant another attempt for the same paper.

Login recovery for a completed attempt still returns `type: start` for compatibility, but `attempt_status` is `completed`, `receipt` is populated, and `hall_token` is null. **Check the attempt status before opening the paper.** Never create a fresh attempt because a submission acknowledgement was lost.

Business rejection can retain HTTP 200 with `type: error` and a readable `message`. Check both HTTP status and `type`. Invalid fields return HTTP 422 JSON validation errors.

The server derives recovery credentials using its application key. Do not rotate `APP_KEY` during an examination. If the original login ID and attempt token are both lost, registration number alone cannot recover the protected attempt; contact staff. There is no insecure token-reset endpoint.

## 2. Start/load the paper exactly once logically

```http
GET /api/load-questions/{department_id}/{set_with_slashes_replaced_by_hyphens}/{attempt_id}
Authorization: Bearer <attempt_token>
```

For set `2026/2027`, send `2026-2027`. The department/set must match the attempt's assigned paper; changing the URL cannot select another paper.

Response shape:

```json
{
  "questions": [{"id":101,"question_text":"Question text","marks":2,"options":[{"id":901,"option_text":"First"},{"id":902,"option_text":"Second"}]}],
  "singleExam": {"duration":40,"course":{"name":"Anatomy","code":"ANA101"},"exam":{"title":"Semester examination","subtitle":"2026/2027"}},
  "attempt_id":123,
  "attempt_status":"in_progress",
  "answers":{},
  "server_now":"2026-11-09T10:50:00.000000Z",
  "started_at":"2026-11-09T10:50:00.000000Z",
  "expires_at":"2026-11-09T11:30:00.000000Z",
  "remaining_seconds":2400,
  "final_delivery_grace_seconds":30,
  "final_delivery_deadline":"2026-11-09T11:30:30.000000Z"
}
```

Examples omit other existing model fields. The backend allocates up to 60 questions and persists the **question IDs and option order** on first load. Subsequent loads/resume return that same order. Correct-answer flags are excluded. Saved answers are an object keyed by question ID, with option IDs or explicit nulls.

Use the returned list order directly. Do not shuffle it again in Flutter. Always send actual database question/option IDs, never array indices or option letters.

The first load establishes the timer; retries retain it. A completed attempt returns HTTP 409 from this route: use status/resume to recover its receipt. An older started attempt without saved allocation returns `LEGACY_ATTEMPT_NOT_RESUMABLE`; staff intervention is required rather than silently selecting a new random paper.

Question/option **order** is frozen, not all question content or grading metadata. Staff must not edit live papers. If an allocated question/option has been removed, load/resume fails with `EXAM_PAPER_CHANGED`; it does not silently replace the question.

## 3. Render the server countdown

`remaining_seconds` is an integer, including **zero**. It is null only before the first start. Never treat zero as a missing value and restore the original duration. Completed status responses report zero remaining time.

Use `expires_at` and `server_now` to establish an offset, then a monotonic local elapsed-time source for display. Reconcile on foreground resume or recovery. Do not rely on the device wall clock, recreate the deadline from `duration`, or grant more time after clearing preferences or changing PCs.

At zero, freeze the final answer map and its submission UUID. Stop ordinary autosaves. Submit that immutable snapshot promptly; keep the original answering deadline separate from the 30-second delivery cutoff.

## 4. Autosave, including empty maps and clears

```http
POST /api/save-answers
Authorization: Bearer <attempt_token>
```

```json
{"attempt_id":123,"answers":{"101":902,"102":null}}
```

```json
{"success":true,"status":"Score synchronized","completed":false}
```

An explicitly present empty object is accepted; a missing `answers` field is invalid. Omitted questions are unchanged. Explicit null clears a previously selected option. The endpoint validates membership in the attempt's allocation and validates option/question relationships. Success means the transaction committed.

Saves are permitted before `expires_at`. At or after expiry they return HTTP 409 `ATTEMPT_EXPIRED`; switch to final submission rather than endlessly retrying autosave. The grace period is for submission, not ordinary answer editing/saving.

After completion, a save containing only values already present on the server returns success with `completed: true`. A changed value returns HTTP 409 `ATTEMPT_COMPLETED`. `completed: true` is a reconciliation signal: retrieve the receipt rather than treating it as a new submission acknowledgement.

Keep answer saves serialized and coalesce newer edits while a request is in flight. There is no per-answer revision protocol or multi-PC merge service; do not run one attempt concurrently on multiple devices. After timeouts, reconcile server state and the durable local journal; do not replay a backlog of older snapshots over newer choices.

## 5. Final submission and receipt

Generate and durably store a submission UUID with the **frozen final answer payload** before sending. Include every selected answer and explicit nulls for cleared prior selections. Missing questions remain unchanged; an empty map does not erase earlier saves.

```http
POST /api/submit-exam
Authorization: Bearer <attempt_token>
```

```json
{
  "attempt_id":123,
  "submission_id":"b56cbd18-9960-4ed5-a866-1ec73ac394c3",
  "answers":{"101":902,"102":null}
}
```

Flutter submissions require `submission_id` and the final `answers` field, even if empty, unless using the explicit saved-only recovery mode below. Final answers, marks and completion commit under the same attempt lock. A separate successful final autosave is **not** required for atomicity. If the existing Flutter flow retains a final save, handle expiry/completion responses and do not let that save block submission forever.

Success:

```json
{
  "success":true,
  "status":"Submitted successfully",
  "attempt_id":123,
  "attempt_status":"completed",
  "submission_id":"b56cbd18-9960-4ed5-a866-1ec73ac394c3",
  "submitted_at":"2026-11-09T11:30:15.000000Z"
}
```

Retry the same UUID and frozen payload after a timeout or crash. Key ordering and integer/string option-ID normalization do not change the payload fingerprint. The server returns the original receipt without reapplying answers, recalculating marks or changing completion time. This retry remains valid after the delivery cutoff.

A different UUID or changed snapshot on an already completed attempt returns HTTP 409 `SUBMISSION_CONFLICT`. Retrieve the original receipt with status. Never overwrite completed work or show that a conflicting snapshot was applied.

An unanswered paper can submit `answers: {}` and receives a recorded zero-score report row. Existing grading formulas, report relationships and layouts are preserved.

### After the final-delivery cutoff

If the final snapshot would change saved answers after `expires_at + 30 seconds`, the server returns HTTP 409 `FINAL_DELIVERY_EXPIRED` without changing answers or completing the attempt. Do not silently discard local unsent answers or present a successful submission.

Retrieve server status and present a clear reconciliation/staff-help state. To intentionally finalize **only the answers already durably saved on the server**, use:

```json
{
  "attempt_id":123,
  "submission_id":"b56cbd18-9960-4ed5-a866-1ec73ac394c3",
  "submit_saved_answers":true
}
```

Omit `answers` in this mode. Use it only after the user/staff explicitly accepts the saved-only result. Preserve unsent local work for investigation. The mode is persisted in the request fingerprint: retries must keep `submit_saved_answers: true` and the same UUID.

An unchanged answer payload may also complete after the cutoff because it introduces no late answer changes. Expiry itself does not automatically mark an attempt completed; submission/finalization still needs acknowledgement.

## 6. Status and resume

### Read status without starting or resetting the exam

```http
GET /api/attempt-status/123
Authorization: Bearer <attempt_token>
```

Returns `success`, `attempt_id`, `attempt_status`, saved `answers`, all timing fields and `receipt` (null until completed). It never starts a timer. A completed attempt returns its receipt even after admission closes or the personal deadline passes. In-progress status with zero time means answering has expired but submission has not yet been acknowledged.

### Recover the full session

```http
POST /api/resume-exam
Authorization: Bearer <attempt_token>
```

```json
{"attempt_id":123}
```

Returns the status payload plus `student`, `singleExam`, `questions`, `hall_token` and `heartbeat_interval_seconds`. Started attempts retain the saved order and deadline. Before first paper load, questions are empty and timing remains null: call load-questions when ready. Completed attempts return an empty question list, null hall token and the receipt; do not reopen editing.

Login recovery and resume rotate the hall token for an open attempt; replace the stored monitoring credential. The exam attempt token stays the same. Hall heartbeat sequences do not reset on token rotation: retain your local sequence, and advance above the server's `last_sequence` if a heartbeat reports an older/duplicate sequence.

Both status/resume require an attempt token and do not expose legacy unprotected attempts. Saved answers are authoritative for acknowledged server state. Merge only known unsynchronized local edits from the same server/student/attempt journal, never overwrite server state blindly, and preserve explicit clears.

## Errors and Flutter behavior

| Response | Meaning/action |
| --- | --- |
| HTTP 200 + login `type: error` | Admission/business rejection; show the readable message. |
| HTTP 401 `INVALID_ATTEMPT_TOKEN` | Missing/wrong credential; do not create a new attempt to work around it. |
| HTTP 404 | Unknown attempt or URL scope mismatch. |
| HTTP 409 `ATTEMPT_NOT_STARTED` | Load the paper before saving/submitting. |
| HTTP 409 `ATTEMPT_EXPIRED` | Freeze editing; submit the final snapshot within the delivery grace period. |
| HTTP 409 `FINAL_DELIVERY_EXPIRED` | Changed late answers were not applied. Reconcile; use saved-only completion only explicitly. |
| HTTP 409 `SUBMISSION_CONFLICT` | Retrieve the existing receipt; do not claim the conflicting snapshot was accepted. |
| HTTP 409 `ATTEMPT_COMPLETED` | Changed save refused. Read the receipt/status. |
| HTTP 409 `EXAM_PAPER_CHANGED` / `LEGACY_ATTEMPT_NOT_RESUMABLE` | Contact staff; do not silently shuffle/restart. |
| HTTP 422 | Invalid fields, missing UUID/final answers, or question/option outside allocation. Fix the payload. |
| Timeout/network/HTTP 5xx | Keep the frozen journal and credentials; reconcile/retry without false completion. |

Do not clear drafts merely because a timer expires, a heartbeat succeeds, or a hall command arrives. Keep submitted evidence until the completion receipt is durable locally and the school's cleanup policy allows deletion. Do not embed the Reverb app secret. Optional monitoring is documented in [FLUTTER_LIVE_EXAM_HALL.md](FLUTTER_LIVE_EXAM_HALL.md); telemetry must not block answers/submission.

## Deployment and acceptance

1. Deploy between exams, after backup and a restored staging rehearsal. Apply `2026_11_09_000319_add_session_contract_to_attempts_table.php` before serving the upgraded API. Do not upgrade a running paper in place: old started attempts have no recoverable allocation.
2. The migration adds nullable deadline, allocation, credential and receipt fields. It does not rewrite historical answers or marks. A unique `(student_id, login_key_hash)` index protects login-recovery keys; existing legacy null hashes remain nullable on the SQLite/MySQL engines targeted by this application.
3. No unique answer index or historical deduplication was added without auditing the deployed data. Current API saves/submissions use the same attempt row lock; concurrent login admission locks the student row.
4. Refresh normal application caches/restart long-lived workers. Keep Reverb/queue setup as documented separately. No Flutter dependencies or source were changed in this Laravel workspace.
5. Run staging with dedicated students for end-of-window admission/full duration, reload/restart, lost login response, saved-answer recovery, zero-answer finalization, exact expiry and 30-second boundary, late recovery, duplicate/conflicting receipts, wrong tokens, stale autosaves after completion, live-hall updates, grading and printed reports.
6. Run race tests on the deployment database engine, not just SQLite: login/login, first load/first load, save/submit and duplicate submit. Load-test the combined workload for 150 clients on the actual server; local feature tests are not a capacity guarantee.

Rollback requires coordinating the Flutter rollout, restoring the previous application code and rebuilding caches. Leave the additive fields intact during an application rollback. Rolling the migration down destroys stored credentials, paper order, deadlines and receipts and cannot reconstruct that state. No live database migration or LAN exam was performed while implementing this contract.

## Local verification for this handoff

- Full Laravel suite: 58 tests pass with 322 assertions, including 21 new real-exam session cases.
- Existing exam-client and live-hall tests continue to pass.
- Route caching and API route discovery pass; generated route cache was cleared after checking.
- Pint formatting and diff whitespace checks pass.
- Static analysis has no new findings in the changed API/session code; nine existing findings remain in dashboard/report/level-service code.
- Tests use the isolated SQLite test database. No live exam or production migration was performed. The deployment engine's race behavior, Flutter client and LAN/Reverb end-to-end flow still require staging acceptance.
