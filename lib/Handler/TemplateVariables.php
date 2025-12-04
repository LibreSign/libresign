<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler;

/**
 * @method self setDirection(string $value)
 * @method self setLinkToSite(string $value)
 * @method self setQrcode(string $value)
 * @method self setQrcodeSize(int $value)
 * @method self setSignedBy(string $value)
 * @method self setSigners(array $value)
 * @method self setUuid(string $value)
 * @method self setValidateIn(string $value)
 * @method self setValidationSite(string $value)
 * @method string|null getDirection()
 * @method string|null getLinkToSite()
 * @method string|null getQrcode()
 * @method int|null getQrcodeSize()
 * @method string|null getSignedBy()
 * @method array|null getSigners()
 * @method string|null getUuid()
 * @method string|null getValidateIn()
 * @method string|null getValidationSite()
 */
class TemplateVariables {
	private array $variables = [];

	/**
	 * Allowed template variable names with their expected types
	 */
	private const ALLOWED_VARIABLES = [
		'direction' => 'string',
		'linkToSite' => 'string',
		'qrcode' => 'string',
		'qrcodeSize' => 'integer',
		'signedBy' => 'string',
		'signers' => 'array',
		'uuid' => 'string',
		'validateIn' => 'string',
		'validationSite' => 'string',
	];

	/**
	 * @throws \InvalidArgumentException if trying to access non-whitelisted variable or wrong type
	 */
	public function __call(string $method, array $args): mixed {
		if (str_starts_with($method, 'set')) {
			$key = lcfirst(substr($method, 3));
			$this->ensureAllowed($key);
			$this->ensureType($key, $args[0]);

			$this->variables[$key] = $args[0];
			return $this;
		}

		if (str_starts_with($method, 'get')) {
			$key = lcfirst(substr($method, 3));
			$this->ensureAllowed($key);
			return $this->variables[$key] ?? null;
		}

		throw new \BadMethodCallException("Method {$method} does not exist");
	}

	private function ensureAllowed(string $key): void {
		if (!array_key_exists($key, self::ALLOWED_VARIABLES)) {
			throw new \InvalidArgumentException("Template variable '{$key}' is not allowed");
		}
	}

	private function ensureType(string $key, mixed $value): void {
		$expected = self::ALLOWED_VARIABLES[$key];
		$actual = gettype($value);

		if ($actual !== $expected) {
			throw new \InvalidArgumentException("Template variable '{$key}' must be of type {$expected}, got {$actual}");
		}
	}

	public function has(string $key): bool {
		return isset($this->variables[$key]);
	}

	public function toArray(): array {
		return $this->variables;
	}

	/**
	 * Merge additional variables, validating against whitelist and types
	 *
	 * @throws \InvalidArgumentException if trying to merge non-whitelisted variable or wrong type
	 */
	public function merge(array $variables): self {
		foreach ($variables as $key => $value) {
			$this->ensureAllowed($key);
			$this->ensureType($key, $value);
		}
		$this->variables = array_merge($this->variables, $variables);
		return $this;
	}
}
