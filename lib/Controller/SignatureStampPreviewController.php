<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\SignatureText\SignatureTextPolicy;
use OCA\Libresign\Service\SignatureBackgroundService;
use OCA\Libresign\Service\SignerElementsService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

/**
 * @psalm-import-type LibresignErrorResponse from \OCA\Libresign\ResponseDefinitions
 */
final class SignatureStampPreviewController extends AEnvironmentAwareController {
	public function __construct(
		IRequest $request,
		private SignatureBackgroundService $signatureBackgroundService,
		private PolicyService $policyService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Render a preview PNG image of the signature stamp with the provided configuration.
	 *
	 * @param string $template Signature text template (Twig syntax)
	 * @param float $templateFontSize Font size for template text in pt
	 * @param float $signatureFontSize Font size for signer name in pt
	 * @param float $signatureWidth Stamp width in mm
	 * @param float $signatureHeight Stamp height in mm
	 * @param string $renderMode Render mode: default, text, graphic, description_only
	 * @param string $backgroundType Background: default, custom, deleted
	 *
	 * @return DataDownloadResponse<Http::STATUS_OK, string, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_UNPROCESSABLE_ENTITY, LibresignErrorResponse, array{}>
	 *
	 * 200: Preview PNG image
	 * 403: Forbidden
	 * 422: Rendering error
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/signature-stamp/preview', requirements: ['apiVersion' => '(v1)'])]
	public function preview(
		string $template = '',
		float $templateFontSize = 10.0,
		float $signatureFontSize = 10.0,
		float $signatureWidth = 90.0,
		float $signatureHeight = 60.0,
		string $renderMode = SignerElementsService::RENDER_MODE_GRAPHIC_AND_DESCRIPTION,
		string $backgroundType = 'default',
	): DataDownloadResponse|DataResponse {
		if (!$this->canEditSignatureStampPolicy()) {
			return new DataResponse([
				'error' => 'Signature stamp preview is only available for users who can edit policies.',
			], Http::STATUS_FORBIDDEN);
		}

		try {
			$png = $this->signatureBackgroundService->renderPreviewImage(
				template: $template,
				templateFontSize: $templateFontSize,
				signatureFontSize: $signatureFontSize,
				signatureWidth: $signatureWidth,
				signatureHeight: $signatureHeight,
				renderMode: $renderMode,
				backgroundType: $backgroundType,
			);
		} catch (\Exception $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		return new DataDownloadResponse($png, 'stamp-preview.png', 'image/png');
	}

	/**
	 * Render a preview PDF of the signature stamp with the provided configuration.
	 *
	 * @param string $template Signature text template (Twig syntax)
	 * @param float $templateFontSize Font size for template text in pt
	 * @param float $signatureFontSize Font size for signer name in pt
	 * @param float $signatureWidth Stamp width in mm
	 * @param float $signatureHeight Stamp height in mm
	 * @param string $renderMode Render mode: default, text, graphic, description_only
	 * @param string $backgroundType Background: default, custom, deleted
	 *
	 * @return DataDownloadResponse<Http::STATUS_OK, string, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_UNPROCESSABLE_ENTITY, LibresignErrorResponse, array{}>
	 *
	 * 200: Preview PDF
	 * 403: Forbidden
	 * 422: Rendering error
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/signature-stamp/preview-pdf', requirements: ['apiVersion' => '(v1)'])]
	public function previewPdf(
		string $template = '',
		float $templateFontSize = 10.0,
		float $signatureFontSize = 10.0,
		float $signatureWidth = 90.0,
		float $signatureHeight = 60.0,
		string $renderMode = SignerElementsService::RENDER_MODE_GRAPHIC_AND_DESCRIPTION,
		string $backgroundType = 'default',
	): DataDownloadResponse|DataResponse {
		if (!$this->canEditSignatureStampPolicy()) {
			return new DataResponse([
				'error' => 'Signature stamp preview is only available for users who can edit policies.',
			], Http::STATUS_FORBIDDEN);
		}

		try {
			$pdf = $this->signatureBackgroundService->renderPreviewPdf(
				template: $template,
				templateFontSize: $templateFontSize,
				signatureFontSize: $signatureFontSize,
				signatureWidth: $signatureWidth,
				signatureHeight: $signatureHeight,
				renderMode: $renderMode,
				backgroundType: $backgroundType,
			);
		} catch (\Exception $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		return new DataDownloadResponse($pdf, 'stamp-preview.pdf', 'application/pdf');
	}

	private function canEditSignatureStampPolicy(): bool {
		$policy = $this->policyService->resolve(SignatureTextPolicy::KEY);

		return $policy->isVisible() && $policy->isEditableByCurrentActor();
	}
}
