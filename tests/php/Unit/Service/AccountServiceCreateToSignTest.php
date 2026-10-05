<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\AccountCertificateService;
use OCA\Libresign\Service\AccountFileService;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\File\AccountSettingsProvider;
use OCA\Libresign\Service\IdDocsService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Policy\RequestSignAuthorizationService;
use OCA\Libresign\Service\SignerElementsService;
use OCA\Settings\Mailer\NewUserMailHelper;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Mail\IEMailTemplate;

final class AccountServiceCreateToSignTest extends \OCA\Libresign\Tests\Unit\TestCase {
	public function testEmailFailureDoesNotCreateCertificate(): void {
		$l10n = $this->createStub(IL10N::class);
		$accountFileService = $this->createMock(AccountFileService::class);
		$accountCertificateService = $this->createMock(AccountCertificateService::class);
		$accountSettingsProvider = $this->createStub(AccountSettingsProvider::class);
		$userManager = $this->createMock(IUserManager::class);
		$appConfig = $this->createMock(IAppConfig::class);
		$newUserMail = $this->createMock(NewUserMailHelper::class);
		$identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$identifyMethodMapper = $this->createStub(IdentifyMethodMapper::class);
		$idDocsService = $this->createStub(IdDocsService::class);
		$signerElementsService = $this->createStub(SignerElementsService::class);
		$requestSignAuthorizationService = $this->createStub(RequestSignAuthorizationService::class);

		$signRequest = new SignRequest();
		$signRequest->setId(1);
		$signRequest->setDisplayName('John Doe');
		$accountFileService
			->expects($this->once())
			->method('getSignRequestByUuid')
			->with('uuid')
			->willReturn($signRequest);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('username');
		$userManager
			->expects($this->once())
			->method('createUser')
			->with('username', 'passwordOfUser')
			->willReturn($user);

		$identifyMethodService
			->expects($this->once())
			->method('getIdentifyMethodsFromSignRequestId')
			->with(1)
			->willReturn([]);

		$appConfig
			->method('getValueString')
			->with('core', 'newUser.sendEmail', 'yes')
			->willReturn('yes');

		$template = $this->createMock(IEMailTemplate::class);
		$newUserMail
			->expects($this->once())
			->method('generateTemplate')
			->with($user, false)
			->willReturn($template);
		$newUserMail
			->expects($this->once())
			->method('sendMail')
			->with($user, $template)
			->willThrowException(new \Exception('Error Processing Request', 1));

		$accountCertificateService
			->expects($this->never())
			->method('createForUser');

		$service = new AccountService(
			$l10n,
			$accountFileService,
			$accountCertificateService,
			$accountSettingsProvider,
			$userManager,
			$appConfig,
			$newUserMail,
			$identifyMethodService,
			$identifyMethodMapper,
			$idDocsService,
			$signerElementsService,
			$requestSignAuthorizationService,
		);

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(1);
		$this->expectExceptionMessage('Unable to send the invitation');

		$service->createToSign('uuid', 'username', 'passwordOfUser', 'passwordToSign');
	}
}
