# Decide open questions, one at a time

> Run it by saying: **`executa deploy/decide.md`** (or `run deploy/decide.md`) in a Claude Code
> session on the project repository, in the same chat as the session that raised the questions
> or in a new one. Optionally add a session number (`executa deploy/decide.md 00`) to only go
> through that session's questions.

You (Claude) will ask me each open question **one at a time**, record my answer immediately,
and apply all the decisions at the end.

## 1. Collect the open questions

1. `git fetch --all --prune && git checkout main && git pull`. If the session that raised the
   questions is not merged yet, work on its branch instead (so its code can be adjusted too).
2. Read `CLAUDE.md`, `docs/decisions.md`, `docs/progress.md` and `docs/specification.md`.
3. The open questions are the entries of `docs/decisions.md` with status **Open** (in session
   order). If `docs/decisions.md` does not exist yet, or a session listed its doubts only in its
   report, pull request or this chat, first copy them into `docs/decisions.md` as Open entries
   with the format below, and tell me how many there are.
4. Put first the ones marked **(decide)** (they change a public convention or security), then the
   rest. Tell me the total and that I can say at any moment:
   - **"accepta la resta"**: accept the proposal of every remaining question;
   - **"atura"**: stop here, keep what is answered and leave the rest Open;
   - **"explica-m'ho"**: explain the current question in more detail, with an example.

## 2. Ask one question at a time

For each question, in my language (Catalan), short and self-contained (I may not see earlier
messages):

- **Title** and its ID (`D-00-03`), and where it appears (specification section, session, file).
- **The problem**, in two or three sentences, with a concrete example when it helps.
- **The options:** your proposal first, marked **(recomanada)**, and one or two real alternatives,
  each with its consequence in one line (what changes, what it costs, security impact).

Ask with the question tool when it is available (one question per call, the options as choices,
the proposal first; I can always type my own answer). Otherwise ask in plain text with lettered
options (A, B, C) and **wait for my answer** before going on.

After each answer:

- if it is unclear or contradicts an earlier decision, ask a short follow-up;
- **write it immediately** in `docs/decisions.md` (status **Decided**, date, the decision in my
  words and in a clear English sentence, the reason if I gave one, what must change) and save the
  file, so nothing is lost if the session stops;
- confirm in one line ("Anotat: D-00-03 → opció B") and go to the next question.

Questions that are not marked (decide) can be answered the same way; with "accepta la resta"
record them as **Accepted (proposal)**.

## 3. Apply the decisions

When all questions are answered (or I said "atura"):

1. Show a summary table: ID, title, decision.
2. Apply them:
   - `docs/specification.md`: change every passage the decisions contradict, so later sessions
     read the decided behaviour; mention the decision ID in the commit, not in the text;
   - `deploy/NN-*.md` of future sessions, when a decision changes their scope;
   - `CLAUDE.md`, when a decision changes a convention;
   - code and tests already written, when a decision changes what an earlier session built (with a
     test for each change);
   - mark each applied entry **Applied** in `docs/decisions.md` with the files changed.
3. Run the checks (`vendor/bin/phpunit`, PHPStan, php-cs-fixer) and show the results.
4. Commit on the current branch (the open session branch) or on a new branch `decisions-NN`
   created from `main`, push, and open (or update) a pull request "Decisions NN" with the summary
   table. The next session merges it like a session branch.
5. Reply with the summary and the next step (usually `executa deploy/next.md`).

## Format of `docs/decisions.md`

```markdown
# Decisions

Decisions here override `docs/specification.md` when they disagree (the specification is updated
when a decision is applied).

| ID | Session | Title | Status |
|---|---|---|---|
| D-00-03 | 00 | Module discovery namespaces | Applied |

## D-00-03 — Module discovery namespaces (decide)

- **Where:** specification "Directory layout"; `CLAUDE.md`.
- **Problem:** …
- **Proposal:** …
- **Alternatives:** A) … B) …
- **Status:** Open | Decided | Accepted (proposal) | Applied
- **Decision (YYYY-MM-DD):** …
- **Applied in:** files / commits
```
