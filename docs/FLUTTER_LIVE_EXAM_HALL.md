# Flutter integration: live exam hall

## What this feature does

Active staff open **Exam hall → View live exam hall**, or **Live exam hall** in the sidebar. The new dashboard page shows each unsubmitted student's stored profile photo, name, registration number, department, course, last reported question and connection freshness. This is a profile picture, not a webcam feed.

Flutter sends small HTTPS heartbeat/progress requests to Laravel. Laravel persists them and broadcasts updates through Reverb to the staff dashboard. Flutter does **not** need a WebSocket connection for reporting progress and must **not** subscribe to the private staff roster.

The header count means **unsubmitted attempts registered in the hall**. It includes stale and not-yet-reporting clients. “Reporting now” is the separate count of clients whose last accepted heartbeat arrived within 60 seconds. Network connectivity is not proof that a student is physically sitting at a desk or reading the reported question.

## 1. Login and store the monitoring credential

Continue using `POST /api/student-login` with the existing registration-number payload:

```json
{"reg_no": "CH/2024/001"}
```

A successful response retains the existing fields and adds:

```json
{
  "type": "start",
  "student": {"id": 17, "first_name": "Ada", "last_name": "Student"},
  "attempt_id": 123,
  "hall_token": "<opaque 64-character token>",
  "heartbeat_interval_seconds": 20
}
```

The student object shown here is abbreviated. Only treat `type: start` as a successful exam login; existing business-error responses can still use HTTP 200 with `type: error`.

Persist `attempt_id`, `hall_token`, the latest reserved telemetry sequence, and the exam's existing local recovery state before starting the exam. Use the Flutter app's platform-appropriate secure credential storage for the token. Never log it, put it in a URL, send it over Reverb, or include it in screenshots or crash-report breadcrumbs. Laravel stores only its SHA-256 hash.

The token belongs to one attempt and only authorizes the **new monitoring endpoint**. It is not a Sanctum access token and is not a substitute for authentication on the legacy answer APIs. Registration-number-only login and the existing load/save/submit APIs retain their current authentication limitations; a broader client authentication rollout is separate work.

Existing attempts created before this migration have no monitoring token. They remain visible but untracked. This change does not add an attempt-resume/token-reissue endpoint, change the existing “already taken” rule, or permit a second attempt. Do not repeatedly call login to recover a lost token.

## 2. Send heartbeat and progress updates

```http
POST /api/exam-hall/heartbeat
Authorization: Bearer <hall_token>
Content-Type: application/json
Accept: application/json
```

After displaying a question:

```json
{
  "attempt_id": 123,
  "sequence": 42,
  "current_question_id": 901,
  "current_question_number": 12,
  "total_questions": 60
}
```

Field rules:

| Field | Meaning |
| --- | --- |
| `attempt_id` | The attempt returned by login; positive integer. |
| `sequence` | A strictly increasing telemetry revision for this attempt, 1–2147483647. It is independent of answer revisions and the displayed question number. |
| `current_question_id` | The real `id` from the displayed question returned by `load-questions`. |
| `current_question_number` | The **1-based index in the exact ordered question list Flutter is displaying**; never use a database ID as the index. |
| `total_questions` | The length of that returned list. The current server allocates at most 60 questions. |

Send all three question fields together, or omit all three. Before questions have loaded, send a presence-only heartbeat:

```json
{"attempt_id": 123, "sequence": 1}
```

Presence-only updates preserve a previously reported position. No question number is invented before the first progress report. The server checks question ownership against the attempt's paper, numeric bounds, and that the reported paper length does not exceed the paper's available questions. The displayed ordinal is client-reported; the server does not currently persist or verify the client's randomized question ordering.

Success:

```json
{
  "accepted": true,
  "last_sequence": 42,
  "server_time": "2026-11-08T12:00:00.000000Z",
  "heartbeat_interval_seconds": 20
}
```

A duplicate or older sequence returns HTTP 200 with `accepted: false` and the latest `last_sequence`. It changes **neither** progress **nor** last-seen time and emits no new update. Advance the next sequence above the server value. Do not retry an old telemetry payload forever.

