Specter Admin v22
- Dark UI, sticky gradient topbar, quick actions.
- Users page (was Playlists): create/edit in modals, minimal fields (Service DNS, Portal creds, MAC (12 lowercase hex), Adult PIN), search + pagination, delete modal, unlinked devices list.
- IPTV Services: edit modal; toggle removed.
- Mass Replace Service DNS tool.
- Forced credential-change modal on Settings when needed.
- Dashboard charts & stats.
- API endpoints aligned to your provided outputs (authenticate, playlists create/delete, parent-control update). MAC handled plain text (base64 accepted input).
- SQLite auto-migrations (idempotent). Default admin (force change).
- Backup/Restore removed entirely.
