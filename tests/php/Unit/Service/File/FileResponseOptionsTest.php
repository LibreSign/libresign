<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\File;

use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Service\File\FileResponseOptions;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCP\IUser;
use PHPUnit\Framework\TestCase;

class FileResponseOptionsTest extends TestCase {
	private FileResponseOptions $options;

	protected function setUp(): void {
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

	private function identifyMethodsOf(int $entityId, string $key, string $value): array {
		$entity = new IdentifyMethod();
		$entity->setId($entityId);
		$entity->setIdentifierKey($key);
		$entity->setIdentifierValue($value);
		$identifyMethod = $this->createMock(IIdentifyMethod::class);
		$identifyMethod->method('getEntity')->willReturn($entity);
		return [$key => [$identifyMethod]];
	}

	public function testAnAnonymousViewerIsNobody(): void {
		$this->assertFalse($this->options->isViewerOfSigner($this->identifyMethodsOf(5, 'account', 'joao')));
	}

	public function testTheAuthenticatedUserIsTheSignerBehindTheirAccountOrEmail(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('joao');
		$user->method('getEMailAddress')->willReturn('joao@example.com');
		$this->options->setMe($user);

		$this->assertTrue($this->options->isViewerOfSigner($this->identifyMethodsOf(5, 'account', 'joao')));
		$this->assertTrue($this->options->isViewerOfSigner($this->identifyMethodsOf(6, 'email', 'joao@example.com')));
		$this->assertFalse($this->options->isViewerOfSigner($this->identifyMethodsOf(7, 'account', 'maria')));
		$this->assertFalse($this->options->isViewerOfSigner([]));
	}

	public function testTheIdentifiedSignerIsRecognizedByTheIdentifyMethodOfTheSession(): void {
		$this->options->setIdentifyMethodId(6);

		$this->assertTrue($this->options->isViewerOfSigner($this->identifyMethodsOf(6, 'email', 'external@example.com')));
		$this->assertFalse($this->options->isViewerOfSigner($this->identifyMethodsOf(7, 'email', 'other@example.com')));
	}
}
