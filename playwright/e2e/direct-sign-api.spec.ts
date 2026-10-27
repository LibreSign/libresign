/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { expect, test } from '@playwright/test'
import { writeFile } from 'node:fs/promises'
import { login } from '../support/nc-login'
import { configureOpenSsl, setSystemPolicy } from '../support/nc-provisioning'
import { getSmallValidPdfBuffer } from '../support/pdf-fixtures'

const endpoint = './ocs/v2.php/apps/libresign/api/v1/sign/direct'
if (process.env.PLAYWRIGHT_CHROME_BIN) {
  test.use({ launchOptions: { executablePath: process.env.PLAYWRIGHT_CHROME_BIN } })
}

test('direct signing returns the signed PDF and replays the same response', async ({ page }, testInfo) => {
  test.setTimeout(90000)
  await login(page.request, 'admin', 'admin')
  await configureOpenSsl(page.request, 'Direct E2E', { C: 'BR', O: 'LibreSign' })
  await setSystemPolicy(page.request, 'identify_methods', JSON.stringify({ factors: [
    { name: 'account', enabled: true, requirement: 'required', signatureMethods: { clickToSign: { enabled: true } } },
  ] }))
  const key = `direct-e2e-${Date.now()}`
  const payload = {
    name: `direct-e2e-${Date.now()}.pdf`,
    file: { base64: (await getSmallValidPdfBuffer()).toString('base64') },
    method: 'clickToSign',
  }
  const headers = { 'OCS-APIRequest': 'true', 'Idempotency-Key': key }
  const response = await page.request.post(endpoint, { headers, data: payload })
  const body = await response.body()
  console.log('FIRST_SIGN:', response.status(), response.headers()['content-type'], body.length)
  expect(response.status()).toBe(200)
  expect(response.headers()['content-type']).toContain('application/pdf')
  expect(body.subarray(0, 5).toString()).toBe('%PDF-')
  expect(body.includes(Buffer.from('/ByteRange'))).toBe(true)
  expect(response.headers()['x-libresign-file-uuid']).toMatch(/^[0-9a-f-]{36}$/)
  expect(response.headers()['cache-control']).toContain('no-store')
  await writeFile(testInfo.outputPath('signed.pdf'), body)
  const repeated = await page.request.post(endpoint, { headers, data: payload })
  expect(repeated.status()).toBe(200)
  expect(await repeated.body()).toEqual(body)
  expect(repeated.headers()['x-libresign-file-uuid']).toBe(response.headers()['x-libresign-file-uuid'])

  const different = await page.request.post(endpoint, {
    headers, data: { ...payload, name: 'different.pdf' },
  })
  expect(different.status()).toBe(409)

  const invalidKey = await page.request.post(endpoint, {
    headers: { ...headers, 'Idempotency-Key': 'short' }, data: payload,
  })
  expect(invalidKey.status()).toBe(400)

  const withOtherSigner = await page.request.post(endpoint, {
    headers: { ...headers, 'Idempotency-Key': `other-signer-${Date.now()}` },
    data: { ...payload, signers: [{ displayName: 'Other' }] },
  })
  expect(withOtherSigner.status()).toBe(400)

  const remoteUrl = await page.request.post(endpoint, {
    headers: { ...headers, 'Idempotency-Key': `remote-url-${Date.now()}` },
    data: { ...payload, file: { url: 'https://example.com/remote.pdf' } },
  })
  expect(remoteUrl.status()).toBe(400)

  const disabledMethod = await page.request.post(endpoint, {
    headers: { ...headers, 'Idempotency-Key': `disabled-method-${Date.now()}` },
    data: { ...payload, method: 'password', token: 'incorrect' },
  })
  expect(disabledMethod.status()).toBe(403)

  const unsupportedMethod = await page.request.post(endpoint, {
    headers: { ...headers, 'Idempotency-Key': `unsupported-method-${Date.now()}` },
    data: { ...payload, method: 'emailToken' },
  })
  expect(unsupportedMethod.status()).toBe(400)

  const identityOverride = await page.request.post(endpoint, {
    headers: { ...headers, 'Idempotency-Key': `identity-override-${Date.now()}` },
    data: { ...payload, identifyValue: 'another-account' },
  })
  expect(identityOverride.status()).toBe(400)
})
