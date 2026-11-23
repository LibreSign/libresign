<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later
-->

# External signing session consumer contract (#9600 -> #9603)

The session store is a **server-internal** persistence boundary, not an
authorization service, controller or provider adapter. Only orchestration
already authorized by LibreSign's SignRequest policies may call it.

## Sequence

1. Resolve the authenticated signing actor, existing SignRequest and permitted
   integration and credential; obtain certificate PEM and **exact source PDF
   bytes and revision** from trusted Nextcloud storage.
2. Call `ExternalPdfSigningService::prepare()` with that certificate. Pass the
   returned **trusted** `PreparedExternalOperation` to
   `ExternalSigningSessionStore::create()`. Never accept client-supplied
   prepared state, certificate fingerprint or payload. The session ID is an
   opaque locator, not an authorization bearer token.
3. On every HTTP request, reauthorize the actor and SignRequest. Reload current
   PDF bytes/revision and expected certificate, then call `resume()` or
   `advance()`. A changed integration revision, certificate, source document
   or expired session must fail closed. Never trust client revision/payload.
4. Follow the declared state machine. Authorization handles and SAD are stored
   by `saveContinuation()` and retrieved by `readContinuation()`; neither
   appears in `get()`, logging or ordinary audit events.
5. Before **any** external private-key call, atomically acquire
   `READY_TO_SIGN -> SIGNING_PENDING` with the current CAS revision. The
   provider adapter must never retry the private-key request automatically.
   A timeout after submission moves to `UNCERTAIN`, even if a response may
   have been lost. A crashed worker leaves `SIGNING_PENDING`: do not reclaim
   or repeat it after a lease/expiry. Authorized reconciliation may use a
   provider-supported status mechanism, **without a second signHash**.
6. Authenticate the provider's signature response/continuation and validate
   returned raw signature against the prepared operation before calling
   `receiveSignature()`. The store **does not perform CSC transport or
   cryptographic verification**; `ExternalPdfSigningService::complete()`
   performs PDF/CMS and signer-certificate validation.
7. Acquire `SIGNATURE_RECEIVED -> COMPLETING` once with CAS, then perform
   **document publication and SignRequest transition atomically/idempotently
   in the workflow layer**. Only after publication succeeds, call
   `markCompleted()`. If the workflow crashes after publication but before
   marking complete, reconcile against the authoritative published document
   and workflow state; **never republish just because the session is
   `COMPLETING`**. It does not automatically transition to `UNCERTAIN`
   (which denotes an unconfirmed private-key operation).
8. Expired, rejected, failed and completed sessions delete protected prepared
   and continuation contents; audit rows retain only event, actor, session ID
   and timestamp. No raw signatures or PDF contents belong in audit.

## Concurrency and guarantees

All transitions use a database compare-and-swap over session ID, actor, previous
status and revision. Competing requests get a conflict, not duplicate execution.
An idempotent completed read is not a second publication permit. The
application can guarantee **at-most-one automatic signHash dispatch per
session**, not that an external provider executed exactly once. Provider
reconciliation and publication idempotency require explicit #9603 integration.

## Responsibilities excluded from #9600

This change intentionally does not discover credentials (#9599), implement
CSC transport (#9602), implement SignRequest authorization and publication or
controllers (#9603), and does not expose a frontend.

## Validation boundary

Unit tests confirm the state graph. DB tests must run against a disposable,
migration-upgraded Nextcloud runtime, with separate persistent volumes and
no credentials to real providers. A passing enum test alone is insufficient
proof of multi-process, PostgreSQL, MariaDB or actual provider behavior.
