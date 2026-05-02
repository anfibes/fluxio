# Fluxio is a command-first CRM/ERP that turns natural language into structured, validated business actions.
## Overview

Fluxio is an open-source CRM/ERP prototype focused on natural-language business interactions.
The goal of the project is not to build a traditional CRM, but to demonstrate how business systems can be controlled through structured, validated natural-language commands.
Fluxio is designed as a technical and architectural showcase, highlighting:
- modular backend design
- domain separation
- event-driven architecture
- API consistency
- command-first user interaction
---
## Why Fluxio
Most business software still relies on rigid UI flows and manual data entry.
Fluxio explores a different approach:
- reduce friction in daily operations
- enable faster interactions
- bridge natural language and structured systems
- maintain full control through validation and confirmation
The goal is not blind automation, but controlled and transparent execution.
---
## Core Idea
Traditional systems:

User → UI → Form → Action

Fluxio:

User → Natural Language → Action Proposal → Validation → Confirmation → Execution

Natural language is never executed directly.
It is always transformed into a structured action proposal that must be validated and explicitly confirmed before execution.
---
## Architecture
### Modular Monolith
Fluxio is built as a modular monolith with clearly defined domain boundaries and future microservice extraction in mind.
```php
packages/
Core/
Identity/
Leads/
Tasks/
Actions/
Calendar/
Analytics/
Notifications/
```
Each module is responsible for its own:
- models
- migrations
- routes
- services
- events
---
## Architectural Principles
### Modular First, Microservices Later
The system starts as a monolith with strong internal boundaries.
Modules can be extracted into microservices only when necessary.
---
### Domain Separation
Each domain owns its logic and responsibilities:
- **Actions** → interprets user intent (natural language → structured action)
- **Tasks** → execution layer
- **Leads** → lead lifecycle
- **Calendar** → scheduling and time-based operations
---
### Event-Driven Communication
Modules communicate through events and listeners:
- `LeadCreated`
- `TaskCompleted`
- `ActionExecuted`
---
### No Direct Cross-Domain Coupling
Direct cross-domain calls are avoided.
Incorrect:
```php
Lead::createTaskDirectly();
```

Correct:
```php
dispatch(new CreateTaskFromLead(...));
```
---
## Actions Module

The Actions module is the core of Fluxio.

It transforms natural-language input into structured action proposals.

Flow

Input text
→ Intent resolution
→ Entity extraction
→ Schema validation
→ Action proposal
→ User confirmation
→ Execution

Example

Input:

Create a follow-up task for Rossini tomorrow morning

Output:
```json
{
  "intent": "create_follow_up_task",
  "entities": {
    "lead_name": "Rossini",
    "due_date": "tomorrow morning"
  },
  "confidence": 0.82,
  "needs_confirmation": true
}
```

⸻

API Design

Fluxio exposes a consistent JSON API designed for frontend applications and integrations.

Success response
```json
{
  "success": true,
  "message": "Operation completed successfully.",
  "data": {}
}
```
Error response
```json
{
  "success": false,
  "message": "Error message.",
  "errors": {}
}
```
Validation error
```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "field": ["Validation message"]
  }
}
```
### Example Endpoint

POST /api/tasks

Response:

```json
{
  "success": true,
  "message": "Task created successfully.",
  "data": {
    "id": 1,
    "title": "Follow up with Rossini",
    "status": "pending"
  }
}
```
---

Exception Handling

All exceptions are standardized at the framework level.

Handled cases

* ValidationException → 422
* AuthenticationException → 401
* AuthorizationException → 403
* NotFoundHttpException → 404
* Generic exceptions → 500

In production, internal error details are not exposed.


## UI Direction
Fluxio is not form-driven. The UI is designed around intent, not data entry.

Fluxio follows a command-first interface approach:
- central command input
- action suggestions
- explicit confirmation before execution
- minimal UI friction
The interface is designed to be interaction-focused rather than form-driven.
---

## Technology Stack

### Backend
- Laravel
- PostgreSQL
- Modular monolith architecture
- Event-driven design
- Standardized API responses

### Frontend
- Vue 3
- Composition API
- Nuxt 3
- Tailwind CSS
- i18n support
---

## Localization
Fluxio is designed as a multilingual system from the beginning.
- backend uses Laravel translation files
- frontend uses i18n
- English is the primary language
- additional languages can be added progressively
---
## Getting Started

### Requirements
- PHP 8.2+
- Composer
- Node.js
- PostgreSQL

### Installation
```bash
git clone https://github.com/anfibes/fluxio.git
cd fluxio/apps/api
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run dev
php artisan migrate
php artisan serve
```
---
## Project Status
Fluxio is currently in early development.

Implemented

* Modular project structure
* Core module
* Standardized API response layer
* Centralized exception handling

In Progress

* Actions module (natural language interpreter)
* Identity module

Planned

* Leads and Tasks core features
* Initial UI implementation

The project is actively evolving and focused on architecture-first development.
---

## Project Goal

Fluxio is currently not production-ready.

It is an evolving project designed to demonstrate:

* backend architecture
* domain-driven design principles
* API consistency
* event-driven systems
* modern UX concepts for business applications
* integration-ready design

---

License

MIT