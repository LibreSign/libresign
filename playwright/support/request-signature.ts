/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, type Page } from '@playwright/test'

/** Accessible name of the admin option in the account signer search list. */
export const ADMIN_SIGNER_OPTION = /admin.*admin@email\.tld/i

/**
 * Opens the add-signer dialog from the request-signature sidebar.
 *
 * When `enable_observer_profile` is disabled the UI exposes "Add signer".
 * When enabled, "Add" opens a menu and this helper chooses "Signer".
 */
export async function clickAddSigner(page: Page): Promise<void> {
	const addSignerButton = page.getByRole('button', { name: 'Add signer', exact: true })
	if (await addSignerButton.isVisible({ timeout: 2_000 }).catch(() => false)) {
		await addSignerButton.click()
	} else {
		const addButton = page.getByRole('button', { name: 'Add', exact: true })
		await expect(addButton).toBeVisible({ timeout: 15_000 })
		await addButton.click()

		const signerMenuItem = page.getByRole('menuitem', { name: 'Signer' })
			.or(page.getByRole('button', { name: 'Signer', exact: true }))
		await expect(signerMenuItem.first()).toBeVisible({ timeout: 5_000 })
		await signerMenuItem.first().click()
	}

	await expect(getAddSignerDialog(page)).toBeVisible({ timeout: 10_000 })
}

/**
 * Opens the add-observer dialog from the request-signature sidebar.
 *
 * Requires `enable_observer_profile` to be enabled so the Add menu is shown.
 */
export async function clickAddObserver(page: Page): Promise<void> {
	const addButton = page.getByRole('button', { name: 'Add', exact: true })
	await expect(addButton).toBeVisible({ timeout: 15_000 })
	await addButton.click()

	const observerMenuItem = page.getByRole('menuitem', { name: 'Observer' })
		.or(page.getByRole('button', { name: 'Observer', exact: true }))
	await expect(observerMenuItem.first()).toBeVisible({ timeout: 5_000 })
	await observerMenuItem.first().click()

	await expect(getAddObserverDialog(page)).toBeVisible({ timeout: 10_000 })
}

/**
 * Asserts that the request-signature sidebar exposes the add-participant control.
 */
export async function expectAddSignerControlVisible(page: Page): Promise<void> {
	const addSignerButton = page.getByRole('button', { name: 'Add signer', exact: true })
	const addMenuButton = page.getByRole('button', { name: 'Add', exact: true })
	await expect(addSignerButton.or(addMenuButton)).toBeVisible({ timeout: 15_000 })
}

function getAddSignerDialog(page: Page) {
	return page.getByRole('dialog', { name: /Add new signer/i }).last()
}

function getAddObserverDialog(page: Page) {
	return page.getByRole('dialog', { name: /Add new observer/i }).last()
}

function getParticipantDialog(page: Page) {
	return page.getByRole('dialog', { name: /Add new (signer|observer)/i }).last()
}

function getSignerSearchCombobox(page: Page) {
	// NcSelect exposes the method placeholder as combobox name (e.g. Account, Email),
	// while input-label stays on the visible label. Target the stable input id instead.
	return getParticipantDialog(page).locator('#account-or-email-input')
}

/**
 * Selects an account-backed signer from the add-signer dialog search list.
 */
export async function selectAccountSigner(
	page: Page,
	query: string,
	optionName: string | RegExp = ADMIN_SIGNER_OPTION,
): Promise<void> {
	const search = getSignerSearchCombobox(page)
	await expect(search).toBeVisible({ timeout: 10_000 })
	await search.click()
	await search.fill('')
	await search.pressSequentially(query, { delay: 50 })

	await expect.poll(async () => page.getByRole('option').count(), {
		timeout: 15_000,
		message: `Expected account search results for query "${query}"`,
	}).toBeGreaterThan(0)

	const namedOption = page.getByRole('option', { name: optionName }).first()
	if (await namedOption.isVisible({ timeout: 2000 }).catch(() => false)) {
		await namedOption.click()
		return
	}

	const emailOption = page.getByRole('option').filter({ hasText: /admin@email\.tld/i }).first()
	await expect(emailOption).toBeVisible({ timeout: 10_000 })
	await emailOption.click()
}

/**
 * Selects an email-backed signer from the add-signer dialog search list.
 */
export async function selectEmailSigner(page: Page, email: string): Promise<void> {
	const search = getSignerSearchCombobox(page)
	await expect(search).toBeVisible({ timeout: 10_000 })
	await search.click()
	await search.pressSequentially(email, { delay: 50 })

	await expect.poll(async () => page.getByRole('option').count(), {
		timeout: 15_000,
		message: `Expected email search results for "${email}"`,
	}).toBeGreaterThan(0)

	const option = page.getByRole('option', { name: email }).first()
	await expect(option).toBeVisible({ timeout: 10_000 })
	await option.click()
}
