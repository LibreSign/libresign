<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign;

use OCA\Libresign\ResponseDefinitions;
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
		protected IAppManager $appManager,
	) {
	}

	/**
	 * @return array{
	 *      libresign?: LibresignCapabilities,
	 * }
	 */
	public function getCapabilities(): array {
		$capabilities = [
			'features' => self::FEATURES,
			'config' => [
				'sign-elements' => [
					'is-available' => $this->signerElementsService->isSignElementsAvailable(),
				],
			],
			'version' => $this->appManager->getAppVersion('libresign'),
		];

		return [
			'libresign' => $capabilities,
		];
	}
}
