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
	public function jsonSerialize(): mixed {
		return ['message' => $this->getMessage()];
	}
}
