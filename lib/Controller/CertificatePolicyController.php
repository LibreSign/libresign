<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\CertificatePolicyService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\IL10N;
use OCP\IRequest;

class CertificatePolicyController extends Controller {
	public function __construct(
		IRequest $request,
		private CertificatePolicyService $certificatePolicyService,
		private IL10N $l10n,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Update certificate policy of this instance
	 *
	 * @return DataResponse<Http::STATUS_OK, array{status: 'success'}, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, array{status: 'failure', message: string}, array{}>
	 *
	 * 200: OK
	 * 404: Not found
	 */
	#[PublicPage]
	#[AnonRateLimit(limit: 10, period: 60)]
	#[FrontpageRoute(verb: 'POST', url: '/certificate-policy.pdf')]
	public function saveCertificatePolicy(): DataResponse {
		$pdf = $this->request->getUploadedFile('pdf');
		$phpFileUploadErrors = [
			UPLOAD_ERR_OK => $this->l10n->t('The file was uploaded'),
			UPLOAD_ERR_INI_SIZE => $this->l10n->t('The uploaded file exceeds the upload_max_filesize directive in php.ini'),
			UPLOAD_ERR_FORM_SIZE => $this->l10n->t('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form'),
			UPLOAD_ERR_PARTIAL => $this->l10n->t('The file was only partially uploaded'),
			UPLOAD_ERR_NO_FILE => $this->l10n->t('No file was uploaded'),
			UPLOAD_ERR_NO_TMP_DIR => $this->l10n->t('Missing a temporary folder'),
			UPLOAD_ERR_CANT_WRITE => $this->l10n->t('Could not write file to disk'),
			UPLOAD_ERR_EXTENSION => $this->l10n->t('A PHP extension stopped the file upload'),
		];
		if (empty($pdf)) {
			$error = $this->l10n->t('No file uploaded');
		} elseif (!empty($pdf) && array_key_exists('error', $pdf) && $pdf['error'] !== UPLOAD_ERR_OK) {
			$error = $phpFileUploadErrors[$pdf['error']];
		}
		if ($error !== null) {
			return new DataResponse(
				[
					'message' => $error,
					'status' => 'failure',
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
		try {
			$this->certificatePolicyService->updateFile($pdf['tmp_name']);
		} catch (\Exception $e) {
			return new DataResponse(
				[
					'message' => $e->getMessage(),
					'status' => 'failure',
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}

		return new DataResponse(
			[
				'status' => 'success',
			]
		);
	}

	/**
	 * Certificate policy of this instance
	 *
	 * @return FileDisplayResponse<Http::STATUS_OK, array{Content-Disposition: 'inline; filename="certificate-policy.pdf"', Content-Type: 'application/pdf'}>|DataResponse<Http::STATUS_NOT_FOUND, array<empty>, array{}>
	 *
	 * 200: OK
	 * 404: Not found
	 */
	#[PublicPage]
	#[AnonRateLimit(limit: 10, period: 60)]
	#[FrontpageRoute(verb: 'GET', url: '/certificate-policy.pdf')]
	public function getCertificatePolicy(): FileDisplayResponse {
		$file = $this->certificatePolicyService->getFile();
		return new FileDisplayResponse($file, Http::STATUS_OK, [
			'Content-Disposition' => 'inline; filename="certificate-policy.pdf"',
			'Content-Type' => 'application/pdf',
		]);
	}
}
