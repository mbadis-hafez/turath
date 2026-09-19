# Privacy rules (non-negotiable)

1. **Owner/donor personal contact data is never stored on public models and never exposed via the public API.** This includes: personal names of private owners/donors, phone numbers, personal emails, street addresses, supervisor names.
2. If contact data is ever needed for operations, it lives in a separate **admin-only table**, never joined into public API resources.
3. Holders that are not public institutions must display as "Private collection, {city}" (`is_public_name = false`).
4. Imported spreadsheet columns that contain contact/contract data (owner names, phones, emails, addresses, condition-report links, authorization letters, lending/contract/availability/communication/batch/selection-process columns) are **never imported**. The only exception: authorization-letter status → `consent_status`.
5. **Rights:** every file and archive item carries `rights_status`, `license`, `rights_holder`, `access_level` (`public | registered | researcher | institution_only | embargoed`). Default for imported items is `institution_only` until reviewed. Nothing auto-publishes.
6. **Access levels are enforced in policies and resources**, not just stored. Public responses must filter by access level.
7. **Immutable originals:** uploaded files are never modified; only derivatives are generated. SHA-256 per file; duplicates are detected by checksum and reported, never silently rejected.
8. **Machine output** (OCR, transcripts, AI-extracted fields) is stored as `machine_generated` drafts and only appears publicly after human review.
9. **Auditability:** every create/update/delete/restore on a tracked model is logged with causer, field-level old/new values, and edit summary (see D14 in `decisions.md`). This log is itself admin-only.
10. **Exports and public APIs respect access levels** (F11); nothing embargoed or institution-only may leak through any read path.
