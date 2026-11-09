# Historical review of the Flutter agent's real-examination integration document

**Superseded:** the implemented handoff is [FLUTTER_REAL_EXAM_API.md](FLUTTER_REAL_EXAM_API.md). The findings below describe the API before those changes; do not use them as the current contract.

This compares the supplied **Live examination API integration** document with the current Laravel source. It is a verified source-code comparison, not an end-to-end check of the LAN servers or Flutter implementation. The document's proposed backend adjustments are requirements to agree and implement, not evidence that those features already exist.

Prioritize the real examination lifecycle before enabling optional live-hall telemetry. Keep practice separate and preserve grading, historical results and printing.

## Compatibility findings

| Area | Current Laravel behavior | Integration consequence |
| --- | --- | --- |
| Login | `POST /api/student-login` accepts `reg_no`. Success includes `type: start`, `student`, `attempt_id`, plus `hall_token` and `heartbeat_interval_seconds`. Business errors can return HTTP 200 with `type: error`. | The Flutter parser must check `type`, not HTTP status alone. Persist the attempt details. Monitoring fields are additive and may be ignored until that feature is integrated. |
| Existing attempt | Login returns `You have already taken this exam.` for **any** matching attempt, including an unfinished attempt. | There is no server-backed resume or completed-attempt confirmation through login yet. Do not claim restart/recovery is complete. |
| Paper loading | Returns only `questions` and `singleExam`. Selects up to 60 questions randomly and randomizes options on every request. `started_at` is initialized once. Correct-answer flags are hidden. | The example `remaining_seconds` and saved `answers` fields are **not implemented**. Do not reload the paper expecting identical allocation/order. |
| Timer | Paper start/end windows control initial login. Load/save/submit do not enforce an attempt deadline. | `singleExam.duration` alone cannot establish an authoritative recoverable deadline. The admission-window/full-personal-duration rule is confirmed below; late-delivery handling remains to be agreed. |
| Save | Validates paper membership and option/question pairing; locks the attempt; compares saved answers; inserts new answers in bulk and updates changed existing rows. Returns HTTP 200, `{"status":"Score synchronized"}`. | Support this exact acknowledgement in the Flutter parser. A missing or malformed acknowledgement is not evidence of a durable save. |
| Empty save | `answers` uses Laravel's `required` validation rule, which rejects an empty map/array. | A client that always waits for an empty final save before submission can get stuck with HTTP 422. Either skip that unnecessary save or coordinate changing the endpoint to accept an explicitly present empty map. A missing `answers` field should still be invalid. |
| Final submission | Optional `answers` are validated and committed with completion under the same attempt lock. Existing attempt-ID-only submission remains accepted. Response is HTTP 200, `{"status":"Submitted successfully"}`. | Sending a final answer snapshot is already supported. A separate final save is not required for atomicity, though retaining it is compatible for nonempty maps. |
| `submission_id` | Not validated, stored or used. Extra input is ignored. | Persisting a client UUID does not currently provide server-side request-ID idempotency. Do not claim a receipt can be retrieved by that ID. |
| Submission retries | Once completed, submission returns success without changing answers, marks or submission time, regardless of any supplied `submission_id` or changed answer snapshot. | Current idempotency is **per attempt**, not per submission ID. An acknowledgement for a completed attempt does not mean a newly supplied conflicting snapshot was applied. |
| Saves after completion | Every save returns HTTP 409, including an identical snapshot. | The Flutter document's requested “unchanged completed save succeeds” behavior is not implemented. A 409 must enter completed-attempt reconciliation rather than an endless final-save retry loop. |
| Answer clearing | Explicit `null` clears an option and its correctness flag. Omitted questions are unchanged. | The draft's statement that the current API has no clear contract is outdated. Flutter can still keep clearing disabled initially; if enabled, send explicit nulls and preserve them in final snapshots. |
| Zero-answer result | Submission with empty answers can complete, but this code path does not create a zero-score report row when no earlier save created one. | Check the school's required representation of an unanswered paper in reports/GPA/carry-over calculations. Do not assume “no report row” is equivalent to a recorded zero everywhere. |
| Unique answer key | No `(attempt_id, question_id)` unique constraint exists. Current API writes are serialized on the attempt. | Audit existing duplicates before adding an index. Do not automatically deduplicate historical answers or replace model behavior with bulk upserts without review. |
| Ownership | Login is registration-number-only. Load/save/submit routes have no student session/token enforcement. The new hall token protects **only telemetry**. | Neither an attempt ID nor department/set proves ownership. A coordinated authentication contract remains necessary; do not silently treat the hall token as an exam API credential. |
| Sweep | Existing `sweep-hall` / `SeepExamHallEvent` names are preserved. Sweep deletes hall rows and invalidates monitoring tokens. | Reuse the exact event spelling. Keep unanswered/pending-submission drafts until the exam's recovery policy allows deletion; do not interpret a sweep notification as proof of submission. |
| Host and Reverb settings | Client-provided LAN IPs/ports have not been tested here. | Treat the listed debug/release hosts as environment configuration, not a verified deployment. Keep `REVERB_APP_SECRET` out of Flutter. |

