<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Libresign\Activity\Settings;

use OCP\Activity\ActivitySettings;

abstract class SignActivitySettings extends ActivitySettings {

	/**
	 * {@inheritdoc}
	 */
	public function getGroupIdentifier() {
		return 'libresign';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getGroupName() {
		return 'Libresign';
	}
}
