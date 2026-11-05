# Exam client API optimization review

Reviewed against the supplied SERVER_OPTIMIZATION.md. This is an initial repository change, not confirmation of capacity for 150 clients. No production configuration or database was changed.

## Implemented

- `save-answers` validates the answer map, scopes questions to the attempt's paper, and validates option/question pairs in batch.
- Saves and submissions acquire the same attempt row lock within a transaction. Completed attempts reject saves with HTTP 409. Submission retries return the existing acknowledgement without changing timestamps, answers or marks.
- Existing answers are read once. Unchanged answers produce no answer/report writes or score aggregation. New answers are inserted in bulk; changed existing rows retain Eloquent updates. No answer observers are currently registered; review this if observers are added later.
- Report scores remain synchronous and use the existing sum of correct question marks. No grading formula, report layout or historical data migration was changed.
- Submission optionally accepts `answers` in the same format as autosave. Answers, marks and completion commit together. Legacy `attempt_id`-only submission remains supported.
- Explicit `null` clears an answer and its correctness flag. Omitted questions remain unchanged, including in submission: clients sending a final snapshot must include explicit nulls for cleared selections.
- Question loading uses the attempt's single exam, hides `is_correct` in returned options, rejects completed attempts, and initializes `started_at` only once. Existing random allocation and 60-question limit remain.

Example final submission:

```json
{"attempt_id": 123, "answers": {"10": 42, "11": null}}
```

Validation errors return HTTP 422; unknown attempts return HTTP 404. Successful response fields are unchanged. Clients must treat failed saves as unacknowledged and retry appropriately.

## Remaining work and constraints

- API routes currently have no student authentication, and login accepts a registration number. An attempt ID is still not proof of ownership. Coordinate authenticated student/attempt credentials with deployed clients before enabling enforcement; these changes do not solve that security gap.
- There is no persisted per-attempt question allocation. Validation checks membership in the assigned paper, not membership in the randomly returned subset. Repeated question loading still randomizes the paper. Persisting allocation needs a separate migration and client recovery review.
- Legacy submission cannot recover unsent answers. Clients using final answers must await submission acknowledgement, serialize saves, and stop sending saves once submitting. Without revisions, two open-attempt saves can still overwrite one another in lock acquisition order rather than edit order.
- No new deadline/late-submission policy was invented. Confirm the school's authoritative duration and grace-period rules before enforcing a deadline.
- Existing duplicate answer rows must be audited before adding a unique `(attempt_id, question_id)` constraint. No automatic deduplication or index migration is included. Current API writes are serialized on the attempt, but other writers must follow the same convention.
- Login concurrency, actual deployed payloads, authentication rollout, hardware, database query plans, server contention, queues, sessions, Reverb and FrankenPHP mode still need inspection. No runtime/driver changes or asynchronous marking were introduced without measurements.

## Validation and rollout

Feature tests cover unchanged saves, grading totals, final answers, clearing, duplicate submissions, saves after completion, invalid pairs, foreign-paper questions, hidden answer keys and unchanged start times. SQLite tests do not establish real concurrent row-lock behavior; test save/submission races on the deployment database engine.

Before deployment, restore a backup to a test environment, compare results and printed reports against the previous semester baseline, then run distinct-client tests at 30, 75 and 150 users including a submission burst and admin activity. Capture p95 latency, query counts, lock waits, CPU, RAM and disk latency. Avoid logging answer payloads or credentials. Do not load-test a live exam.

Rollback: deploy the prior application version. No schema changes need reversal. Coordinate rollback with clients using final answers/null clearing, since the prior implementation does not support those semantics safely.
