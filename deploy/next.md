# Next session

> Run it by saying: **`executa deploy/next.md`** (or `run deploy/next.md`) in a Claude Code
> session on the project repository. It finds the next session and runs it.

1. `git fetch --all --prune && git checkout main && git pull`.
2. If `docs/decisions.md` has **Open** entries marked (decide), tell me how many and ask whether to
   answer them first (recommended: then follow `deploy/decide.md`) or continue with the next
   session.
3. If `docs/progress.md` does not exist on `main` nor on the head branch of a pull request
   "Session 00: …", the project has not started: run `deploy/00-bootstrap.md`.
4. Otherwise find the highest session N that is **done**. Sessions are identified by their pull
   request title "Session NN: …" and by the branch recorded for them in `docs/progress.md` (a
   `session-NN-*` name or a branch assigned by the environment): look at `docs/progress.md` on
   `main` and on the unmerged branches of those pull requests (`git branch -r --no-merged
   origin/main`).
   - If a session's branch exists but the session is **not** marked done (no `deploy-status/NN-*/`
     report), that session stopped half way: run `deploy/continue.md` for it.
   - Otherwise the next session is N+1: run `deploy/<N+1>-*.md`. It starts by merging the finished
     branches into `main`.
5. Say which session you are going to run (number and title), then follow its file from start to
   end.
6. If session 36 is done: it merges itself into `main`; if its branch is still unmerged, merge it
   with the procedure of the session files (never force-push; if `main` cannot be pushed, ask me
   to merge the pull request). Then tell me the project is complete and to create the `v1.0.0`
   tag.
