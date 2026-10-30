# CRM Conversations Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans for native implementation.

**Goal:** Integrate Starsender inbox with prospects and the CRM layout.
**Architecture:** Extend scoped InboxRepository and add ConversationRepository for grouped rail/timeline, use existing CRM sending ledger and shared view partials. Preserve webhook receiver.
**Tech Stack:** PHP8.3/PDO, MariaDB/SQLite tests, CSS, existing JS.
**Spec:** docs/superpowers/specs/2026-10-30-crm-conversations-design.md

## Global Constraints
- Existing v1.2.2 schema/environment and receiver remain compatible; no destructive migration.
- Current-assignment authorization, no guessed outgoing recipients, no real sending during tests.
- Admin create/link is transactional and CSRF-protected; members cannot create/link or access unknown contacts.

## Review Focus
- Same phone linked to different prospects/projects must not leak across members.
- Ledger outbound records must match exact current prospect recipient; timestamps normalized correctly.
- Creation double submissions, stale project/member and concurrent linking must not duplicate or cross workspaces.
- Empty/long/hostile text and array-valued query parameters must fail safely.
- Unknown outgoing cannot be grouped with most recent inbound.

### Task1: Grouping, timeline and creation
- [x] Add tests/conversations.php for scoped groups, history, ledger merge, unknown queue, create/link duplicates and workspace rejection; observe RED.
- [x] Implement app/Models/ConversationRepository.php and InboxRepository creation/context helpers; tests GREEN.

### Task2: Inbox and CRM integration
- [x] Extend HTTP tests for grouped page, matching candidates, POST/CSRF creation, member route safety and chronological escaping; observe RED.
- [x] Update InboxController and inbox/index; add CSS and shared messages partial; connect CRM WhatsApp tab and shared header. Preserve legacy review endpoint.
- [x] Verify admin/member pages, mobile/desktop layout where browser available, test suite and lint.

### Task3: Review/package
- [x] Independent review, fix important findings with regression tests; rerun suite.
- [x] Produce patch ZIP and guide listing updated files, no DB/env changes and outbound limitations; save artifact.

Verification: 136 PHP checks + 23 Python HTTP/normalization tests passed; 93 PHP files lint clean. Browser visual QA and live MariaDB concurrency tests unavailable. Independent review findings corrected; receiver/env unchanged.
