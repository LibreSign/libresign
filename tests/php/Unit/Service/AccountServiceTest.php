<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileTypeMapper;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Service\AccountCertificateService;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\File\AccountSettingsProvider;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\IdDocsService;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Policy\RequestSignAuthorizationService;
use OCA\Libresign\Service\RequestSignatureService;
use OCA\Libresign\Service\SignerElementsService;
use OCA\Libresign\Service\SignFileService;
use OCA\Settings\Mailer\NewUserMailHelper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\File;
use OCP\Files\NotFoundException;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @internal
 * @group DB
 */
final class AccountServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IL10N&MockObject $l10n;
	private AccountCertificateService&MockObject $accountCertificateService;
	private AccountSettingsProvider&MockObject $accountSettingsProvider;
	private SignRequestMapper&MockObject $signRequestMapper;
	private IUserManager&MockObject $userManager;
	private FileMapper&MockObject $fileMapper;
	private FileTypeMapper&MockObject $fileTypeMapper;
	private SignFileService&MockObject $signFile;
	private IAppConfig&MockObject $appConfig;
	private IMountProviderCollection&MockObject $mountProviderCollection;
	private NewUserMailHelper&MockObject $newUserMail;
	private IdentifyMethodService&MockObject $identifyMethodService;
	private IdentifyMethodMapper&MockObject $identifyMethodMapper;
	private IURLGenerator&MockObject $urlGenerator;
	private IdDocsService&MockObject $idDocsService;
	private SignerElementsService&MockObject $signerElementsService;
	private FolderService&MockObject $folderService;
	private RequestSignatureService&MockObject $requestSignatureService;
	private RequestSignAuthorizationService&MockObject $requestSignAuthorizationService;

	public function setUp(): void {
		parent::setUp();
		$this->accountCertificateService = $this->createMock(AccountCertificateService::class);
		$this->accountSettingsProvider = $this->createMock(AccountSettingsProvider::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->method('t')
			->willReturnArgument(0);
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->fileTypeMapper = $this->createMock(FileTypeMapper::class);
		$this->signFile = $this->createMock(SignFileService::class);
		$this->requestSignatureService = $this->createMock(RequestSignatureService::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->mountProviderCollection = $this->createMock(IMountProviderCollection::class);
		$this->newUserMail = $this->createMock(NewUserMailHelper::class);
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$this->identifyMethodMapper = $this->createMock(IdentifyMethodMapper::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->idDocsService = $this->createMock(IdDocsService::class);
		$this->signerElementsService = $this->createMock(SignerElementsService::class);
		$this->folderService = $this->createMock(FolderService::class);
		$this->requestSignAuthorizationService = $this->createMock(RequestSignAuthorizationService::class);
	}

	private function getService(): AccountService {
		return new AccountService(
			$this->l10n,
			$this->accountCertificateService,
			$this->accountSettingsProvider,
			$this->signRequestMapper,
			$this->userManager,
			$this->fileMapper,
			$this->fileTypeMapper,
			$this->signFile,
			$this->requestSignatureService,
			$this->appConfig,
			$this->mountProviderCollection,
			$this->newUserMail,
			$this->identifyMethodService,
			$this->identifyMethodMapper,
			$this->urlGenerator,
			$this->idDocsService,
			$this->signerElementsService,
			$this->folderService,
			$this->requestSignAuthorizationService,
		);
	}

	#[DataProvider('provideUuidLookupSequences')]
	public function testGetSignRequestByUuidResolvesEachRequestedUuid(array $uuids): void {
		$requests = [];
		foreach (['uuid-a', 'uuid-b'] as $uuid) {
			$requests[$uuid] = new SignRequest();
			$requests[$uuid]->setUuid($uuid);
		}
		$this->signRequestMapper->method('getByUuid')->willReturnCallback(
			static fn (string $uuid): SignRequest => $requests[$uuid],
		);

		$service = $this->getService();
		foreach ($uuids as $uuid) {
			$this->assertSame($requests[$uuid], $service->getSignRequestByUuid($uuid));
		}
	}

	public static function provideUuidLookupSequences(): array {
		return [
			'repeated UUID' => [['uuid-a', 'uuid-a']],
			'different UUIDs' => [['uuid-a', 'uuid-b']],
			'return to first UUID' => [['uuid-a', 'uuid-b', 'uuid-a']],
		];
	}

	#[DataProvider('provideUuidLookupSequences')]
	public function testGetFileByUuidResolvesEachRequestedUuid(array $uuids): void {
		$requests = [];
		$files = [];
		$nodes = [];
		foreach (['uuid-a' => 10, 'uuid-b' => 20] as $uuid => $fileId) {
			$requests[$uuid] = new SignRequest();
			$requests[$uuid]->setUuid($uuid);
			$requests[$uuid]->setFileId($fileId);
			$files[$fileId] = new \OCA\Libresign\Db\File();
			$files[$fileId]->setId($fileId);
			$files[$fileId]->setUserId('owner-' . $uuid);
			$files[$fileId]->setNodeId($fileId + 1);
			$nodes[$fileId + 1] = $this->createMock(File::class);
		}
		$this->signRequestMapper->method('getByUuid')->willReturnCallback(
			static fn (string $uuid): SignRequest => $requests[$uuid],
		);
		$this->fileMapper->method('getById')->willReturnCallback(
			static fn (int $id): \OCA\Libresign\Db\File => $files[$id],
		);
		$this->folderService->method('getReadableNodeById')->willReturnMap([
			['owner-uuid-a', 11, $nodes[11]],
			['owner-uuid-b', 21, $nodes[21]],
		]);

		$service = $this->getService();
		foreach ($uuids as $uuid) {
			$fileId = $requests[$uuid]->getFileId();
			$this->assertSame([
				'fileData' => $files[$fileId],
				'fileToSign' => $nodes[$fileId + 1],
			], $service->getFileByUuid($uuid));
			$this->assertSame($requests[$uuid], $service->getSignRequestByUuid($uuid));
		}
	}

	#[DataProvider('provideMissingFileNodes')]
	public function testGetFileByUuidDoesNotReusePreviousNode(bool $isFolder): void {
		$request = new SignRequest();
		$request->setFileId(10);
		$file = new \OCA\Libresign\Db\File();
		$file->setUserId('owner');
		$file->setNodeId(11);
		$firstNode = $this->createMock(File::class);
		$secondNode = $isFolder ? $this->createMock(\OCP\Files\Folder::class) : null;
		$this->signRequestMapper->method('getByUuid')->willReturn($request);
		$this->fileMapper->method('getById')->with(10)->willReturn($file);
		$this->folderService->method('getReadableNodeById')
			->with('owner', 11)->willReturn($firstNode, $secondNode);

		$service = $this->getService();
		$this->assertSame($firstNode, $service->getFileByUuid('uuid-a')['fileToSign']);
		$this->assertSame(['fileData' => $file, 'fileToSign' => null], $service->getFileByUuid('uuid-b'));
	}

	public static function provideMissingFileNodes(): array {
		return [
			'not found' => [false],
			'folder instead of file' => [true],
		];
	}

	#[DataProvider('provideUuidLookupMethods')]
	public function testUuidLookupDoesNotReturnPreviousRequestWhenUuidIsMissing(string $method): void {
		$request = new SignRequest();
		$error = new DoesNotExistException('Unknown UUID');
		$this->signRequestMapper->method('getByUuid')->willReturnCallback(
			static fn (string $uuid): SignRequest => $uuid === 'uuid-a' ? $request : throw $error,
		);
		$this->fileMapper->expects($this->never())->method('getById');
		$service = $this->getService();
		$this->assertSame($request, $service->getSignRequestByUuid('uuid-a'));

		$this->expectExceptionObject($error);
		$service->$method('missing-uuid');
	}

	public static function provideUuidLookupMethods(): array {
		return [
			'sign request' => ['getSignRequestByUuid'],
			'file' => ['getFileByUuid'],
		];
	}

	public function testGetFileByUuidRetriesAfterNodeLookupFails(): void {
		$request = new SignRequest();
		$request->setFileId(10);
		$file = new \OCA\Libresign\Db\File();
		$file->setUserId('owner');
		$file->setNodeId(11);
		$node = $this->createMock(File::class);
		$error = new NotFoundException('Storage unavailable');
		$this->signRequestMapper->method('getByUuid')->with('uuid-a')->willReturn($request);
		$this->fileMapper->method('getById')->with(10)->willReturn($file);
		$attempts = 0;
		$this->folderService->method('getReadableNodeById')->with('owner', 11)
			->willReturnCallback(static function () use (&$attempts, $node, $error): File {
				if ($attempts++ === 0) {
					throw $error;
				}
				return $node;
			});

		$service = $this->getService();
		try {
			$service->getFileByUuid('uuid-a');
			$this->fail('The storage error must propagate.');
		} catch (NotFoundException $actual) {
			$this->assertSame($error, $actual);
		}
		$this->assertSame(['fileData' => $file, 'fileToSign' => $node], $service->getFileByUuid('uuid-a'));
	}

	public function testSaveVisibleElementsDelegatesToSignerElementsService(): void {
		$user = $this->createMock(IUser::class);
		$elements = [['type' => 'signature'], ['type' => 'initial']];
		$this->signerElementsService->expects($this->once())->method('saveVisibleElements')
			->with($elements, 'session-id', $user);

		$this->getService()->saveVisibleElements($elements, 'session-id', $user);
	}

	public function testSaveVisibleElementDelegatesToSignerElementsService(): void {
		$element = ['type' => 'signature', 'nodeId' => 42];
		$this->signerElementsService->expects($this->once())->method('saveVisibleElement')
			->with($element, 'session-id', null);

		$this->getService()->saveVisibleElement($element, 'session-id', null);
	}

	public function testDeleteSignatureElementDelegatesToSignerElementsService(): void {
		$user = $this->createMock(IUser::class);
		$this->signerElementsService->expects($this->once())->method('deleteSignatureElement')
			->with($user, 'session-id', 42);

		$this->getService()->deleteSignatureElement($user, 'session-id', 42);
	}

	public function testSaveVisibleElementsPropagatesStorageErrors(): void {
		$error = new NotFoundException('Element not found');
		$this->signerElementsService->method('saveVisibleElements')->willThrowException($error);
		$this->expectExceptionObject($error);

		$this->getService()->saveVisibleElements([['elementId' => 42]], 'session-id', null);
	}

	public function testCreateToSignWithErrorInSendingEmail():void {
		$signRequest = $this->createMock(\OCA\Libresign\Db\SignRequest::class);
		$signRequest
			->method('__call')
			->willReturnCallback(fn (string $method)
				=> match ($method) {
					'getDisplayName' => 'John Doe',
					'getId' => 1,
				}
			);
		$this->signRequestMapper->method('getByUuid')->willReturn($signRequest);
		$userToSign = $this->createMock(\OCP\IUser::class);
		$userToSign->method('getUID')->willReturn('username');
		$this->userManager->method('createUser')->willReturn($userToSign);
		$this->identifyMethodService->method('getIdentifyMethodsFromSignRequestId')->willReturn([]);
		$this->appConfig->method('getValueString')->willReturn('yes');
		$template = $this->createMock(\OCP\Mail\IEMailTemplate::class);
		$this->newUserMail->method('generateTemplate')->willReturn($template);
		$this->newUserMail->method('sendMail')->willReturnCallback(function ():void {
			throw new \Exception('Error Processing Request', 1);
		});
		$this->expectExceptionMessage('Unable to send the invitation');
		$this->getService()->createToSign('uuid', 'username', 'passwordOfUser', 'passwordToSign');
	}

	#[DataProvider('provideGetPdfByUuidNodeSelection')]
	public function testGetPdfByUuidSelectsExpectedNode(
		int $status,
		?int $signedNodeId,
		int $nodeId,
		int $expectedNodeId,
	): void {
		$libresignFile = new \OCA\Libresign\Db\File();
		$libresignFile->setSignedNodeId($signedNodeId);
		$libresignFile->setNodeId($nodeId);
		$libresignFile->setStatus($status);

		$this->fileMapper->method('getByUuid')->with('uuid')->willReturn($libresignFile);
		$this->fileMapper->method('getStorageUserIdByUuid')->with('uuid')->willReturn('storage-user');
		$this->folderService->expects($this->once())->method('setUserId')->with('storage-user');

		$node = $this->createMock(File::class);
		$this->folderService
			->expects($this->once())
			->method('getFileByNodeId')
			->with($expectedNodeId)
			->willReturn($node);

		$this->assertSame($node, $this->getService()->getPdfByUuid('uuid'));
	}

	public static function provideGetPdfByUuidNodeSelection(): array {
		return [
			'signed file uses signed node' => [FileStatus::SIGNED->value, 200, 100, 200],
			'partially signed file uses signed node' => [FileStatus::PARTIAL_SIGNED->value, 201, 101, 201],
			'draft file uses original node' => [FileStatus::DRAFT->value, null, 102, 102],
		];
	}

	public function testGetPdfByUuidThrowsDoesNotExistWhenNodeNotFound(): void {
		$libresignFile = $this->createMock(\OCA\Libresign\Db\File::class);
		$libresignFile->method('__call')
			->willReturnCallback(fn ($method)
				=> match ($method) {
					'getSignedNodeId' => null,
					'getNodeId' => 123,
					'getStatus' => \OCA\Libresign\Enum\FileStatus::DRAFT->value,
				}
			);

		$this->fileMapper
			->expects($this->once())
			->method('getByUuid')
			->with('uuid')
			->willReturn($libresignFile);

		$this->fileMapper
			->expects($this->once())
			->method('getStorageUserIdByUuid')
			->with('uuid')
			->willReturn('guest-user');

		$this->folderService
			->expects($this->once())
			->method('setUserId')
			->with('guest-user');

		$this->folderService
			->expects($this->once())
			->method('getFileByNodeId')
			->with(123)
			->willThrowException(new NotFoundException('Invalid node'));

		$this->expectException(DoesNotExistException::class);
		$this->expectExceptionMessage('Not found');

		$this->getService()->getPdfByUuid('uuid');
	}

	public function testCanRequestSignWithUnexistentUser():void {
		$this->requestSignAuthorizationService
			->expects($this->once())
			->method('canRequestSign')
			->with(null)
			->willReturn(false);

		$actual = $this->getService()->canRequestSign();
		$this->assertFalse($actual);
	}

	public function testCanRequestSignWithoutGroups():void {
		$user = $this->createMock(\OCP\IUser::class);
		$this->requestSignAuthorizationService
			->expects($this->once())
			->method('canRequestSign')
			->with($user)
			->willReturn(false);

		$actual = $this->getService()->canRequestSign($user);
		$this->assertFalse($actual);
	}

	public function testCanRequestSignWithUserOutOfAuthorizedGroups():void {
		$user = $this->createMock(\OCP\IUser::class);
		$this->requestSignAuthorizationService
			->expects($this->once())
			->method('canRequestSign')
			->with($user)
			->willReturn(false);

		$actual = $this->getService()->canRequestSign($user);
		$this->assertFalse($actual);
	}

	public function testCanRequestSignWithSuccess():void {
		$user = $this->createMock(\OCP\IUser::class);
		$this->requestSignAuthorizationService
			->expects($this->once())
			->method('canRequestSign')
			->with($user)
			->willReturn(true);

		$actual = $this->getService()->canRequestSign($user);
		$this->assertTrue($actual);
	}

	#[DataProvider('provideAccountSettings')]
	public function testGetSettingsPreservesAccountAuthorizationAndPhoneNumber(bool $hasUser, bool $canRequestSign, bool $hasSignatureFile, string $phoneNumber): void {
		$user = $hasUser ? $this->createMock(IUser::class) : null;
		$this->requestSignAuthorizationService->expects($this->once())->method('canRequestSign')->with($user)->willReturn($canRequestSign);
		$this->accountSettingsProvider->expects($this->once())->method('hasSignatureFile')->with($user)->willReturn($hasSignatureFile);
		$this->accountSettingsProvider->expects($this->once())->method('getPhoneNumber')->with($user)->willReturn($phoneNumber);
		$this->accountSettingsProvider->expects($this->never())->method('getSettings');
		$this->assertSame([
			'canRequestSign' => $canRequestSign,
			'hasSignatureFile' => $hasSignatureFile,
			'phoneNumber' => $phoneNumber,
		], $this->getService()->getSettings($user));
	}

	public static function provideAccountSettings(): array {
		return [
			'anonymous' => [false, false, false, ''],
			'authorized with certificate and phone' => [true, true, true, '+5511999999999'],
			'unauthorized with certificate and phone' => [true, false, true, '+5511999999999'],
			'authorized without certificate or phone' => [true, true, false, ''],
		];
	}

	#[DataProvider('provideValidateCreateToSignCases')]
	public function testValidateCreateToSignUsingDataProvider($arguments, $expectedErrorMessage):void {
		if (is_callable($arguments)) {
			$arguments = $arguments($this);
		}

		$this->expectExceptionMessage($expectedErrorMessage);
		$this->getService()->validateCreateToSign($arguments);
	}

	public static function provideValidateCreateToSignCases():array {
		return [
			'invalidUuid' => [
				[
					'uuid' => 'invalid uuid'
				],
				'Invalid UUID'
			],
			'uuidNotFound' => [
				function ($self):array {
					$uuid = '12345678-1234-1234-1234-123456789012';
					$self->signRequestMapper = $self->createMock(SignRequestMapper::class);
					$self->signRequestMapper
						->method('getByUuid')
						->will($self->returnCallback(function ():void {
							throw new \Exception('Beep, beep, not found!', 1);
						}));
					return [
						'uuid' => $uuid
					];
				},
				'UUID not found'
			],
			'emailMismatch' => [
				function ($self):array {
					$signRequest = $self->createMock(SignRequest::class);
					$signRequest
						->method('__call')
						->willReturnCallback(fn (string $method)
							=> match ($method) {
								'getEmail' => 'valid@test.coop',
								'getId' => 10,
							}
						);
					$self->signRequestMapper
						->method('getByUuid')
						->will($self->returnValue($signRequest));
					$identifyMethod = $self->createMock(IIdentifyMethod::class);
					$identifyMethod
						->method('validateToCreateAccount')
						->willReturnCallback(function ():void {
							throw new \OCA\Libresign\Exception\LibresignException('This is not your file');
						});
					$self->identifyMethodService
						->method('getIdentifyMethodsFromSignRequestId')
						->willReturn(['email' => [$identifyMethod]]);
					return [
						'uuid' => '12345678-1234-1234-1234-123456789012',
						'user' => [
							'email' => 'invalid@test.coop',
							'identify' => [
								'email' => 'invalid@test.coop',
							],
						],
						'signPassword' => '132456789',
						'password' => '123456789',
					];
				},
				'This is not your file'
			],
			'userAlreadyExists' => [
				function ($self):array {
					$signRequest = $self->createMock(SignRequest::class);
					$signRequest
						->method('__call')
						->willReturnCallback(fn (string $method)
							=> match ($method) {
								'getEmail' => 'valid@test.coop',
								'getId' => 11,
							}
						);
					$self->signRequestMapper
						->method('getByUuid')
						->will($self->returnValue($signRequest));
					$identifyMethod = $self->createMock(IIdentifyMethod::class);
					$identifyMethod
						->method('validateToCreateAccount')
						->willReturnCallback(function ():void {
							throw new \OCA\Libresign\Exception\LibresignException('User already exists');
						});
					$self->identifyMethodService
						->method('getIdentifyMethodsFromSignRequestId')
						->willReturn(['email' => [$identifyMethod]]);
					return [
						'uuid' => '12345678-1234-1234-1234-123456789012',
						'user' => [
							'identify' => [
								'email' => 'valid@test.coop',
							],
						],
						'signPassword' => '123456789',
						'signPassword' => '123456789',
					];
				},
				'User already exists'
			],
			'emptyPassword' => [
				function ($self):array {
					$signRequest = $self->createMock(SignRequest::class);
					$signRequest
						->method('__call')
						->willReturnCallback(fn (string $method)
							=> match ($method) {
								'getEmail' => 'valid@test.coop',
								'getId' => 12,
							}
						);
					$self->signRequestMapper
						->method('getByUuid')
						->will($self->returnValue($signRequest));
					$identifyMethod = $self->createMock(IIdentifyMethod::class);
					$self->identifyMethodService
						->method('getIdentifyMethodsFromSignRequestId')
						->willReturn(['email' => [$identifyMethod]]);
					return [
						'uuid' => '12345678-1234-1234-1234-123456789012',
						'user' => [
							'identify' => [
								'email' => 'valid@test.coop',
							],
						],
						'signPassword' => '132456789',
						'password' => ''
					];
				},
				'Password is mandatory'
			],
			'fileNotFound' => [
				function ($self):array {
					$signRequest = $self->createMock(SignRequest::class);
					$signRequest
						->method('__call')
						->willReturnCallback(fn (string $method)
							=> match ($method) {
								'getEmail' => 'valid@test.coop',
								'getFileId' => 171,
								'getId' => 13,
								'getUserId' => 'username',
							}
						);
					$file = new \OCA\Libresign\Db\File();
					$file->setNodeId(999);
					$file->setUserId('username');
					$self->fileMapper
						->method('getById')
						->will($self->returnValue($file));
					$self->signRequestMapper
						->method('getByUuid')
						->will($self->returnValue($signRequest));
					$identifyMethod = $self->createMock(IIdentifyMethod::class);
					$self->identifyMethodService
						->method('getIdentifyMethodsFromSignRequestId')
						->willReturn(['email' => [$identifyMethod]]);

					$self->folderService
						->method('getReadableNodeById')
						->with('username', 999)
						->willReturn(null);
					return [
						'uuid' => '12345678-1234-1234-1234-123456789012',
						'user' => [
							'identify' => [
								'email' => 'valid@test.coop',
							],
						],
						'signPassword' => '132456789',
						'password' => '123456789'
					];
				},
				'File not found'
			],
		];
	}

	#[DataProvider('provideAccountCreationOptions')]
	public function testCreateToSignPreservesIdentityMailAndCertificateContracts(string $sendEmail, ?string $signPassword): void {
		$request = new SignRequest();
		$request->setId(77);
		$request->setDisplayName('Signer Name');
		$this->signRequestMapper->method('getByUuid')->with('request-uuid')->willReturn($request);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('internal-uid');
		$user->method('getPrimaryEMailAddress')->willReturn('signer@example.com');
		$user->method('getDisplayName')->willReturn('Signer Name');
		$user->expects($this->once())->method('setDisplayName')->with('Signer Name');
		$user->expects($this->once())->method('setSystemEMailAddress')->with('signer@example.com');
		$this->userManager->expects($this->once())->method('createUser')
			->with('signer@example.com', 'account-password')->willReturn($user);
		$matching = new \OCA\Libresign\Db\IdentifyMethod();
		$matching->setIdentifierKey('email');
		$matching->setIdentifierValue('signer@example.com');
		$other = new \OCA\Libresign\Db\IdentifyMethod();
		$other->setIdentifierKey('email');
		$other->setIdentifierValue('other@example.com');
		$account = new \OCA\Libresign\Db\IdentifyMethod();
		$account->setIdentifierKey('account');
		$account->setIdentifierValue('signer@example.com');
		$methods = [];
		foreach ([$matching, $other, $account] as $entity) {
			$method = $this->createMock(IIdentifyMethod::class);
			$method->method('getEntity')->willReturn($entity);
			$methods[$entity->getIdentifierKey()][] = $method;
		}
		$this->identifyMethodService->method('getIdentifyMethodsFromSignRequestId')->with(77)->willReturn($methods);
		$this->identifyMethodMapper->expects($this->once())->method('update')->with($matching);
		$this->appConfig->method('getValueString')->with('core', 'newUser.sendEmail', 'yes')->willReturn($sendEmail);
		if ($sendEmail === 'yes') {
			$template = $this->createMock(\OCP\Mail\IEMailTemplate::class);
			$this->newUserMail->expects($this->once())->method('generateTemplate')->with($user, false)->willReturn($template);
			$this->newUserMail->expects($this->once())->method('sendMail')->with($user, $template);
		} else {
			$this->newUserMail->expects($this->never())->method('generateTemplate');
			$this->newUserMail->expects($this->never())->method('sendMail');
		}
		$this->accountCertificateService->expects($signPassword ? $this->once() : $this->never())
			->method('createForUser')->with($user, $signPassword);
		$this->getService()->createToSign('request-uuid', 'signer@example.com', 'account-password', $signPassword);
		$this->assertSame('account', $matching->getIdentifierKey());
		$this->assertSame('internal-uid', $matching->getIdentifierValue());
		$this->assertSame('email', $other->getIdentifierKey());
		$this->assertSame('other@example.com', $other->getIdentifierValue());
		$this->assertSame('signer@example.com', $account->getIdentifierValue());
	}

	public static function provideAccountCreationOptions(): array {
		return [
			'email and certificate' => ['yes', 'sign-password'],
			'email only' => ['yes', null],
			'certificate only' => ['no', 'sign-password'],
			'neither' => ['no', ''],
		];
	}

	#[DataProvider('provideAccountSettingsDelegation')]
	public function testAccountSettingsDelegatesToProvider(string $method, bool $hasUser): void {
		$user = $hasUser ? $this->createMock(IUser::class) : null;
		$expected = $method === 'hasSignatureFile' ? true : ['setting' => 'value'];
		$this->accountSettingsProvider->expects($this->once())->method($method)
			->with($user)->willReturn($expected);
		$this->assertSame($expected, $this->getService()->$method($user));
	}

	#[DataProvider('provideCertificateDelegation')]
	public function testCertificateMethodsDelegate(string $method, mixed $expected): void {
		$user = $this->createMock(IUser::class);
		$arguments = match ($method) {
			'validateCertificateData' => [['user' => ['email' => 'signer@example.com']]],
			'uploadPfx' => [['name' => 'certificate.pfx'], $user],
			'deletePfx' => [$user],
			'updatePfxPassword' => [$user, 'old', 'new'],
			'readPfxData' => [$user, 'password'],
			default => [],
		};
		$call = $this->accountCertificateService->expects($this->once())->method($method)->with(...$arguments);
		if ($expected !== null) {
			$call->willReturn($expected);
		}
		$this->assertSame($expected, $this->getService()->$method(...$arguments));
	}

	public static function provideCertificateDelegation(): array {
		return [
			['validateCertificateData', null], ['uploadPfx', null], ['deletePfx', null],
			['updatePfxPassword', null], ['readPfxData', ['subject' => 'signer']],
			['getCertificateEngineName', 'openssl'], ['isSetupOk', true], ['isSetupOk', false],
		];
	}

	public static function provideAccountSettingsDelegation(): array {
		$cases = [];
		foreach (['getConfig', 'getConfigFilters', 'getConfigSorting', 'hasSignatureFile'] as $method) {
			$cases[$method . ': authenticated'] = [$method, true];
			$cases[$method . ': anonymous'] = [$method, false];
		}
		return $cases;
	}
}
