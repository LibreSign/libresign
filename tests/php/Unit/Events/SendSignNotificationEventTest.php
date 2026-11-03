<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Events;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Events\SendSignNotificationEvent;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use PHPUnit\Framework\TestCase;

final class SendSignNotificationEventTest extends TestCase {
	public function testDuplicateBusinessStateHasOneIdentityDespiteLegacyHistory(): void {
		$file = new File();
		$file->setUuid('file-uuid');
		$request = new SignRequest();
		$request->setId(100);
		$request->setUuid('request-uuid');
		$request->setDescription('Initial');
		$identity = $this->createMock(IIdentifyMethod::class);

		$first = new SendSignNotificationEvent($request, $file, $identity);
		$request->setMetadata(['notify' => [['method' => 'mail', 'date' => 10]]]);
		$second = new SendSignNotificationEvent($request, $file, $identity);

		$this->assertSame($first->getDeliveryEventId(), $second->getDeliveryEventId());
		$request->setMetadata([
			'notify' => [['method' => 'activity', 'date' => 20]],
			'last_visit' => 'different unrelated value',
		]);
		$this->assertSame($first->getDeliveryEventId(), (new SendSignNotificationEvent($request, $file, $identity))->getDeliveryEventId());
		$request->setDescription('Updated');
		$updated = new SendSignNotificationEvent($request, $file, $identity);
		$this->assertNotSame($first->getDeliveryEventId(), $updated->getDeliveryEventId());
	}

	public function testReminderAndRegularRequestHaveDifferentIdentities(): void {
		$file = new File();
		$request = new SignRequest();
		$identity = $this->createMock(IIdentifyMethod::class);
		$regular = new SendSignNotificationEvent($request, $file, $identity);
		$reminder = new SendSignNotificationEvent($request, $file, $identity, 'reminder:one:2026-10-25');
		$next = new SendSignNotificationEvent($request, $file, $identity, 'reminder:one:2026-10-26');

		$this->assertNotSame($regular->getDeliveryEventId(), $reminder->getDeliveryEventId());
		$this->assertNotSame($reminder->getDeliveryEventId(), $next->getDeliveryEventId());
	}
}
