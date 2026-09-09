<!--
 - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# LibreSign

Domain language for LibreSign signing, policy, and audit concepts.

## Signer geolocation

**Signer geolocation policy**:
Policy that defines whether device-reported location participates in the signing flow.
_Avoid_: GPS policy, GeoIP, IP geolocation

**Device-reported location**:
Coordinates and collection status reported by the signer's device at signing time; not verified proof of physical presence.
_Avoid_: verified location, proof of location, GPS proof

**Geolocation mode**:
Effective policy value: `disabled`, `optional`, or `required`. `optional` means the requester may elevate selected signers; it does not by itself mean soft collection at sign time.
_Avoid_: allowRequesterOverride (legacy ignored field)

**Per-signer geolocation requirement**:
Frozen requirement on a sign request for one signer: `disabled` or `required`. The browser Geolocation API is requested only when this value is `required`.
_Avoid_: optional as a frozen requirement value

**Geolocation collection status**:
Outcome of a collection attempt: `collected`, `denied`, `unavailable`, or `skipped`. The current signing API accepts a successful submit with geolocation only as `collected` when required; other statuses are not used by the frontend happy path.
_Avoid_: conflating with `collect_metadata` (IP / User-Agent)
