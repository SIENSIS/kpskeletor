# Next session

> Run it by saying: **`executa deploy/next.md`** (or `run deploy/next.md`) in a Claude Code
> session on the project repository. It finds the next session and runs it.

1. `git fetch --all --prune && git checkout main && git pull`.
2. If `docs/progress.md` does not exist on `main` nor on a `session-00-*` branch, the project has
   not started: run `deploy/00-bootstrap.md`.
3. Otherwise find the highest session N that is **done**: look at `docs/progress.md` on `main` and
   on the unmerged session branches (`git branch -r --no-merged origin/main`, or the head branches
   of the pull requests "Session NN: …").
   - If a session branch exists whose session is **not** marked done (no `deploy-status/NN-*/`
     report), that session stopped half way: run `deploy/continue.md` for it.
   - Otherwise the next session is N+1: run `deploy/<N+1>-*.md`. It starts by merging the finished
     branches into `main`.
4. Say which session you are going to run (number and title), then follow its file from start to
   end.
5. If session 36 is done: it merges itself into `main`; if its branch is still unmerged, merge it
   with the procedure of the session files. Then tell me the project is complete and to create the
   `v1.0.0` tag.
