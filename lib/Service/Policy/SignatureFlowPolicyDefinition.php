<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy;

use OCA\Libresign\Enum\SignatureFlow;

final class SignatureFlowPolicyDefinition implements PolicyDefinitionInterface {
	#[\Override]
	public function key(): string {
		return 'signature_flow';
	}

	#[\Override]
	public function normalizeValue(mixed $rawValue): mixed {
		if (is_int($rawValue)) {
			return SignatureFlow::fromNumeric($rawValue)->value;
		}

		if ($rawValue instanceof SignatureFlow) {
			return $rawValue->value;
		}

		return $rawValue;
	}

	#[\Override]
	public function validateValue(mixed $value): void {
		if (!is_string($value) || !in_array($value, $this->allowedValues(new PolicyContext()), true)) {
			throw new \InvalidArgumentException(sprintf('Invalid value for %s', $this->key()));
		}
	}

	#[\Override]
	public function allowedValues(PolicyContext $context): array {
		return [
			SignatureFlow::NONE->value,
			SignatureFlow::PARALLEL->value,
			SignatureFlow::ORDERED_NUMERIC->value,
		];
	}

	#[\Override]
	public function defaultSystemValue(): mixed {
		return SignatureFlow::NONE->value;
	}
}
