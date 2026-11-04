# Apply review feedback

> Run it by saying: **`executa deploy/review-fixes.md NN`** followed by your comments (or point to
> the review comments of the pull request).

1. `git fetch --all --prune`. If the branch of session NN is **not merged** into `main` yet, work on
   it. If it is already merged, create `session-NN-fixes` from `main`.
2. Read `CLAUDE.md`, `docs/progress.md`, `deploy/NN-*.md` and `deploy-status/NN-*/README.md`.
   Collect the feedback: the comments given with the command and the unresolved review comments
   of the session's pull request.
3. Apply each one inside the session's scope, with a test for each fix; ask before changing a
   public convention or weakening security.
4. Run all checks; update `deploy-status/NN-*/README.md` (a "Review fixes" section, and new
   screenshots if the screens changed) and `docs/progress.md`.
5. Push (to the same branch, or open a pull request "Session NN: review fixes"), and reply on the
   pull request and to me with what changed for each comment. If you created `session-NN-fixes`,
   the next session merges it like any other session branch.
