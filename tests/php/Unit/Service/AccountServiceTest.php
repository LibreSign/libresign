<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OC\AppFramework\Utility\TimeFactory;
use OC\Http\Client\ClientService;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileTypeMapper;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Db\UserElement;
use OCA\Libresign\Db\UserElementMapper;
use OCA\Libresign\Enum\CRLReason;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Helper\FileUploadHelper;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\Crl\CrlService;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\IdDocsPolicyService;
use OCA\Libresign\Service\IdDocsService;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyAuthorizationService;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\RequestSignAuthorizationService;
use OCA\Libresign\Service\RequestSignatureService;
use OCA\Libresign\Service\SignerElementsService;
use OCA\Libresign\Service\SignFileService;
use OCA\Libresign\Service\Validation\FileInputValidator;
use OCA\Libresign\Service\Validation\IdentityDocumentValidator;
use OCA\Settings\Mailer\NewUserMailHelper;
use OCP\Accounts\IAccount;
use OCP\Accounts\IAccountManager;
use OCP\Accounts\IAccountProperty;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Config\IUserConfig;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\NotFoundException;
use OCP\Group\ISubAdmin;
use OCP\IAppConfig;
use OCP\IGroup;
use OCP\IGroupManager;
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
	private SignRequestMapper&MockObject $signRequestMapper;
	private IUserManager&MockObject $userManager;
	private IAccountManager&MockObject $accountManager;
	private IMimeTypeDetector&MockObject $mimeTypeDetector;
	private FileMapper&MockObject $fileMapper;
	private FileTypeMapper&MockObject $fileTypeMapper;
	private SignFileService&MockObject $signFile;
	private CertificateEngineFactory&MockObject $certificateEngineFactory;
	private IAppConfig&MockObject $appConfig;
	private IUserConfig&MockObject $userConfig;
	private IMountProviderCollection&MockObject $mountProviderCollection;
	private NewUserMailHelper&MockObject $newUserMail;
	private IdentifyMethodService&MockObject $identifyMethodService;
	private IdentifyMethodMapper&MockObject $identifyMethodMapper;
	private IdentityDocumentValidator&MockObject $identityDocumentValidator;
	private FileInputValidator&MockObject $fileInputValidator;
	private IURLGenerator&MockObject $urlGenerator;
	private IGroupManager&MockObject $groupManager;
	private ISubAdmin&MockObject $subAdmin;
	private PolicyService&MockObject $policyService;
	private PolicyAuthorizationService $policyAuthorizationService;
	private IdDocsPolicyService&MockObject $idDocsPolicyService;
	private IdDocsService&MockObject $idDocsService;
	private SignerElementsService&MockObject $signerElementsService;
	private UserElementMapper&MockObject $userElementMapper;
	private FolderService&MockObject $folderService;
	private ClientService&MockObject $clientService;
	private TimeFactory&MockObject $timeFactory;
	private RequestSignatureService&MockObject $requestSignatureService;
	private Pkcs12Handler&MockObject $pkcs12Handler;
	private FileUploadHelper&MockObject $uploadHelper;
	private CrlService&MockObject $crlService;
	private RequestSignAuthorizationService&MockObject $requestSignAuthorizationService;

	public function setUp(): void {
		parent::setUp();
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->method('t')
			->willReturnArgument(0);
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->accountManager = $this->createMock(IAccountManager::class);
		$this->mimeTypeDetector = $this->createMock(IMimeTypeDetector::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->fileTypeMapper = $this->createMock(FileTypeMapper::class);
		$this->signFile = $this->createMock(SignFileService::class);
		$this->requestSignatureService = $this->createMock(RequestSignatureService::class);
		$this->certificateEngineFactory = $this->createMock(CertificateEngineFactory::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->userConfig = $this->createMock(IUserConfig::class);
		$this->mountProviderCollection = $this->createMock(IMountProviderCollection::class);
		$this->newUserMail = $this->createMock(NewUserMailHelper::class);
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$this->identifyMethodMapper = $this->createMock(IdentifyMethodMapper::class);
		$this->identityDocumentValidator = $this->createMock(IdentityDocumentValidator::class);
		$this->fileInputValidator = $this->createMock(FileInputValidator::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->pkcs12Handler = $this->createMock(Pkcs12Handler::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->subAdmin = $this->createMock(ISubAdmin::class);
		$this->policyService = $this->createMock(PolicyService::class);
		$this->policyAuthorizationService = new PolicyAuthorizationService($this->groupManager, $this->subAdmin, $this->policyService);
		$this->idDocsPolicyService = $this->createMock(IdDocsPolicyService::class);
		$this->idDocsPolicyService->method('isIdentificationDocumentsEnabled')->willReturn(false);
		$this->idDocsService = $this->createMock(IdDocsService::class);
		$this->signerElementsService = $this->createMock(SignerElementsService::class);
		$this->userElementMapper = $this->createMock(UserElementMapper::class);
		$this->folderService = $this->createMock(FolderService::class);
		$this->clientService = $this->createMock(ClientService::class);
		$this->timeFactory = $this->createMock(TimeFactory::class);
		$this->uploadHelper = $this->createMock(FileUploadHelper::class);
		$this->crlService = $this->createMock(CrlService::class);
		$this->requestSignAuthorizationService = $this->createMock(RequestSignAuthorizationService::class);
	}

	private function getService(): AccountService {
		return new AccountService(
			$this->l10n,
			$this->signRequestMapper,
			$this->userManager,
			$this->accountManager,
			$this->mimeTypeDetector,
			$this->fileMapper,
			$this->fileTypeMapper,
			$this->signFile,
			$this->requestSignatureService,
			$this->certificateEngineFactory,
			$this->appConfig,
			$this->userConfig,
			$this->mountProviderCollection,
			$this->newUserMail,
			$this->identifyMethodService,
			$this->identifyMethodMapper,
			$this->identityDocumentValidator,
			$this->fileInputValidator,
			$this->urlGenerator,
			$this->pkcs12Handler,
			$this->groupManager,
			$this->policyAuthorizationService,
			$this->idDocsPolicyService,
			$this->idDocsService,
			$this->signerElementsService,
			$this->userElementMapper,
			$this->folderService,
			$this->clientService,
			$this->timeFactory,
			$this->uploadHelper,
			$this->crlService,
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

	public function testDeletePfxRevokesCertificatesWithReasonAndDeletesPfx(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');

		$this->crlService->expects($this->once())
			->method('revokeUserCertificates')
			->with(
				'admin',
				CRLReason::CESSATION_OF_OPERATION,
				'Certificate deleted by account owner.',
				'admin'
			)
			->willReturn(1);

		$this->pkcs12Handler->expects($this->once())
			->method('deletePfx')
			->with('admin');

		$this->getService()->deletePfx($user);
	}

	public function testGetConfigSetsCanManageGroupPoliciesForSubAdmin(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('subadmin-user');

		$this->groupManager->method('isAdmin')->with('subadmin-user')->willReturn(false);
		$this->subAdmin->method('isSubAdmin')->with($user)->willReturn(true);

		$config = $this->getService()->getConfig($user);

		$this->assertArrayHasKey('can_manage_group_policies', $config);
		$this->assertTrue($config['can_manage_group_policies']);
	}

	public function testGetConfigIncludesPolicyWorkbenchCatalogCompactViewPreference(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('preference-user');

		$this->userConfig
			->expects($this->atLeastOnce())
			->method('getValueString')
			->willReturnCallback(static function (string $uid, string $appId, string $key, string $default = ''): string {
				if ($uid === 'preference-user'
					&& $appId === Application::APP_ID
					&& $key === 'policy_workbench_catalog_compact_view') {
					return '1';
				}

				return $default;
			});

		$config = $this->getService()->getConfig($user);

		$this->assertArrayHasKey('policy_workbench_catalog_compact_view', $config);
		$this->assertTrue($config['policy_workbench_catalog_compact_view']);
	}

	public function testGetConfigIncludesPolicyWorkbenchCollapsedPreferences(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('preference-user');

		$storedCollapsedState = [
			'who-can-sign' => true,
			'how-signing-works' => true,
			'signer-experience' => false,
			'what-gets-recorded' => false,
			'time-and-limits' => true,
			'trust-and-verification' => false,
			'system-behavior' => true,
		];

		$this->userConfig
			->expects($this->atLeastOnce())
			->method('getValueString')
			->willReturnCallback(static function (string $uid, string $appId, string $key, string $default = '') use ($storedCollapsedState): string {
				if ($uid !== 'preference-user' || $appId !== Application::APP_ID) {
					return $default;
				}

				if ($key === 'policy_workbench_catalog_collapsed') {
					return '1';
				}

				if ($key === 'policy_workbench_category_collapsed_state') {
					return json_encode($storedCollapsedState);
				}

				return $default;
			});

		$config = $this->getService()->getConfig($user);

		$this->assertArrayHasKey('policy_workbench_catalog_collapsed', $config);
		$this->assertTrue($config['policy_workbench_catalog_collapsed']);
		$this->assertArrayHasKey('policy_workbench_category_collapsed_state', $config);
		$this->assertSame($storedCollapsedState, $config['policy_workbench_category_collapsed_state']);
	}

	#[DataProvider('provideWarnWithoutVisibleSignatureFieldsCases')]
	public function testGetConfigIncludesWarnWithoutVisibleSignatureFieldsPreference(string $storedValue, bool $expected): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('preference-user');

		$this->userConfig
			->expects($this->atLeastOnce())
			->method('getValueString')
			->willReturnCallback(static function (string $uid, string $appId, string $key, string $default = '') use ($storedValue): string {
				if ($uid === 'preference-user'
					&& $appId === Application::APP_ID
					&& $key === 'warn_without_visible_signature_fields') {
					return $storedValue;
				}

				return $default;
			});

		$config = $this->getService()->getConfig($user);

		$this->assertArrayHasKey('warn_without_visible_signature_fields', $config);
		$this->assertSame($expected, $config['warn_without_visible_signature_fields']);
	}

	public static function provideWarnWithoutVisibleSignatureFieldsCases(): array {
		return [
			'stored 1 shows the warning' => ['1', true],
			'stored 0 hides the warning' => ['0', false],
			'no stored value falls back to the default' => ['', true],
		];
	}

	#[DataProvider('provideValidateCertificateDataCases')]
	public function testValidateCertificateDataUsingDataProvider($arguments, $expectedErrorMessage):void {
		if (is_callable($arguments)) {
			$arguments = $arguments($this);
		}

		$this->expectExceptionMessage($expectedErrorMessage);
		$this->getService()->validateCertificateData($arguments);
	}

	public static function provideValidateCertificateDataCases():array {
		return [
			'emptyCertificateEmail' => [
				[
					'uuid' => '12345678-1234-1234-1234-123456789012',
					'user' => [
						'email' => '',
					],
				],
				'You must have an email. You can define the email in your profile.'
			],
			'invalidCertificateEmail' => [
				[
					'uuid' => '12345678-1234-1234-1234-123456789012',
					'user' => [
						'email' => 'invalid',
					],
				],
				'Invalid email'
			]
		];
	}

	public function testValidateCertificateDataWithSuccess():void {
		$signRequest = $this->createMock(SignRequest::class);
		$signRequest
			->method('__call')
			->with($this->equalTo('getEmail'), $this->anything())
			->willReturn('valid@test.coop');
		$this->signRequestMapper
			->method('getByUuid')
			->willReturn($signRequest);
		$actual = $this->getService()->validateCertificateData([
			'uuid' => '12345678-1234-1234-1234-123456789012',
			'user' => [
				'email' => 'valid@test.coop',
			],
			'password' => '123456789',
			'signPassword' => '123456',
		]);
		$this->assertNull($actual);
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

	public function testGetSettingsIncludesPhoneNumber(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('testuser');

		$this->requestSignAuthorizationService
			->method('canRequestSign')
			->with($user)
			->willReturn(true);
		$this->pkcs12Handler
			->method('getPfxOfCurrentSigner')
			->with('testuser')
			->willReturn('signature_content');

		$accountProperty = $this->createMock(IAccountProperty::class);
		$accountProperty->method('getValue')->willReturn('+5511999999999');

		$account = $this->createMock(IAccount::class);
		$account->method('getProperty')
			->with(IAccountManager::PROPERTY_PHONE)
			->willReturn($accountProperty);

		$this->accountManager
			->method('getAccount')
			->with($user)
			->willReturn($account);

		$actual = $this->getService()->getSettings($user);

		$this->assertSame(true, $actual['canRequestSign']);
		$this->assertSame(true, $actual['hasSignatureFile']);
		$this->assertSame('+5511999999999', $actual['phoneNumber']);
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

	public function testDeleteSignatureElementWithUserDeletesFromDB(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('testuser');

		// Use real UserElement instead of mock since it uses magic methods
		$element = new UserElement();
		$element->setId(42);
		$element->setNodeId(123);

		$this->userElementMapper
			->expects($this->once())
			->method('findOne')
			->with([
				'node_id' => 123,
				'user_id' => 'testuser',
			])
			->willReturn($element);

		$this->userElementMapper
			->expects($this->once())
			->method('delete')
			->with($element);

		$file = $this->createMock(File::class);
		$file->expects($this->once())
			->method('delete');

		$this->folderService
			->expects($this->once())
			->method('getFileByNodeId')
			->with(123)
			->willReturn($file);

		$this->getService()->deleteSignatureElement($user, 'session123', 123);
	}

	public function testDeleteSignatureElementWithUserWhenFileNotFound(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('testuser');

		// Use real UserElement
		$element = new UserElement();
		$element->setNodeId(123);

		$this->userElementMapper
			->expects($this->once())
			->method('findOne')
			->willReturn($element);

		$this->userElementMapper
			->expects($this->once())
			->method('delete')
			->with($element);

		$this->folderService
			->expects($this->once())
			->method('getFileByNodeId')
			->willThrowException(new NotFoundException());

		// Should not throw, just skip file deletion
		$this->getService()->deleteSignatureElement($user, 'session123', 123);
	}

	public function testDeleteSignatureElementWithUserWhenFileDeleteFails(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('testuser');

		$element = new UserElement();
		$element->setNodeId(123);

		$this->userElementMapper
			->expects($this->once())
			->method('findOne')
			->willReturn($element);

		$this->userElementMapper
			->expects($this->once())
			->method('delete')
			->with($element);

		$file = $this->createMock(File::class);
		$file->expects($this->once())
			->method('delete')
			->willThrowException(new \Exception('storage error'));

		$this->folderService
			->expects($this->once())
			->method('getFileByNodeId')
			->with(123)
			->willReturn($file);

		// Should not throw, element deletion in DB must be enough
		$this->getService()->deleteSignatureElement($user, 'session123', 123);
	}

	public function testDeleteSignatureElementWithoutUserDeletesFromSession(): void {
		$sessionFolder = $this->createMock(Folder::class);
		$element = $this->createMock(File::class);

		$element->expects($this->once())
			->method('delete');

		$sessionFolder
			->expects($this->once())
			->method('getFirstNodeById')
			->with(456)
			->willReturn($element);

		// Session folder becomes empty after deletion
		$sessionFolder
			->expects($this->once())
			->method('getDirectoryListing')
			->willReturn([]);

		// Empty folder should be deleted too
		$sessionFolder
			->expects($this->once())
			->method('delete');

		$rootFolder = $this->createMock(Folder::class);
		$rootFolder
			->expects($this->once())
			->method('get')
			->with('session789')
			->willReturn($sessionFolder);

		$this->folderService
			->expects($this->once())
			->method('getFolder')
			->willReturn($rootFolder);

		$this->getService()->deleteSignatureElement(null, 'session789', 456);
	}

	public function testDeleteSignatureElementWithoutUserThrowsWhenSessionFolderNotFound(): void {
		$this->expectException(DoesNotExistException::class);
		$this->expectExceptionMessage('Element not found');

		$rootFolder = $this->createMock(Folder::class);
		$rootFolder
			->expects($this->once())
			->method('get')
			->with('nonexistent')
			->willThrowException(new NotFoundException());

		$this->folderService
			->expects($this->once())
			->method('getFolder')
			->willReturn($rootFolder);

		$this->getService()->deleteSignatureElement(null, 'nonexistent', 999);
	}

	public function testDeleteSignatureElementWithoutUserThrowsWhenNodeNotInSession(): void {
		$this->expectException(DoesNotExistException::class);
		$this->expectExceptionMessage('Element not found');

		$sessionFolder = $this->createMock(Folder::class);
		$sessionFolder
			->expects($this->once())
			->method('getFirstNodeById')
			->with(999)
			->willReturn(null);

		$rootFolder = $this->createMock(Folder::class);
		$rootFolder
			->expects($this->once())
			->method('get')
			->with('session123')
			->willReturn($sessionFolder);

		$this->folderService
			->expects($this->once())
			->method('getFolder')
			->willReturn($rootFolder);

		$this->getService()->deleteSignatureElement(null, 'session123', 999);
	}

	public function testDeleteSignatureElementWithoutUserThrowsWhenNodeIsNotFile(): void {
		$this->expectException(DoesNotExistException::class);
		$this->expectExceptionMessage('Element not found');

		$sessionFolder = $this->createMock(Folder::class);
		$folderNode = $this->createMock(Folder::class); // Not a File!

		$sessionFolder
			->expects($this->once())
			->method('getFirstNodeById')
			->with(777)
			->willReturn($folderNode);

		$rootFolder = $this->createMock(Folder::class);
		$rootFolder
			->expects($this->once())
			->method('get')
			->with('session456')
			->willReturn($sessionFolder);

		$this->folderService
			->expects($this->once())
			->method('getFolder')
			->willReturn($rootFolder);

		$this->getService()->deleteSignatureElement(null, 'session456', 777);
	}

	public function testDeleteSignatureElementOnlyDeletesSpecificFileNotWholeFolder(): void {
		// This test validates the critical security fix:
		// Previously: deleted entire session folder immediately (losing all files)
		// Now: deletes only specific file by nodeId, keeps other files intact

		$sessionFolder = $this->createMock(Folder::class);
		$targetFile = $this->createMock(File::class);
		$otherFile = $this->createMock(File::class);

		// Should call delete on the specific FILE, not on the FOLDER
		$targetFile->expects($this->once())
			->method('delete');

		$sessionFolder
			->expects($this->once())
			->method('getFirstNodeById')
			->with(100)
			->willReturn($targetFile);

		// After deleting target file, folder still has other files
		$sessionFolder
			->expects($this->once())
			->method('getDirectoryListing')
			->willReturn([$otherFile]);

		// Folder should NOT be deleted because it still has files
		$sessionFolder->expects($this->never())
			->method('delete');

		$rootFolder = $this->createMock(Folder::class);
		$rootFolder
			->expects($this->once())
			->method('get')
			->with('mysession')
			->willReturn($sessionFolder);

		$this->folderService
			->expects($this->once())
			->method('getFolder')
			->willReturn($rootFolder);

		$this->getService()->deleteSignatureElement(null, 'mysession', 100);
	}

	public function testDeleteSignatureElementDeletesEmptySessionFolder(): void {
		// When the last element is deleted, the empty session folder should be cleaned up

		$sessionFolder = $this->createMock(Folder::class);
		$lastFile = $this->createMock(File::class);

		$lastFile->expects($this->once())
			->method('delete');

		$sessionFolder
			->expects($this->once())
			->method('getFirstNodeById')
			->with(200)
			->willReturn($lastFile);

		// After deleting last file, folder is empty
		$sessionFolder
			->expects($this->once())
			->method('getDirectoryListing')
			->willReturn([]);

		// Empty folder SHOULD be deleted
		$sessionFolder->expects($this->once())
			->method('delete');

		$rootFolder = $this->createMock(Folder::class);
		$rootFolder
			->expects($this->once())
			->method('get')
			->with('session999')
			->willReturn($sessionFolder);

		$this->folderService
			->expects($this->once())
			->method('getFolder')
			->willReturn($rootFolder);

		$this->getService()->deleteSignatureElement(null, 'session999', 200);
	}

	public function testGetConfigIncludesManageablePolicyGroupIds(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('manageable-user');

		$this->groupManager->method('isAdmin')->with('manageable-user')->willReturn(false);
		$this->subAdmin->method('isSubAdmin')->with($user)->willReturn(true);

		$finance = $this->createMock(IGroup::class);
		$finance->method('getGID')->willReturn('finance');
		$legal = $this->createMock(IGroup::class);
		$legal->method('getGID')->willReturn('legal');
		$this->subAdmin->method('getSubAdminsGroups')->with($user)->willReturn([$finance, $legal]);

		$this->policyService->method('resolveForUser')
			->willReturn((new ResolvedPolicy())
				->setEffectiveValue('{"allowGroups":["finance"],"denyGroups":[]}')
				->setEditableByCurrentActor(true));

		$config = $this->getService()->getConfig($user);

		$this->assertArrayHasKey('manageable_policy_group_ids', $config);
		$this->assertSame(['finance', 'legal'], $config['manageable_policy_group_ids']);
	}

	#[DataProvider('provideStoredAccountPreferences')]
	public function testGetConfigPreservesStoredPreferencesAndRoleBoundaries(string $role, string $json, ?array $decoded): void {
		$user = null;
		if ($role !== 'anonymous') {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn('preference-owner');
		}
		$isAdmin = $role === 'admin';
		$isApprover = $role === 'approver';
		$this->groupManager->method('isAdmin')->with('preference-owner')->willReturn($isAdmin);
		$this->identityDocumentValidator->method('userCanApproveValidationDocuments')
			->with($user, false)->willReturn($isApprover);
		$this->pkcs12Handler->method('getPfxOfCurrentSigner')->willReturn('certificate');
		$stored = [
			'id_docs_filters' => $json, 'id_docs_sort' => $json,
			'crl_filters' => $json, 'crl_sort' => $json,
			'policy_workbench_category_collapsed_state' => $json,
			'files_list_sorting_mode' => 'size', 'files_list_sorting_direction' => 'desc',
			'files_list_grid_view' => '1', 'files_list_signer_identify_tab' => 'account',
			'policy_workbench_catalog_compact_view' => '1', 'policy_workbench_catalog_collapsed' => '1',
			'warn_without_visible_signature_fields' => '0',
		];
		if ($user === null) {
			$this->userConfig->expects($this->never())->method('getValueString');
		} else {
			$this->userConfig->method('getValueString')
				->willReturnCallback(static function (string $uid, string $appId, string $key, string $default = '') use ($stored): string {
					self::assertSame('preference-owner', $uid);
					self::assertSame(Application::APP_ID, $appId);
					return $stored[$key] ?? $default;
				});
		}
		$expected = [
			'identificationDocumentsFlow' => false,
			'hasSignatureFile' => $user !== null,
			'isApprover' => $isApprover,
			'id_docs_filters' => $user !== null ? ($decoded ?? []) : [],
			'id_docs_sort' => $isApprover ? ($decoded ?? ['sortBy' => null, 'sortOrder' => null]) : ['sortBy' => null, 'sortOrder' => null],
			'crl_filters' => $isAdmin ? ($decoded ?? []) : [],
			'crl_sort' => $isAdmin ? ($decoded ?? ['sortBy' => 'revoked_at', 'sortOrder' => 'DESC']) : ['sortBy' => 'revoked_at', 'sortOrder' => 'DESC'],
			'files_list_grid_view' => $user !== null,
			'files_list_sorting_mode' => $user !== null ? 'size' : 'name',
			'files_list_sorting_direction' => $user !== null ? 'desc' : 'asc',
			'policy_workbench_catalog_compact_view' => $user !== null,
			'policy_workbench_catalog_collapsed' => $user !== null,
			'warn_without_visible_signature_fields' => $user === null,
			'can_manage_group_policies' => $isAdmin,
			'manageable_policy_group_ids' => [],
		];
		if ($user !== null) {
			$expected['files_list_signer_identify_tab'] = 'account';
			if ($decoded !== null) {
				$expected['policy_workbench_category_collapsed_state'] = $decoded;
			}
		}
		$actual = $this->getService()->getConfig($user);
		ksort($actual);
		ksort($expected);
		$this->assertSame($expected, $actual);
	}

	public static function provideStoredAccountPreferences(): array {
		$cases = [];
		foreach (['anonymous', 'user', 'admin', 'approver'] as $role) {
			foreach ([
				'empty' => ['', null],
				'invalid JSON' => ['{invalid', null],
				'scalar' => ['"text"', null],
				'null' => ['null', null],
				'empty array' => ['[]', []],
				'populated object' => ['{"sortBy":"name","sortOrder":"ASC"}', ['sortBy' => 'name', 'sortOrder' => 'ASC']],
			] as $name => [$json, $decoded]) {
				$cases[$role . ': ' . $name] = [$role, $json, $decoded];
			}
		}
		return $cases;
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
		if ($signPassword) {
			$this->pkcs12Handler->expects($this->once())->method('generateCertificate')
				->with(['host' => 'signer@example.com', 'uid' => 'account:internal-uid', 'name' => 'Signer Name'], $signPassword, 'Signer Name')
				->willReturn('generated-pfx');
			$this->pkcs12Handler->expects($this->once())->method('savePfx')->with('signer@example.com', 'generated-pfx');
		} else {
			$this->pkcs12Handler->expects($this->never())->method('generateCertificate');
			$this->pkcs12Handler->expects($this->never())->method('savePfx');
		}
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

	#[DataProvider('provideSignatureFileAvailability')]
	public function testHasSignatureFileHandlesAbsentCertificates(bool $hasUser, bool $hasCertificate): void {
		$user = null;
		if ($hasUser) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn('signer');
			$lookup = $this->pkcs12Handler->expects($this->once())->method('getPfxOfCurrentSigner')->with('signer');
			if ($hasCertificate) {
				$lookup->willReturn('pfx');
			} else {
				$lookup->willThrowException(new \OCA\Libresign\Exception\LibresignException('No certificate'));
			}
		} else {
			$this->pkcs12Handler->expects($this->never())->method('getPfxOfCurrentSigner');
		}
		$this->assertSame($hasCertificate, $this->getService()->hasSignatureFile($user));
	}

	public static function provideSignatureFileAvailability(): array {
		return ['anonymous' => [false, false], 'existing' => [true, true], 'missing' => [true, false]];
	}

	public function testGetConfigIncludesCanManageGroupPoliciesForInstanceAdmin(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('instance-admin');

		$this->groupManager->method('isAdmin')->with('instance-admin')->willReturn(true);

		$config = $this->getService()->getConfig($user);

		$this->assertArrayHasKey('can_manage_group_policies', $config);
		$this->assertTrue($config['can_manage_group_policies']);
	}
}
