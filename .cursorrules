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

When editing any of `AGENTS.md`, `CLAUDE.md`, or `.cursorrules`, update all three in the same change. Keep shared instructions consistent across all three and preserve tool-specific content.
