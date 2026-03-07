/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { components as AdministrationComponents } from './openapi/openapi-administration'

export type LibreSignCapabilities = AdministrationComponents['schemas']['Capabilities']

export type LibreSignSignElementsConfig = LibreSignCapabilities['config']['sign-elements']

export type LibreSignUploadConfig = LibreSignCapabilities['config']['upload']

export type NextcloudCapabilities = {
	libresign?: LibreSignCapabilities
	[key: string]: unknown
}