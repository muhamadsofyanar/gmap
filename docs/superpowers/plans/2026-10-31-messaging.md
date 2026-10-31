# Messaging Implementation Plan
Goal: free composition, explicit recipients and refined UI.
Architecture: preserve gateway reservation and CRM scopes; extend native controller/model/view flows.
Spec: docs/superpowers/specs/2026-10-31-messaging-design.md
Constraints: admin-only, CSRF, blocks, no real sends in tests, receiver/config unchanged.
Review focus: changed token payloads; foreign/unselected leads; no-template empty state; search selection persistence; duplicate phone and reduced motion.
- [x] Tests RED for manual text, selected broadcast, request payload binding.
- [x] Extend validation, reservation, recipient endpoint and broadcast snapshots; tests GREEN.
- [x] Add editable CRM/inbox composer and broadcast selector; improve CSS/motion.
- [x] Verify tests/JS/PHP/browser where available; independent review and package patch from v1.3.0.

Verification: 154 PHP checks and 29 HTTP/normalization tests passed; 95 PHP syntax checks, 3 JS syntax checks. Independent review findings fixed using RED/GREEN: JSON selection prevents max_input_vars truncation; canonical intent hash binds raw draft and original selected IDs. Browser install failed; desktop/mobile visual QA unavailable. No actual WhatsApp sends.
