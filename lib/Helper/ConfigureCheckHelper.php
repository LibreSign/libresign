<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Helper;

use JsonSerializable;

/**
 * @method ConfigureCheckHelper setSuccessMessage(string $value)
 * @method ConfigureCheckHelper setInfoMessage(string $value)
 * @method ConfigureCheckHelper setErrorMessage(string $value)
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
	use MagicGetterSetterTrait {
		MagicGetterSetterTrait::__call as __getSet;
	}
	private string $status = '';
	private string $message = '';
	private string $resource = '';
	private string $tip = '';

	public function __call($name, $arguments) {
		if (!preg_match('/^set(?<status>.+)Message/', (string)$name, $matches)) {
			return $this->__getSet($name, $arguments);
		}
		$status = strtolower($matches['status']);
		if (!in_array($status, ['error', 'success', 'info'])) {
			throw new \LogicException(sprintf('Cannot set non existing message status %s.', $status));
		}
		$message = $arguments[0] ?? null;
		if (!is_string($message)) {
			throw new \LogicException(sprintf('Invalid error message %s.', var_export($arguments, true)));
		}
		if (count($arguments) > 1) {
			throw new \LogicException(sprintf('Need to have only an argument %s.', var_export($arguments, true)));
		}
		$this->setStatus($status);
		$this->setMessage($message);
		return $this;
	}

	#[\Override]
	public function jsonSerialize(): array {
		return [
			'status' => $this->getStatus(),
			'message' => $this->getMessage(),
			'resource' => $this->getResource(),
			'tip' => $this->getTip(),
		];
	}
}
