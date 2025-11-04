<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Certificate;

use InvalidArgumentException;
use OCP\IL10N;

class ValidateService {

	public function __construct(
		protected RulesService $rulesService,
		protected IL10N $l10n,
	) {

	}

	public function validate(string $fieldName, string|array $value): void {
		$rule = $this->rulesService->getRule($fieldName);
		$expectedType = $rule['type'] ?? 'string';

		if ($expectedType === 'array' && !is_array($value)) {
			throw new InvalidArgumentException(
				$this->l10n->t("Parameter '%s' is required!", [$fieldName])
			);
		}

		if ($expectedType === 'string' && !is_string($value)) {
			throw new InvalidArgumentException(
				$this->l10n->t("Parameter '%s' is required!", [$fieldName])
			);
		}

		if ($expectedType === 'array') {
			$this->validateArray($fieldName, $value, $rule);
		} else {
			$this->validateString($fieldName, $value, $rule);
		}
	}

	private function validateString(string $fieldName, string $value, array $rule): void {
		$value = trim($value);
		$length = strlen($value);
		if (!$length && isset($rule['required']) && $rule['required']) {
			throw new InvalidArgumentException(
				$this->l10n->t("Parameter '%s' is required!", [$fieldName])
			);
		}
		if ($length > $rule['max'] || $length < $rule['min']) {
			throw new InvalidArgumentException(
				$this->l10n->t("Parameter '%s' should be betweeen %s and %s.", [$fieldName, $rule['min'], $rule['max']])
			);
		}
	}

	private function validateArray(string $fieldName, array $values, array $rule): void {
		$arrayLength = count($values);
		if (isset($rule['minItems']) && $arrayLength < $rule['minItems']) {
			throw new InvalidArgumentException(
				$this->l10n->t("Parameter '%s' should be betweeen %s and %s.", [$fieldName . ' items', $rule['minItems'], $rule['maxItems'] ?? '∞'])
			);
		}
		if (isset($rule['maxItems']) && $arrayLength > $rule['maxItems']) {
			throw new InvalidArgumentException(
				$this->l10n->t("Parameter '%s' should be betweeen %s and %s.", [$fieldName . ' items', $rule['minItems'] ?? 0, $rule['maxItems']])
			);
		}

		$nonEmptyValues = array_filter($values, fn ($value) => is_string($value) && trim($value) !== '');

		if (empty($nonEmptyValues) && isset($rule['required']) && $rule['required']) {
			throw new InvalidArgumentException(
				$this->l10n->t("Parameter '%s' is required!", [$fieldName])
			);
		}

		foreach ($values as $index => $value) {
			if (!is_string($value)) {
				throw new InvalidArgumentException(
					$this->l10n->t("Parameter '%s' is required!", [$fieldName])
				);
			}

			if (trim($value) !== '') {
				$this->validateString($fieldName, $value, $rule);
			}
		}
	}

	public function validateNames(array $names): void {
		foreach ($names as $item) {
			if (empty($item['id'])) {
				throw new InvalidArgumentException('Parameter id is required!');
			}

			if (!isset($item['value'])) {
				throw new InvalidArgumentException("Parameter 'value' is required for field '{$item['id']}'!");
			}

			$this->validate($item['id'], $item['value']);
		}
	}

}
