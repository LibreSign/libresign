<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Model;

use Closure;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;

use function in_array;
use function sprintf;

final class PolicySpec implements IPolicyDefinition {
	public const RESOLUTION_MODE_RESOLVED = 'resolved';
	public const RESOLUTION_MODE_VALUE_CHOICE = 'value_choice';

	/** @var list<mixed>|Closure(PolicyContext): list<mixed> */
	private array|Closure $allowedValuesResolver;
	/** @var Closure(mixed): mixed|null */
	private ?Closure $normalizer;
	/** @var Closure(mixed, PolicyContext): void|null */
	private ?Closure $validator;

	/**
	 * @param list<mixed>|Closure(PolicyContext): list<mixed> $allowedValues
	 * @param Closure(mixed): mixed|null $normalizer
	 * @param Closure(mixed, PolicyContext): void|null $validator
	 */
	public function __construct(
		private string $key,
		private mixed $defaultSystemValue,
		array|Closure $allowedValues,
		?Closure $normalizer = null,
		?Closure $validator = null,
		private ?string $appConfigKey = null,
		private ?string $userPreferenceKey = null,
		private string $resolutionMode = self::RESOLUTION_MODE_RESOLVED,
		private bool $supportsUserPreference = true,
	) {
		$this->allowedValuesResolver = $allowedValues;
		$this->normalizer = $normalizer;
		$this->validator = $validator;
	}

	#[\Override]
	public function key(): string {
		return $this->key;
	}

	#[\Override]
	public function resolutionMode(): string {
		return $this->resolutionMode;
	}

	#[\Override]
	public function getAppConfigKey(): string {
		return $this->appConfigKey ?? $this->key;
	}

	#[\Override]
	public function getUserPreferenceKey(): string {
		return $this->userPreferenceKey ?? 'policy.' . $this->key;
	}

	#[\Override]
	public function normalizeValue(mixed $rawValue): mixed {
		if ($this->normalizer !== null) {
			return ($this->normalizer)($rawValue);
		}

		return $rawValue;
	}

	#[\Override]
	public function validateValue(mixed $value, PolicyContext $context): void {
		if ($this->validator !== null) {
			($this->validator)($value, $context);
			return;
		}

		if (!in_array($value, $this->allowedValues($context), true)) {
			throw new \InvalidArgumentException(sprintf('Invalid value for %s', $this->key()));
		}
	}

	#[\Override]
	public function allowedValues(PolicyContext $context): array {
		if ($this->allowedValuesResolver instanceof Closure) {
			return ($this->allowedValuesResolver)($context);
		}

		return $this->allowedValuesResolver;
	}

	#[\Override]
	public function defaultSystemValue(): mixed {
		return $this->defaultSystemValue;
	}

	#[\Override]
	public function supportsUserPreference(): bool {
		return $this->supportsUserPreference;
	}
}
