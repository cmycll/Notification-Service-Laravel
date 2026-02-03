# Notification Service (Laravel)

A small notification service that accepts batch requests and processes messages asynchronously across multiple channels (SMS / Email / Push), with priority queues, retries, and basic observability.

## What this project does
- Accepts a **request** containing a template + up to **1000 recipients**
- Creates one **message record per recipient**
- Processes messages via **Redis queues** and workers
- Enforces a simple **per-channel throughput cap** (100 msg/sec/channel)
- Supports **priorities** (`high`, `normal`, `low`) mapped to separate queues
- Supports **scheduled sends** via the Laravel scheduler
- Exposes basic **system metrics** and **health** endpoints

## Quick Start (Docker Compose)
Run all containers:

```
docker compose up -d --build --scale queue_high=4 --scale queue_normal=2 --scale queue_low=1
```

Why scaling the queues?
- **High** priority gets more workers for burst traffic.
- **Normal** gets moderate throughput.
- **Low** still gets at least one worker so it **doesn’t starve** when higher-priority work is continuously arriving.

## Architecture Overview

High-level flow:
```
Client -> API -> MySQL (requests + messages) -> Queue -> Worker -> Provider
                                                -> Redis counters -> scheduled flush -> MySQL
```

Queues:
- `high`, `normal`, `low` mapped by request priority.
- Workers are scaled manually via Docker Compose (`--scale`).

Scheduler:
- Runs `request-counters:flush` and `notifications:dispatch-scheduled` every minute.

## Idempotency
This API supports an optional `idempotency_key` on `POST /api/notifications`.

- **If you provide `idempotency_key`**: repeated requests with the same key will return the existing `request_id` instead of creating a duplicate request.
- **If you omit `idempotency_key`**: the server still accepts the request, but re-sending the same payload may create a new request (i.e. duplicates are possible).

Recommended: generate a unique, stable key per client request (e.g. UUID) and reuse it for retries.

## Flow diagrams (ASCII)

### Request lifecycle
```
POST /api/notifications
  -> validate payload
  -> insert notif_requests + notif_request_notifications (1 row per recipient)
  -> if scheduled_at is empty: dispatch message jobs to queue
  -> else: scheduler will dispatch later
```

### Scheduled dispatch
```
schedule:work (every minute)
  -> find due requests
  -> mark request as PROCESSING
  -> dispatch message jobs
  -> clear scheduled_at
```

## API Endpoints (Auth: Sanctum)

### User & token
You must create a user once and use the returned token for subsequent requests.

- `POST /api/user` → creates a user and returns `api_token`
- `GET /api/user` → returns the current user (requires Bearer token)

Notifications:
- `POST /api/notifications`
- `GET /api/notifications`
- `GET /api/notifications/{requestId}`
- `POST /api/notifications/message/{id}/cancel`
- `POST /api/notifications/request/{id}/cancel`

System:
- `GET /api/system/health`
- `GET /api/system/metrics?window_minutes=60`

Metrics include queue depth, success/failure rates, and **average processing latency** (computed as `updated_at - created_at` for completed messages).

## Example Requests

### Template and recipient variables
Templates support `{{variable}}` placeholders. Each recipient can provide a `vars` object; variables are substituted per recipient when the message is rendered.

Example payload:
```json
{
  "channel": "email",
  "priority": "high",
  "template": {
    "subject": "Hello {{name}}",
    "body": "<p>Dear {{name}} {{surname}},<br/>You have a new account notification.</p>"
  },
  "recipients": [
    {
      "to": "alex.taylor@example.com",
      "vars": {
        "name": "Alex",
        "surname": "Taylor"
      }
    },
    {
      "to": "jane.smith@example.com",
      "vars": {
        "name": "Jane",
        "surname": "Smith"
      }
    }
  ]
}
```

Create user and get token:
```
curl -X POST http://localhost/api/user \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Alice",
    "email": "alice@example.com",
    "password": "password123"
  }'
```

Example response:
```json
{
  "message": "User created successfully",
  "user": {
    "name": "Alice Example",
    "email": "alice@example.com",
    "updated_at": "2026-02-03T00:00:00.000000Z",
    "created_at": "2026-02-03T00:00:00.000000Z",
    "id": 1
  },
  "api_token": "1|REDACTED_EXAMPLE_TOKEN"
}
```

Create (scheduled):
```
curl -X POST http://localhost/api/notifications \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{
    "channel": "sms",
    "priority": "normal",
    "idempotency_key": "client-generated-unique-key-123",
    "template": { "subject": "Hello", "body": "Test message" },
    "recipients": [{ "to": "+905551234567" }, { "to": "+905551234568" }],
    "scheduled_at": "2026-03-02T18:00:00Z"
  }'
```

Example response:
```json
{
  "success": true,
  "request_id": "019c213a-0e18-72c2-8a23-1b222c807264",
  "requested_count": 2,
  "accepted_count": 2,
  "rejected_count": 0,
  "pending_count": 2
}
```

List requests:
```
curl -H "Authorization: Bearer <TOKEN>" http://localhost/api/notifications
```

