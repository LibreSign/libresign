<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\File;

use OCA\Libresign\Db\File;
use OCA\Libresign\Service\File\FileResponseOptions;
use OCA\Libresign\Service\File\MessagesLoader;
use OCA\Libresign\Service\File\SignersLoader;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use stdClass;

final class MessagesLoaderTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private SignersLoader|MockObject $signersLoader;
	private IL10N|MockObject $l10n;

	public function setUp(): void {
		parent::setUp();
		$this->signersLoader = $this->createMock(SignersLoader::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnCallback(fn ($text) => $text);
	}

	private function getService(): MessagesLoader {
		return new MessagesLoader(
			$this->signersLoader,
			$this->l10n,
		);
	}

	public function testLoadMessagesNotShown(): void {
		$file = $this->createMock(File::class);
		$fileData = new stdClass();
		$fileData->settings = [];
		$options = $this->createMock(FileResponseOptions::class);
		$options->method('isShowMessages')->willReturn(false);

		$service = $this->getService();
		$service->loadMessages($file, $fileData, $options);

		$this->assertFalse(property_exists($fileData, 'messages'));
	}

	public function testLoadMessagesCanSign(): void {
		$file = $this->createMock(File::class);
		$fileData = new stdClass();
		$fileData->settings = ['canSign' => true];
		$options = $this->createMock(FileResponseOptions::class);
		$options->method('isShowMessages')->willReturn(true);

		$service = $this->getService();
		$service->loadMessages($file, $fileData, $options);

		$this->assertTrue(isset($fileData->messages));
		$this->assertCount(1, $fileData->messages);
		$this->assertEquals('info', $fileData->messages[0]['type']);
		$this->assertEquals('You need to sign this document', $fileData->messages[0]['message']);
	}

	public function testLoadMessagesCannotRequestSign(): void {
		$file = $this->createMock(File::class);
		$fileData = new stdClass();
		$fileData->settings = ['canRequestSign' => true];
		$fileData->signers = [];
		$options = $this->createMock(FileResponseOptions::class);
		$options->method('isShowMessages')->willReturn(true);

		$this->signersLoader->method('loadLibreSignSigners');

		$service = $this->getService();
		$service->loadMessages($file, $fileData, $options);

		$this->assertTrue(isset($fileData->messages));
		$this->assertCount(1, $fileData->messages);
		$this->assertEquals('You cannot request signature for this document, please contact your administrator', $fileData->messages[0]['message']);
	}

	public function testLoadMessagesCanRequestSignWithSigners(): void {
		$file = $this->createMock(File::class);
		$fileData = new stdClass();
		$fileData->settings = ['canRequestSign' => true];
		$fileData->signers = [
			(object)['displayName' => 'John Doe'],
		];
		$options = $this->createMock(FileResponseOptions::class);
		$options->method('isShowMessages')->willReturn(true);

		$this->signersLoader->method('loadLibreSignSigners');

		$service = $this->getService();
		$service->loadMessages($file, $fileData, $options);

		// No message when there are signers
		$this->assertFalse(property_exists($fileData, 'messages'));
	}

	public function testLoadMessagesNoSettings(): void {
		$file = $this->createMock(File::class);
		$fileData = new stdClass();
		$options = $this->createMock(FileResponseOptions::class);
		$options->method('isShowMessages')->willReturn(true);

		$service = $this->getService();
		$service->loadMessages($file, $fileData, $options);

		$this->assertFalse(property_exists($fileData, 'messages'));
	}

	public function testLoadMessagesBothConditions(): void {
		$file = $this->createMock(File::class);
		$fileData = new stdClass();
		$fileData->settings = [
			'canSign' => true,
			'canRequestSign' => true,
		];
		$fileData->signers = [];
		$options = $this->createMock(FileResponseOptions::class);
		$options->method('isShowMessages')->willReturn(true);

		$this->signersLoader->method('loadLibreSignSigners');

		$service = $this->getService();
		$service->loadMessages($file, $fileData, $options);

		$this->assertTrue(isset($fileData->messages));
		$this->assertCount(2, $fileData->messages);
	}

	public function testLoadMessagesCallsSignersLoaderWhenCanRequestSign(): void {
		$file = $this->createMock(File::class);
		$fileData = new stdClass();
		$fileData->settings = ['canRequestSign' => true];
		$fileData->signers = [];
		$options = $this->createMock(FileResponseOptions::class);
		$options->method('isShowMessages')->willReturn(true);

		$certData = ['cert1', 'cert2'];

		$this->signersLoader
			->expects($this->once())
			->method('loadLibreSignSigners')
			->with($file, $fileData, $options, $certData);

		$service = $this->getService();
		$service->loadMessages($file, $fileData, $options, $certData);
	}

	public function testLoadMessagesWithNullFile(): void {
		$fileData = new stdClass();
		$fileData->settings = ['canSign' => true];
		$options = $this->createMock(FileResponseOptions::class);
		$options->method('isShowMessages')->willReturn(true);

		$service = $this->getService();
		$service->loadMessages(null, $fileData, $options);

		$this->assertTrue(isset($fileData->messages));
		$this->assertCount(1, $fileData->messages);
	}

	public function testLoadMessagesCanSignFalse(): void {
		$file = $this->createMock(File::class);
		$fileData = new stdClass();
		$fileData->settings = ['canSign' => false];
		$options = $this->createMock(FileResponseOptions::class);
		$options->method('isShowMessages')->willReturn(true);

		$service = $this->getService();
		$service->loadMessages($file, $fileData, $options);

		$this->assertFalse(property_exists($fileData, 'messages'));
	}

	public function testLoadMessagesCanRequestSignFalse(): void {
		$file = $this->createMock(File::class);
		$fileData = new stdClass();
		$fileData->settings = ['canRequestSign' => false];
		$options = $this->createMock(FileResponseOptions::class);
		$options->method('isShowMessages')->willReturn(true);

		$service = $this->getService();
		$service->loadMessages($file, $fileData, $options);

		$this->assertFalse(property_exists($fileData, 'messages'));
	}
}
