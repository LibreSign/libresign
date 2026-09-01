<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Exception;

use JsonSerializable;

/**
 * @codeCoverageIgnore
 */
class LibresignException extends \Exception implements JsonSerializable {
	/**
	 * Error codes follow the closest HTTP status with one extra digit
	 * (401 -> 4010), so they are not mistaken for HTTP status codes.
	 */
	public const CODE_INVALID_TOKEN = 4010;

	#[\Override]
	public function jsonSerialize(): mixed {
		return ['message' => $this->getMessage()];
	}
}
