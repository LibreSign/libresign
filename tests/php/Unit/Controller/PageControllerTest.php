<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Controller\PageController;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest as SignRequestEntity;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\DocMdp\ConfigService;
use OCA\Libresign\Service\File\FileListService;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\RequestSignatureService;
use OCA\Libresign\Service\SessionService;
use OCA\Libresign\Service\SignerElementsService;
use OCA\Libresign\Service\SignFileService;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IAppConfig;
use OCP\IInitialStateService;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

final class PageControllerTest extends TestCase {
	private IRequest&MockObject $request;
	private IUserSession&MockObject $userSession;
	private AccountService&MockObject $accountService;
	private FileService&MockObject $fileService;
	private SignFileService&MockObject $signFileService;
	private SignerElementsService&MockObject $signerElementsService;
	private PageController $controller;

	public function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->request->method('getServerHost')->willReturn('localhost:8080');
		$this->userSession = $this->createMock(IUserSession::class);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('requester');
		$this->userSession->method('getUser')->willReturn($user);

		$this->accountService = $this->createMock(AccountService::class);
		$this->accountService->method('getConfig')->willReturn([]);
		$this->accountService->method('getConfigFilters')->willReturn([]);
		$this->accountService->method('getConfigSorting')->willReturn([]);
		$this->accountService->method('getCertificateEngineName')->willReturn('openssl');

		$this->fileService = $this->createMock(FileService::class);
		$this->fileService->method('setFile')->willReturnSelf();
		$this->fileService->method('setSignRequest')->willReturnSelf();
		$this->fileService->method('setHost')->willReturnSelf();
		$this->fileService->method('setMe')->willReturnSelf();
		$this->fileService->method('setSignerIdentified')->willReturnSelf();
		$this->fileService->method('setIdentifyMethodId')->willReturnSelf();
		$this->fileService->method('showVisibleElements')->willReturnSelf();
		$this->fileService->method('showSigners')->willReturnSelf();
		$this->fileService->method('showSettings')->willReturnSelf();
		$this->fileService->method('toArray')->willReturn([
			'id' => 5,
			'nodeId' => 50,
			'status' => 1,
			'statusText' => 'Ready to sign',
			'signers' => [],
			'visibleElements' => [],
			'settings' => [
				'needIdentificationDocuments' => false,
				'identificationDocumentsWaitingApproval' => false,
			],
		]);

		$this->signFileService = $this->createMock(SignFileService::class);
		$this->signFileService->method('getPdfUrlsForSigning')->willReturn(['/apps/libresign/pdf/sign-uuid']);

		$this->signerElementsService = $this->createMock(SignerElementsService::class);
		$this->signerElementsService->method('getElementsFromSessionAsArray')->willReturn([]);
		$this->signerElementsService->method('getUserElements')->willReturn([]);

		$this->controller = new PageController(
			request: $this->request,
			userSession: $this->userSession,
			sessionService: $this->createMock(SessionService::class),
			initialState: new \OC\AppFramework\Services\InitialState(
				$this->createMock(IInitialStateService::class),
				Application::APP_ID,
			),
			accountService: $this->accountService,
			signFileService: $this->signFileService,
			requestSignatureService: \OCP\Server::get(RequestSignatureService::class),
			signerElementsService: $this->signerElementsService,
			l10n: $this->createMock(IL10N::class),
			identifyMethodService: $this->createConfiguredMock(IdentifyMethodService::class, [
				'getIdentifyMethodsSettings' => [],
			]),
			appConfig: \OCP\Server::get(IAppConfig::class),
			fileService: $this->fileService,
			fileListService: \OCP\Server::get(FileListService::class),
			fileMapper: \OCP\Server::get(\OCA\Libresign\Db\FileMapper::class),
			signRequestMapper: \OCP\Server::get(\OCA\Libresign\Db\SignRequestMapper::class),
			logger: \OCP\Server::get(LoggerInterface::class),
			validateHelper: $this->createMock(ValidateHelper::class),
			eventDispatcher: $this->createMock(IEventDispatcher::class),
			urlGenerator: \OCP\Server::get(IURLGenerator::class),
			docMdpConfigService: $this->createConfiguredMock(ConfigService::class, [
				'getConfig' => [],
			]),
		);
	}

	public function testIndexAllowsSelfWorkerSrcDomain(): void {
		$response = $this->controller->index();

		self::assertStringContainsString("worker-src 'self'", $response->getContentSecurityPolicy()->buildPolicy());
	}

	public function testPublicSignAllowsSelfWorkerSrcDomain(): void {
		$fileEntity = new FileEntity();
		$fileEntity->setId(5);
		$fileEntity->setName('small_valid');
		$fileEntity->setNodeId(50);
		$fileEntity->setNodeType('file');

		$signRequestEntity = new SignRequestEntity();
		$signRequestEntity->setFileId(5);
		$signRequestEntity->setUuid('sign-uuid');
		$signRequestEntity->setDescription('');
		$this->signFileService->method('getSignRequestByUuid')->willReturn($signRequestEntity);
		$this->signFileService->method('getFile')->willReturn($fileEntity);
		$this->controller->loadNextcloudFileFromSignRequestUuid('sign-uuid');

		$response = $this->controller->sign('sign-uuid');

		self::assertStringContainsString("worker-src 'self'", $response->getContentSecurityPolicy()->buildPolicy());
	}
}
