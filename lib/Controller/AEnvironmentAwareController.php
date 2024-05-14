<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCP\AppFramework\OCSController;

abstract class AEnvironmentAwareController extends OCSController {
	protected int $apiVersion = 1;

	public function setAPIVersion(int $apiVersion): void {
		$this->apiVersion = $apiVersion;
	}

	public function getAPIVersion(): int {
		return $this->apiVersion;
	}
}
