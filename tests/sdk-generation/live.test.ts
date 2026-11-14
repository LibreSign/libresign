/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import * as assert from 'node:assert/strict'
import { test } from 'node:test'
import axios, { AxiosError } from 'axios'
import { runLiveChecks } from './live'

for (const pdfStatus of [200, 404, 500]) {
  for (const authStatus of [200, 401, 403, 429]) {
    test(`PDF ${pdfStatus}, invalid credentials ${authStatus}`, async () => {
      let invalidAuthChecked = false
      const instance = axios.create({ adapter: async config => {
        const invalidAuth = config.auth?.password === 'deliberately-invalid-test-password'
        const pdf = config.url?.endsWith('.pdf')
        invalidAuthChecked ||= invalidAuth
        const status = invalidAuth ? authStatus : pdf ? pdfStatus : 200
        const response = {
          config, status, statusText: String(status), headers: { 'content-type': 'application/pdf' },
          data: pdf ? Buffer.from('%PDF-test') : { ocs: { meta: { statuscode: 200 }, data: { account: { uid: 'test' } } } },
        }
        if (status >= 400) {
          throw new AxiosError('Synthetic HTTP error', 'ERR_BAD_RESPONSE', config, undefined, response)
        }
        return response
      } })
      const run = () => runLiveChecks('https://localhost', 'test', 'synthetic-password', instance)
      if (authStatus === 401) {
        assert.equal(await run(), pdfStatus !== 500)
      } else {
        await assert.rejects(run)
      }
      assert.equal(invalidAuthChecked, true, 'Authentication must be checked even after PDF failure')
    })
  }
}
