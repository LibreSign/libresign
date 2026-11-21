<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing\Provider\Drivers\Csc;

use OCA\Libresign\ResponseDefinitions;
use OCP\IL10N;
use SignerPHP\PdfSigner\RemoteSigning\Contract\RemoteCertificateDiscoveryInterface;
use SignerPHP\PdfSigner\RemoteSigning\Contract\RemoteSignatureProviderInterface;

/**
 * Administrative metadata for the experimental CSC v1 adapter.
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
			// TRANSLATORS Label for an experimental external signing integration driver.
			'name' => $this->l10n->t('Cloud Signature Consortium (CSC v1)'),
			// TRANSLATORS Description of the CSC v1 external signing driver.
			'description' => $this->l10n->t('Experimental CSC v1.0.4.0 signing with a pre-discovered RSA certificate.'),
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
						'allowLoopbackHttp' => true,
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
					'validation' => [],
				],
			],
		];
	}

	/**
	 * This means the reusable SignerPHP adapter is installed, not that any
	 * administrative instance is configured or ready to sign.
	 */
	private function isAdapterInstalled(): bool {
		return class_exists('SignerPHP\\PdfSigner\\Infrastructure\\Remote\\Csc\\CscClient')
			&& is_subclass_of('SignerPHP\\PdfSigner\\Infrastructure\\Remote\\Csc\\CscCertificateDiscovery', RemoteCertificateDiscoveryInterface::class)
			&& is_subclass_of('SignerPHP\\PdfSigner\\Infrastructure\\Remote\\Csc\\CscSignatureProvider', RemoteSignatureProviderInterface::class);
	}
}
