# API Response Standard

All HTTP responses from `apps/api` must use the standardized response structures defined in this document.

Implementation:

```text
packages/Core/src/Http/Responses/ApiResponse.php
```

Controllers should extend:

```text
Fluxio\Core\Http\Controllers\BaseApiController
```

and use:
- `sendResponse()`
- `sendError()`
- `sendValidationError()`
- `sendNotFound()`
- `sendUnauthorized()`
- `sendForbidden()`
- `sendPaginated()`

instead of manually constructing JSON payloads.

---

# Purpose

The API response layer provides:

- stable frontend/backend contracts
- predictable TypeScript integration
- deterministic proposal transport
- centralized exception rendering
- proposal lifecycle consistency
- operational explainability

Fluxio depends heavily on:
- stable proposal payloads
- deterministic refinement responses
- predictable execution transitions
- ambiguity-aware proposal workflows

The response structure must remain:
- explicit
- stable
- composable
- proposal-centric
- frontend-friendly

---

# Core Principles

## 1. Predictable Structure

Every endpoint must return a predictable structure.

Frontend applications should never require:
- endpoint-specific parsing
- custom error extraction
- proposal-specific transport logic

Proposal operations must preserve:
- stable proposal identity
- stable payload structure
- deterministic lifecycle behavior

---

## 2. Explicit Success / Failure

Responses are discriminated through the `success` boolean.

Success response:

```json
{
    "success": true
}
```

Rules:
- contains `data`
- never contains `errors`

Failure response:

```json
{
    "success": false
}
```

Rules:
- always contains `message`
- may contain `errors`
- never contains `data`

This contract is important for:
- TypeScript narrowing
- frontend composables
- proposal orchestration
- predictable UX rendering

---

## 3. Proposal-Centric Compatibility

Fluxio is proposal-driven.

Many endpoints return `ActionProposal` payloads instead of directly executing business actions.

The response layer must support:
- proposal interpretation
- proposal refinement
- ambiguity resolution
- proposal mutation tracking
- confirmation workflows
- execution workflows

without requiring custom response formats.

---

# Success Response

HTTP status:
- `200 OK`
- or any explicit 2xx status

Structure:

```json
{
    "success": true,
    "message": "Operation completed successfully.",
    "data": {}
}
```

Rules:
- `data` may be:
  - object
  - array
  - scalar
  - `null`
- `errors` must NOT exist
- `message` may be:
  - translation key
  - raw string

---

# Success Example

```php
return ApiResponse::success(
    (new LeadResource($lead))->resolve(),
    'leads::leads.created',
    Response::HTTP_CREATED,
);
```

---

# Generic Error Response

Default HTTP status:
- `400 Bad Request`

Structure:

```json
{
    "success": false,
    "message": "Error message."
}
```

Optional validation details:

```json
{
    "success": false,
    "message": "Error message.",
    "errors": {
        "field_name": [
            "Validation detail."
        ]
    }
}
```

Rules:
- `data` must NOT exist
- `errors` omitted when empty

---

# Validation Error Response

HTTP status:
- `422 Unprocessable Entity`

Structure:

```json
{
    "success": false,
    "message": "The given data was invalid.",
    "errors": {
        "email": [
            "The email field is required."
        ],
        "title": [
            "The title field must be at least 3 characters."
        ]
    }
}
```

Triggered automatically by:
- `ValidationException`

Only applies to:
- JSON requests

Translation key:

```text
core::api.validation_failed
```

---

# Unauthorized Response

HTTP status:
- `401 Unauthorized`

Structure:

```json
{
    "success": false,
    "message": "Unauthenticated."
}
```

Triggered automatically by:
- `AuthenticationException`

Translation key:

```text
core::api.unauthorized
```

---

# Forbidden Response

HTTP status:
- `403 Forbidden`

Structure:

```json
{
    "success": false,
    "message": "This action is not allowed."
}
```

Triggered automatically by:
- `AccessDeniedHttpException`

Laravel internally converts:
- `AuthorizationException`

into:
- `AccessDeniedHttpException`

before render callbacks execute.

Translation key:

