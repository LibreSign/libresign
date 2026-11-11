<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service;

use OCA\Libresign\Service\SignatureRejection\SignatureRejectionService;
use OCP\Server;

/** @group DB */
final class SignatureRejectionWiringTest extends \OCA\Libresign\Tests\Integration\TestCase {
	public function testServiceResolvesThroughTheNextcloudContainer(): void {
		$this->assertInstanceOf(SignatureRejectionService::class, Server::get(SignatureRejectionService::class));
	}
}
