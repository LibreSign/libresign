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
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\MailSenderStrategy\MailSenderStrategyPolicy;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage as ISystemMessage;
use OCP\Mail\Provider\Address;
use OCP\Mail\Provider\IManager as IMailProviderManager;
use OCP\Mail\Provider\IMessage;
use OCP\Mail\Provider\IMessageSend;
use OCP\Mail\Provider\IService;
use OCP\Mail\Provider\Message;
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
	private PolicyService&MockObject $policyService;
	private IMailProviderManager&MockObject $mailProviderManager;
	private IUserManager&MockObject $userManager;
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
		$this->policyService = $this->createMock(PolicyService::class);
		$this->mailProviderManager = $this->createMock(IMailProviderManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->service = new MailService(
			$this->logger,
			$this->mailer,
			$this->fileMapper,
			$this->l10n,
			$this->urlGenerator,
			$this->appConfig,
			$this->policyService,
			$this->mailProviderManager,
			$this->userManager,
		);
	}

	public function testSuccessNotifyUnsignedUser():void {
		$this->mockRequest();
		$this->mockStrategy(MailSenderStrategyPolicy::STRATEGY_SYSTEM);
		$this->mailer->expects($this->once())
			->method('send');
		$this->mailProviderManager->expects($this->never())
			->method('has');

		$actual = $this->service->notifyUnsignedUser($this->mockSignRequest(), 'a@b.coop');
		$this->assertNull($actual);
	}

	public function testSuccessNotifyUnsignedObserverUsesValidationLink(): void {
		$this->mockRequest();
		$this->mockStrategy(MailSenderStrategyPolicy::STRATEGY_SYSTEM);
		$this->mailer->expects($this->once())
			->method('send');

		$signRequest = $this->createMock(SignRequest::class);
		$signRequest
			->method('__call')
			->willReturnCallback(fn (string $method)
				=> match ($method) {
					'getUuid' => 'observer-uuid',
					'getFileId' => 1,
					'getDisplayName' => 'Jane Observer',
				}
			);
		$signRequest->method('isObserver')->willReturn(true);

		$this->urlGenerator
			->expects($this->once())
			->method('linkToRouteAbsolute')
			->with(
				'libresign.page.validationFilePublic',
				['uuid' => 'file-uuid'],
			)
			->willReturn('https://example.com/validation/file-uuid');

		$this->service->notifyUnsignedUser($signRequest, 'observer@example.com');
	}

	public function testFailToSendMailToUnsignedUser():void {
		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Notify unsigned notification mail could not be sent');

		$this->mockRequest();
		$this->mockStrategy(MailSenderStrategyPolicy::STRATEGY_SYSTEM);
		$this->mailer
			->method('send')
			->willReturnCallback(function ():void {
				throw new \Exception('Error Processing Request', 1);
			});

		$this->service->notifyUnsignedUser($this->mockSignRequest(), 'a@b.coop');
	}

	public function testNotifySignDataUpdatedUsesSystemMailerByDefault(): void {
		$this->mockRequest();
		$this->mockStrategy(MailSenderStrategyPolicy::STRATEGY_SYSTEM);
		$this->mailer->expects($this->once())
			->method('send');
		$this->mailProviderManager->expects($this->never())
			->method('has');

		$this->service->notifySignDataUpdated($this->mockSignRequest(), 'a@b.coop');
	}

	public function testNotifyUnsignedUserSendsThroughRequesterAccount(): void {
		$this->mockRequest();
		$this->mockStrategy(MailSenderStrategyPolicy::STRATEGY_REQUESTER);
		$this->mockEmailTemplate();
		$this->mockRequesterAccount('requester@domain.coop');
		$service = $this->mockSendableService(new Address('requester@domain.coop', 'Requester'));
		$this->mailProviderManager->method('has')->willReturn(true);
		$this->mailProviderManager->expects($this->once())
			->method('findServiceByAddress')
			->with('requester', 'requester@domain.coop')
			->willReturn($service);
		$sent = null;
		$service->expects($this->once())
			->method('sendMessage')
			->willReturnCallback(function (IMessage $message) use (&$sent): void {
				$sent = $message;
			});
		$this->mailer->expects($this->never())
			->method('send');

		$this->service->notifyUnsignedUser($this->mockSignRequest('John Doe'), 'a@b.coop');

		$this->assertInstanceOf(IMessage::class, $sent);
		$this->assertSame('requester@domain.coop', $sent->getFrom()?->getAddress());
		$this->assertSame('Requester', $sent->getFrom()?->getLabel());
		$this->assertCount(1, $sent->getTo());
		$this->assertSame('a@b.coop', $sent->getTo()[0]->getAddress());
		$this->assertSame('John Doe', $sent->getTo()[0]->getLabel());
		$this->assertSame('Rendered subject', $sent->getSubject());
		$this->assertSame('<p>Rendered html</p>', $sent->getBodyHtml());
		$this->assertSame('Rendered text', $sent->getBodyPlain());
	}

	public function testNotifySignDataUpdatedSendsThroughRequesterAccount(): void {
		$this->mockRequest();
		$this->mockStrategy(MailSenderStrategyPolicy::STRATEGY_REQUESTER);
		$this->mockEmailTemplate();
		$this->mockRequesterAccount('requester@domain.coop');
		$service = $this->mockSendableService(new Address('requester@domain.coop'));
		$this->mailProviderManager->method('has')->willReturn(true);
		$this->mailProviderManager->method('findServiceByAddress')->willReturn($service);
		$service->expects($this->once())
			->method('sendMessage');
		$this->mailer->expects($this->never())
			->method('send');

		$this->service->notifySignDataUpdated($this->mockSignRequest(), 'a@b.coop');
	}

	public function testRequesterStrategyOmitsRecipientLabelWithoutDisplayName(): void {
		$this->mockRequest();
		$this->mockStrategy(MailSenderStrategyPolicy::STRATEGY_REQUESTER);
		$this->mockEmailTemplate();
		$this->mockRequesterAccount('requester@domain.coop');
		$service = $this->mockSendableService(new Address('requester@domain.coop'));
		$this->mailProviderManager->method('has')->willReturn(true);
		$this->mailProviderManager->method('findServiceByAddress')->willReturn($service);
		$sent = null;
		$service->expects($this->once())
			->method('sendMessage')
			->willReturnCallback(function (IMessage $message) use (&$sent): void {
				$sent = $message;
			});

		$this->service->notifyUnsignedUser($this->mockSignRequest(''), 'a@b.coop');

		$this->assertInstanceOf(IMessage::class, $sent);
		$this->assertSame('a@b.coop', $sent->getTo()[0]->getAddress());
		$this->assertNull($sent->getTo()[0]->getLabel());
	}

	public function testRequesterStrategyFallsBackWhenNoProviderIsAvailable(): void {
		$this->mockRequest();
		$this->mockStrategy(MailSenderStrategyPolicy::STRATEGY_REQUESTER);
		$this->mailProviderManager->method('has')->willReturn(false);
		$this->mailProviderManager->expects($this->never())
			->method('findServiceByAddress');
		$this->logger->expects($this->once())
			->method('info');
		$this->mailer->expects($this->once())
			->method('send');

		$this->service->notifyUnsignedUser($this->mockSignRequest(), 'a@b.coop');
	}

	public function testRequesterStrategyFallsBackWhenRequesterIsUnknown(): void {
		$this->mockRequest(requesterId: '');
		$this->policyService->expects($this->once())
			->method('resolveForUserId')
			->with(MailSenderStrategyPolicy::KEY, null)
			->willReturn((new ResolvedPolicy())->setEffectiveValue(MailSenderStrategyPolicy::STRATEGY_REQUESTER));
		$this->mailProviderManager->expects($this->never())
			->method('has');
		$this->mailer->expects($this->once())
			->method('send');

		$this->service->notifyUnsignedUser($this->mockSignRequest(), 'a@b.coop');
	}

	public function testRequesterStrategyFallsBackWhenRequesterAccountIsMissing(): void {
		$this->mockRequest();
		$this->mockStrategy(MailSenderStrategyPolicy::STRATEGY_REQUESTER);
		$this->mailProviderManager->method('has')->willReturn(true);
		$this->userManager->method('get')->with('requester')->willReturn(null);
		$this->mailProviderManager->expects($this->never())
			->method('findServiceByAddress');
		$this->logger->expects($this->once())
			->method('info');
		$this->mailer->expects($this->once())
			->method('send');

		$this->service->notifyUnsignedUser($this->mockSignRequest(), 'a@b.coop');
	}

	public function testRequesterStrategyFallsBackWhenNoAccountMatchesTheRequesterAddress(): void {
		$this->mockRequest();
		$this->mockStrategy(MailSenderStrategyPolicy::STRATEGY_REQUESTER);
		$this->mockRequesterAccount('requester@domain.coop');
		$this->mailProviderManager->method('has')->willReturn(true);
		$this->mailProviderManager->expects($this->once())
			->method('findServiceByAddress')
			->with('requester', 'requester@domain.coop')
			->willReturn(null);
		$this->mailProviderManager->expects($this->never())
			->method('services');
		$this->logger->expects($this->once())
			->method('info');
		$this->mailer->expects($this->once())
			->method('send');

		$this->service->notifyUnsignedUser($this->mockSignRequest(), 'a@b.coop');
	}

	public function testRequesterStrategyFallsBackWhenTheMatchingAccountCannotSend(): void {
		$this->mockRequest();
		$this->mockStrategy(MailSenderStrategyPolicy::STRATEGY_REQUESTER);
		$this->mockRequesterAccount('requester@domain.coop');
		$this->mailProviderManager->method('has')->willReturn(true);
		$this->mailProviderManager->expects($this->once())
			->method('findServiceByAddress')
			->with('requester', 'requester@domain.coop')
			->willReturn($this->createMock(IService::class));
		$this->mailProviderManager->expects($this->never())
			->method('services');
		$this->logger->expects($this->once())
			->method('info');
		$this->mailer->expects($this->once())
			->method('send');

		$this->service->notifyUnsignedUser($this->mockSignRequest(), 'a@b.coop');
	}

	public function testRequesterStrategyFallsBackWhenTheRequesterHasNoEmailAddress(): void {
		$this->mockRequest();
		$this->mockStrategy(MailSenderStrategyPolicy::STRATEGY_REQUESTER);
		$this->mockRequesterAccount('');
		$this->mailProviderManager->method('has')->willReturn(true);
		$this->mailProviderManager->expects($this->never())
			->method('findServiceByAddress');
		$this->mailProviderManager->expects($this->never())
			->method('services');
		$this->logger->expects($this->once())
			->method('info');
		$this->mailer->expects($this->once())
			->method('send');

		$this->service->notifyUnsignedUser($this->mockSignRequest(), 'a@b.coop');
	}

	public function testRequesterStrategyFallsBackWhenProviderSendingFails(): void {
		$this->mockRequest();
		$this->mockStrategy(MailSenderStrategyPolicy::STRATEGY_REQUESTER);
		$this->mockEmailTemplate();
		$this->mockRequesterAccount('requester@domain.coop');
		$service = $this->mockSendableService(new Address('requester@domain.coop'));
		$this->mailProviderManager->method('has')->willReturn(true);
		$this->mailProviderManager->method('findServiceByAddress')->willReturn($service);
		$service->method('sendMessage')
			->willThrowException(new \RuntimeException('SMTP unavailable'));
		$this->logger->expects($this->once())
			->method('warning');
		$systemMessage = $this->mockSystemMessage();
		$systemMessage->expects($this->once())
			->method('setReplyTo')
			->with(['requester@domain.coop' => 'Requester Name']);
		$this->mailer->expects($this->once())
			->method('send')
			->with($systemMessage);

		$this->service->notifyUnsignedUser($this->mockSignRequest(), 'a@b.coop');
	}

	public function testRequesterStrategyFallbackSetsReplyToRequesterWhenNoProviderIsAvailable(): void {
		$this->mockRequest();
		$this->mockStrategy(MailSenderStrategyPolicy::STRATEGY_REQUESTER);
		$this->mockRequesterAccount('requester@domain.coop', 'Requester Name');
		$this->mailProviderManager->method('has')->willReturn(false);
		$systemMessage = $this->mockSystemMessage();
		$systemMessage->expects($this->once())
			->method('setReplyTo')
			->with(['requester@domain.coop' => 'Requester Name']);
		$this->mailer->expects($this->once())
			->method('send')
			->with($systemMessage);

		$this->service->notifySignDataUpdated($this->mockSignRequest(), 'a@b.coop');
	}

	public function testRequesterStrategyFallbackWithoutRequesterEmailDoesNotSetReplyTo(): void {
		$this->mockRequest();
		$this->mockStrategy(MailSenderStrategyPolicy::STRATEGY_REQUESTER);
		$this->mockRequesterAccount('');
		$this->mailProviderManager->method('has')->willReturn(true);
		$this->mailProviderManager->method('services')->willReturn([]);
		$systemMessage = $this->mockSystemMessage();
		$systemMessage->expects($this->never())
			->method('setReplyTo');
		$this->mailer->expects($this->once())
			->method('send');

		$this->service->notifyUnsignedUser($this->mockSignRequest(), 'a@b.coop');
	}

	public function testSystemStrategyDoesNotSetReplyTo(): void {
		$this->mockRequest();
		$this->mockStrategy(MailSenderStrategyPolicy::STRATEGY_SYSTEM);
		$this->mockRequesterAccount('requester@domain.coop');
		$systemMessage = $this->mockSystemMessage();
		$systemMessage->expects($this->never())
			->method('setReplyTo');
		$this->mailer->expects($this->once())
			->method('send');

		$this->service->notifyUnsignedUser($this->mockSignRequest(), 'a@b.coop');
	}

	public function testRequesterStrategyThrowsWhenFallbackAlsoFails(): void {
		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Notify unsigned notification mail could not be sent');

		$this->mockRequest();
		$this->mockStrategy(MailSenderStrategyPolicy::STRATEGY_REQUESTER);
		$this->mailProviderManager->method('has')->willReturn(false);
		$this->mailer->method('send')
			->willThrowException(new \Exception('Error Processing Request', 1));

		$this->service->notifyUnsignedUser($this->mockSignRequest(), 'a@b.coop');
	}

	private function mockSignRequest(string $displayName = 'John Doe'): SignRequest&MockObject {
		$signRequest = $this->createMock(SignRequest::class);
		$signRequest
			->method('__call')
			->willReturnCallback(fn (string $method)
				=> match ($method) {
					'getUuid' => 'asdfg',
					'getFileId' => 1,
					'getDisplayName' => $displayName,
				}
			);
		$signRequest->method('isObserver')->willReturn(false);
		return $signRequest;
	}

	private function mockRequest(string $requesterId = 'requester'): void {
		$file = $this->createMock(File::class);
		$file
			->method('__call')
			->with($this->equalTo('getName'), $this->anything())
			->willReturn('Filename');
		$file->method('getUserId')->willReturn($requesterId);
		$file->method('getUuid')->willReturn('file-uuid');
		$this->fileMapper
			->method('getById')
			->with(1)
			->willReturn($file);
		$this->appConfig
			->method('getValueBool')
			->willReturn(true);
	}

	private function mockStrategy(string $strategy): void {
		$this->policyService
			->method('resolveForUserId')
			->with(MailSenderStrategyPolicy::KEY, 'requester')
			->willReturn((new ResolvedPolicy())->setEffectiveValue($strategy));
	}

	private function mockEmailTemplate(): void {
		$template = $this->createMock(IEMailTemplate::class);
		$template->method('renderSubject')->willReturn('Rendered subject');
		$template->method('renderHtml')->willReturn('<p>Rendered html</p>');
		$template->method('renderText')->willReturn('Rendered text');
		$this->mailer->method('createEMailTemplate')->willReturn($template);
	}

	private function mockRequesterAccount(string $email, string $displayName = 'Requester Name'): void {
		$user = $this->createMock(IUser::class);
		$user->method('getEMailAddress')->willReturn($email);
		$user->method('getDisplayName')->willReturn($displayName);
		$this->userManager->method('get')->with('requester')->willReturn($user);
	}

	private function mockSystemMessage(): ISystemMessage&MockObject {
		$message = $this->createMock(ISystemMessage::class);
		$this->mailer->method('createMessage')->willReturn($message);
		return $message;
	}

	/**
	 * @return IService&IMessageSend&MockObject
	 */
	private function mockSendableService(Address $primaryAddress): MockObject {
		$service = $this->createMockForIntersectionOfInterfaces([IService::class, IMessageSend::class]);
		$service->method('initiateMessage')->willReturnCallback(static fn (): Message => new Message());
		$service->method('getPrimaryAddress')->willReturn($primaryAddress);
		return $service;
	}
}