### Timing and ordering

- Start reporting after successful login, including a presence-only report while the paper loads.
- Send a heartbeat about every 20 seconds even while the student stays on the same question. Add a small random initial offset so a hall of clients does not synchronize its timers.
- Report question navigation after roughly 400–750 ms of debounce. Include moving backward, jumping to a flagged question, and moving forward without selecting an answer.
- Keep **one telemetry request in flight per attempt**. While it runs, retain the latest visible position and send that next if it changed.
- Reserve and durably persist each new sequence **before sending it**, including requests that may fail. Restore it on app restart. Never derive it from wall-clock time.
- On reconnect or foreground resume, send the latest position with a new sequence. Do not replay every historic navigation step.
- Keep one active reporting coordinator for an attempt; do not run independent timers in every question widget.
- This is telemetry. It does not save answers, mark a question answered, or change the exam timer. Answer synchronization must continue independently even if monitoring fails.

## 3. Reference Dart coordinator

This helper uses the Dart team's [`http` package](https://pub.dev/packages/http). Add it in the Flutter repository if it is not already installed:

```sh
flutter pub add http
```

Adapt the storage and status callbacks to your app. `endpoint` is the complete heartbeat URI; `initialSequence` comes from durable attempt state. Create a dedicated HTTP client for this coordinator. The helper coalesces navigation changes and keeps telemetry requests serialized.

```dart
import 'dart:async';
import 'dart:convert';
import 'dart:math';
import 'package:http/http.dart' as http;

class ExamHallReporter {
  ExamHallReporter({
    required this.endpoint,
    required this.attemptId,
    required this.token,
    required int initialSequence,
    required this.persistSequence,
    required this.onStopped,
    required this.onError,
    this.interval = const Duration(seconds: 20),
  }) : _sequence = initialSequence;

  final Uri endpoint;
  final int attemptId;
  final String token;
  final Future<void> Function(int sequence) persistSequence;
  final void Function(String reason) onStopped;
  final void Function(Object error) onError;
  final Duration interval;
  http.Client _client = http.Client();
  Timer? _heartbeat;
  Timer? _navigation;
  Map<String, int> _position = {};
  int _sequence;
  bool _sending = false;
  bool _pending = false;
  bool _stopped = false;

  void start() {
    // Call once after restoring the attempt and credentials.
    if (_stopped || _heartbeat != null) return;
    _heartbeat = Timer(
      Duration(milliseconds: Random().nextInt(3000)),
      () {
        if (_stopped) return;
        unawaited(reportNow());
        _heartbeat = Timer.periodic(interval, (_) => unawaited(reportNow()));
      },
    );
  }

  void showQuestion({
    required int questionId,
    required int zeroBasedIndex,
    required int questionCount,
  }) {
    _position = {
      'current_question_id': questionId,
      'current_question_number': zeroBasedIndex + 1,
      'total_questions': questionCount,
    };
    _navigation?.cancel();
    _navigation = Timer(
      const Duration(milliseconds: 500),
      () => unawaited(reportNow()),
    );
  }

  Future<void> reportNow() async {
    if (_stopped) return;
    if (_sending) {
      _pending = true;
      return;
    }
    _sending = true;
    try {
      do {
        _pending = false;
        final sequence = ++_sequence;
        final payload = {
          'attempt_id': attemptId,
          'sequence': sequence,
          ..._position,
        };
        await persistSequence(sequence); // A failed write must prevent sending.
        if (_stopped) return;
        final response = await _client.post(
          endpoint,
          headers: {
            'Authorization': 'Bearer $token',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
          },
          body: jsonEncode(payload),
        ).timeout(const Duration(seconds: 10));
        if (_stopped) return;
        if (response.statusCode == 401 || response.statusCode == 409) {
          stop();
          onStopped(response.statusCode == 409
              ? 'The server has completed this attempt.'
              : 'Monitoring credentials were rejected; contact staff.');
          return;
        }
        if (response.statusCode != 200) {
          throw StateError('Monitoring HTTP ${response.statusCode}');
        }
        final data = jsonDecode(response.body) as Map<String, dynamic>;
        final serverSequence = data['last_sequence'] as int;
        if (serverSequence > _sequence) {
          _sequence = serverSequence;
          await persistSequence(_sequence);
        }
      } while (_pending && !_stopped);
    } catch (error) {
      if (!_stopped) {
        // Abort an HTTP operation that may still be running after a timeout.
        _client.close();
        _client = http.Client();
        onError(error); // Never include credentials in diagnostics.
      }
      // The next heartbeat sends current state with a fresh sequence.
    } finally {
      _sending = false;
    }
  }

  void stop() {
    _stopped = true;
    _heartbeat?.cancel();
    _navigation?.cancel();
    _client.close();
  }
}
```

