/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import * as assert from 'node:assert/strict'
import axios, { AxiosError, type AxiosAdapter, type InternalAxiosRequestConfig } from 'axios'
import { AppearanceApi, CertificatesApi, DocumentsApi, IdentityApi, PoliciesApi, RequestsApi } from '../../build/sdk-generation/typescript/api'
import { Configuration } from '../../build/sdk-generation/typescript/configuration'

async function run() {
  let last!: InternalAxiosRequestConfig
  let status = 200
  let body: unknown = { ocs: { meta: { status: 'ok', statuscode: 200 }, data: { account: { uid: 'test', emailAddress: 'test@example.test', displayName: 'Test' }, settings: { canRequestSign: true, hasSignatureFile: false, phoneNumber: '' } } } }
  const adapter: AxiosAdapter = async config => {
    last = config
    const response = { data: body, status, statusText: String(status), headers: {}, config }
    if (config.validateStatus && !config.validateStatus(status)) throw new AxiosError('HTTP error', 'ERR_BAD_RESPONSE', config, undefined, response)
    return response
  }
  const instance = axios.create({ adapter, headers: { 'X-Instance': 'injected' } })
  const configuration = new Configuration({ basePath: 'https://example.test/nextcloud', username: 'test', password: 'secret' })
  const identity = new IdentityApi(configuration, undefined, instance)
  const account = await identity.accountMe({ apiVersion: 'v1', oCSAPIRequest: true })
  assert.equal(last.url, 'https://example.test/nextcloud/ocs/v2.php/apps/libresign/api/v1/account/me')
  assert.equal(last.method, 'get')
  assert.equal(last.headers.get('OCS-APIRequest'), 'true')
  assert.equal(last.headers.get('X-Instance'), 'injected')
  assert.deepEqual(last.auth, { username: 'test', password: 'secret' })
  assert.equal(last.headers.get('Authorization'), undefined)
  assert.equal(account.data.ocs.data.account.uid, 'test')
  const bearerConfiguration = new Configuration({ basePath: configuration.basePath, accessToken: 'synthetic-token' })
  const policies = new PoliciesApi(bearerConfiguration, undefined, instance)
  await policies.policyGetSystem({ apiVersion: 'v1', policyKey: 'a/b ?', oCSAPIRequest: true })
  assert.equal(last.url, 'https://example.test/nextcloud/ocs/v2.php/apps/libresign/api/v1/policies/system/a%2Fb%20%3F')
  assert.equal(last.headers.get('Authorization'), 'Bearer synthetic-token')
  assert.equal(last.auth, undefined)
  const requests = new RequestsApi(configuration, undefined, instance)
  await requests.requestSignatureRequestSignature({ apiVersion: 'v1', oCSAPIRequest: true, requestSignatureRequestSignatureRequest: { name: 'Example', file: { nodeId: '123' } } })
  assert.equal(last.method, 'post')
  assert.equal(last.url, 'https://example.test/nextcloud/ocs/v2.php/apps/libresign/api/v1/request-signature')
  assert.match(String(last.headers.get('Content-Type')), /application\/json/)
  assert.deepEqual(JSON.parse(String(last.data)), { name: 'Example', file: { nodeId: '123' } })
  const certificates = new CertificatesApi(configuration, undefined, instance)
  body = new Uint8Array([37, 80, 68, 70])
  const pdf = await certificates.certificatePolicyGetCertificatePolicy({ responseType: 'arraybuffer' })
  assert.equal(last.url, 'https://example.test/nextcloud/index.php/apps/libresign/certificate-policy.pdf')
  assert.equal(last.responseType, 'arraybuffer')
  assert.deepEqual(pdf.data, body)
  status = 403
  await assert.rejects(() => policies.policyGetSystem({ apiVersion: 'v1', policyKey: 'test', oCSAPIRequest: true }), (error: unknown) => axios.isAxiosError(error) && error.response?.status === 403)
  const root = new IdentityApi(new Configuration({ basePath: 'https://example.test' }), undefined, instance)
  status = 200
  await root.accountMe({ apiVersion: 'v1', oCSAPIRequest: true })
  assert.equal(last.url, 'https://example.test/ocs/v2.php/apps/libresign/api/v1/account/me')
  await identity.accountUploadPfx({ apiVersion: 'v1', oCSAPIRequest: true })
  assert.equal(last.data, undefined)
  await new AppearanceApi(configuration, undefined, instance).adminSignatureBackgroundSave({ apiVersion: 'v1', oCSAPIRequest: true })
  assert.equal(last.data, undefined)
  await new DocumentsApi(configuration, undefined, instance).fileSave({ apiVersion: 'v1', oCSAPIRequest: true, fileSaveRequest: { file: { nodeId: 123 } } })
  assert.match(String(last.headers.get('Content-Type')), /application\/json/)
  assert.deepEqual(JSON.parse(String(last.data)), { file: { nodeId: 123 } })
  const supplied = axios.create({ adapter, baseURL: 'https://instance.test/installed' })
  const suppliedIdentity = new IdentityApi(configuration, undefined, supplied)
  await suppliedIdentity.accountMe({ apiVersion: 'v1', oCSAPIRequest: true })
  assert.equal(supplied.getUri(last), 'https://instance.test/installed/ocs/v2.php/apps/libresign/api/v1/account/me')
  const suppliedCertificates = new CertificatesApi(configuration, undefined, supplied)
  await suppliedCertificates.certificatePolicyGetCertificatePolicy({ responseType: 'arraybuffer' })
  assert.equal(supplied.getUri(last), 'https://instance.test/installed/index.php/apps/libresign/certificate-policy.pdf')
  console.log('PASS: generated transport, root/subdirectory paths, independent basic/bearer auth, baseURL precedence, JSON, binary options and HTTP errors')
}
run().catch(error => { console.error(error); process.exitCode = 1 })
