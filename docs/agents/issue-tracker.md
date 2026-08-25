# Issue tracker: Linear (GitHub is code-only)

Issues, tickets, and specs for this project live in **Linear**, not GitHub Issues. GitHub hosts the code only — the repo, commits, and pull requests. Do not use `gh issue create` or treat GitHub Issues as a source of truth; this repo doesn't use them for tracking.

## Conventions

- **Create a ticket**: create it in Linear. If a Linear MCP/integration is connected in this session, use it. Otherwise, ask the user for the ticket details (title, description, team) rather than guessing a workspace or team key.
- **Read a ticket**: fetch it from Linear via the connected integration, or ask the user for the Linear issue URL/ID (e.g. `ENG-123`) if none is connected.
- **List tickets**: query Linear, filtered by team/state/label as needed.
- **Comment on a ticket**: add the comment in Linear.
- **Apply / remove labels**: update the ticket's labels in Linear.
- **Close a ticket**: move it to the appropriate "done"/"closed" state in Linear.

## Linking code to tickets

Reference the Linear issue ID in PR titles or descriptions (e.g. `[ENG-123] Fix login redirect`) so work stays traceable back to Linear. PRs themselves are just code review — they are not a request or triage surface.

## When a skill says "publish to the issue tracker"

Create or update a ticket in Linear.

## When a skill says "fetch the relevant ticket"

Look it up in Linear (via the connected integration, or ask the user for the ticket ID/URL).
