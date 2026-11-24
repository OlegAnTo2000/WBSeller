## SSH requires manual user approval

- Before every SSH request, show the user the target server, the exact command or connection action, and its purpose. Ask for separate, explicit manual approval and wait for the user's confirmation before making the request.
- This applies to Codex, Claude Code, Cursor, and every other AI agent. It covers opening a connection, every command in an existing session, read-only checks, retries, and indirect SSH access through scripts, tools, subagents, or automations, including SCP, SFTP, tunnels, and Git over SSH.
- Auto-approval, allowlists, saved permissions, and approval for a previous request never substitute for the user's manual confirmation of the current request. If the tool would auto-approve the request, ask the user in the conversation before invoking it. If confirmation is unavailable, do not connect or run the command.

## Language

Write intermediate technical findings in English. Present final responses, results, and data summaries for the user in Russian.

## Git Commit Messages

Always generate Git commit messages in Russian.

Requirements:
- Commit messages MUST be written in Russian.
- Do not generate commit messages in English.
- Keep technical identifiers, class names, method names, filenames and other code entities in their original form.
- Keep the message concise and descriptive.

## ClickHouse: Alias Conflicts

- Do not give a computed expression or aggregate the name of a source column in the same SELECT: `toString(week) AS week`, `toString(date) AS date`, `sum(cost) AS cost`, `argMax(value, updated_at) AS value`.
- ClickHouse may substitute the alias expression into WHERE, GROUP BY, HAVING, ORDER BY, and other SELECT expressions. The result is a String compared with Date/Date32 (`NO_COMMON_TYPE`), a nested aggregate (`ILLEGAL_AGGREGATION`), or a changed query meaning.
- Use unique internal names (`week_start`, `statistics_date`, `cost_sum`, `latest_value`). If the contract requires the original names, assign them in the outer SELECT after filtering and aggregation. The source columns must not appear there under a conflicting name.
- Check the entire SELECT and all subqueries, including SQL in tests. Preserve filter types, grouping, ordering, and response field names. Do not work around the error by comparing dates as strings, changing global ClickHouse settings, or suppressing the exception.
- A test with a mocked ClickHouse does not verify SQL parsing. Confirming a fix requires a query against a test ClickHouse. If that query was not run, say so explicitly.

## Keeping Agent Instructions in Sync

`AGENTS.md` holds the shared rules. `CLAUDE.md` imports it with `@AGENTS.md` and adds only Claude Code–specific content: do not copy shared rules into it. `.cursorrules` repeats the shared rules Cursor needs. When editing shared rules, update `AGENTS.md` and `.cursorrules` in the same change, and `CLAUDE.md` only if its Claude-specific part is affected. Preserve tool-specific content.
