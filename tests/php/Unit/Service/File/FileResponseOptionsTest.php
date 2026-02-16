<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\File;

use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Service\File\FileResponseOptions;
use OCP\IUser;
use PHPUnit\Framework\TestCase;

class FileResponseOptionsTest extends TestCase {
	private FileResponseOptions $options;

	protected function setUp(): void {
		parent::setUp();
		$this->options = new FileResponseOptions();
	}

	public function testDefaultsAreFalseForBooleanOptions(): void {
		$this->assertFalse($this->options->isShowSigners());
		$this->assertFalse($this->options->isShowSettings());
		$this->assertFalse($this->options->isShowVisibleElements());
		$this->assertFalse($this->options->isShowMessages());
		$this->assertFalse($this->options->isValidateFile());
		$this->assertFalse($this->options->isSignerIdentified());
	}

	public function testDefaultsAreNullForOptionalOptions(): void {
		$this->assertNull($this->options->getMe());
		$this->assertNull($this->options->getIdentifyMethodId());
	}

	public function testDefaultHostIsEmptyString(): void {
		$this->assertEquals('', $this->options->getHost());
	}

	public function testCanSetShowSigners(): void {
		$this->options->showSigners(true);
		$this->assertTrue($this->options->isShowSigners());

		$this->options->showSigners(false);
		$this->assertFalse($this->options->isShowSigners());
	}

	public function testCanSetMe(): void {
		$user = $this->createMock(IUser::class);
		$this->options->setMe($user);
		$this->assertSame($user, $this->options->getMe());
	}

	public function testCanSetIdentifyMethodId(): void {
		$this->options->setIdentifyMethodId(42);
		$this->assertEquals(42, $this->options->getIdentifyMethodId());
	}

	public function testCanSetHost(): void {
		$this->options->setHost('localhost');
		$this->assertEquals('localhost', $this->options->getHost());
	}

	public function testReturnsSelfForChaining(): void {
		$result = $this->options->showSigners()->showSettings()->showMessages();
		$this->assertSame($this->options, $result);
	}

	public function testCanChainMultipleOptions(): void {
		$user = $this->createMock(IUser::class);
		$this->options
			->showSigners(true)
			->showSettings(true)
			->setMe($user)
			->setHost('example.com');

		$this->assertTrue($this->options->isShowSigners());
		$this->assertTrue($this->options->isShowSettings());
		$this->assertSame($user, $this->options->getMe());
		$this->assertEquals('example.com', $this->options->getHost());
	}

	public function testDefaultSignRequestIsNull(): void {
		$this->assertNull($this->options->getSignRequest());
	}

	public function testCanSetSignRequest(): void {
		$signRequest = $this->createMock(SignRequest::class);
		$result = $this->options->setSignRequest($signRequest);
		$this->assertSame($signRequest, $this->options->getSignRequest());
		$this->assertSame($this->options, $result);
	}

	public function testCanSetSignRequestToNull(): void {
		$signRequest = $this->createMock(SignRequest::class);
		$this->options->setSignRequest($signRequest);
		$this->options->setSignRequest(null);
		$this->assertNull($this->options->getSignRequest());
	}
}
