<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler;

use OCP\IL10N;

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
	private array $variablesMetadata = [];

	public function __construct(
		private IL10N $l10n,
	) {
		$this->initializeVariablesMetadata();
	}

	private function initializeVariablesMetadata(): void {
		$this->variablesMetadata = [
			'direction' => [
				'type' => 'string',
				'description' => $this->l10n->t('Text direction for the footer (ltr or rtl based on language)'),
				'example' => 'ltr',
			],
			'linkToSite' => [
				'type' => 'string',
				'description' => $this->l10n->t('Link to LibreSign or custom website'),
				'example' => 'https://libresign.coop',
				'default' => 'https://libresign.coop',
			],
			'qrcode' => [
				'type' => 'string',
				'description' => $this->l10n->t('QR Code image in base64 format for document validation'),
				'example' => 'iVBORw0KGgoAAAANSUhEUgAA...',
			],
			'qrcodeSize' => [
				'type' => 'integer',
				'description' => $this->l10n->t('QR Code size in pixels (includes margin)'),
				'example' => 108,
			],
			'signedBy' => [
				'type' => 'string',
				'description' => $this->l10n->t('Message indicating the document was digitally signed'),
				'example' => 'Digitally signed by LibreSign.',
				'default' => $this->l10n->t('Digitally signed by LibreSign.'),
			],
			'signers' => [
				'type' => 'array',
				'description' => $this->l10n->t('Array of signers with displayName and signed timestamp'),
				'example' => '[{"displayName": "John Doe", "signed": "2025-01-01T10:00:00Z"}]',
			],
			'uuid' => [
				'type' => 'string',
				'description' => $this->l10n->t('Document unique identifier (UUID format)'),
				'example' => 'de0a18d4-fe65-4abc-bdd1-84e819700260',
			],
			'validateIn' => [
				'type' => 'string',
				'description' => $this->l10n->t('Validation message template with placeholder'),
				'example' => 'Validate in %s.',
				'default' => $this->l10n->t('Validate in %s.', ['%s']),
			],
			'validationSite' => [
				'type' => 'string',
				'description' => $this->l10n->t('Complete URL for document validation with UUID'),
				'example' => 'https://example.com/validation/de0a18d4-fe65-4abc-bdd1-84e819700260',
			],
		];
	}

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
		if (!array_key_exists($key, $this->variablesMetadata)) {
			throw new \InvalidArgumentException("Template variable '{$key}' is not allowed");
		}
	}

	private function ensureType(string $key, mixed $value): void {
		$expected = $this->variablesMetadata[$key]['type'];
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
	 * Get metadata for all available template variables
	 *
	 * @return array Associative array of variable metadata (name => config)
	 */
	public function getVariablesMetadata(): array {
		return $this->variablesMetadata;
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
