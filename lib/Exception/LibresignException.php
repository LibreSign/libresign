<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Exception;

use JsonSerializable;
use OCP\AppFramework\Http;

class LibresignException extends \Exception implements JsonSerializable {
	/**
	 * @param int|null $httpStatus status of the HTTP response built from this
	 *                             exception; when null, the code is used as the
	 *                             status, as callers did before this argument existed
	 */
	public function __construct(
		string $message = '',
		int $code = 0,
		?\Throwable $previous = null,
		private ?int $httpStatus = null,
	) {
		parent::__construct($message, $code, $previous);
	}

	public function getHttpStatus(): int {
		if ($this->httpStatus !== null) {
			return $this->httpStatus;
		}
		if ($this->getCode() === 0) {
			return Http::STATUS_UNPROCESSABLE_ENTITY;
		}
		return (int)$this->getCode();
	}

	#[\Override]
	public function jsonSerialize(): mixed {
		return ['message' => $this->getMessage()];
	}
}
