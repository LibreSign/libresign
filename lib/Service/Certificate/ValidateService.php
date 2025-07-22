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
	)
	{

	}

	public function validate(string $fieldName, string $value):void {
		$rule = $this->rulesService->getRule($fieldName);
		$value = trim($value);
		$length = strlen($value);
		if(!$length && isset($rule['required']) && $rule['required']){
			throw new InvalidArgumentException(
				$this->l10n->t("Parameter '%s' is required!", [$fieldName])
			);
		}
		if($length > $rule['max'] || $length < $rule['min']){
			throw new InvalidArgumentException(
				$this->l10n->t("Parameter '%s' should be betweeen %s and %s.", [$fieldName, $rule['min'], $rule['max']])
			);
		}
	}

	public function validateNames(array $names){
		foreach ($names as $item) {
			if (empty($item['id'])) {
				throw new InvalidArgumentException('Parameter id is required!');
			}
			$this->validate($item['id'], $item['value']);
		}
	}

}
