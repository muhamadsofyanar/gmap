# Coolify Team Implementation Plan

> For agentic workers: use superpowers:executing-plans, complete each task with behavioral verification.

**Goal:** Package a single-workspace team CRM with assignment isolation for Coolify.
**Architecture:** Preserve admin MVC; allowlisted member surface with SQL ownership predicates and transaction locks. CLI installation plus Compose persistence.
**Tech Stack:** PHP 8.3 Apache, MariaDB 11.4, Python 3, Selenium, Debian Chromium.
**Spec:** docs/superpowers/specs/2026-10-30-team-design.md

## Global Constraints
- One workspace per installation; no public signup.
- Members access only assigned leads, including counts, files, export and tasks.
- Admin-only legacy controllers. Profile scoped to current identity.
- No license/device activation or baked-in credentials. Preserve CSRF and session revocation.
- Secrets via environment; DB internal only; database and sessions survive redeploy.

## Review Focus
- Guessed IDs and mixed ownership must return no foreign data.
- Reassignment and deactivation must invalidate subsequent requests and race-safe writes.
- Role values in session cannot override the current database role.
- Repeated startup must not reset credentials or duplicate schema.
- Proxy HTTPS and session filesystem permissions must work in container.

### Task 1: Access policy and repository
Files: app/Services/TeamAccess.php, app/Models/TeamRepository.php, tests/team.php.
Interfaces: TeamAccess::allowsController(array $actor,string $controller):bool; TeamRepository(PDO $db,array $actor); lead(int):?array; listing(string,int,int):array; assign(array,?int):int; updateLead(int,array):void; tasks/files/export under the same predicate.
- [x] Write and run failing behavioral tests for foreign lead read/write, assignment and admin policy.
- [x] Implement policy, ownership-filtered queries, transaction locks, audit, account management.
- [x] Verify isolation and atomic reassignment with behavioral tests.

### Task 2: Web integration
Files: AuthGuard, App router, AuthController, User, TeamController, team views/CSS, headers.
- [x] Test member legacy routes denied and team data/export/file queries scoped.
- [x] Remove device license code; apply DB identity roles on every request.
- [x] Build admin team management and member scoped workspace; protect every write with POST+CSRF.
- [x] Verify HTTP login/role/CSRF/foreign ID requests.

### Task 3: Deployment and distribution
Files: Dockerfile, compose.yaml, docker/*, config/*, scripts/install.php, requirements.txt, README.md, .gitignore, .dockerignore.
- [x] Test bootstrap repeated runs preserve existing users; fail for missing production settings.
- [x] Implement fresh schema, environment configuration, bootstrap, health checks, persistence, Chromium driver selection.
- [x] Run PHP lint, full behavior suite, Python compile, shell syntax, Compose checks available in environment.
- [x] Review full changes, package ZIP including hidden files and save deliverable.
