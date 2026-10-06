<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# LibreSign usage statistics schema

`schema-v1.json` is the metric contract LibreSign registers with the usage statistics server. It uses only the fields the server accepts. This file records what the JSON cannot hold: where each value comes from and how a past month is rebuilt.

A registered schema version is immutable. Any change to a metric, including a new optional one, needs a new `schemaVersion`. A new metric must not shorten the history of the metrics we can already rebuild.

## Reports

- Each report covers one calendar month in UTC. The start is inclusive and the end exclusive.
- The *current* report covers the most recent closed month and includes the snapshots of the environment at collection time.
- A *historical* report rebuilds an older month from stored events. It never includes snapshots, because LibreSign has no record of past versions or settings.
- `0` is a known zero: no activity in a month that can be rebuilt. A missing metric means its value is unknown for that month.
- Report generation fails instead of leaving a metric out when that metric should be available but is missing or invalid.
- Every metric has exactly one collector in `lib/Service/UsageStatistics/Collector/`, and a test enforces this.

## Counting rules

- A signing request is one participant expected to sign. Observers are counted only in `participants.observers_added`.
- An envelope and each of its files carry their own signing request for the same signer. Signing metrics count only requests on top-level files, that is, standalone files and envelopes, never the files inside an envelope.
- Identification documents also create LibreSign files and signing requests, but they are not signing requests. They are left out of every document and signing metric.
- Counts are aggregates of events and never deduplicate people.
- Deleting a signing request, deleting the signed file and `occ libresign:crl:cleanup` remove rows. A rebuilt month is therefore a lower bound of what happened.

## Metrics

### Snapshots: current report only

| Metric | Source of truth |
|---|---|
| `nextcloud.version` | `OCP\ServerVersion`, major.minor.patch |
| `nextcloud.users_total` | `IUserManager::countUsersTotal()`. Unavailable when a user backend cannot count. |
| `nextcloud.users_active_30d` | Users whose last login is within 30 days of collection |
| `nextcloud.background_job_mode` | `core` / `backgroundjobs_mode`, reduced to `ajax`, `webcron`, `cron` or `other` |
| `php.version` | PHP major.minor |
| `database.type` | `IDBConnection::getDatabaseProvider(true)` |
| `database.version` | The server version reduced to major.minor. Unavailable on Oracle. |
| `system.os_family` | `PHP_OS_FAMILY` |
| `system.architecture` | Machine type normalized to `x86_64`, `aarch64` or `other`. Never the host name or kernel. |
| `libresign.version` | Installed LibreSign version |
| `libresign.signature_engine` | `signature_engine` setting. Anything other than `PhpNative` runs `JSignPdf`. |
| `libresign.signing_mode` | `signing_mode` setting, `sync` or `async` |
| `certificate.engine` | `certificate_engine` setting: `openssl`, `cfssl`, `none` or `other` |
| `certificate.root_ca_configured` | `ca.pem` and `ca-key.pem` exist in the stored PKI path. The value is read without creating the path or contacting CFSSL. |

No certificate subject, issuer, serial number, CA identifier, path or URL is ever reported.

### Monthly counters

| Metric | Event timestamp | History |
|---|---|---|
| `documents.files_created` | `libresign_file.created_at` of top-level files that are not envelopes and have no files | Since the first install |
| `documents.envelopes_created` | `libresign_file.created_at` of top-level files stored as envelopes or holding files | Envelopes arrived in 12.2/13. Earlier months are a known zero. |
| `signatures.requests_created` | `libresign_sign_request.created_at`, signers only | Since the first install |
| `signatures.completed` | `libresign_sign_request.signed`, signers only | Since the first install |
| `signatures.rejected` | `libresign_sign_request.rejected_at`, signers only | Rejection arrived in 15.0 together with the column. Earlier months are a known zero. |
| `participants.observers_added` | `libresign_sign_request.created_at`, observers only | Observers arrived together with `participant_role`. Earlier months are a known zero. |
| `certificate.certificates_issued` | `libresign_crl.issued_at` of `leaf` certificates | See below |
| `certificate.certificates_revoked` | `libresign_crl.revoked_at` of `leaf` certificates | See below |

Certificates are recorded only since 12.1 (OpenSSL) and 12.2/13 (CFSSL). The certificate counters are known for every month when the first recorded certificate is not later than the first LibreSign file. This is a fresh install, which creates its root CA first. Otherwise the instance was upgraded, and they are known only for months that start after the first recorded certificate.

The historical series starts at the month of the first LibreSign file.
