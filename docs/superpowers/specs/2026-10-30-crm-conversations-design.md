# CRM conversations — approved follow-up

User approved the proposed integrated inbox: shared CRM sidebar, grouped conversations per contact, create/link prospects from inbox, related history in CRM. Preserve working Starsender v1.2.2 receiver and configured fingerprint. No external messages sent by development tools.

Native PHP MVC implementation. Admin sees all inbound contacts and a separate queue for webhook outgoing messages without recipient. Members see only current assigned prospects. Unlinked contacts are grouped by device and phone. Linked history is grouped by prospect id across device identities, with explicit project context to avoid combining unrelated duplicated prospects. Reassignment revokes old access. Unknown outbound events are never assigned by timestamps or inferred from `from`.

Three-column desktop view: conversations, selected chronological history, prospect/project/owner context. Mobile stacks panels and offers clear navigation. Reuse CRM shell/colors. CRM detail WhatsApp tab shows recent related events and link to full inbox; team detail links remain available.

Existing CRM deliveries have known prospect and recipient; merge them into a linked prospect's timeline, label source/status and convert Asia/Jakarta database times to UTC for consistent order. Do not claim delivery/read or identify the original sending device for legacy records. Reply action opens existing CRM WhatsApp composer for admin; member permissions do not expand to legacy admin routes.

Admin can create one prospect in an existing workspace project from an unlinked inbound message, with editable name, fixed verified phone and optional active member assignment. Transactional creation writes CRM/source/history and mapping. Reject duplicate matches and return existing linked prospect on repeated submission. Existing prospect link only accepts matching phone, promotes into CRM, and returns to context. No change to schemas or credentials; historical unmatched outbound remains admin-only.

Validate all request types, lengths, page bounds; POST+CSRF mutations; exact membership/workspace checks at SQL boundary; escape provider content; media metadata only; keep device hashes out of ordinary chat UI. No polling and no auto-send in this update.
