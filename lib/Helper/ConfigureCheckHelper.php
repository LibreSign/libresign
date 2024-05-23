<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Helper;

use JsonSerializable;

/**
 * @method ConfigureCheckHelper setStatus(string $value)
 * @method string getStatus()
 * @method ConfigureCheckHelper setMessage(string $value)
 * @method string getMessage()
 * @method ConfigureCheckHelper setResource(string $value)
 * @method string getResource()
 * @method ConfigureCheckHelper setTip(string $value)
 * @method string getTip()
 */
class ConfigureCheckHelper implements JsonSerializable {
	use MagicGetterSetterTrait;
	private string $status = '';
	private string $message = '';
	private string $resource = '';
	private string $tip = '';

	public function setErrorMessage(string $message): ConfigureCheckHelper {
		$this->setStatus('error');
		$this->setMessage($message);
		return $this;
	}

	public function setSuccessMessage(string $message): ConfigureCheckHelper {
		$this->setStatus('success');
		$this->setMessage($message);
		return $this;
	}

	public function jsonSerialize(): array {
		return [
			'status' => $this->getStatus(),
			'message' => $this->getMessage(),
			'resource' => $this->getResource(),
			'tip' => $this->getTip(),
		];
	}
}
