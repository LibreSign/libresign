<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\IdentifyMethod;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\IdentifyMethod\IdentifyService;
use OCA\Libresign\Service\SessionService;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IRootFolder;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Security\IHasher;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

final class IdentifyServiceTest extends TestCase {
	private IdentifyMethodMapper&MockObject $identifyMethodMapper;
	private SessionService&MockObject $sessionService;
	private ITimeFactory&MockObject $timeFactory;
	private IEventDispatcher&MockObject $eventDispatcher;
	private IRootFolder&MockObject $rootFolder;
	private IAppConfig&MockObject $appConfig;
	private SignRequestMapper&MockObject $signRequestMapper;
	private IL10N&MockObject $l10n;
	private FileMapper&MockObject $fileMapper;
	private IHasher&MockObject $hasher;
	private IUserManager&MockObject $userManager;
	private IURLGenerator&MockObject $urlGenerator;
	private LoggerInterface&MockObject $logger;
	private FolderService&MockObject $folderService;
	private IdentifyService $service;

	public function setUp(): void {
		parent::setUp();

		$this->identifyMethodMapper = $this->createMock(IdentifyMethodMapper::class);
		$this->sessionService = $this->createMock(SessionService::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->hasher = $this->createMock(IHasher::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->folderService = $this->createMock(\OCA\Libresign\Service\FolderService::class);

		$this->service = new IdentifyService(
			$this->identifyMethodMapper,
			$this->sessionService,
			$this->timeFactory,
			$this->eventDispatcher,
			$this->rootFolder,
			$this->appConfig,
			$this->signRequestMapper,
			$this->l10n,
			$this->fileMapper,
			$this->hasher,
			$this->userManager,
			$this->urlGenerator,
			$this->logger,
			$this->folderService,
		);
	}

	public function testPropagateIdentifiedDateSkipsCurrentRequestAndUpdatesSiblings(): void {
		$identifyMethod = new IdentifyMethod();
		$identifyMethod->setSignRequestId(1);
		$identifyMethod->setIdentifierKey('email');
		$identifyMethod->setIdentifierValue('user@example.com');
		$identifyMethod->setIdentifiedAtDate('2024-01-01T00:00:00Z');

		$parentEnvelopeId = 99;

		$currentFile = new File();
		$currentFile->setId(10);
		$currentFile->setParentFileId($parentEnvelopeId);

		$currentSignRequest = new SignRequest();
		$currentSignRequest->setId(1);
		$currentSignRequest->setFileId($currentFile->getId());

		$siblingFile = new File();
		$siblingFile->setId(11);
		$siblingFile->setParentFileId($parentEnvelopeId);

		$siblingSignRequest = new SignRequest();
		$siblingSignRequest->setId(2);
		$siblingSignRequest->setFileId($siblingFile->getId());

		$this->signRequestMapper
			->expects($this->once())
			->method('getById')
			->with($identifyMethod->getSignRequestId())
			->willReturn($currentSignRequest);

		$this->fileMapper
			->expects($this->once())
			->method('getById')
			->with($currentFile->getId())
			->willReturn($currentFile);

		$this->signRequestMapper
			->expects($this->once())
			->method('getByEnvelopeChildrenAndIdentifyMethod')
			->with($parentEnvelopeId, $currentSignRequest->getId())
			->willReturn([$currentSignRequest, $siblingSignRequest]);

		$siblingIdentifyMethod = new IdentifyMethod();
		$siblingIdentifyMethod->setSignRequestId($siblingSignRequest->getId());
		$siblingIdentifyMethod->setIdentifierKey($identifyMethod->getIdentifierKey());
		$siblingIdentifyMethod->setIdentifierValue($identifyMethod->getIdentifierValue());

		$this->identifyMethodMapper
			->expects($this->exactly(2))
			->method('getIdentifyMethodsFromSignRequestId')
			->willReturnMap([
				[$identifyMethod->getSignRequestId(), []],
				[$siblingSignRequest->getId(), [$siblingIdentifyMethod]],
			]);

		$this->identifyMethodMapper
			->expects($this->once())
			->method('insertOrUpdate')
			->with($identifyMethod);

		$this->identifyMethodMapper
			->expects($this->once())
			->method('update')
			->with($this->callback(function (IdentifyMethod $updated) use ($siblingIdentifyMethod, $identifyMethod) {
				return $updated->getSignRequestId() === $siblingIdentifyMethod->getSignRequestId()
					&& $updated->getIdentifiedAtDate() == $identifyMethod->getIdentifiedAtDate();
			}));

		$this->service->save($identifyMethod);
	}
}
