# Release Notes

Production has real members and real data, so what reaches it is recorded in
`CHANGELOG.md` at the repo root. Write for the people who use the app (bureau,
instructors, members), not for developers — the commit log already covers that.

## When

A **release** is a production deploy, not a commit and not a staging deploy.
Update `CHANGELOG.md` in the same sitting as the deploy, ideally just before it.

## What actually ships (check this first)

`/opt/deploy/auto-deploy.sh` does `git reset --hard origin/main` on prod and runs
`migrate --force`. It deploys **everything on `main` since prod's current commit**,
not just the change you had in mind — and staging-only work on `main` goes with it.
Before running it:

```bash
PROD=$(ssh prod.clubcep.eu "cd /opt/deploy/apps/divingclub-prod && sudo -u clubcep git rev-parse --short HEAD")
git fetch origin main && git log --oneline $PROD..origin/main   # this is the release
git diff --name-only $PROD..origin/main -- database/migrations  # these will run on prod data
```

If that range contains anything not yet approved for production, don't run the
script; deploy a narrower set or ask. The range *is* the release-notes source.

## Format

- One section per release, newest first: `## YYYY-MM-DD — prod at <short sha>`.
- Anything on `main`/staging that hasn't gone to prod yet lives under `## Unreleased`;
  move it into a dated section when it ships.
- Group as **New**, **Changed**, **Fixed**. One line each, in plain language, saying
  what changed for the user (not which file). Merge related commits into one line.
- Add **Data changes** whenever a migration rewrites existing rows (e.g. the club
  timezone correction of registration dates) — say what was touched and how to check it.
- Add **Needs attention** for anything an admin must do or re-check after the deploy
  (a setting to set, a rule that needs re-saving, a queue to watch).
- Skip pure refactors, test-only, docs and CI commits unless they change behavior.
