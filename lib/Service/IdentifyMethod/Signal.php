<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\IdentifyMethod;

use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\ISignatureMethod;

class Signal extends TwofactorGateway {
	public array $availableSignatureMethods = [
		ISignatureMethod::SIGNATURE_METHOD_CLICK_TO_SIGN,
		ISignatureMethod::SIGNATURE_METHOD_SIGNAL,
	];

	#[\Override]
	public function getFriendlyName(): string {
		// TRANSLATORS Name of possible authenticator method. This signalize that the signer could be identified by Signal
		return $this->identifyService->getL10n()->t('Signal');
	}
}
