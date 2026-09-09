<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Controller;

use OCA\Libresign\Controller\FileController;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\File\FileListService;
use OCA\Libresign\Service\File\SettingsLoader;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\Policy\ValidationEffectivePolicyService;
use OCA\Libresign\Service\RequestSignatureService;
use OCA\Libresign\Service\SessionService;
use OCP\AppFramework\Http;
use OCP\IL10N;
use OCP\IPreview;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Preview\IMimeIconProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class FileControllerTest extends TestCase {
	public function testAddFileToEnvelopeRejectsDifferentOwnerBeforeProcessingUpload(): void {
		$request = $this->createMock(IRequest::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$userSession = $this->createMock(IUserSession::class);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('attacker');
		$userSession->method('getUser')->willReturn($user);

		$envelope = new File();
		$envelope->setId(42);
		$envelope->setUuid('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
		$envelope->setUserId('owner');
		$envelope->setNodeType('envelope');
		$envelope->setStatus(FileStatus::DRAFT->value);

		$fileMapper = $this->createMock(FileMapper::class);
		$fileMapper
			->method('getByUuid')
			->with($envelope->getUuid())
			->willReturn($envelope);

		$validateHelper = $this->createMock(ValidateHelper::class);
		$validateHelper
			->expects($this->once())
			->method('canRequestSign')
			->with($user);
		$validateHelper
			->expects($this->once())
			->method('iRequestedSignThisFile')
			->with($user, 42)
			->willThrowException(new LibresignException('You do not have permission for this action.'));

		$request
			->expects($this->never())
			->method('getUploadedFile');

		$requestSignatureService = $this->createMock(RequestSignatureService::class);
		$requestSignatureService
			->expects($this->never())
			->method('save');

		/** @var ValidationEffectivePolicyService $validationEffectivePolicyService */
		$validationEffectivePolicyService = (new \ReflectionClass(ValidationEffectivePolicyService::class))->newInstanceWithoutConstructor();

		$controller = new FileController(
			$request,
			$l10n,
			$this->createMock(LoggerInterface::class),
			$userSession,
			$this->unused(SessionService::class),
			$this->createMock(SignRequestMapper::class),
			$fileMapper,
			$requestSignatureService,
			$this->unused(AccountService::class),
			$validationEffectivePolicyService,
			$this->createMock(IPreview::class),
			$this->createMock(IMimeIconProvider::class),
			$this->unused(FileService::class),
			$this->unused(FileListService::class),
			$validateHelper,
			$this->unused(SettingsLoader::class),
			$this->createMock(IURLGenerator::class),
		);

		$response = $controller->addFileToEnvelope($envelope->getUuid());

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
		$this->assertSame(
			'You do not have permission for this action.',
			$response->getData()['message'],
		);
	}

	/**
	 * @template T of object
	 * @param class-string<T> $className
	 * @return T
	 */
	private function unused(string $className): object {
		return (new \ReflectionClass($className))->newInstanceWithoutConstructor();
	}
}
