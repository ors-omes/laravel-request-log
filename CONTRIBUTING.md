# Contributing

1. Fork and create a branch from `main`.
2. `composer install`
3. Make the change with tests. Bug fixes need a regression test.
4. Run the quality gates:

   ```bash
   composer test
   composer analyse
   composer format:test
   ```

   Changes to the migration or queries should also pass against PostgreSQL:

   ```bash
   REQUEST_LOG_TEST_DB=pgsql DB_DATABASE=request_log_test DB_USERNAME=postgres DB_PASSWORD=secret composer test
   ```

5. Open a pull request describing the behaviour change. Changes to stored
   columns, config keys, the reference format or the API shape are breaking
   and need a CHANGELOG entry.

Security issues: see [SECURITY.md](SECURITY.md), do not open a public issue.
