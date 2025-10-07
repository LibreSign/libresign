<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\IdentifyMethod\SignatureMethod;

use OCA\Libresign\Service\IdentifyMethod\AbstractIdentifyMethod;

abstract class AbstractSignatureMethod extends AbstractIdentifyMethod implements ISignatureMethod {
	private bool $enabled = false;

	#[\Override]
	public function enable(): void {
		$this->enabled = true;
	}

	#[\Override]
	public function isEnabled(): bool {
		return $this->enabled;
	}

	#[\Override]
	public function toArray(): array {
		return [
			'label' => $this->getFriendlyName(),
		];
	}
}
