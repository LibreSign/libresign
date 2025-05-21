<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Activity\Settings;

use OCA\Libresign\Activity\Settings\FileSigned;
use OCA\Libresign\Events\SignedEvent;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\ValidateHelper;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class FileSignedTest extends TestCase {
	private $l10nMock;
	private $validateHelperMock;
	private $userSessionMock;

	protected function setUp(): void {
		$this->l10nMock = $this->createMock(IL10N::class);
		$this->validateHelperMock = $this->createMock(ValidateHelper::class);
		$this->userSessionMock = $this->createMock(IUserSession::class);
	}

	private function getClass(): FileSigned {
		return new FileSigned(
			$this->l10nMock,
			$this->validateHelperMock,
			$this->userSessionMock
		);
	}

	public function testGetIdentifier(): void {
		$this->assertSame(SignedEvent::FILE_SIGNED, $this->getClass()->getIdentifier());
	}

	public function testCanChangeNotificationSuccess(): void {
		$userMock = $this->createMock(IUser::class);
		$this->userSessionMock->method('getUser')->willReturn($userMock);
		$this->validateHelperMock->method('canrequestSign')->with($userMock);

		$this->assertTrue($this->getClass()->canChangeNotification());
	}

	public function testCanChangeNotificationFailure(): void {
		$userMock = $this->createMock(IUser::class);
		$this->userSessionMock->method('getUser')->willReturn($userMock);
		$this->validateHelperMock->method('canrequestSign')
			->willThrowException(new LibresignException());

		$this->assertFalse($this->getClass()->canChangeNotification());
	}

	public function testCanChangeMailSuccess(): void {
		$userMock = $this->createMock(IUser::class);
		$this->userSessionMock->method('getUser')->willReturn($userMock);
		$this->validateHelperMock->method('canrequestSign')->with($userMock);

		$this->assertTrue($this->getClass()->canChangeMail());
	}

	public function testCanChangeMailFailure(): void {
		$userMock = $this->createMock(IUser::class);
		$this->userSessionMock->method('getUser')->willReturn($userMock);
		$this->validateHelperMock->method('canrequestSign')
			->willThrowException(new LibresignException());

		$this->assertFalse($this->getClass()->canChangeMail());
	}
}
