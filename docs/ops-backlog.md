## Offsite backup via Google Drive (deferred)

**Idea:** Automate offsite backup to Google Drive instead of manual SFTP.
- Pros: No external server to manage, OAuth-based auth, familiar UI
- Cons: Rate limits, quota management, requires Google account
- Decision: Use SFTP drop-box (ecb.pm) instead for now (more control, simpler ops)
- If revisited: Use rclone + Google Drive API with incremental syncs

**Status:** Deferred. Current approach: SFTP to ecb.pm drop-box.
