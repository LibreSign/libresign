<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2024 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
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
		return ['file_to_sign'];
	}

	public function allowedApps() {
		return [
			'file_to_sign',
			Application::APP_ID,
		];
	}
}
