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

use OCP\Activity\ActivitySettings;
use OCP\IL10N;

class FileToSign extends ActivitySettings {
	public function __construct(
		protected IL10N $l,
	) {
	}

	/**
	 * @return string Lowercase a-z and underscore only identifier. The type of table activity
	 * @since 20.0.0
	 */
	public function getIdentifier(): string {
		return 'file_to_sign';
	}

	/**
	 * @return string A translated string
	 * @since 11.0.0
	 */
	public function getName(): string {
		return $this->l->t('You have a <strong>file to sign</strong>');
	}

	/**
	 * {@inheritdoc}
	 */
	public function getGroupIdentifier(): string {
		return 'other';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getGroupName(): string {
		return $this->l->t('Other activities');
	}

	/**
	 * {@inheritdoc}
	 */
	public function getPriority(): int {
		return 51;
	}
	/**
	 * {@inheritdoc}
	 */
	public function canChangeNotification(): bool {
		return true;
	}
	/**
	 * {@inheritdoc}
	 */
	public function canChangeMail() {
		return true;
	}
	/**
	 * {@inheritdoc}
	 */
	public function isDefaultEnabledMail() {
		return true;
	}
	/**
	 * {@inheritdoc}
	 */
	public function isDefaultEnabledNotification(): bool {
		return true;
	}
}
