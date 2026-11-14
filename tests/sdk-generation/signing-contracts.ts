/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type {
  SignFileSignByFileIdRequest,
  SignFileSignBySignerUuidRequest,
  SignatureElementsCreateSignatureElementRequest,
  SignatureElementsPatchSignatureElementRequest,
} from '../../build/sdk-generation/typescript/api'

const sign: SignFileSignByFileIdRequest = {
  method: 'clickToSign',
  elements: [
    { documentElementId: 100, profileNodeId: 42 },
    { documentElementId: 101 },
  ],
  deviceGeolocation: { status: 'collected', latitude: 10, longitude: -20, accuracy: 15, timestamp: 123 },
}
const external: SignFileSignBySignerUuidRequest = { method: 'clickToSign', elements: [], saveSignature: true }
const create: SignatureElementsCreateSignatureElementRequest = {
  elements: [{ type: 'signature', file: { base64: 'synthetic-data' } }, { type: 'initial', file: { url: 'https://example.test/image.png' } }],
}
const update: SignatureElementsPatchSignatureElementRequest = { type: 'signature', file: { base64: 'synthetic-data' } }

// @ts-expect-error elements must be a list, not an object map
const badSignElements: SignFileSignByFileIdRequest = { method: 'clickToSign', elements: { documentElementId: 100 } }
// @ts-expect-error documentElementId is required in each list item
const missingDocumentElement: SignFileSignByFileIdRequest = { method: 'clickToSign', elements: [{ profileNodeId: 42 }] }
// @ts-expect-error profileNodeId must be numeric
const badProfile: SignFileSignBySignerUuidRequest = { method: 'clickToSign', elements: [{ documentElementId: 100, profileNodeId: '42' }] }
// @ts-expect-error signature elements must be a list
const badCreate: SignatureElementsCreateSignatureElementRequest = { elements: { type: 'signature' } }
// @ts-expect-error unsupported signature type
const badType: SignatureElementsCreateSignatureElementRequest = { elements: [{ type: 'unknown' }] }
// @ts-expect-error saved image data must be a string
const badFile: SignatureElementsPatchSignatureElementRequest = { file: { base64: 100 } }
// @ts-expect-error location status must be a supported status
const badGeo: SignFileSignByFileIdRequest = { method: 'clickToSign', deviceGeolocation: { status: 'unknown' } }

void [sign, external, create, update, badSignElements, missingDocumentElement, badProfile, badCreate, badType, badFile, badGeo]
