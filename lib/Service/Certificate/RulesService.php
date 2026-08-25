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
			'type' => 'string',
			'required' => true,
			'min' => 1,
			'max' => 64,
		],
		'C' => [
			'type' => 'string',
			'min' => 2,
			'max' => 2,
		],
		'ST' => [
			'type' => 'string',
			'min' => 1,
			'max' => 128,
		],
		'L' => [
			'type' => 'string',
			'min' => 1,
			'max' => 128,
		],
		'O' => [
			'type' => 'string',
			'min' => 1,
			'max' => 64,
		],
		'OU' => [
			'type' => 'array',
			'required' => false,
			'min' => 1,
			'max' => 64,
			'minItems' => 0,
			'maxItems' => 10,
		],
	];

	public function __construct(
		protected IL10N $l10n,
	) {

	}

	public function getRule(string $fieldName): array {
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
			// TRANSLATORS Label for the X.509 certificate subject Common Name (CN) field shown when generating a signing certificate.
			'CN' => $this->l10n->t('Common Name (CN)'),
			// TRANSLATORS Help text for the X.509 certificate subject country (C) field; value must be a two-letter ISO 3166 code.
			'C' => $this->l10n->t('Two-letter ISO 3166 country code'),
			// TRANSLATORS Help text for the X.509 certificate subject state or province (ST) field.
			'ST' => $this->l10n->t('Full name of states or provinces'),
			// TRANSLATORS Help text for the X.509 certificate subject locality (L) field such as city or region.
			'L' => $this->l10n->t('Name of a locality or place, such as a city, county, or other geographic region'),
			// TRANSLATORS Help text for the X.509 certificate subject organization (O) field.
			'O' => $this->l10n->t('Name of an organization'),
			// TRANSLATORS Help text for the X.509 certificate subject organizational unit (OU) field.
			'OU' => $this->l10n->t('Name of an organizational unit'),
			default => null,
		};
	}
}
