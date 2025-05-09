<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Activity;

use OCA\Libresign\AppInfo\Application;
use OCP\Activity\IFilter;
use OCP\IL10N;
use OCP\IURLGenerator;

class Filter implements IFilter {
	public function __construct(
		protected IL10N $l,
		protected IURLGenerator $url,
	) {
		$this->l = $l;
		$this->url = $url;
	}

	public function getIdentifier() {
		return Application::APP_ID;
	}

	public function getName() {
		return 'LibreSign';
	}

	public function getPriority() {
		return 31;
	}

	public function getIcon() {
		return $this->url->getAbsoluteURL($this->url->imagePath('libresign', 'app-dark.svg'));
	}

	public function filterTypes(array $types) {
		return array_intersect([
			'file_to_sign',
			'file_signed',
		], $types);
	}

	public function allowedApps() {
		return [
			Application::APP_ID,
		];
	}
}
