# Security Policy

This package stores HTTP traffic, so a redaction gap or an access-control bug
can expose personal data or credentials. Please report such issues privately.

## Reporting a vulnerability

Use GitHub's private vulnerability reporting:
<https://github.com/ors-omes/laravel-request-log/security/advisories/new>

Please include the package version, Laravel/PHP versions, the configuration
involved and a minimal reproduction. Do not open a public issue and do not
include real user data in the report.

We aim to acknowledge reports within 3 working days and to ship a fix or a
mitigation before any public disclosure.

## Supported versions

| Version | Supported |
|---|---|
| 0.x (beta) | Latest pre-release only |

## Scope

In scope: values stored without redaction under the default configuration,
bypasses of the `viewRequestLogs` gate or the viewer scope callback, script
execution in the viewer, and anything that lets logging break or slow down a
request it observes.

Redaction of keys or formats the default list does not cover is a
configuration matter (`redact.extra_keys`, `redact.value_patterns`); feel free
to propose new defaults in a regular issue.

## Recommendations for operators

- Store logs on a dedicated connection with its own credentials and backups.
- Grant `viewRequestLogs` to platform operators only; exception snippets
  contain source code.
- Keep retention short and run `php artisan request-log:audit` after changing
  routes or payload shapes.
