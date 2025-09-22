<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\IdentifyMethod\SignatureMethod;

use OCA\Libresign\Service\IdentifyMethod\IdentifyService;

class Sms extends TwofactorGatewayToken {
	public function __construct(
		protected IdentifyService $identifyService,
		protected TokenService $tokenService,
	) {
		// TRANSLATORS Name of possible authenticator method. This signalize that the signer could be identified by SMS
		$this->setFriendlyName($this->identifyService->getL10n()->t('SMS'));
		parent::__construct(
			$identifyService,
			$tokenService,
		);
	}
}
