/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type { NewFile, SignerDetail, RequestSignatureRequestSignatureRequest, AccountMe200Response, IdentityApiAccountUploadPfxRequest, AppearanceApiAdminSignatureBackgroundSaveRequest } from '../../build/sdk-generation/typescript/api'

const file1: NewFile = { nodeId: 42 }
const file2: NewFile = { nodeId: '9007199254740993' }
// @ts-expect-error nodeId is not a boolean
const badFile: NewFile = { nodeId: true }
const signer: SignerDetail = {
  signRequestId: 1, displayName: 'Example', signed: null,
  displayStatus: 'signed', statusText: 'signed', description: null,
  request_sign_date: '2026-01-01', me: true, visibleElements: [],
}
// @ts-expect-error allOf requires inherited fields
const badSigner: SignerDetail = { me: true, visibleElements: [] }
const request: RequestSignatureRequestSignatureRequest = { callback: null }
// @ts-expect-error callback must be a string or null
const badRequest: RequestSignatureRequestSignatureRequest = { callback: 2 }
// @ts-expect-error status is a finite numeric enum
const badStatus: SignerDetail['status'] = 99
function account(response: AccountMe200Response): string { return response.ocs.data.account.uid }
void [file1, file2, badFile, signer, badSigner, request, badRequest, badStatus, account]

// @ts-expect-error the generated request has no upload parameter
const pfx: IdentityApiAccountUploadPfxRequest = { apiVersion: 'v1', oCSAPIRequest: true, file: new Blob() }
// @ts-expect-error the generated request has no image parameter
const background: AppearanceApiAdminSignatureBackgroundSaveRequest = { apiVersion: 'v1', oCSAPIRequest: true, image: new Blob() }
void [pfx, background]
