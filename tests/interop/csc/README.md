# CSC external-provider interoperability proof

This is an **opt-in development test**, not an application runtime dependency and not a production signing provider. It exercises two OAuth 2.0 authorization-code legs on Cleverbase's documented public **service stub**, signs the **actual CMS signed-attributes digest** of a prepared PDF using a remote CSC key, and asks SignerPHP to assemble a PAdES B-B PDF.

The resulting signature is cryptographically verifiable but uses an **untrusted test certificate**. The stub does not provide a real signer's consent, identity, qualified certificate or a regulated signing service. In particular, a green result here does **not** prove the LibreSign browser-to-provider OAuth callback workflow; that remains a separate requirement before enabling real personal credentials.

## Prerequisites

- Python 3 and the development-only `requests` package.
- PHP 8.3+ with OpenSSL and an installed unprefixed `signerphp/pdf-signer` Composer dependency, normally `vendor/autoload.php`. The PHP steps may run inside a disposable Nextcloud development container with that vendor directory mounted.
- Optional: Poppler `pdfsig` for independent PDF validation.
- Outbound HTTPS access to **only** `trust-driver-stub-hash-signing.cleverbase.com` for the Python authorization steps.
- An isolated scratch directory; no personal, private or production document must be supplied.

Use the two **public, stub-only** development OAuth client parameters described in [Cleverbase's client registration documentation](https://cleverbase.com/en/dev-docs/before-you-build/registering-your-client/). Provide them through environment variables; do **not** commit them into a repository or record OAuth codes, access tokens or SAD values in logs.

## Run the proof

From the LibreSign repository root in a disposable development environment:

```sh
umask 077
export LIBRESIGN_CSC_INTEROP_DIR="$(mktemp -d)"
export LIBRESIGN_CSC_INTEROP_AUTOLOAD="$PWD/vendor/autoload.php"
# Set LIBRESIGN_CSC_STUB_CLIENT_ID and LIBRESIGN_CSC_STUB_CLIENT_SECRET
# using the published Cleverbase development-only client parameters.

python3 tests/interop/csc/stub_oauth.py certificate
php tests/interop/csc/stub_pdf.php prepare
python3 tests/interop/csc/stub_oauth.py sign
php tests/interop/csc/stub_pdf.php complete
pdfsig "$LIBRESIGN_CSC_INTEROP_DIR/provider-signed.pdf"
```

The certificate step uses `credentials/list` and `credentials/info` to select the one compatible RSA-SHA256 credential. The PDF step prepares a PDF using SignerPHP. The second OAuth step requests a hash-bound SAD with scope `credential` and submits exactly one `signatures/signHash` operation. The final PHP step checks the returned signature against the prepared hash and certificate before embedding it. **No automatic retry of `signHash` is performed.**

Inspect the output for a valid SHA-256 PDF signature covering the document and a test/untrusted issuer. Destroy the scratch directory after inspection:

```sh
rm -rf -- "$LIBRESIGN_CSC_INTEROP_DIR"
unset LIBRESIGN_CSC_STUB_CLIENT_ID LIBRESIGN_CSC_STUB_CLIENT_SECRET
```

## Interoperability limits and follow-up

This proof only covers CSC API **v1.0.4**, RSA PKCS#1 v1.5, SHA-256 and a synchronous `signHash` response. It does not cover CSC v2.2, ECDSA P-256, production OAuth registration, the mobile approval flow, token refresh, polling or a real signer-bound session. Cleverbase's acceptance environment requires a registered OAuth client and a test signer using the real app.

Production readiness must additionally demonstrate server-owned, single-use OAuth state, exact redirect allowlisting, per-actor binding, encrypted access token/SAD retention and expiry, verified credential authorization for the **exact** document's CMS hash, failure/timeout reconciliation without blind signing retries, PDF/CMS validation, and native LibreSign publication on supported Nextcloud/DB versions.

No stub credential or fake provider response should be displayed to end users as if it were a real personal signing credential.
