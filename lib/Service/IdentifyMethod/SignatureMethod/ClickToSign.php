<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\IdentifyMethod\SignatureMethod;

use OCA\Libresign\Service\IdentifyMethod\IdentifyService;

class ClickToSign extends AbstractSignatureMethod {
	public function __construct(
		protected IdentifyService $identifyService,
	) {
		// TRANSLATORS Name of possible authenticator method. This signalize that the signer only need to click to sign after was identified
		$this->setFriendlyName($this->identifyService->getL10n()->t('Click to sign'));
		parent::__construct(
			$identifyService,
		);
	}
}