Use it from the exam session owner, not a widget that is recreated on every question:

```dart
reporter.showQuestion(
  questionId: questions[currentIndex].id,
  zeroBasedIndex: currentIndex,
  questionCount: questions.length,
);
```

On app lifecycle resume, call `reportNow()` with the current restored position. Dispose/stop the reporter when the attempt is confirmed completed or the exam session owner is disposed. Do not log out the student or discard local answers merely because monitoring reports a network error or rejected credential. Set the helper's interval from `heartbeat_interval_seconds` returned by login.

## 4. Error handling

| HTTP/result | Flutter action |
| --- | --- |
| `200`, `accepted: true` | Continue. This acknowledges telemetry, **not answers**. |
| `200`, `accepted: false` | Keep the newer local position; advance the next sequence above `last_sequence`. |
| `401` | Stop telemetry retries and show a staff-help state. The token is missing/wrong, for another attempt, or the hall was swept. Do not automatically re-login or discard answers. |
| `409` | Stop telemetry; the server says the attempt is already completed. Reconcile the exam session using the application's submission flow. |
| `422` | Fix the payload: missing/invalid sequence, partial question fields, invalid ordinal/length or question outside the paper. Avoid tight automatic retries. |
| Network error / `5xx` | Preserve local answers and continue the exam's established offline policy. Retry monitoring on the next heartbeat with a fresh sequence and latest position. |
| `429` from deployment controls | Respect backoff/Retry-After; do not generate rapid retries. The application adds no broad shared-IP limiter to this endpoint. |

Do not show a successful exam submission because a heartbeat succeeded. Submission must use the existing `POST /api/submit-exam` flow and wait for its acknowledgement. Send the final answer map using the previously added optional `answers` field, with explicit `null` for cleared answers. A legacy attempt-ID-only submission can still lose an answer the client never sent.

## 5. Existing Flutter/Reverb control subscriptions

Keep the existing Pusher-compatible Reverb subscriptions if Flutter uses hall control commands:

| Public channel | Laravel event name | Purpose |
| --- | --- | --- |
| `exam-hall` | `App\Events\ExamHallEvent` | Legacy unsubmitted hall count in `totalStudent`; no student profiles. |
| `sweep-hall` | `App\Events\SeepExamHallEvent` | Existing sweep command. **The spelling `Seep` is intentional compatibility.** |
| `close-exam-hall` | `App\Events\CloseExamHallEvent` | Existing close-hall command. |

The names in the table are decoded event strings with single backslashes, e.g. `App\Events\CloseExamHallEvent`; a Dart raw string is `r'App\Events\CloseExamHallEvent'`.

Use the public Reverb application key, host, port and TLS scheme supplied for the client environment. **Never embed `REVERB_APP_SECRET` in Flutter.** Reconnect and resubscribe after interruptions, handle the Pusher protocol's heartbeat/ping/pong requirements, and retain the existing control-command behavior. A raw WebSocket connection is not a complete Pusher/Reverb client; use a compatible client or implement the protocol explicitly.

These public commands retain their previous trust and delivery limitations. They are not persisted server-side control state and must not be the only mechanism enforcing exam deadlines or proving submission. When handling a close command, use the school's existing final-save/submission policy and await the server acknowledgement; do not just exit and lose unsent answers. Sweep removes hall records and invalidates telemetry tokens; staff should use it for end-of-session cleanup, not to refresh an active monitor.

