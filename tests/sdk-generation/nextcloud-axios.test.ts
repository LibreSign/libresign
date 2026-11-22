/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
// Injects the real, locked @nextcloud/axios instance into a generated client.
// The package needs browser globals, so a jsdom window is installed first.
// Only the network adapter is replaced; the package's own defaults and its CSRF
// interceptor run unmodified. No real Nextcloud server is contacted.
import * as assert from 'node:assert/strict'
import { test } from 'node:test'
import { AxiosError, type AxiosAdapter, type InternalAxiosRequestConfig } from 'axios'
import { JSDOM } from 'jsdom'
import { IdentityApi } from '../../build/sdk-generation/typescript/api'
import { Configuration } from '../../build/sdk-generation/typescript/configuration'

const dom = new JSDOM('<!doctype html><html data-requesttoken="initial-token"><head data-requesttoken="initial-token"></head><body></body></html>', { url: 'https://example.test/nextcloud/' })
Object.assign(globalThis, { window: dom.window, document: dom.window.document, location: dom.window.location, localStorage: dom.window.localStorage, sessionStorage: dom.window.sessionStorage })

const okBody = { ocs: { meta: { status: 'ok', statuscode: 200 }, data: { account: { uid: 'test', emailAddress: 'test@example.test', displayName: 'Test' }, settings: { canRequestSign: true, hasSignatureFile: false, phoneNumber: '' } } } }

test('generated client works with the real @nextcloud/axios instance and its CSRF retry', async () => {
  const { default: nextcloudAxios } = await import('@nextcloud/axios')
  const seen: InternalAxiosRequestConfig[] = []
  let calls = 0
  const adapter: AxiosAdapter = async config => {
    seen.push(config)
    calls++
    const reply = (status: number, data: unknown) => {
      const response = { data, status, statusText: String(status), headers: {}, config }
      if (config.validateStatus && !config.validateStatus(status)) {
        throw new AxiosError('HTTP error', 'ERR_BAD_REQUEST', config, undefined, response)
      }
      return response
    }
    // First account request: simulate a stale token, as Nextcloud does.
    return calls === 1 ? reply(412, { message: 'CSRF check failed' }) : reply(200, okBody)
  }
  // fetchRequestToken() uses the global fetch(), not Axios.
  const fetched: string[] = []
  globalThis.fetch = (async (input: RequestInfo | URL) => {
    fetched.push(String(input))
    return new Response(JSON.stringify({ token: 'refreshed-token' }), { status: 200, headers: { 'Content-Type': 'application/json' } })
  }) as typeof fetch
  nextcloudAxios.defaults.adapter = adapter

  const api = new IdentityApi(new Configuration({ basePath: 'https://example.test/nextcloud' }), undefined, nextcloudAxios)
  const account = await api.accountMe({ apiVersion: 'v1', oCSAPIRequest: true })

  const first = seen[0]
  assert.equal(first.url, 'https://example.test/nextcloud/ocs/v2.php/apps/libresign/api/v1/account/me')
  assert.equal(first.headers.get('OCS-APIRequest'), 'true')
  assert.equal(first.headers.get('requesttoken'), 'initial-token')
  assert.equal(first.headers.get('X-Requested-With'), 'XMLHttpRequest')
  const retry = seen[seen.length - 1]
  assert.equal(retry.url, first.url)
  assert.equal(retry.headers.get('requesttoken'), 'refreshed-token')
  assert.equal(retry.headers.get('OCS-APIRequest'), 'true')
  assert.deepEqual(fetched, ['/nextcloud/index.php/csrftoken'])
  assert.equal(account.data.ocs.data.account.uid, 'test')
})
