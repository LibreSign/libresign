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
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\MailService;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
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
			->willReturnArgument(0);
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

			->willReturnCallback(fn (string $method)
				=> match ($method) {
					'getUuid' => 'asdfg',
					'getFileId' => 1,
					'getDisplayName' => 'John Doe'
				}
			);

		$file = $this->createMock(File::class);
		$file
			->method('__call')
			->with($this->equalTo('getName'), $this->anything())
			->willReturn('Filename');
		$this->fileMapper
			->method('getById')
			->willReturn($file);
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
			->willReturnCallback(fn (string $method)
				=> match ($method) {
					'getUuid' => 'asdfg',
					'getFileId' => 1,
					'getDisplayName' => 'John doe',
				}
			);

		$file = $this->createMock(File::class);
		$file
			->method('__call')
			->with($this->equalTo('getName'), $this->anything())
			->willReturn('Filename');
		$this->fileMapper
			->method('getById')
			->willReturn($file);
		$this->mailer
			->method('send')
			->willReturnCallback(function ():void {
				throw new \Exception('Error Processing Request', 1);
			});
		$this->appConfig
			->method('getValueBool')
			->willReturn(true);
		$actual = $this->service->notifyUnsignedUser($signRequest, 'a@b.coop');
		$this->assertNull($actual);
	}

	public function testSendCodeToSignAcceptsSuccessfulSubmission(): void {
		$template = $this->createMock(IEMailTemplate::class);
		$this->mailer->method('createEMailTemplate')->willReturn($template);
		$message = $this->createMock(IMessage::class);
		$this->mailer->method('createMessage')->willReturn($message);
		$message->expects($this->once())
			->method('setTo')
			->with(['recipient@example.com']);
		$this->mailer->expects($this->once())
			->method('send')
			->with($message)
			->willReturn([]);

		$this->service->sendCodeToSign('recipient@example.com', '', '123456');
	}

	public function testSendCodeToSignReportsRejectedRecipients(): void {
		$template = $this->createMock(IEMailTemplate::class);
		$this->mailer->method('createEMailTemplate')->willReturn($template);
		$message = $this->createMock(IMessage::class);
		$this->mailer->method('createMessage')->willReturn($message);
		$this->mailer->expects($this->once())
			->method('send')
			->with($message)
			->willReturn(['recipient@example.com']);
		$this->logger->expects($this->once())->method('error');

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Mail with code to sign document could not be sent');
		$this->service->sendCodeToSign('recipient@example.com', 'Recipient', '123456');
	}
}