## Exact current request examples

Use `Accept: application/json` on every API request, and `Content-Type: application/json` for JSON bodies. Framework validation, not-found and conflict errors are then returned in JSON through normal Laravel error handling. Client/server/proxy failures must not be parsed as successful acknowledgements.

Save a selected option:

```http
POST /api/save-answers
```

```json
{"attempt_id":123,"answers":{"101":902}}
```

Successful save:

```json
{"status":"Score synchronized"}
```

Final submission, including an explicit cleared answer:

```http
POST /api/submit-exam
```

```json
{"attempt_id":123,"answers":{"101":902,"102":null}}
```

Successful submission or retry of an already completed attempt:

```json
{"status":"Submitted successfully"}
```

For a paper with no selections, `submit-exam` accepts an explicitly empty map:

```json
{"attempt_id":123,"answers":{}}
```

Do not send the empty map to `save-answers` and assume it succeeds under the current validation rules. The missing zero-score report-row issue must also be resolved before relying on zero-answer completion in production.

## Corrections to send back to the Flutter agent now

1. Replace hypothetical save/submit success examples with the actual JSON acknowledgements above, while keeping strict checks for HTTP errors and business failures. Verify the actual parser; the client source was not supplied in this review.
2. Mark `remaining_seconds`, `expires_at`, `server_now`, resume answers and persistent allocation as **pending backend work**, not fields guaranteed by today's API.
3. Mark `submission_id` as currently ignored. Keep the local identifier if useful for the journal, but do not claim the server persists or enforces it.
4. Prevent an empty final-save request from blocking submission. Preserve final answers until submission is acknowledged; never show completion for an autosave or telemetry acknowledgement.
5. Handle completed-attempt save conflicts without an infinite retry loop. A reliable reconciliation/status contract still needs backend work.
6. Keep local question order and option IDs for the session; do not reload or merge a different random paper as if it were a server-resumed attempt.
7. Update clearing documentation: explicit null is supported; omission means unchanged. Keeping the clear UI disabled for the initial release is a client product choice.
8. Do not add live hall reporting as a dependency of answer saving or submission. The separate [Flutter live-hall guide](FLUTTER_LIVE_EXAM_HALL.md) covers optional telemetry.

## Confirmed timing rule

The school admits students in batches for the exam's configured set. The scheduled start/end times define the admission window, not a shared completion deadline. Staff configure each paper's duration in minutes.

For an admission window of 08:00–12:00 and a duration of 40 minutes:

- A student who first starts the paper at 08:10 finishes at 08:50.
- A student who first starts the paper at 10:30 finishes at 11:10.
- A student admitted before noon who starts the paper at 11:50 gets the full duration, ending at 12:30.

Use the server-recorded first paper start plus the assigned duration to establish the attempt deadline. Do not cap that deadline at the admission window's end. Admission eligibility must continue to match the student's department, set, level and assigned paper. Define the exact boundary consistently so new logins are not accepted at or after the scheduled end.

The implementation should persist the attempt deadline when the paper first starts, retain it on reload/resume, and return authoritative remaining seconds, including zero. Changing a paper's duration afterward must not silently reset existing students' clocks. A restart, switching PCs or deleting local preferences must not grant another 40 minutes.

This section records the agreed rule; the current API still does not return or enforce this server deadline. Starting the clock on first paper load follows the existing `started_at` lifecycle. A separate admission/credentials response must not itself consume the student's answering time.

## Remaining decisions before changing the real-exam lifecycle

These affect exam rules and cannot be inferred safely from client consumers:

- **Late delivery:** what grace period, if any, can accept answers after the deadline? A client timestamp or “frozen at expiry” flag is not proof that an answer was selected earlier. Define how unsynchronized answers are treated.
- **Resume authorization:** what credential proves the student is resuming the same attempt, including on another PC? Registration-number-only login cannot establish identity.
- **Conflicting submissions:** should a changed snapshot/new submission ID after completion return the existing receipt, or an explicit conflict? Define the exact response and client recovery behavior.
- **Zero-answer papers:** confirm how a recorded zero must appear in reports and carry-over calculations, preserving the existing school's grading rules.

## Backend work after those decisions

Implement and test in small steps:

1. Settle exact save/final-submission acknowledgements and zero-answer behavior; verify the Flutter parser against them.
2. Add the agreed attempt credentials and status/reconciliation response without breaking the controlled legacy-client rollout.
3. Persist question allocation/order and server timing for each attempt. Resume must return the same question IDs/order, current saved answers, and an authoritative non-resetting deadline, including zero remaining time.
4. If submission-ID receipts are adopted, persist the ID and define the retry/conflict rules inside the existing attempt lock/transaction. Keep final answers and completion atomic.
5. Audit duplicates before a unique answer index; optimize only with grading/model/report compatibility preserved.
6. Run staging end-to-end recovery and deadline tests with dedicated students, compare reports/printing, then load-test realistic 150-client behavior on the actual server. No real student attempt was created or submitted in this review.

The live hall is useful supervision, but it does not replace these real-exam correctness requirements.
