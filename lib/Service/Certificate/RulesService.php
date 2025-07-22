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
	)
	{

	}

	public function getRule(string $fieldName){
		if(!isset($this->rules[$fieldName]['helperText'])){
			$this->rules[$fieldName]['helperText'] = $this->getHelperText($fieldName);
		}
		return $this->rules[$fieldName];
	}

	public function getHelperText(string $fieldName) {
		return match ($fieldName) {
			'CN' => $this->l10n->t('Name (CN)'),
			'C' => $this->l10n->t('Two-letter ISO 3166 country code'),
			'ST' => $this->l10n->t('Full name of states or provinces'),
			'L' => $this->l10n->t('Name of a locality or place, such as a city, county, or other geographic region'),
			'O' => $this->l10n->t('Name of an organization'),
			'OU' => $this->l10n->t('Name of an organizational unit'),
			default => null,
		};
	}
}
