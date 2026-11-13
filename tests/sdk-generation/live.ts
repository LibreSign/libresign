/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import * as assert from 'node:assert/strict'
import { Agent } from 'node:https'
import axios, { type AxiosInstance } from 'axios'
import { CertificatesApi, IdentityApi, PoliciesApi } from '../../build/sdk-generation/typescript/api'
import { Configuration } from '../../build/sdk-generation/typescript/configuration'

async function main() {
  const basePath = process.env.SDK_TEST_URL
  const username = process.env.SDK_TEST_USER
  const password = process.env.SDK_TEST_PASSWORD
  if (!basePath || !username || !password) {
    throw new Error('Set SDK_TEST_URL, SDK_TEST_USER and SDK_TEST_PASSWORD')
  }
  const url = new URL(basePath)
  if (url.protocol !== 'https:' || !(url.hostname === 'localhost' || url.hostname.endsWith('.localhost'))) {
    throw new Error('Only local HTTPS Nextcloud instances are supported')
  }
  // NCDD uses a local self-signed certificate; never use this for remote hosts.
  const instance = axios.create({ httpsAgent: new Agent({ rejectUnauthorized: false }), proxy: false, timeout: 15000 })
  if (!await runLiveChecks(basePath, username, password, instance)) {
    process.exitCode = 1
  }
}

export async function runLiveChecks(basePath: string, username: string, password: string, instance: AxiosInstance): Promise<boolean> {
  const config = new Configuration({ basePath, username, password })
  const identity = new IdentityApi(config, undefined, instance)
  let failed = false
  try {
    const account = await identity.accountMe({ apiVersion: 'v1', oCSAPIRequest: true })
    assert.equal(account.status, 200)
    assert.equal(account.data.ocs.meta.statuscode, 200)
    assert.equal(typeof account.data.ocs.data.account.uid, 'string')
    console.log('account-me: HTTP 200, OCS envelope and typed account fields verified')
  } catch (error) {
    failed = true
    console.log('account-me failed: HTTP', axios.isAxiosError(error) ? error.response?.status : 'assertion failure')
  }
  try {
    const policy = await new PoliciesApi(config, undefined, instance).policyGetSystem({ apiVersion: 'v1', policyKey: 'signature_flow', oCSAPIRequest: true })
    assert.equal(policy.status, 200)
    assert.equal(typeof policy.data.ocs.meta.statuscode, 'number')
    assert.ok(policy.data.ocs.data)
    console.log('policy-get-system: HTTP 200, OCS envelope verified with administrator')
  } catch (error) {
    failed = true
    console.log('policy-get-system failed: HTTP', axios.isAxiosError(error) ? error.response?.status : 'assertion failure')
  }
  try {
    const pdf = await new CertificatesApi(config, undefined, instance).certificatePolicyGetCertificatePolicy({ responseType: 'arraybuffer' })
    assert.equal(pdf.status, 200)
    assert.match(String(pdf.headers['content-type']), /application\/pdf/i)
    assert.equal(Buffer.from(pdf.data as unknown as ArrayBuffer).subarray(0, 5).toString(), '%PDF-')
    console.log('certificate-policy: HTTP 200, PDF content type and bytes verified')
  } catch (error) {
    if (!axios.isAxiosError(error) || error.response?.status !== 404) {
      failed = true
      console.log('certificate-policy failed: HTTP', axios.isAxiosError(error) ? error.response?.status : 'assertion failure')
    } else {
      console.log('certificate-policy: HTTP 404 (no configured policy PDF); binary response not verified')
    }
  }
  const invalid = new IdentityApi(new Configuration({ basePath, username, password: 'deliberately-invalid-test-password' }), undefined, instance)
  await assert.rejects(() => invalid.accountMe({ apiVersion: 'v1', oCSAPIRequest: true }), (error: unknown) => axios.isAxiosError(error) && error.response?.status === 401)
  console.log('account-me with invalid credentials: HTTP 401 verified')
  return !failed
}
if (require.main === module) {
  main().catch(error => {
    if (axios.isAxiosError(error)) {
      console.error('Live request failed with HTTP status', error.response?.status ?? 'unavailable', 'code', error.code ?? 'none')
    } else {
      console.error(error instanceof Error ? error.message : 'Live test failed')
    }
    process.exitCode = 1
  })
}
