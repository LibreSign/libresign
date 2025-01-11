<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Service\MailService;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Mail\IMailer;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final class MailServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private LoggerInterface&MockObject $logger;
	private IMailer&MockObject $mailer;
	private FileMapper&MockObject $fileMapper;
	private IL10N&MockObject $l10n;
	private IURLGenerator&MockObject $urlGenerator;
	private IAppConfig&MockObject $appConfig;
	private MailService $service;

	public function setUp(): void {
		parent::setUp();
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->mailer = $this->createMock(IMailer::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->service = new MailService(
			$this->logger,
			$this->mailer,
			$this->fileMapper,
			$this->l10n,
			$this->urlGenerator,
			$this->appConfig
		);
	}

	public function testSuccessNotifyUnsignedUser():void {
		$signRequest = $this->createMock(SignRequest::class);
		$signRequest
			->method('__call')

			->willReturnCallback(fn (string $method) =>
				match ($method) {
					'getUuid' => 'asdfg',
					'getFileId' => 1,
					'getDisplayName' => 'John Doe'
				}
			);

		$file = $this->createMock(File::class);
		$file
			->method('__call')
			->with($this->equalTo('getName'), $this->anything())
			->will($this->returnValue('Filename'));
		$this->fileMapper
			->method('getById')
			->will($this->returnValue($file));
		$this->appConfig
			->method('getValueBool')
			->willReturn(true);
		$actual = $this->service->notifyUnsignedUser($signRequest, 'a@b.coop');
		$this->assertNull($actual);
	}

	public function testFailToSendMailToUnsignedUser():void {
		$this->expectExceptionMessage('Notify unsigned notification mail could not be sent');

		$signRequest = $this->createMock(SignRequest::class);
		$signRequest
			->method('__call')
			->willReturnCallback(fn (string $method) =>
				match ($method) {
					'getUuid' => 'asdfg',
					'getFileId' => 1,
					'getDisplayName' => 'John doe',
				}
			);

		$file = $this->createMock(File::class);
		$file
			->method('__call')
			->with($this->equalTo('getName'), $this->anything())
			->will($this->returnValue('Filename'));
		$this->fileMapper
			->method('getById')
			->will($this->returnValue($file));
		$this->mailer
			->method('send')
			->willReturnCallback(function ():void {
				throw new \Exception('Error Processing Request', 1);
			});
		$this->appConfig
			->method('getValueBool')
			->will($this->returnValue(true));
		$actual = $this->service->notifyUnsignedUser($signRequest, 'a@b.coop');
		$this->assertNull($actual);
	}
}
