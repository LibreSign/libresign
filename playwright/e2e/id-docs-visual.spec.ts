/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test, type Locator, type Page } from '@playwright/test'
import { mkdir } from 'node:fs/promises'
import { resolve } from 'node:path'

import { login } from '../support/nc-login'

const SCREENSHOT_DIR = resolve(process.cwd(), 'playwright/.visual-output')

test.describe.configure({ mode: 'serial', timeout: 180000 })

async function shot(page: Page, name: string): Promise<string> {
	await mkdir(SCREENSHOT_DIR, { recursive: true })
	const path = resolve(SCREENSHOT_DIR, `${name}.png`)
	await page.screenshot({ path, fullPage: true })
	return path
}

function emptyStatus(page: Page): Locator {
	return page.getByText(/Not sent yet|Ainda não enviado/i)
}

function deleteButton(page: Page): Locator {
	return page.getByRole('button', { name: /Delete file|Excluir arquivo/i })
}

function uploadButton(page: Page): Locator {
	return page.getByRole('button', { name: /Upload file|Enviar arquivo/i })
}

async function waitForIdDocsCard(page: Page): Promise<void> {
	await expect(emptyStatus(page).or(deleteButton(page))).toBeVisible({ timeout: 20_000 })
}

async function clearExistingIdDocument(page: Page): Promise<void> {
	await waitForIdDocsCard(page)
	for (let attempt = 0; attempt < 5; attempt++) {
		if (!(await deleteButton(page).isVisible().catch(() => false))) {
			break
		}
		await deleteButton(page).click()
		await expect(page.getByText(/File was deleted\.|Arquivo foi apagado\./i)).toBeVisible({ timeout: 20_000 })
		await waitForIdDocsCard(page)
	}
	await expect(emptyStatus(page)).toBeVisible({ timeout: 20_000 })
	await expect(uploadButton(page)).toBeVisible()
}

test('identification documents appear on the account page after upload', async ({ page }) => {
	test.slow()

	await login(
		page.request,
		process.env.NEXTCLOUD_ADMIN_USER ?? 'admin',
		process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin',
	)

	const policyResponse = await page.request.post(
		'./ocs/v2.php/apps/libresign/api/v1/policies/system/identification_documents?format=json',
		{
			headers: {
				'OCS-ApiRequest': 'true',
				Accept: 'application/json',
				Authorization: 'Basic ' + Buffer.from('admin:admin').toString('base64'),
				'Content-Type': 'application/json',
			},
			data: {
				value: { enabled: true, approvers: ['admin'] },
			},
		},
	)
	expect(policyResponse.ok(), await policyResponse.text()).toBeTruthy()

	await page.goto('./apps/libresign')
	await expect(page.getByRole('button', { name: /Upload from URL|Carregar do URL/i })).toBeVisible({ timeout: 20_000 })
	await expect(page.getByText(/Document Validation|Validação de Documentos/i).first()).toBeVisible()
	await shot(page, '01-libresign-home')

	await page.goto('./apps/libresign/f/account')
	await expect(page.getByRole('heading', { name: /Identification documents|Documentos de identificação/i })).toBeVisible({ timeout: 20_000 })
	await clearExistingIdDocument(page)
	await shot(page, '02-account-id-docs-empty')

	const [fileChooser] = await Promise.all([
		page.waitForEvent('filechooser'),
		uploadButton(page).click(),
	])
	await fileChooser.setFiles(resolve(process.cwd(), 'tests/php/fixtures/pdfs/small_valid.pdf'))

	await expect(page.getByText(/File was sent\.|Arquivo foi enviado\./i)).toBeVisible({ timeout: 20_000 })
	await expect(deleteButton(page)).toBeVisible({ timeout: 20_000 })
	await expect(emptyStatus(page)).toHaveCount(0)
	await shot(page, '03-account-id-doc-uploaded')

	await page.goto('./apps/libresign/f/docs/id-docs/validation')
	await expect(page.getByRole('columnheader', { name: /Owner|Proprietário/i })).toBeVisible({ timeout: 20_000 })
	await expect(page.getByRole('cell', { name: /^admin$/i }).first()).toBeVisible({ timeout: 20_000 })
	await expect(page.getByText(/waiting for approval|aguardando aprovação/i).first()).toBeVisible()
	await expect(page.getByRole('button', { name: /Sign|Assinar/i }).first()).toBeVisible()
	await shot(page, '04-id-docs-approval-list')

	await page.goto('./settings/admin/libresign')
	const catalogSearch = page.locator('.policy-workbench__catalog-search').getByRole('textbox').first()
	await expect(catalogSearch).toBeVisible({ timeout: 20_000 })

	const collapseButton = page.getByRole('button', {
		name: /Collapse settings categories|Recolher categorias de configurações|Expand settings categories|Expandir categorias de configurações/i,
	}).first()
	if (/Expand|Expandir/i.test((await collapseButton.getAttribute('aria-label')) ?? '')) {
		await collapseButton.click()
	}

	await catalogSearch.fill('identifica')
	await expect(page.getByRole('button', {
		name: /Identification documents flow|Fluxo de documentos de identificação/i,
	}).first()).toBeVisible({ timeout: 20_000 })
	await shot(page, '05-settings-identification-documents')
})
