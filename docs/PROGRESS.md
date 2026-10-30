# Ledger: docs/superpowers/plans/2026-10-30-coolify-team.md

Task 1 complete: isolated repository and controller policy; team.php 58 checks pass.
Task 2 complete: server-rendered team pages, guard, removal of license, member/admin links, CSRF. test_http.py 64 HTTP checks pass with real MariaDB.
Task 3 complete: Compose, Dockerfile, CLI bootstrap, persistent storage, documentation and ZIP packaging. install.php 7 checks pass; full lint and syntax/structural checks pass. Docker build and VPS smoke test explicitly remain unverified.

Ruling: native inline execution in a fresh extracted folder/branch, not a worktree against a user's existing repository, because the user wants a ZIP for manual upload. No external repository push or deployment performed.
Ruling: one workspace per installation with a dedicated member surface; legacy admin MVC remains global/admin-only. Cost: members have a focused workflow; adding multiple companies later needs additional work and isolation changes.
Final fixed: SQL :actor binding regression reproduced on real MariaDB, removed extra SELECT binding; legacy.php pass.
Final fixed: stale password-reset actor mutation reproduced in team.php, compare session_version under account row lock; suite passes.
Final fixed: fresh assigned prospect omitted from admin CRM reports reproduced in team.php; assignment enrolls it in CRM and preserves crm_added_at; suite passes.
Final minor deferred: profile password change keeps the original eight-character minimum; bootstrap/admin-reset use 12-byte minimum. Documented in README.
Final ruling: container/proxy/browser runtime checks set aside by reviewer cannot be simulated by SQL/HTTP tests; report as unverified and supply VPS smoke test steps. Cost: deployment-specific problems may still appear on first build.