```text
core::api.forbidden
```

---

# Not Found Response

HTTP status:
- `404 Not Found`

Structure:

```json
{
    "success": false,
    "message": "Resource not found."
}
```

Triggered automatically by:
- `NotFoundHttpException`

Laravel internally converts:
- `ModelNotFoundException`

into:
- `NotFoundHttpException`

before render callbacks execute.

Translation key:

```text
core::api.not_found
```

---

# Server Error Response

HTTP status:
- `500 Internal Server Error`

Production response:

```json
{
    "success": false,
    "message": "An unexpected error occurred."
}
```

Non-production response:

```json
{
    "success": false,
    "message": "<actual exception message>"
}
```

Rules:
- stack traces are never exposed in production
- internal exception details remain hidden

Translation key:

```text
core::api.server_error
```

---

# Pagination Response

HTTP status:
- `200 OK`

Structure:

```json
{
    "success": true,
    "message": "Data retrieved successfully.",
    "data": [],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 42,
        "last_page": 3
    }
}
```

Rules:
- `meta` exists ONLY on paginated responses
- regular success responses never contain `meta`

Pagination defaults:
- default `per_page`: `15`
- minimum: `1`
- maximum: `100`

Query parameters:
- `?page=N`
- `?per_page=N`

---

# Pagination Example

```php
$paginator = $this->leadService
    ->paginate($request)
    ->through(fn ($lead) => (new LeadResource($lead))->resolve());

return ApiResponse::paginated(
    $paginator,
    'leads::leads.index_success'
);
```

---

# Action Proposal Responses

Fluxio is proposal-driven.

Many endpoints return `ActionProposal` payloads.

Current proposal endpoints:
- `POST /api/actions/interpret`
- `POST /api/actions/{proposal}/refine`
- `POST /api/actions/{proposal}/confirm`
- `POST /api/actions/{proposal}/execute`

These endpoints still use the same standardized response structure.

---

# Example Proposal Response

```json
{
    "success": true,
    "message": "Command interpreted successfully.",
    "data": {
        "id": "proposal_uuid",
        "intent": "create_task",
        "status": "ready",
        "confidence": 0.91,
        "source_text": "Create a follow-up task for Rossini tomorrow at 10am",
        "entities": {
            "lead": "Rossini",
            "due_at": "2026-05-04T10:00:00Z"
        },
        "missing": [],
        "warnings": [],
        "ambiguities": [],
        "editable_fields": [],
        "changes": [],
        "needs_confirmation": true,
        "confirmed_at": null,
        "executed_at": null,
        "failed_at": null,
        "failure_reason": null,
        "execution_result": null,
        "last_refinement": null
    }
}
```

---

# Ambiguity-Aware Proposal Responses

Fluxio supports structured ambiguity handling.

The API contract supports explicit ambiguity rendering through:

```json
{
    "ambiguities": [
        {
            "key": "lead",
            "label": "Lead",
            "reason": "multiple_matches",
            "blocking": true,
            "selected_candidate_id": null,
            "candidates": [
                {
                    "id": 1,
                    "type": "person",
                    "label": "Mario Rossi"
                },
                {
                    "id": 7,
                    "type": "company",
                    "label": "Rossi SRL"
                }
            ]
        }
    ]
}
```

Important rules:
- ambiguity must never be hidden
- proposals remain stable during clarification
- ambiguity may block readiness
- execution must remain explicit
- refinement updates the SAME proposal

This allows:
- operational ambiguity resolution
- deterministic proposal refinement
- proposal continuity preservation

---

# Proposal Lifecycle Guarantees

Allowed proposal statuses:

- `draft`
- `ready`
- `confirmed`
- `executed`
- `failed`

Typical lifecycle:

```text
interpret
→ draft/ready
→ refine
→ confirm
→ execute
→ executed/failed
```

Rules:
- refinement does NOT create new proposals
- proposal IDs remain stable
- proposal continuity is preserved
- `draft` proposals may be refined
- `ready` proposals may still be refined
- execution must remain explicit

---

# Proposal Refinement

Fluxio supports iterative proposal refinement.

Example:

User:

