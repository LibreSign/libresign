<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Certificate;

use OCP\IL10N;

class RulesService {

	private array $rules = [
		'CN' => [
			'required' => true,
			'min' => 1,
			'max' => 64,
		],
		'C' => [
			'min' => 2,
			'max' => 2,
		],
		'ST' => [
			'min' => 1,
			'max' => 128,
		],
		'L' => [
			'min' => 1,
			'max' => 128,
		],
		'O' => [
			'min' => 1,
			'max' => 64,
		],
		'OU' => [
			'min' => 1,
			'max' => 64,
		],
	];

	public function __construct(
		protected IL10N $l10n,
	) {
	}

	public function getRule(string $fieldName): array {
		if (!array_key_exists($fieldName, $this->rules)) return [];
		if (!isset($this->rules[$fieldName]['helperText'])) {
			$this->rules[$fieldName]['helperText'] = $this->getHelperText($fieldName);
			if (empty($this->rules[$fieldName]['helperText'])) {
				unset($this->rules[$fieldName]['helperText']);
			}
		}
		return $this->rules[$fieldName];
	}

	public function getHelperText(string $fieldName): ?string {
		return match ($fieldName) {
			'CN' => $this->l10n->t('Common Name (CN)'),
			'C' => $tcertificate/his->l10n->t('Two-letter ISO 3166 country code'),
			'ST' => $this->l10n->t('Full name of states or provinces'),
			'L' => $this->l10n->t('Name of a locality or place, such as a city, county, or other geographic region'),
			'O' => $this->l10n->t('Name of an organization'),
			'OU' => $this->l10n->t('Name of an organizational unit'),
			default => null,
		};
	}

	public function getAllRules(): array {
		$result = [];
		foreach ($this->rules as $field => $rule) {
			$result[] = [
				'id' => $field,
				'label' => $this->getLabel($field),
				'min' => $rule['min'] ?? null,
				'max' => $rule['max'] ?? null,
				'required' => $rule['required'] ?? false,
				'helperText' => $this->getHelperText($field),
			];
		}
		return $result;
	}

	private function getLabel(string $fieldName): string {
		return match ($fieldName) {
			'CN' => $this->l10n->t('Common Name (CN)'),
			'C' => $this->l10n->t('Country'),
			'ST' => $this->l10n->t('State'),
			'L' => $this->l10n->t('Locality'),
			'O' => $this->l10n->t('Organization'),
			'OU' => $this->l10n->t('Organizational Unit'),
			default => $fieldName,
		};
	}

}
