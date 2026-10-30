# Starsender Inbox Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a private team inbox backed by Starsender Premium webhooks.
**Architecture:** A validated callback stores events in MariaDB. An MVC inbox uses existing DB identity and prospect assignment permissions; SQLite tests exercise the same repository.
**Tech Stack:** PHP 8.3, PDO, MariaDB, HTML/CSS.
**Spec:** docs/superpowers/specs/2026-10-30-starsender-inbox-design.md

## Global Constraints
- Single workspace and one configured sending device; preserve existing features.
- No guessed outbound recipients, no automatic sends or status changes.
- Callback secret and device environment must be configured or receiver fails closed.
- No downloaded or automatically rendered media; escape all text.

## Review Focus
- Malformed boolean/group/phone inputs must not create false contacts.
- Same message ID on another device must not collide.
- Reassignment must revoke previous member read/write access immediately.
- Unknown and ambiguous matches remain admin-only.
- Callback must remain independent of sessions, login, and CSRF.

### Task 1: Ingestion and repository
Files: app/Services/StarsenderWebhook.php; app/Models/InboxRepository.php; database.sql; tests/inbox.php.
Interfaces: normalize(array $event, string $device): ?array; ingest(array $event): bool; listing(array $actor, string $filter, int $page): array; review(array $actor, int $id): void; link(array $actor, int $id, int $lead): void.
- [x] Write tests for inbound normalization, group skip, outbound ambiguity, invalid types, duplicate events, unknown/multiple matches, scoping, review and linking.
- [x] Run php tests/inbox.php: fail before classes exist.
- [x] Implement validator, schema, repository; run tests until green.

### Task 2: Callback and inbox UI
Files: public/starsender-webhook.php; app/Controllers/InboxController.php; app/Views/inbox/index.php; existing navigation and TeamAccess; compose.yaml; .env.example.
- [x] Write HTTP tests for fail-closed config, wrong token, method, size, malformed JSON and device filtering.
- [x] Run tests before receiver exists to establish failure.
- [x] Implement receiver with bounded input, POST-only callback, generic JSON errors; render inbox, conversation and admin link form.
- [x] Run repository, existing team/install tests, callback tests and PHP lint.

### Task 3: Package and review
Files: docs/STARSENDER_INBOX.md; README.md; VALIDATION.md.
- [x] Review authorization, ambiguous recipients, configuration, timezone, XSS and SQL parameters.
- [x] Verify clean package and include full source plus precise change list and setup steps.
- [x] Save deliverable ZIP and report tested versus live-unverified behavior.

Execution record: isolated copy of source ZIP, no remote git repository. Native implementation authorized by user approval of proposed scope. Reviewer found timestamp normalization and persistent manual association issues; regression tests reproduced both and passed after fixes. Local PHP runtime was extracted into scratch; it is not shipped. Real MariaDB/Apache/Starsender tests remain deployment checks, not claimed completed.
