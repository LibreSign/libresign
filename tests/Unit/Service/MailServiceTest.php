<?php

namespace OCA\Libresign\Tests\Unit\Service;

use OC\Mail\Mailer;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUser;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Service\MailService;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Mail\IMailer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @internal
 * @coversNothing
 */
final class MailServiceTest extends TestCase {
	/** @var LoggerInterface */
	private $logger;
	/** @var Mailer */
	private $mailer;
	/** @var FileMapper */
	private $fileMapper;
	/** @var FileUserMapper */
	private $fileUserMapper;
	/** @var IL10N */
	private $l10n;
	/** @var IURLGenerator */
	private $urlGenerator;

	public function setUp(): void {
		parent::setUp();
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->mailer = $this->createMock(IMailer::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->fileUserMapper = $this->createMock(FileUserMapper::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->service = new MailService(
			$this->logger,
			$this->mailer,
			$this->fileMapper,
			$this->fileUserMapper,
			$this->l10n,
			$this->urlGenerator
		);
	}

	public function testFailNoUsersToNotify() {
		$this->expectExceptionMessage('No users to notify');

		$this->service->notifyAllUnsigned();
	}

	public function testSuccessNotifyAllUnsigned() {
		$this->fileUserMapper
			->method('findUnsigned')
			->will($this->returnValue([new FileUser()]));

		$actual = $this->service->notifyAllUnsigned();
		$this->assertTrue($actual);
	}

	public function testFailToSendMailToUnsignedUser() {
		$this->expectExceptionMessage('Notify unsigned notification mail could not be sent');

		$this->mailer
			->method('send')
			->willReturnCallback(function () {
				throw new \Exception("Error Processing Request", 1);
			});
		// ->will($this->throwException(new \Exception()));
		$this->fileUserMapper
			->method('findUnsigned')
			->will($this->returnValue([new FileUser()]));

		$actual = $this->service->notifyAllUnsigned();
		$this->assertTrue($actual);
	}
}
