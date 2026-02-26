/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { ref } from 'vue'

interface ActionCodes {
	REDIRECT: number
	CREATE_ACCOUNT: number
	DO_NOTHING: number
	SIGN: number
	SIGN_INTERNAL: number
	SIGN_ID_DOC: number
	SHOW_ERROR: number
	SIGNED: number
	CREATE_SIGNATURE_PASSWORD: number
	RENEW_EMAIL: number
	INCOMPLETE_SETUP: number
}

interface ActionCodeToRoute {
	[key: number]: string
}

interface RequirementToModal {
	[key: string]: string
}

export const ACTION_CODES: Readonly<ActionCodes> = Object.freeze({
	REDIRECT: 1000,
	CREATE_ACCOUNT: 1500,
	DO_NOTHING: 2000,
	SIGN: 2500,
	SIGN_INTERNAL: 2625,
	SIGN_ID_DOC: 2750,
	SHOW_ERROR: 3000,
	SIGNED: 3500,
	CREATE_SIGNATURE_PASSWORD: 4000,
	RENEW_EMAIL: 4500,
	INCOMPLETE_SETUP: 5000,
})

export const ACTION_CODE_TO_ROUTE: Readonly<ActionCodeToRoute> = Object.freeze({
	[ACTION_CODES.REDIRECT]: 'redirect',
	[ACTION_CODES.CREATE_ACCOUNT]: 'CreateAccount',
	[ACTION_CODES.DO_NOTHING]: 'current',
	[ACTION_CODES.SIGN]: 'SignPDF',
	[ACTION_CODES.SIGN_INTERNAL]: 'SignPDF',
	[ACTION_CODES.SIGN_ID_DOC]: 'IdDocsApprove',
	[ACTION_CODES.SHOW_ERROR]: 'DefaultPageError',
	[ACTION_CODES.SIGNED]: 'ValidationFile',
	[ACTION_CODES.CREATE_SIGNATURE_PASSWORD]: 'CreatePassword',
	[ACTION_CODES.RENEW_EMAIL]: 'RenewEmail',
	[ACTION_CODES.INCOMPLETE_SETUP]: 'Incomplete',
})

/**
 * Shared reactive ref for the initial action code injected by the server
 * (#initial-state-libresign-action). Written once by router.ts beforeEach,
 * read by App.vue. Lives here (not in router.ts) to avoid App.vue triggering
 * the router module's side effects (createRouter, generateUrl) on import.
 */
export const initialActionCode = ref(0)

export const REQUIREMENT_TO_MODAL: Readonly<RequirementToModal> = Object.freeze({
	identificationDocuments: 'uploadDocuments',
	emailCode: 'emailToken',
	createSignature: 'createSignature',
	tokenCode: 'token',
	uploadCertificate: 'uploadCertificate',
	createPassword: 'createPassword',
	passwordSignature: 'password',
	clickToSign: 'clickToSign',
})
