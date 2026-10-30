# Starsender Premium inbox — approved scope

Goal: receive personal Starsender Premium webhook events, associate unambiguous phone numbers with existing prospects, and expose conversation history to the assigned member and admin. Unknown/ambiguous contacts remain admin-only for manual association. Preserve existing sending, CRM, and design.

Approach: extend the existing PHP MVC application and MariaDB schema with a message repository, authenticated inbox, and a narrowly scoped standalone webhook receiver. Reusing Starsender's own dashboard would not provide prospect permissions; replacing the framework adds unrelated risk. The user approved the inbox/history proposal in conversation.

Constraints: single workspace, one configured Starsender device in this release, read-only conversation view (existing CRM sends remain available), explicit refresh, no auto-send or automatic status changes. Provider documentation does not specify webhook signatures, recipient identity for outbound events, or delivery receipts. Use a dedicated 64-hex-character callback URL secret and configured device allowlist; outbound events require a validated contact field and otherwise remain admin-only. Do not guess the recipient. Do not infer delivered/read.

Media: retain valid HTTPS file URLs as metadata but do not fetch or embed remote content automatically. Group events are acknowledged and ignored. Stable device/message IDs deduplicate replays. Limit JSON body to 256 KiB, text to 32,000 characters, validate types and timestamps, reject malformed input before writes. Persist UTC timestamps and label local displays Asia/Jakarta.

Authorization: DB identity on every request; members see only messages linked to their currently assigned prospect, including after reassignment; unknown contacts visible only to admin. Mark-as-reviewed and manual linking require POST and CSRF. Linking changes only messages matching the same device/contact conversation, with server-side prospect number verification. Browser view escapes all provider data. Pagination bounds data volume.

Deliverables: full source ZIP, change/installation guide, SQLite repository tests, PHP syntax checks, HTTP boundary tests where runtime permits. Real Starsender connectivity and production deployment remain external validation steps.
