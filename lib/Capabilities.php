<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign;

use OCA\Libresign\Service\SignatureTextService;
use OCA\Libresign\Service\SignerElementsService;
use OCP\App\IAppManager;
use OCP\Capabilities\IPublicCapability;

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
				'sign-elements' => [
					'is-available' => $this->signerElementsService->isSignElementsAvailable(),
					'can-create-signature' => $this->signerElementsService->canCreateSignature(),
					'full-signature-width' => $this->signatureTextService->getFullSignatureWidth(),
					'full-signature-height' => $this->signatureTextService->getFullSignatureHeight(),
					'signature-width' => $this->signatureTextService->getSignatureWidth(),
					'signature-height' => $this->signatureTextService->getSignatureHeight(),
				],
			],
			'version' => $this->appManager->getAppVersion('libresign'),
		];

		return [
			'libresign' => $capabilities,
		];
	}
}
