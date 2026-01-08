When adding new features:

For PHP, add unit tests or at least integration tests (if testing stack exists).

For Node blockchain service, add tests for:

Parsing transactions

Callback payload format

For React:

At least test critical logic hooks (like “ticket purchase flow”).

When changing existing behavior:

Explain in comments what changed and why (if non-trivial).

Always ensure code compiles/builds with no TypeScript/PHP errors before finishing.