## 6. Staff-only snapshot and Reverb contract

For the web dashboard, not the Flutter student app:

- Page: `GET /dashboard/exam-hall`.
- Snapshot: `GET /dashboard/exam-hall/snapshot`, active staff web session required, `Cache-Control: no-store, private`.
- Private channel: `private-exam-hall.monitor` on the wire, `Echo.private('exam-hall.monitor')` in JavaScript.
- Auth: Laravel's `/broadcasting/auth`; only authenticated active staff are allowed. All active staff can monitor the hall, matching the existing dashboard access policy.
- Event: `App\Events\ExamHallUpdated`, consumed with `.listen('ExamHallUpdated', ...)`.
- Operations: `progress` carries one attempt's telemetry; `refresh`, `remove`, and `reset` reconcile roster membership. No answer selections, correct answers, scores, token hashes or tokens are broadcast.
- Updates are dispatched after database commit. Reverb or queue enqueue failures do not roll back accepted telemetry or completed submissions; exceptions are reported and snapshot refresh reconciles state.
- The dashboard reads an initial snapshot, refreshes on subscription/reconnection and every 30 seconds, and applies private progress deltas between snapshots. Sequence checks reject delayed older deltas. A local five-second timer updates stale labels without more requests.

Snapshots include `participants`, `count`, `server_time`, and `stale_after_seconds`. Each participant includes `attempt_id`, `student` (`name`, `reg_no`, `photo_url`, `department`), `exam` (`course`, `code`, `title`), `started_at`, and the telemetry fields (`last_seen_at`, `current_question_id`, `current_question_number`, `total_questions`, `last_sequence`). Missing profile pictures use initials. Photos use the existing public student-photo storage; private-channel authorization does not change those existing file URLs into protected media.

## 7. Backend deployment and acceptance checks

The repository includes the new API, private channel, hall page and nullable monitoring fields. No Flutter repository was modified.

1. Back up the database, then run `php artisan migrate` on the target server before deploying traffic to the new code. The migration adds fields to `exam_halls` without changing attempts, answers or marks. Existing rows remain untracked until a compatible client participates; no credentials are fabricated for old rows.
2. Ensure existing student photos are served (`php artisan storage:link` if the public storage link is missing).
3. Build the frontend, refresh normal deployment caches and restart long-running workers/Reverb/FrankenPHP as appropriate.
4. Set `BROADCAST_CONNECTION=reverb`. If queues are asynchronous, run the queue worker for the event's default queue; a WebSocket server alone does not process queued broadcast jobs. `QUEUE_CONNECTION=sync` works but broadcasts inside requests and can add latency.
5. Check Reverb host, origins, TLS, proxy WebSocket forwarding and the staff channel auth request. Student tokens must receive no staff roster access.
6. Before an exam, test real Flutter clients: login/photo display; first question; forward/back navigation; same-question heartbeats; interruption past 60 seconds; reconnect; app restart with sequence restored; invalid tokens; submission while a heartbeat is in flight; queued event reordering; staff reload/reconnect; sweep after the session.
7. Verify answer saving, marks and printed results still match the baseline. Do not run destructive load tests during a live exam.

Suggested capacity scenario: 150 clients at 20-second intervals generate about 7.5 steady heartbeat requests/second, plus debounced navigation updates and answer saves. This is arithmetic, not a measured capacity guarantee. Test the combined workload on the school's hardware and keep verbose request-body/header logging disabled during exams.

Rollback: coordinate stopping new client telemetry first, deploy the previous application version and rebuild caches. The additive nullable columns may remain during rollback. Rolling the migration down drops telemetry and token hashes; it cannot restore live monitoring state.

References: [Laravel broadcasting](https://laravel.com/docs/13.x/broadcasting), [Laravel Reverb](https://laravel.com/docs/13.x/reverb), [Dart HTTP client](https://pub.dev/packages/http).
