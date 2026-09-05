<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Activity\Settings;

use OCA\Libresign\Activity\Settings\SignatureRejected;
use OCA\Libresign\Events\SignatureRejectedEvent;
use OCA\Libresign\Helper\ValidateHelper;
use OCP\IL10N;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

final class SignatureRejectedTest extends TestCase {
	private function getClass(): SignatureRejected {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		return new SignatureRejected(
			$l10n,
			$this->createMock(ValidateHelper::class),
			$this->createMock(IUserSession::class),
		);
	}

	public function testGetIdentifier(): void {
		$this->assertSame(SignatureRejectedEvent::SIGNATURE_REJECTED, $this->getClass()->getIdentifier());
	}

	public function testGetName(): void {
		$this->assertSame('A signature request has been <strong>rejected</strong>', $this->getClass()->getName());
	}

	public function testIsListedAfterTheCancellationSetting(): void {
		$this->assertSame(52, $this->getClass()->getPriority());
	}

	public function testDeliveryChannelsAreConfigurable(): void {
		$settings = $this->getClass();

		$this->assertTrue($settings->canChangeNotification());
		$this->assertTrue($settings->canChangeMail());
	}
}
