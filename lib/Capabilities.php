<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Envelope\EnvelopeService;
use OCA\Libresign\Service\SignatureTextService;
use OCA\Libresign\Service\SignerElementsService;
use OCP\App\IAppManager;
use OCP\Capabilities\IPublicCapability;
use OCP\IAppConfig;

/**
 * @psalm-import-type LibresignCapabilities from ResponseDefinitions
 */
class Capabilities implements IPublicCapability {
	public const FEATURES = [
		'customize-signature'
	];

	public function __construct(
		protected SignerElementsService $signerElementsService,
		protected SignatureTextService $signatureTextService,
		protected IAppManager $appManager,
		protected EnvelopeService $envelopeService,
		protected IAppConfig $appConfig,
	) {
	}

	/**
	 * @return array{
	 *      libresign?: LibresignCapabilities,
	 * }
	 */
	#[\Override]
	public function getCapabilities(): array {
		$capabilities = [
			'features' => self::FEATURES,
			'config' => [
				'show-confetti' => $this->appConfig->getValueBool(Application::APP_ID, 'show_confetti_after_signing', true),
				'sign-elements' => [
					'is-available' => $this->signerElementsService->isSignElementsAvailable(),
					'can-create-signature' => $this->signerElementsService->canCreateSignature(),
					'full-signature-width' => $this->signatureTextService->getFullSignatureWidth(),
					'full-signature-height' => $this->signatureTextService->getFullSignatureHeight(),
					'signature-width' => $this->signatureTextService->getSignatureWidth(),
					'signature-height' => $this->signatureTextService->getSignatureHeight(),
				],
				'envelope' => [
					'is-available' => $this->envelopeService->isEnabled(),
				],
				'upload' => [
					'max-file-uploads' => $this->getMaxFileUploads(),
				],
			],
			'version' => $this->appManager->getAppVersion('libresign'),
		];

		return [
			'libresign' => $capabilities,
		];
	}

	private function getMaxFileUploads(): int {
		$maxFileUploads = ini_get('max_file_uploads');
		if (!is_numeric($maxFileUploads) || (int)$maxFileUploads <= 0) {
			return 20;
		}
		return (int)$maxFileUploads;
	}
}
