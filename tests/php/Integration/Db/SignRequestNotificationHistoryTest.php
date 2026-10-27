<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Db;

use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCP\Server;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/** @group DB */
final class SignRequestNotificationHistoryTest extends \OCA\Libresign\Tests\Integration\TestCase {
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testEachChannelUpdatesHistoryWithoutReusingFirstNotificationFlag(): void {
		$mapper = Server::get(SignRequestMapper::class);
		$request = new SignRequest();
		$request->setUuid('notify-' . bin2hex(random_bytes(14)));
		$request->setFileId(null);
		$request->setDisplayName('Synthetic signer');
		$request->setDescription('Synthetic request');
		$request->setCreatedAt(new \DateTime('now', new \DateTimeZone('UTC')));
		$request = $mapper->insert($request);

		try {
			$this->assertTrue($mapper->incrementNotificationCounter($request, 'mail'));
			$this->assertFalse($mapper->incrementNotificationCounter($request, 'notify'));
			$this->assertFalse($mapper->incrementNotificationCounter($request, 'activity'));
			$this->assertFalse($mapper->incrementNotificationCounter($request, 'mail'));

			$persisted = $mapper->getByIdUncached($request->getId());
			$history = $persisted->getMetadata()['notify'] ?? [];
			$this->assertCount(4, $history);
			$this->assertSame(['mail', 'notify', 'activity', 'mail'], array_column($history, 'method'));
			$this->assertSame('Synthetic request', $history[0]['description']);
		} finally {
			$mapper->delete($request);
		}
	}
}
