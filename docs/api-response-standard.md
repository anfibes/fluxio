# API Response Standard

All HTTP responses from `apps/api` must follow the standardized response contracts defined in this document.

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

The response layer provides:
- stable frontend/backend contracts
- deterministic transport structures
- centralized exception rendering
- predictable TypeScript integration
- proposal-safe payload transport

The response contract must remain:
- explicit
- stable
- frontend-safe
- composable
- deterministic

---

# Response Invariants

## Success responses

Must:
- set `success=true`
- contain `data`
- never contain `errors`

## Error responses

Must:
- set `success=false`
- contain `message`
- may contain `errors`
- never contain `data`

## Proposal responses

Must:
- preserve proposal identity
- preserve proposal continuity
- expose ambiguities explicitly
- expose refinement mutations explicitly
- remain deterministic

The response layer transports proposal state but does NOT implement:
- proposal lifecycle logic
- ambiguity resolution logic
- mutation semantics
- execution orchestration

Those remain inside the Actions module.

---

# Core Principles

## Predictable structure

Every endpoint must return a predictable structure.

Frontend applications should never require:
- endpoint-specific parsing
- custom error extraction
- proposal-specific transport logic

---

## Explicit success/failure

Responses are discriminated through:

```json
{
    "success": true
}
```

or:

```json
{
    "success": false
}
```

This enables:
- TypeScript narrowing
- stable composables
- deterministic proposal orchestration
- predictable operational UX

---

## Stable proposal transport

Many Actions endpoints return proposal payloads instead of executing business operations directly.

The response layer transports proposal state without requiring endpoint-specific response structures.

This includes:
- proposal interpretation
- refinement state
- ambiguities
- mutations
- execution state
- execution results

---

# Success Response

HTTP status:
- `200 OK`
- or explicit `2xx`

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
- `errors` must never exist

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
- `data` must never exist
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
        ]
    }
}
```

Triggered automatically by:
- `ValidationException`

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

Translation key:

```text
core::api.not_found
```

---

# Invalid Provider Output Response

HTTP status:
- `422 Unprocessable Entity`

Structure:

```json
{
    "success": false,
    "message": "The interpreted command is invalid."
}
```

Triggered automatically by:

```text
InvalidNormalizedCommandException
```

Purpose:
- isolate malformed provider output
- protect proposal lifecycle integrity
- prevent invalid `NormalizedCommand` payloads from entering proposal orchestration

This currently applies to:
- interpretation providers
- fake LLM providers
- future external inference providers

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
- `meta` exists only on paginated responses
- regular success responses never contain `meta`

Defaults:
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

# Proposal Response Contract

Current proposal endpoints:

| Method | Endpoint |
|---|---|
| POST | `/api/actions/interpret` |
| POST | `/api/actions/{proposal}/refine` |
| POST | `/api/actions/{proposal}/confirm` |
| POST | `/api/actions/{proposal}/execute` |

These endpoints use the same standardized response envelope used by the rest of the API.

---

# Action Proposal Structure

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
        "entities": {},
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
        "failure_reason_code": null,
        "execution_failure": null,
        "execution_result": null,
        "last_refinement": null
    }
}
```

On `executed`, `execution_result` is a typed `{ "summary": string, "details": { … } }`.
On `failed`, `execution_failure` is `{ "reason": "unsupported_intent" | "execution_failed",
"message": string }`, with `failure_reason_code` mirroring the reason and `failure_reason`
holding the same sanitized message. Raw exception text is never exposed.

---

# Proposal Lifecycle Guarantees

Allowed statuses:

```text
draft
ready
confirmed
executed
failed
```

Lifecycle:

```text
interpret
→ draft/ready
→ refine
→ confirm
→ execute
→ executed/failed
```

Rules:
- proposal IDs remain stable
- refinement never creates a new proposal
- proposal continuity is preserved
- ambiguities remain explicit
- execution remains explicit
- execution is idempotent

---

# Proposal Continuity Rules

Fluxio supports iterative refinement.

Example:

Original proposal:

```text
Schedule a call with Rossini
```

Refinement:

```text
Tomorrow morning
```

Expected behavior:
- refine the SAME proposal
- preserve proposal identity
- improve readiness
- preserve existing fields

Fluxio also supports:
- full-command refinements
- contextual refinements
- mutation-based refinements

Example:

```text
Schedule a call with Rossini tomorrow morning
```

even when applied to an existing proposal.

---

# Ambiguity-Aware Responses

Ambiguity is explicit operational state.

Example:

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

Rules:
- ambiguity is never hidden
- ambiguity may block readiness
- refinement updates the SAME proposal
- execution must remain explicit

---

# Proposal Mutation Transparency

Proposal mutations remain intentionally visible.

Example:

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
                "to": "2026-05-10",
                "semantic_type": "replace_date"
            }
        ]
    }
}
```

`semantic_type` is an optional, additive field that names the operational
meaning of a change (`replace_time`, `replace_date`, `shift_time`,
`add_participant`, `remove_participant`, `replace_participant`, `unknown`).
Consumers must treat it as optional — changes without it stay valid.

Purpose:
- explainability
- operational clarity
- mutation visibility
- deterministic tracking

The frontend uses this information for:
- refinement panels
- mutation summaries
- operational continuity
- proposal explainability

---

# Idempotent Proposal Execution

Proposal execution endpoints must be idempotent.

Repeated execution calls must NOT:
- duplicate tasks
- duplicate operations
- create inconsistent state

Repeated execution should:
- return the same executed proposal
- preserve execution metadata
- preserve proposal integrity

This is important for:
- retries
- refreshes
- async workflows
- unstable networks

---

# Frontend Contract Stability

Fluxio frontend depends heavily on:
- stable response contracts
- deterministic proposal payloads
- stable refinement semantics
- stable execution states

Breaking response changes must be treated as:
- architectural changes
- cross-layer changes

and coordinated carefully between:
- backend
- frontend
- TypeScript contracts

---

# Translation Resolution

Translations live in:

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
| `InvalidNormalizedCommandException` | itself | 422 |
| Generic `Throwable` | itself | 500 |

Rendering applies only to:

```text
Accept: application/json
```

Non-JSON requests fall back to Laravel HTML rendering.

---

# Validation Boundaries

Current provider flow:

```text
Interpretation Provider
→ NormalizedCommand
→ Validation
→ Proposal Lifecycle
```

The response layer participates in provider isolation by:
- rendering validation failures safely
- preventing malformed provider payloads from leaking
- preserving deterministic API contracts

The response layer does NOT:
- validate business workflows
- decide proposal readiness
- resolve ambiguities
- execute business actions

---

# Testing Expectations

Priority areas:
- response consistency
- exception rendering
- pagination structure
- proposal continuity
- ambiguity handling
- mutation tracking
- execution consistency
- frontend compatibility
- idempotent execution
- provider validation

The response layer is foundational infrastructure and must remain highly stable.

---

# Architectural Importance

The response layer acts as:
- backend/frontend transport contract
- deterministic API boundary
- proposal transport layer
- operational explainability layer

Fluxio depends heavily on:
- stable proposal payloads
- deterministic refinement semantics
- predictable execution transitions
- explicit ambiguity handling

This layer is one of the core foundations enabling:
- proposal-driven operational workflows
- controlled interpretation pipelines
- frontend/backend contract stability