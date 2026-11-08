# Native Nextcloud signing-completion webhook (contract v1)

LibreSign 16 (`main`, Nextcloud 36) emits `OCA\Libresign\Events\SigningRequestCompletedEvent` when the **aggregate signing workflow** changes from a non-signed status to `SIGNED`. This is different from the internal per-signature `SignedEvent`.

- A document is completed only when its workflow status becomes `SIGNED`.
- An envelope produces **one logical completion event for the envelope**, after all included documents meet the existing completion rules. Partial signatures do not emit this event.
- The existing LibreSign workflow semantics apply: `SIGNED` can include a finished workflow in which some signers rejected, depending on policy. It does **not** assert that every invited person signed or that every document has an identical set of signatures.
- Legacy API callbacks remain unchanged, including their configuration and delivery behavior. Using both mechanisms during migration may produce notifications through both transports.

## Register

Enable the Nextcloud bundled app as an administrator:

```shell
php occ app:enable webhook_listeners
```

Use the official Nextcloud webhook OCS API (`POST /ocs/v1.php/apps/webhook_listeners/api/v1/webhooks`) with an administrator or delegated webhook-administrator account. Configure `httpMethod=POST`, `uri` as a trusted HTTPS receiver, `event=OCA\Libresign\Events\SigningRequestCompletedEvent`, and `eventFilter={}` to receive all completions. Choose the `authMethod` and `authData` supported by the installed Nextcloud version, preferably a receiver-specific authentication header, and verify the webhook configuration with `php occ webhook_listeners:list`. The optional Orchestration Gateway app offers an administrative UI.

For a specific aggregate request, set the event filter to `{"event.requestUuid":"<request-uuid>"}`. Do **not** filter by `user.uid` for external/worker-driven signatures: the ambient Nextcloud user can be absent or different from the signer.

The OCS API requires an authenticated administrator and has version-specific parameter encoding. Follow the installed Nextcloud API schema for creating the listener rather than constructing a LibreSign-specific callback.

## Wire format

The webhook body uses Nextcloud's standard `event`, `user`, `time` envelope:

```json
{
  "event": {
    "class": "OCA\\Libresign\\Events\\SigningRequestCompletedEvent",
    "contractVersion": 1,
    "eventId": "libresign.workflow.completed.v1:4ce5a91a-3f2c-4cf0-b4a3-8baad19ac82f",
    "requestUuid": "4ce5a91a-3f2c-4cf0-b4a3-8baad19ac82f",
    "requestType": "envelope",
    "status": "completed"
  },
  "user": null,
  "time": 1794170000
}
```

`requestType` is `document` or `envelope`. `requestUuid` is the LibreSign aggregate identifier; it is not a file path or downloadable URL. The stable `eventId` is an **idempotency key**, not a delivery-attempt identifier. Store it at the receiving system and process duplicates only once. This contract does not promise exactly-once delivery, guaranteed order, or immediate delivery. If a workflow is reopened after completion, the same event ID is used; consumers should reconcile the current state using authenticated APIs.

Nextcloud adds `user` from the current server session (which can contain UID and display name), or `null` for background/external flows. LibreSign does not include email, signer identities, credential material, document content, node IDs, private URLs, certificate serials or signing hashes in the public event. Secure access to signed PDFs and evidence requires **separate authenticated and authorized** LibreSign API calls.

The Nextcloud webhook listener enqueues its own background job. Its worker must run (`cron.php` or `occ background:cron`) and have outbound network access to the receiver. Use HTTPS, a narrow destination allowlist and receiver-side authentication; guard against SSRF and avoid sending events to untrusted endpoints. Nextcloud logs and the receiver should be checked for delivery errors. The receiver must be idempotent; retries and delivery guarantees depend on the installed Nextcloud release.

## Compatibility and limitations

`OCP\EventDispatcher\IWebhookCompatibleEvent` has existed since Nextcloud 30. The first release of this contract is intended for LibreSign 16 / Nextcloud 36 only. NC33–NC35 have the underlying interface, but should receive a backport only after their own signing-flow and worker demonstrations pass. The Nextcloud `webhook_listeners` app must be enabled; absence of the app does not disable signing or the legacy callback. The public payload is intentionally small because background-job argument storage can impose limits. No Nextcloud webhook is an authorization mechanism for downloading a PDF.

## Reproducible delivery verification

1. Enable `webhook_listeners` and confirm the LibreSign completion event is registered as above.
2. Configure an authenticated test receiver that captures the raw JSON POST, method, headers and timestamp.
3. Make a request with two signers: after only one signs, assert **no** completion POST; after the last finishes, run the Nextcloud background job worker and assert one logical `completed` event with the correct `requestUuid` and `contractVersion`.
4. Repeat for a multi-document envelope: assert no completion at the intermediate document and one logical envelope event at the aggregate completion.
5. Simulate receiver failure, inspect Nextcloud logs and retry behavior, and confirm the receiver deduplicates by `eventId`.
6. Configure the legacy callback in parallel and confirm it still delivers under its existing semantics.

Do not claim this verification succeeded unless a real Nextcloud worker and external receiver were run. Test credentials must not be committed.
