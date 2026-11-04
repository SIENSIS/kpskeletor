# Continue an unfinished session

> Run it by saying: **`executa deploy/continue.md NN`** (NN = the session number), or let
> `deploy/next.md` call it. Use it when a session stopped before finishing (context full, time
> limit, error).

1. `git fetch --all --prune`, switch to the branch of session NN (`session-NN-*`, or the head
   branch of the pull request "Session NN: …") and `git pull`.
2. Make sure the earlier sessions are in the branch: if `main` has moved since the branch was
   created, `git merge origin/main` (resolve conflicts; stop and tell me if they touch code).
3. Read `CLAUDE.md`, `docs/progress.md`, `deploy/NN-*.md` and the sections of
   `docs/specification.md` it lists; then `git log --oneline origin/main..HEAD`, the pull request
   and `deploy-status/NN-*/` (if it exists) to see what is already done.
4. Make a list of what is missing compared with the session's "Scope" and "Done when", show it,
   and carry on until the session is complete.
5. Finish exactly as `deploy/NN-*.md` says: checks, `deploy-status/NN-*/` report with screenshots,
   `docs/progress.md`, commits, push, pull request created or updated, short report.
