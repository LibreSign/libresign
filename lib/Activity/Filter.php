<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Activity;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Events\SendSignNotificationEvent;
use OCA\Libresign\Events\SignedEvent;
use OCP\Activity\IFilter;
use OCP\IL10N;
use OCP\IURLGenerator;

class Filter implements IFilter {
	private const ALLOWED_TYPES = [
		SendSignNotificationEvent::FILE_TO_SIGN,
		SignedEvent::FILE_SIGNED,
	];
	public function __construct(
		protected IL10N $l,
		protected IURLGenerator $url,
	) {
		$this->l = $l;
		$this->url = $url;
	}

	#[\Override]
	public function getIdentifier() {
		return Application::APP_ID;
	}

	#[\Override]
	public function getName() {
		return 'LibreSign';
	}

	#[\Override]
	public function getPriority() {
		return 31;
	}

	#[\Override]
	public function getIcon() {
		return $this->url->getAbsoluteURL($this->url->imagePath('libresign', 'app-dark.svg'));
	}

	#[\Override]
	public function filterTypes(array $types) {
		return array_intersect(self::ALLOWED_TYPES, $types);
	}

	#[\Override]
	public function allowedApps() {
		return [
			Application::APP_ID,
		];
	}
}
