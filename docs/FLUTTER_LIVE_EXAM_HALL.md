# Flutter integration: examination status hall (no heartbeat)

This replaces the earlier question-progress and heartbeat design. **Remove the old `ExamHallReporter`/heartbeat coordinator from Flutter.** Changing the server alone cannot stop an old client timer from making HTTP requests.

## Flutter changes to make now

1. Delete/disable the repeating hall heartbeat timer and its retries.
2. Remove all calls to `POST /api/exam-hall/heartbeat`, including calls on question navigation, foreground resume, reconnect and submission.
3. Remove telemetry sequence storage, question-position payloads, and monitoring credential storage. No hall-specific token is required.
4. Keep the exam's **attempt token**, login recovery ID, answer autosave, server countdown, resume/status recovery and final submission logic. These protect exam correctness and must continue.
5. Do not add a replacement attendance/online polling endpoint or send a new status request on every question change.
6. A successful final submission already updates the hall. Do not send a separate “I submitted” request and do not publish a client-authored status message.

Login and resume retain compatibility fields:

```json
{
  "hall_token": null,
  "heartbeat_interval_seconds": null,
  "hall_heartbeat_required": false
}
```

Never turn a null interval into a default 20-second timer. New clients should not construct the old reporter at all. This does not change the separate `attempt_token` returned for protected Flutter real examinations.

For an older client that still calls the retired endpoint, Laravel returns HTTP **410 Gone** without querying or writing the database or broadcasting events:

```json
{
  "success": false,
  "code": "HALL_HEARTBEAT_RETIRED",
  "message": "Stop hall heartbeat and question-progress requests. Hall status now updates from login and submission.",
  "heartbeat_required": false
}
```

Treat that response as permanently disabling telemetry, not as an exam failure or a reason to retry. Answer saving/submission must work independently. The compatibility endpoint is intentionally cheap, but requests from an unchanged client still incur HTTP/PHP overhead until the client timer is removed.

## What staff see

The **Live exam hall** page shows only:

- The student's stored passport/profile photograph, with initials if absent or broken.
- Student name and registration number.
- **Ongoing**: the attempt has not been submitted.
- **Submitted**: the server committed the submission successfully.

Submitted cards remain in the hall until staff sweep it. The header's counter counts ongoing attempts; the page separately totals all, ongoing and submitted students. No current question, question count, last-seen timestamp, course, department or connection-freshness data is sent in the roster.

**Ongoing is an examination status, not a network-presence guarantee.** Without heartbeats, the server cannot claim that the student's device is connected or that the student is physically at the desk. Login registers the attempt as ongoing; successful submission changes its status. Expiry alone does not prove successful submission.

## How live updates work without student monitoring HTTP

- The normal login request creates a hall entry and broadcasts a single profile/status update.
- Login recovery/resume of an already-listed student does not write a replacement hall credential or broadcast another hall update.
- The normal successful submission commits answers/results/completion, then broadcasts the submitted status.
- Idempotent submission retries and ordinary answer saves do not emit more hall updates.
- Staff receive these updates over their existing private Reverb channel and update the relevant card directly, without fetching the entire roster.
- Staff fetch an authoritative snapshot after subscribing/reconnecting, when using the manual **Refresh** button, and after a hall sweep. There is **no 30-second polling loop**.
- If WebSocket subscription is unavailable, a one-off fallback snapshot may load the list. It does not start recurring polling. Staff see a disconnected indicator and can refresh manually.

The Reverb/Pusher WebSocket protocol still has its own connection ping/pong behavior. That is different from the removed Flutter HTTP heartbeat and does not require the student app to post monitoring payloads to Laravel.

## Staff-only contract

- Page: `GET /dashboard/exam-hall`.
- Snapshot: `GET /dashboard/exam-hall/snapshot`, authenticated active staff web session required, `Cache-Control: no-store, private`.
- Private channel: `private-exam-hall.monitor` on the wire; `Echo.private('exam-hall.monitor')` in the staff app.
- Event: `App\Events\ExamHallUpdated`, listened to as `ExamHallUpdated` in Echo.

Snapshot:

```json
{
  "participants": [{
    "attempt_id": 123,
    "student": {"name": "Ada Student", "reg_no": "CH/2026/001", "photo_url": "/storage/students/photo.jpg"},
    "status": "ongoing"
  }],
  "count": 1,
  "submitted_count": 0,
  "snapshot_started_at": "2026-11-09T10:00:00.000000Z",
  "server_time": "2026-11-09T10:00:00.010000Z"
}
```

An `upsert` event contains `operation`, `attempt_id`, one `participant` in the shape above and `occurred_at` (a UTC timestamp). Its participant status is `ongoing` or `submitted`. A `reset` event tells staff to reconcile after a sweep. The staff state buffers updates arriving during a snapshot and rejects outdated events; an old ongoing message cannot reopen a submitted card. Delayed pre-snapshot events cannot resurrect swept entries.

Student profiles remain on the private staff channel. The existing public `exam-hall` / `ExamHallEvent` carries only the legacy ongoing count, not identity data. Existing public `sweep-hall` / `SeepExamHallEvent` and `close-exam-hall` / `CloseExamHallEvent` commands retain their names and previous behavior. Never embed `REVERB_APP_SECRET` in Flutter.

## Deployment and checks

- Deploy the backend and rebuilt staff assets, then release the Flutter change that removes the reporter. Stop/restart active old clients as appropriate to actually remove their timers.
- Run Reverb and the queue worker if broadcasts use an asynchronous queue. Reverb alone does not process queued Laravel broadcast jobs.
- No new schema migration or destructive cleanup is needed. The old nullable telemetry columns are left unused to avoid altering live data or coupling this simplification to a migration.
- Verify on staging: login adds ongoing; submission switches the same card to submitted; a repeated submission emits no second update; reload retains submitted cards; sweep clears the roster; reconnect/manual refresh reconciles status; private-channel access remains restricted.
- In Flutter's network inspector, leave a question open, navigate repeatedly, and background/resume the app. Confirm **zero hall heartbeat requests**. Existing answer saves and intentional exam recovery requests should still occur normally.
- In the staff browser, staying idle must not produce periodic snapshot requests. Login and submission events should update cards without a new snapshot request.
- Keep the school's normal staging/load checks for the real exam flow. Removing telemetry does not replace answer durability or submission acknowledgement.

The real examination contract remains [FLUTTER_REAL_EXAM_API.md](FLUTTER_REAL_EXAM_API.md). Forward this updated hall guide to the Flutter agent together with that contract.
