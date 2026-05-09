# API Response Standard

All HTTP responses from `apps/api` must use the standardized response shapes defined in this document.

The implementation lives in:

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

instead of manually constructing JSON responses.

---

# Goals

The API response layer exists to provide:
- predictable frontend contracts
- stable TypeScript integration
- consistent exception rendering
- reusable module behavior
- proposal-driven workflow support

The response structure must remain:
- explicit
- stable
- composable
- frontend-friendly

---

# Core Principles

## 1. Predictable structure

Every response must follow a predictable shape.

Frontend applications should never need endpoint-specific parsing logic.

---

## 2. Explicit success/failure discrimination

Responses are discriminated by the `success` boolean field.

When:

```json
{
    "success": true
}
```

the response:
- contains `data`
- does NOT contain `errors`

When:

```json
{
    "success": false
}
```

the response:
- always contains `message`
- may contain `errors`
- never contains `data`

This contract is important for:
- frontend composables
- TypeScript narrowing
- predictable error handling

---

## 3. Proposal-driven architecture compatibility

Fluxio is proposal-centric.

Many endpoints return `ActionProposal` payloads instead of immediately executing business actions.

The response layer must support:
- proposal interpretation
- proposal refinement
- proposal confirmation
- proposal execution
- execution results

without requiring custom response formats.

---

# Success Response

HTTP status:
- `200 OK`
- or any explicit 2xx status passed to the response helper

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
  - plain string
  - translation key

---

# Example

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

Optional field-level errors:

```json
{
    "success": false,
    "message": "Error message.",
    "errors": {
        "field_name": ["Validation detail."]
    }
}
```

Rules:
- `data` must NOT exist
- `errors` is omitted when empty

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
        "email": ["The email field is required."],
        "title": ["The title field must be at least 3 characters."]
    }
}
```

Behavior:
- Triggered automatically by `ApiExceptionRenderer`
- Only applies to JSON requests
- Uses Laravel validation error bag structure

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

Triggered automatically for:
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

Triggered automatically for:
- `AccessDeniedHttpException`

Notes:
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

Triggered automatically for:
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

Pagination behavior:
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

Example endpoints:
- `POST /api/actions/interpret`
- `POST /api/actions/{proposal}/confirm`
- `POST /api/actions/{proposal}/execute`

These endpoints still use the same standardized response structure.

---

# Example Action Proposal Response

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
        "editable_fields": [
            {
                "key": "title",
                "label": "Title",
                "value": "Follow-up task for Rossini",
                "source": "detected",
                "required": true
            }
        ],
        "changes": [
            {
                "type": "create",
                "label": "Create Task",
                "module": "tasks"
            }
        ],
        "needs_confirmation": true
    }
}
```

---

# Action Proposal Lifecycle

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
→ confirm
→ execute
→ executed/failed
```

---

# Proposal Refinement

Fluxio supports iterative proposal refinement.

Example:

User:

```text
Schedule a call with Rossini
```

System:
- draft proposal
- missing fields

User:

```text
Tomorrow morning
```

Expected behavior:
- refine the existing proposal
- improve confidence
- reduce missing fields
- preserve proposal continuity

The API contract must remain stable during refinement.

---

# Idempotent Proposal Execution

Proposal execution endpoints must be idempotent.

Executing the same proposal multiple times must NOT:
- duplicate tasks
- duplicate mutations
- produce inconsistent business state

Repeated execution calls should:
- return the existing executed proposal state
- preserve execution metadata

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

Frontend composables rely on stable response contracts.

Breaking response structure changes should be treated as:
- architectural changes
- cross-layer changes

and coordinated carefully between:
- backend
- frontend
- TypeScript contracts

---

# Translation Keys

Default messages are defined in:

```text
packages/Core/lang/en/api.php
```

Current keys:

| Key                            | Default message                    |
|--------------------------------|------------------------------------|
| `core::api.validation_failed`  | The given data was invalid.        |
| `core::api.not_found`          | Resource not found.                |
| `core::api.unauthorized`       | Unauthenticated.                   |
| `core::api.forbidden`          | This action is not allowed.        |
| `core::api.server_error`       | An unexpected error occurred.      |

---

# Translation Resolution

Messages may be:
- translation keys
- raw strings

The response layer automatically:
- resolves translation keys when available
- falls back to raw strings otherwise

This allows:
- multilingual APIs
- frontend-friendly consistency
- module-level localization

---

# Exception → Status Mapping

| Exception class               | Prepared as                     | Status |
|--------------------------------|----------------------------------|--------|
| `ValidationException`          | itself                           | 422    |
| `AuthenticationException`      | itself                           | 401    |
| `AuthorizationException`       | `AccessDeniedHttpException`      | 403    |
| `ModelNotFoundException`       | `NotFoundHttpException`          | 404    |
| `NotFoundHttpException`        | itself                           | 404    |
| Any other `Throwable`          | itself                           | 500    |

Rendering applies only to requests with:

```text
Accept: application/json
```

Non-JSON requests fall back to Laravel's default HTML handling.

---

# Testing Expectations

Core response behavior should be tested thoroughly.

Priority areas:
- response shape consistency
- exception rendering
- validation formatting
- pagination structure
- proposal response integrity
- proposal execution consistency
- idempotent execution behavior

The API response layer is foundational infrastructure and must remain highly stable.