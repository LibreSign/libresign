<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing\Provider\Drivers\Csc;

use OCA\Libresign\ResponseDefinitions;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\RemoteSigning\Contract\RemoteCertificateDiscoveryInterface;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\RemoteSigning\Contract\RemoteSignatureProviderInterface;
use OCP\IL10N;

/**
 * Administrative metadata for the CSC v1 adapter.
 *
 * No configured integration, credential, token or signing session is created here.
 *
 * @psalm-import-type LibresignExternalSigningDriver from ResponseDefinitions
 */
final class CscDriver {
	public const ID = 'csc-v1';

	public function __construct(
		private IL10N $l10n,
	) {
	}

	/** @return LibresignExternalSigningDriver */
	public function metadata(): array {
		$available = $this->isAdapterInstalled();

		return [
			'id' => self::ID,
			// TRANSLATORS Name of an external signing provider.
			'name' => $this->l10n->t('Cloud Signature Consortium (CSC)'),
			// TRANSLATORS Description of an external signing provider.
			'description' => $this->l10n->t('Sign with an external CSC-compatible service using pre-discovered RSA certificates.'),
			'experimental' => true,
			'available' => $available,
			'unavailableReason' => $available ? null : 'signerphp_csc_adapter_missing',
			'fields' => [
				[
					'key' => 'baseUrl',
					// TRANSLATORS Administrator field for the base URL of a CSC remote signing service.
					'label' => $this->l10n->t('CSC service URL'),
					'type' => 'url',
					'required' => true,
					'secret' => false,
					'validation' => [
						'schemes' => ['https'],
						'allowUserInfo' => false,
					],
				],
				[
					'key' => 'accessToken',
					// TRANSLATORS Optional administrator secret for CSC service authentication.
					'label' => $this->l10n->t('CSC service access token'),
					'type' => 'password',
					'required' => false,
					'secret' => true,
				],
			],
		];
	}

	/**
	 * This means the reusable SignerPHP adapter is installed, not that any
	 * administrative instance is configured or ready to sign.
	 */
	private function isAdapterInstalled(): bool {
		return class_exists('OCA\\Libresign\\Vendor\\SignerPHP\\PdfSigner\\Infrastructure\\Remote\\Csc\\CscClient')
			&& is_subclass_of('OCA\\Libresign\\Vendor\\SignerPHP\\PdfSigner\\Infrastructure\\Remote\\Csc\\CscCertificateDiscovery', RemoteCertificateDiscoveryInterface::class)
			&& is_subclass_of('OCA\\Libresign\\Vendor\\SignerPHP\\PdfSigner\\Infrastructure\\Remote\\Csc\\CscSignatureProvider', RemoteSignatureProviderInterface::class);
	}
}