Example response:
```json
{
  "success": true,
  "data": [
    {
      "request_id": "019c213a-0e18-72c2-8a23-1b222c807264",
      "status": "sent",
      "channel": "sms",
      "priority": "high",
      "requested_count": 4,
      "accepted_count": 4,
      "pending_count": 0,
      "sent_count": 4,
      "failed_count": 0,
      "cancelled_count": 0,
      "scheduled_at": null,
      "created_at": "2026-02-03T01:59:38.000000Z"
    },
    {
      "request_id": "019c211b-2695-70a0-adc8-c79e0d3620f8",
      "status": "sent",
      "channel": "email",
      "priority": "high",
      "requested_count": 4,
      "accepted_count": 4,
      "pending_count": 0,
      "sent_count": 3,
      "failed_count": 1,
      "cancelled_count": 0,
      "scheduled_at": null,
      "created_at": "2026-02-03T01:25:53.000000Z"
    }
  ],
  "meta": {
    "total": 2,
    "per_page": 20,
    "current_page": 1,
    "last_page": 1
  }
}
```

Get messages for a request:
```
curl -H "Authorization: Bearer <TOKEN>" http://localhost/api/notifications/<REQUEST_ID>
```

Example response:
```json
{
  "success": true,
  "data": {
    "request": {
      "id": "019c0000-0000-7000-8000-000000000001",
      "status": "sent",
      "requested_count": 4,
      "accepted_count": 4,
      "pending_count": 0,
      "sent_count": 3,
      "failed_count": 1,
      "cancelled_count": 0,
      "channel": "email",
      "priority": "high",
      "scheduled_at": null,
      "created_at": "2026-02-03T01:59:38.000000Z"
    },
    "messages": [
      {
        "id": "019c0000-0000-7000-8000-000000000101",
        "to": "alex.taylor@example.com",
        "channel": "email",
        "priority": "high",
        "status": "sent",
        "delivery_state": "queued",
        "attempts": 1,
        "provider_message_id": "provider-msg-001",
        "last_error": null,
        "created_at": "2026-02-03T01:59:38.000000Z"
      },
      {
        "id": "019c0000-0000-7000-8000-000000000102",
        "to": "jane.smith@example.com",
        "channel": "email",
        "priority": "high",
        "status": "sent",
        "delivery_state": "queued",
        "attempts": 1,
        "provider_message_id": "provider-msg-002",
        "last_error": null,
        "created_at": "2026-02-03T01:59:38.000000Z"
      },
      {
        "id": "019c0000-0000-7000-8000-000000000103",
        "to": "sam.lee@example.com",
        "channel": "email",
        "priority": "high",
        "status": "sent",
        "delivery_state": "queued",
        "attempts": 1,
        "provider_message_id": "provider-msg-003",
        "last_error": null,
        "created_at": "2026-02-03T01:59:38.000000Z"
      },
      {
        "id": "019c0000-0000-7000-8000-000000000104",
        "to": "pat.morgan@example.com",
        "channel": "email",
        "priority": "high",
        "status": "failed",
        "delivery_state": "failed",
        "attempts": 5,
        "provider_message_id": null,
        "last_error": "Max attempts exceeded",
        "created_at": "2026-02-03T01:59:38.000000Z"
      }
    ]
  }
}
```

Cancel a message:
```
curl -X POST -H "Authorization: Bearer <TOKEN>" \
  http://localhost/api/notifications/message/<MESSAGE_ID>/cancel
```

Example response:
```json
{
  "success": true,
  "data": {
    "id": "019c0000-0000-7000-8000-000000000101",
    "request_id": "019c213a-0e18-72c2-8a23-1b222c807264",
    "status": "cancelled",
    "delivery_state": "rejected"
  }
}
```

Cancel a request:
```
curl -X POST -H "Authorization: Bearer <TOKEN>" \
  http://localhost/api/notifications/request/<REQUEST_ID>/cancel
```

Example response:
```json
{
  "success": true,
  "data": {
    "request_id": "019c213a-0e18-72c2-8a23-1b222c807264",
    "cancelled_count": 2,
    "pending_count": 0,
    "status": "cancelled"
  }
}
```

## Tests

Run all tests:
```
php artisan test
```

## API Documentation
- OpenAPI spec: `openapi.yaml`

## Notes
- **Provider integration** uses `NOTIF_SERVICE_URL` (e.g. a webhook URL).
- **Expected provider response**:
  - HTTP status: `202`
  - JSON body:
    - `status`: `"accepted"` (or `"error"` for failure simulation)
    - `messageId`: string
    - `timestamp`: ISO8601

If the provider returns a non-202 response, the job is retried and eventually marked as failed when attempts are exhausted.

## Roadmap
- **Dead Letter Queue (DLQ)**: Add a dedicated storage/stream for messages that exceed the maximum retry attempts.
- **Observability stack**: Integrate OpenTelemetry + Loki + Grafana for real-time log/trace exploration and dashboards.
- **Admin / client UI**: Build a modern web UI for managing requests and monitoring metrics.
