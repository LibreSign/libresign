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
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\Files\NotFoundException;
use OCP\IRequest;

class CertificatePolicyController extends Controller {
	public function __construct(
		IRequest $request,
		private CertificatePolicyService $certificatePolicyService,
	) {
		parent::__construct(Application::APP_ID, $request);
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
	#[NoCSRFRequired]
	#[AnonRateLimit(limit: 10, period: 60)]
	#[FrontpageRoute(verb: 'GET', url: '/certificate-policy.pdf')]
	public function getCertificatePolicy(): FileDisplayResponse|DataResponse {
		try {
			$file = $this->certificatePolicyService->getFile();
			return new FileDisplayResponse($file, Http::STATUS_OK, [
				'Content-Disposition' => 'inline; filename="certificate-policy.pdf"',
				'Content-Type' => 'application/pdf',
			]);
		} catch (NotFoundException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}
}
