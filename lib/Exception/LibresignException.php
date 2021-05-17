<?php

namespace OCA\Libresign\Exception;

use JsonSerializable;

/**
 * @codeCoverageIgnore
 */
class LibresignException extends \Exception implements JsonSerializable {
	public function jsonSerialize() {
		return ['message' => $this->getMessage()];
	}
}
