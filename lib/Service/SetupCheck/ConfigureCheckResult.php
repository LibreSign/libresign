<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\SetupCheck;

use JsonSerializable;

class ConfigureCheckResult implements JsonSerializable {
	public function __construct(
		private string $status,
		private string $resource,
		private string $message,
		private string $tip,
		private string $category,
	) {
	}

	public function getStatus(): string {
		return $this->status;
	}

	public function getResource(): string {
		return $this->resource;
	}

	public function getMessage(): string {
		return $this->message;
	}

	public function getTip(): string {
		return $this->tip;
	}

	public function getCategory(): string {
		return $this->category;
	}

	#[\Override]
	public function jsonSerialize(): array {
		return [
			'status' => $this->status,
			'resource' => $this->resource,
			'message' => $this->message,
			'tip' => $this->tip,
		];
	}
}
