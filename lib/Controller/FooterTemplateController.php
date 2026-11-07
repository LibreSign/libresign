<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\FooterService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

/**
 * @psalm-import-type LibresignErrorResponse from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignFooterTemplateResponse from \OCA\Libresign\ResponseDefinitions
 */
final class FooterTemplateController extends AEnvironmentAwareController {
	public function __construct(
		IRequest $request,
		private FooterService $footerService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Get footer template
	 *
	 * Returns the current footer template if set, otherwise returns the default template.
	 *
	 * @return DataResponse<Http::STATUS_OK, LibresignFooterTemplateResponse, array{}>|DataResponse<Http::STATUS_FORBIDDEN, LibresignErrorResponse, array{}>
	 *
	 * 200: OK
	 * 403: Forbidden
	 */
	#[NoAdminRequired]
	#[OpenAPI(tags: ['appearance'])]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/footer-template', requirements: ['apiVersion' => '(v1)'])]
	public function getFooterTemplate(): DataResponse {
		if (!$this->footerService->isPreviewAllowed()) {
			return new DataResponse([
				'error' => 'Footer template is disabled by policy for the current user.',
			], Http::STATUS_FORBIDDEN);
		}

		$previewSettings = $this->footerService->getPreviewSettings();

		return new DataResponse([
			'template' => $this->footerService->getTemplate(),
			'isDefault' => $this->footerService->isDefaultTemplate(),
			'template_variables' => $this->footerService->getTemplateVariablesMetadata(),
			'preview_width' => $previewSettings['preview_width'],
			'preview_height' => $previewSettings['preview_height'],
			'preview_zoom' => $previewSettings['preview_zoom'],
		]);
	}

	/**
	 * Save footer template and render preview
	 *
	 * Saves the footer template and returns the rendered PDF preview.
	 *
	 * @param string $template The Twig template to save (empty to reset to default)
	 * @param int $width Width of preview in points (default: 595 - A4 width)
	 * @param int $height Height of preview in points (default: 50)
	 * @return DataDownloadResponse<Http::STATUS_OK, 'application/pdf', array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN, LibresignErrorResponse, array{}>
	 *
	 * 200: OK
	 * 400: Bad request
	 * 403: Forbidden
	 */
	#[OpenAPI(tags: ['appearance'])]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/footer-template', requirements: ['apiVersion' => '(v1)'])]
	public function saveFooterTemplate(string $template = '', int $width = 595, int $height = 50) {
		if (!$this->footerService->isPreviewAllowed()) {
			return new DataResponse([
				'error' => 'Footer template is disabled by policy for the current user.',
			], Http::STATUS_FORBIDDEN);
		}

		try {
			// Validate and render before persisting: a rejected template must not
			// replace the global footer used by other signing requests.
			$pdf = $this->footerService->renderPreviewPdf(
				$template !== '' ? $template : $this->footerService->getDefaultTemplate(),
				$width,
				$height,
			);
			$this->footerService->saveTemplate($template, $width, $height);

			return new DataDownloadResponse($pdf, 'footer-preview.pdf', 'application/pdf');
		} catch (\Exception $e) {
			return new DataResponse([
				'error' => $e->getMessage(),
			], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Preview footer template as PDF
	 *
	 * @param string $template Template to preview
	 * @param int $width Width of preview in points (default: 595 - A4 width)
	 * @param int $height Height of preview in points (default: 50)
	 * @param ?bool $writeQrcodeOnFooter Whether to force QR code rendering in footer preview (null uses policy)
	 * @return DataDownloadResponse<Http::STATUS_OK, 'application/pdf', array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN, LibresignErrorResponse, array{}>
	 *
	 * 200: OK
	 * 400: Bad request
	 * 403: Forbidden
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[OpenAPI(tags: ['appearance'])]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/footer-template/preview-pdf', requirements: ['apiVersion' => '(v1)'])]
	public function previewPdf(string $template = '', int $width = 595, int $height = 50, ?bool $writeQrcodeOnFooter = null) {
		if (!$this->footerService->isPreviewAllowed()) {
			return new DataResponse([
				'error' => 'Footer preview is disabled by policy for the current user.',
			], Http::STATUS_FORBIDDEN);
		}

		try {
			$pdf = $this->footerService->renderPreviewPdf($template, $width, $height, $writeQrcodeOnFooter);
			return new DataDownloadResponse($pdf, 'footer-preview.pdf', 'application/pdf');
		} catch (\Exception $e) {
			return new DataResponse([
				'error' => $e->getMessage(),
			], Http::STATUS_BAD_REQUEST);
		}
	}
}