```text
Schedule a call with Rossini
```

System:
- creates draft proposal
- detects missing information
- blocks execution

User:

```text
Tomorrow morning
```

Expected behavior:
- refine the existing proposal
- improve confidence
- reduce missing fields
- preserve proposal continuity
- transition proposal toward `ready`

---

# Full-Command Refinement

Fluxio also supports full-command refinement.

Original proposal:

```text
Schedule a call with Rossini
```

Refinement input:

```text
Schedule a call with Rossini tomorrow morning
```

Expected behavior:
- still treated as refinement
- original `source_text` remains unchanged
- effective refinement extracted internally
- proposal updated correctly

This supports:
- natural editing behavior
- proposal continuity
- deterministic refinement workflows

---

# Proposal Mutation Transparency

Fluxio intentionally exposes proposal mutations.

Proposal payloads may include:

```json
{
    "last_refinement": {
        "text": "Tomorrow morning",
        "effective_text": "Tomorrow morning",
        "summary": "Date and time added.",
        "changes": [
            {
                "field": "date",
                "label": "Date",
                "from": null,
                "to": "2026-05-10"
            }
        ]
    }
}
```

Purpose:
- explainability
- operational clarity
- mutation visibility
- deterministic proposal tracking

The frontend uses this information for:
- refinement panels
- mutation rendering
- proposal explainability
- operational UX continuity

---

# Idempotent Proposal Execution

Proposal execution endpoints must be idempotent.

Executing the same proposal multiple times must NOT:
- duplicate tasks
- duplicate mutations
- create inconsistent business state

Repeated execution calls should:
- return the existing executed proposal
- preserve execution metadata
- preserve proposal integrity

This is especially important for:
- retries
- frontend refreshes
- network instability
- future async workflows

---

# Frontend Contract Stability

Fluxio includes a typed Nuxt frontend using:
- TypeScript
- composables
- proposal-driven orchestration

Frontend composables depend heavily on:
- stable response contracts
- deterministic proposal payloads
- stable refinement semantics
- stable execution states

Breaking response changes should be treated as:
- architectural changes
- cross-layer changes

and coordinated carefully between:
- backend
- frontend
- TypeScript contracts

---

# Translation Resolution

Default translations live in:

```text
packages/Core/lang/en/api.php
```

Current keys:

| Key | Default message |
|---|---|
| `core::api.validation_failed` | The given data was invalid. |
| `core::api.not_found` | Resource not found. |
| `core::api.unauthorized` | Unauthenticated. |
| `core::api.forbidden` | This action is not allowed. |
| `core::api.server_error` | An unexpected error occurred. |

Messages may be:
- translation keys
- raw strings

The response layer automatically:
- resolves translation keys when available
- falls back to raw strings otherwise

---

# Exception → Status Mapping

| Exception class | Prepared as | Status |
|---|---|---|
| `ValidationException` | itself | 422 |
| `AuthenticationException` | itself | 401 |
| `AuthorizationException` | `AccessDeniedHttpException` | 403 |
| `ModelNotFoundException` | `NotFoundHttpException` | 404 |
| `NotFoundHttpException` | itself | 404 |
| Any other `Throwable` | itself | 500 |

Rendering applies only to requests with:

```text
Accept: application/json
```

Non-JSON requests fall back to Laravel's default HTML rendering.

---

# Testing Expectations

The response layer is foundational infrastructure and must remain highly stable.

Priority areas:
- response shape consistency
- exception rendering
- validation formatting
- pagination structure
- proposal integrity
- proposal continuity
- ambiguity handling
- proposal refinement
- refinement mutation tracking
- execution consistency
- idempotent execution behavior
- frontend contract stability

---

# Architectural Importance

The response layer acts as:
- the proposal transport contract
- the frontend orchestration contract
- the proposal lifecycle transport layer
- the operational explainability layer

Fluxio depends on:
- stable proposal payloads
- deterministic refinement semantics
- predictable execution transitions
- ambiguity-aware operational workflows

This response layer is one of the core foundations enabling:
- proposal-centric UX
- AI-first operational workflows
- controlled enterprise AI interaction