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
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Helper\FileUploadHelper;
use OCA\Libresign\Helper\ValidateHelper;
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
use OCP\Files\IRootFolder;
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
	private IRootFolder&MockObject $root;
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
	private ValidateHelper&MockObject $validateHelper;
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
		$this->root = $this->createMock(IRootFolder::class);
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
		$this->validateHelper = $this->createMock(ValidateHelper::class);
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
			$this->root,
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
			$this->validateHelper,
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
			],
			'emptySignPassword' => [
				[
					'uuid' => '12345678-1234-1234-1234-123456789012',
					'user' => [
						'email' => 'valid@test.coop',
					],
					'signPassword' => '',
				],
				'Password to sign is mandatory'
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

	public function testGetPdfByUuidWithSuccessAndSignedFile():void {
		$libresignFile = $this->createMock(\OCA\Libresign\Db\File::class);
		$libresignFile->method('__call')
			->willReturnCallback(fn ($method)
				=> match ($method) {
					'getSignedNodeId' => 1,
					'getNodeId' => 1,
					'getStatus' => \OCA\Libresign\Enum\FileStatus::SIGNED->value,
				}
			);
		$this->fileMapper
			->method('getByUuid')
			->willReturn($libresignFile);
		$node = $this->createMock(\OCP\Files\File::class);
		$this->root
			->method('getUserFolder')
			->willReturn($this->root);
		$this->root
			->method('getFirstNodeById')
			->willReturn($node);

		$actual = $this->getService()->getPdfByUuid('uuid');
		$this->assertInstanceOf(\OCP\Files\File::class, $actual);
	}

	public function testGetPdfByUuidWithSuccessAndUnignedFile():void {
		$libresignFile = $this->createMock(\OCA\Libresign\Db\File::class);
		$libresignFile->method('__call')
			->willReturnCallback(fn ($method)
				=> match ($method) {
					'getSignedNodeId' => 1,
					'getNodeId' => 1,
					'getStatus' => \OCA\Libresign\Enum\FileStatus::SIGNED->value,
				}
			);
		$this->fileMapper
			->method('getByUuid')
			->willReturn($libresignFile);
		$node = $this->createMock(\OCP\Files\File::class);
		$this->root
			->method('getUserFolder')
			->willReturn($this->root);
		$this->root
			->method('getFirstNodeById')
			->willReturn($node);

		$actual = $this->getService()->getPdfByUuid('uuid');
		$this->assertInstanceOf(\OCP\Files\File::class, $actual);
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
			'missingIdentify' => [
				function ($self): array {
					$signRequest = $self->createMock(SignRequest::class);
					$signRequest->method('getId')->willReturn(10);
					$self->signRequestMapper->method('getByUuid')->willReturn($signRequest);
					return [
						'uuid' => '12345678-1234-1234-1234-123456789012',
						'user' => [],
					];
				},
				'Invalid identification method'
			],
			'invalidIdentifyMethod' => [
				function ($self): array {
					$signRequest = $self->createMock(SignRequest::class);
					$signRequest->method('getId')->willReturn(10);
					$self->signRequestMapper->method('getByUuid')->willReturn($signRequest);
					$self->identifyMethodService->method('getIdentifyMethodsFromSignRequestId')->willReturn(['email' => []]);
					return [
						'uuid' => '12345678-1234-1234-1234-123456789012',
						'user' => [
							'identify' => [
								'phone' => '123456',
							],
						],
					];
				},
				'Invalid identification method'
			],
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
					$file = $self->createMock(\OCA\Libresign\Db\File::class);
					$file
						->method('__call')
						->willReturnCallback(fn (string $method)
							=> match ($method) {
								'getNodeId' => 999,
								'getUserId' => 'username',
							}
						);
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

					$self->root
						->method('getById')
						->will($self->returnValue([]));
					$folder = $self->createMock(\OCP\Files\Folder::class);
					$folder
						->method('getById')
						->willReturn([]);
					$self->root
						->method('getUserFolder')
						->willReturn($folder);
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

	public function testGetConfigIncludesCanManageGroupPoliciesForInstanceAdmin(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('instance-admin');

		$this->groupManager->method('isAdmin')->with('instance-admin')->willReturn(true);

		$config = $this->getService()->getConfig($user);

		$this->assertArrayHasKey('can_manage_group_policies', $config);
		$this->assertTrue($config['can_manage_group_policies']);
	}

	public function testGetFileByUuidWithSuccess(): void {
		$signRequest = $this->createMock(SignRequest::class);
		$signRequest->method('getFileId')->willReturn(123);
		$this->signRequestMapper->expects($this->once())
			->method('getByUuid')
			->with('test-uuid')
			->willReturn($signRequest);

		$file = $this->createMock(\OCA\Libresign\Db\File::class);
		$file->method('getNodeId')->willReturn(456);
		$file->method('getUserId')->willReturn('testuser');
		$this->fileMapper->expects($this->once())
			->method('getById')
			->with(123)
			->willReturn($file);

		$userFolder = $this->createMock(Folder::class);
		$fileToSign = $this->createMock(File::class);
		$userFolder->expects($this->once())
			->method('getFirstNodeById')
			->with(456)
			->willReturn($fileToSign);

		$this->root->expects($this->once())
			->method('getUserFolder')
			->with('testuser')
			->willReturn($userFolder);

		$result = $this->getService()->getFileByUuid('test-uuid');
		$this->assertSame($file, $result['fileData']);
		$this->assertSame($fileToSign, $result['fileToSign']);
	}

	public function testGetFileByUuidUsesCache(): void {
		$signRequest = $this->createMock(SignRequest::class);
		$signRequest->method('getFileId')->willReturn(123);
		$this->signRequestMapper->expects($this->once())
			->method('getByUuid')
			->with('test-uuid')
			->willReturn($signRequest);

		$file = $this->createMock(\OCA\Libresign\Db\File::class);
		$file->method('getNodeId')->willReturn(456);
		$file->method('getUserId')->willReturn('testuser');
		$this->fileMapper->expects($this->once())
			->method('getById')
			->with(123)
			->willReturn($file);

		$userFolder = $this->createMock(Folder::class);
		$fileToSign = $this->createMock(File::class);
		$userFolder->expects($this->once())
			->method('getFirstNodeById')
			->with(456)
			->willReturn($fileToSign);

		$this->root->expects($this->once())
			->method('getUserFolder')
			->with('testuser')
			->willReturn($userFolder);

		$service = $this->getService();
		// Call first time (populates cache)
		$service->getFileByUuid('test-uuid');
		// Call second time (uses cache)
		$result = $service->getFileByUuid('test-uuid');
		
		$this->assertSame($file, $result['fileData']);
		$this->assertSame($fileToSign, $result['fileToSign']);
	}

	public function testCreateToSignWithSuccessAndEmailAndSignPassword(): void {
		$signRequest = $this->createMock(SignRequest::class);
		$signRequest->method('getId')->willReturn(123);
		$signRequest->method('getDisplayName')->willReturn('John Doe');
		$this->signRequestMapper->method('getByUuid')
			->with('uuid-123')
			->willReturn($signRequest);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('john_doe_uid');
		$user->method('getDisplayName')->willReturn('John Doe');
		$user->method('getPrimaryEMailAddress')->willReturn('john@example.com');
		$this->userManager->expects($this->once())
			->method('createUser')
			->with('john@example.com', 'pass123')
			->willReturn($user);

		$user->expects($this->once())->method('setDisplayName')->with('John Doe');
		$user->expects($this->once())->method('setSystemEMailAddress')->with('john@example.com');

		// Stub identifyMethodService
		$identifyMethod = $this->createMock(IIdentifyMethod::class);
		$entity = $this->createMock(\OCA\Libresign\Db\IdentifyMethod::class);
		$entity->method('getIdentifierValue')->willReturn('john@example.com');
		$identifyMethod->method('getEntity')->willReturn($entity);
		
		$this->identifyMethodService->expects($this->once())
			->method('getIdentifyMethodsFromSignRequestId')
			->with(123)
			->willReturn([
				IdentifyMethodService::IDENTIFY_EMAIL => [$identifyMethod]
			]);

		// updateIdentifyMethodToAccount assertions
		$entity->expects($this->once())->method('setIdentifierKey')->with(IdentifyMethodService::IDENTIFY_ACCOUNT);
		$entity->expects($this->once())->method('setIdentifierValue')->with('john_doe_uid');
		$this->identifyMethodMapper->expects($this->once())
			->method('update')
			->with($entity);

		// new user email config sendEmail = yes
		$this->appConfig->method('getValueString')->with('core', 'newUser.sendEmail', 'yes')->willReturn('yes');
		$template = $this->createMock(\OCP\Mail\IEMailTemplate::class);
		$this->newUserMail->expects($this->once())->method('generateTemplate')->with($user, false)->willReturn($template);
		$this->newUserMail->expects($this->once())->method('sendMail')->with($user, $template);

		// certificate creation assertions
		$this->pkcs12Handler->expects($this->once())
			->method('generateCertificate')
			->with(
				[
					'host' => 'john@example.com',
					'uid' => 'account:john_doe_uid',
					'name' => 'John Doe'
				],
				'signPass123',
				'John Doe'
			)
			->willReturn('cert_content');

		$this->pkcs12Handler->expects($this->once())
			->method('savePfx')
			->with('john@example.com', 'cert_content');

		$this->getService()->createToSign('uuid-123', 'john@example.com', 'pass123', 'signPass123');
	}

	public function testCreateToSignWhenSendEmailIsNo(): void {
		$signRequest = $this->createMock(SignRequest::class);
		$signRequest->method('getId')->willReturn(123);
		$signRequest->method('getDisplayName')->willReturn('John Doe');
		$this->signRequestMapper->method('getByUuid')->willReturn($signRequest);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('john_doe_uid');
		$user->method('getDisplayName')->willReturn('John Doe');
		$user->method('getPrimaryEMailAddress')->willReturn('john@example.com');
		$this->userManager->method('createUser')->willReturn($user);

		$this->identifyMethodService->method('getIdentifyMethodsFromSignRequestId')->willReturn([]);

		$this->appConfig->method('getValueString')->with('core', 'newUser.sendEmail', 'yes')->willReturn('no');
		$this->newUserMail->expects($this->never())->method('generateTemplate');
		$this->newUserMail->expects($this->never())->method('sendMail');

		$this->getService()->createToSign('uuid-123', 'john@example.com', 'pass123', null);
	}

	public function testCreateToSignDoesNotUpdateIdentifyMethodWhenEmailDoesNotMatch(): void {
		$signRequest = $this->createMock(SignRequest::class);
		$signRequest->method('getId')->willReturn(123);
		$signRequest->method('getDisplayName')->willReturn('John Doe');
		$this->signRequestMapper->method('getByUuid')->willReturn($signRequest);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('john_doe_uid');
		$user->method('getDisplayName')->willReturn('John Doe');
		$user->method('getPrimaryEMailAddress')->willReturn('john@example.com');
		$this->userManager->method('createUser')->willReturn($user);

		// Email does not match target email
		$identifyMethod = $this->createMock(IIdentifyMethod::class);
		$entity = $this->createMock(\OCA\Libresign\Db\IdentifyMethod::class);
		$entity->method('getIdentifierValue')->willReturn('different@example.com');
		$identifyMethod->method('getEntity')->willReturn($entity);

		$this->identifyMethodService->method('getIdentifyMethodsFromSignRequestId')
			->willReturn([
				IdentifyMethodService::IDENTIFY_EMAIL => [$identifyMethod]
			]);

		$entity->expects($this->never())->method('setIdentifierKey');
		$this->identifyMethodMapper->expects($this->never())->method('update');
		$this->appConfig->method('getValueString')->willReturn('no');

		$this->getService()->createToSign('uuid-123', 'john@example.com', 'pass123', null);
	}

	public function testCreateToSignDoesNotUpdateIdentifyMethodWhenMethodIsNotEmail(): void {
		$signRequest = $this->createMock(SignRequest::class);
		$signRequest->method('getId')->willReturn(123);
		$signRequest->method('getDisplayName')->willReturn('John Doe');
		$this->signRequestMapper->method('getByUuid')->willReturn($signRequest);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('john_doe_uid');
		$user->method('getDisplayName')->willReturn('John Doe');
		$user->method('getPrimaryEMailAddress')->willReturn('john@example.com');
		$this->userManager->method('createUser')->willReturn($user);

		$identifyMethod = $this->createMock(IIdentifyMethod::class);
		$this->identifyMethodService->method('getIdentifyMethodsFromSignRequestId')
			->willReturn([
				'phone' => [$identifyMethod] // Method type is phone, not email
			]);

		$identifyMethod->expects($this->never())->method('getEntity');
		$this->identifyMethodMapper->expects($this->never())->method('update');
		$this->appConfig->method('getValueString')->willReturn('no');

		$this->getService()->createToSign('uuid-123', 'john@example.com', 'pass123', null);
	}

	public function testGetCertificateEngineName(): void {
		$engine = $this->createMock(\OCA\Libresign\Handler\CertificateEngine\ICertificateEngine::class);
		$engine->method('getName')->willReturn('CFSSL_TEST');
		$this->certificateEngineFactory->method('getEngine')->willReturn($engine);

		$this->assertSame('CFSSL_TEST', $this->getService()->getCertificateEngineName());
	}

	public function testIsSetupOk(): void {
		$engine = $this->createMock(\OCA\Libresign\Handler\CertificateEngine\ICertificateEngine::class);
		$engine->method('isSetupOk')->willReturn(true);
		$this->certificateEngineFactory->method('getEngine')->willReturn($engine);

		$this->assertTrue($this->getService()->isSetupOk());
	}

	public function testGetConfigWithNullUser(): void {
		$this->idDocsPolicyService->method('isIdentificationDocumentsEnabled')->with(null)->willReturn(false);
		$this->validateHelper->method('userCanApproveValidationDocuments')->with(null, false)->willReturn(false);
		$this->policyAuthorizationService->method('canUserManageGroupPolicies')->with(null)->willReturn(false);
		$this->policyAuthorizationService->method('getManageablePolicyGroupIds')->with(null)->willReturn([]);

		$config = $this->getService()->getConfig(null);

		// null/empty string values should be filtered out by array_filter
		$this->assertFalse($config['identificationDocumentsFlow']);
		$this->assertFalse($config['hasSignatureFile']);
		$this->assertArrayNotHasKey('phoneNumber', $config);
		$this->assertArrayHasKey('id_docs_filters', $config);
		$this->assertSame([], $config['id_docs_filters']);
		$this->assertArrayHasKey('crl_filters', $config);
		$this->assertSame([], $config['crl_filters']);
	}

	public function testGetConfigFiltersWithUser(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('testuser');

		$this->userConfig->method('getValueString')
			->willReturnCallback(fn (string $uid, string $appId, string $key) => match ($key) {
				'files_list_filter_modified' => '1',
				'files_list_filter_status' => 'signed',
				default => '',
			});

		$filters = $this->getService()->getConfigFilters($user);
		$this->assertSame('1', $filters['files_list_filter_modified']);
		$this->assertSame('signed', $filters['files_list_filter_status']);
	}

	public function testGetConfigFiltersWithNullUser(): void {
		$filters = $this->getService()->getConfigFilters(null);
		$this->assertSame('', $filters['files_list_filter_modified']);
		$this->assertSame('', $filters['files_list_filter_status']);
	}

	public function testGetConfigSortingWithUser(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('testuser');

		$this->userConfig->method('getValueString')
			->willReturnCallback(fn (string $uid, string $appId, string $key) => match ($key) {
				'files_list_sorting_mode' => 'date',
				'files_list_sorting_direction' => 'desc',
				default => '',
			});

		$sorting = $this->getService()->getConfigSorting($user);
		$this->assertSame('date', $sorting['files_list_sorting_mode']);
		$this->assertSame('desc', $sorting['files_list_sorting_direction']);
	}

	public function testGetConfigSortingWithNullUser(): void {
		$sorting = $this->getService()->getConfigSorting(null);
		$this->assertSame('name', $sorting['files_list_sorting_mode']);
		$this->assertSame('asc', $sorting['files_list_sorting_direction']);
	}

	public function testGetConfigDecodesJsonConfigs(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('testuser');

		$this->userConfig->method('getValueString')
			->willReturnCallback(fn (string $uid, string $appId, string $key) => match ($key) {
				'id_docs_filters' => '{"status":"pending"}',
				'crl_filters' => '{"reason":"cessation"}',
				'crl_sort' => '{"sortBy":"serial","sortOrder":"ASC"}',
				'id_docs_sort' => '{"sortBy":"name","sortOrder":"DESC"}',
				default => '',
			});

		$this->groupManager->method('isAdmin')->with('testuser')->willReturn(true);
		$this->validateHelper->method('userCanApproveValidationDocuments')->with($user, false)->willReturn(true);

		$config = $this->getService()->getConfig($user);
		$this->assertSame(['status' => 'pending'], $config['id_docs_filters']);
		$this->assertSame(['reason' => 'cessation'], $config['crl_filters']);
		$this->assertSame(['sortBy' => 'serial', 'sortOrder' => 'ASC'], $config['crl_sort']);
		$this->assertSame(['sortBy' => 'name', 'sortOrder' => 'DESC'], $config['id_docs_sort']);
	}

	public function testGetConfigFallbackForInvalidJsonConfigs(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('testuser');

		$this->userConfig->method('getValueString')
			->willReturnCallback(fn (string $uid, string $appId, string $key) => match ($key) {
				'id_docs_filters' => 'invalid-json',
				'crl_filters' => 'invalid-json',
				'crl_sort' => 'invalid-json',
				'id_docs_sort' => 'invalid-json',
				default => '',
			});

		$this->groupManager->method('isAdmin')->with('testuser')->willReturn(true);
		$this->validateHelper->method('userCanApproveValidationDocuments')->with($user, false)->willReturn(true);

		$config = $this->getService()->getConfig($user);
		$this->assertSame([], $config['id_docs_filters']);
		$this->assertSame([], $config['crl_filters']);
		$this->assertSame(['sortBy' => 'revoked_at', 'sortOrder' => 'DESC'], $config['crl_sort']);
		$this->assertSame(['sortBy' => null, 'sortOrder' => null], $config['id_docs_sort']);
	}

	public function testGetFileByNodeIdWithSuccess(): void {
		$file = $this->createMock(File::class);
		$this->folderService->expects($this->once())
			->method('getFileByNodeId')
			->with(123)
			->willReturn($file);

		$this->assertSame($file, $this->getService()->getFileByNodeId(123));
	}

	public function testGetFileByNodeIdThrowsDoesNotExistException(): void {
		$this->folderService->expects($this->once())
			->method('getFileByNodeId')
			->with(123)
			->willThrowException(new NotFoundException());

		$this->expectException(DoesNotExistException::class);
		$this->expectExceptionMessage('Not found');

		$this->getService()->getFileByNodeId(123);
	}

	public function testAddFilesToAccount(): void {
		$user = $this->createMock(IUser::class);
		$files = [];
		$this->idDocsService->expects($this->once())
			->method('addIdDocs')
			->with($files, $user);

		$this->getService()->addFilesToAccount($files, $user);
	}

	public function testDeleteFileFromAccount(): void {
		$user = $this->createMock(IUser::class);
		$this->idDocsService->expects($this->once())
			->method('deleteIdDoc')
			->with(123, $user);

		$this->getService()->deleteFileFromAccount(123, $user);
	}

	public function testSaveVisibleElements(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('testuser');

		$elements = [
			['elementId' => 456, 'starred' => true],
			['elementId' => 789, 'starred' => false]
		];

		$userElement1 = new UserElement();
		$userElement1->setNodeId(111);
		$userElement2 = new UserElement();
		$userElement2->setNodeId(222);

		$this->userElementMapper->expects($this->exactly(2))
			->method('findOne')
			->willReturnCallback(fn (array $criteria) => match ($criteria['id']) {
				456 => $userElement1,
				789 => $userElement2,
			});

		$file1 = $this->createMock(File::class);
		$file2 = $this->createMock(File::class);
		$this->folderService->expects($this->exactly(2))
			->method('getFileByNodeId')
			->willReturnCallback(fn (int $nodeId) => match ($nodeId) {
				111 => $file1,
				222 => $file2,
			});

		$this->userElementMapper->expects($this->exactly(2))
			->method('update')
			->willReturnCallback(function (UserElement $element) {
				return $element;
			});

		$this->getService()->saveVisibleElements($elements, 'session123', $user);
	}

	public function testSaveVisibleElementUpdateFileAndStarred(): void {
		$user = $this->createMock(IUser::class);

		$data = [
			'elementId' => 123,
			'starred' => true,
			'file' => [
				'base64' => 'data:image/png;base64,ZmFrZV9wbmc='
			]
		];

		$userElement = new UserElement();
		$userElement->setNodeId(456);

		$this->userElementMapper->expects($this->once())
			->method('findOne')
			->with(['id' => 123])
			->willReturn($userElement);

		$file = $this->createMock(File::class);
		$this->folderService->expects($this->once())
			->method('getFileByNodeId')
			->with(456)
			->willReturn($file);

		$file->expects($this->once())
			->method('putContent')
			->with('fake_png');

		$this->userElementMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function (UserElement $elem) {
				$this->assertEquals(1, $elem->getStarred());
				return $elem;
			});

		$this->getService()->saveVisibleElement($data, 'session123', $user);
	}

	public function testSaveVisibleElementWithUserCreatesNew(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('testuser');

		$data = [
			'type' => 'signature',
			'starred' => true,
			'file' => [
				'base64' => 'ZmFrZV9wbmc='
			]
		];

		$rootFolder = $this->createMock(Folder::class);
		$this->folderService->method('getFolder')->willReturn($rootFolder);
		$this->folderService->method('getFolderName')->willReturn('testuser_folder');

		$userFolder = $this->createMock(Folder::class);
		$rootFolder->expects($this->once())
			->method('newFolder')
			->with('testuser_folder')
			->willReturn($userFolder);

		$newFile = $this->createMock(File::class);
		$newFile->method('getId')->willReturn(789);
		$userFolder->expects($this->once())
			->method('newFile')
			->with($this->stringEndsWith('.png'), 'fake_png')
			->willReturn($newFile);

		$this->timeFactory->method('getDateTime')->willReturn(new \DateTime('2026-08-30 12:00:00'));

		$this->userElementMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (UserElement $elem) {
				$this->assertSame('signature', $elem->getType());
				$this->assertSame(789, $elem->getNodeId());
				$this->assertSame('testuser', $elem->getUserId());
				$this->assertSame(1, $elem->getStarred());
				return $elem;
			});

		$this->getService()->saveVisibleElement($data, 'session123', $user);
	}

	public function testSaveVisibleElementWithSessionUpdatesExisting(): void {
		$data = [
			'nodeId' => 456,
			'file' => [
				'base64' => 'ZmFrZV9wbmc='
			]
		];

		$file1 = $this->createMock(File::class);
		$file1->method('getId')->willReturn(123);
		$file2 = $this->createMock(File::class);
		$file2->method('getId')->willReturn(456);

		$this->signerElementsService->expects($this->once())
			->method('getElementsFromSession')
			->willReturn([$file1, $file2]);

		$file2->expects($this->once())
			->method('putContent')
			->with('fake_png');

		$this->getService()->saveVisibleElement($data, 'session123', null);
	}

	public function testSaveVisibleElementWithSessionUpdatesExistingThrowsWhenNotFound(): void {
		$data = [
			'nodeId' => 999,
			'file' => [
				'base64' => 'ZmFrZV9wbmc='
			]
		];

		$this->signerElementsService->expects($this->once())
			->method('getElementsFromSession')
			->willReturn([]);

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('File not found');

		$this->getService()->saveVisibleElement($data, 'session123', null);
	}

	public function testSaveVisibleElementWithSessionCreatesNew(): void {
		$data = [
			'type' => 'initials',
			'file' => [
				'base64' => 'ZmFrZV9wbmc='
			]
		];

		$rootFolder = $this->createMock(Folder::class);
		$this->folderService->method('getFolder')->willReturn($rootFolder);

		$sessionFolder = $this->createMock(Folder::class);
		$rootFolder->expects($this->once())
			->method('newFolder')
			->with('session123')
			->willReturn($sessionFolder);

		$dt = new \DateTime();
		$this->timeFactory->method('getDateTime')->willReturn($dt);

		$newFile = $this->createMock(File::class);
		$sessionFolder->expects($this->once())
			->method('newFile')
			->with('initials_' . $dt->getTimestamp() . '.png', 'fake_png')
			->willReturn($newFile);

		$this->getService()->saveVisibleElement($data, 'session123', null);
	}

	public function testGetFileRawWithUrlSuccess(): void {
		$data = [
			'file' => [
				'url' => 'https://example.com/image.png'
			]
		];

		$client = $this->createMock(\OCP\Http\Client\IClient::class);
		$response = $this->createMock(\OCP\Http\Client\IResponse::class);

		$this->clientService->method('newClient')->willReturn($client);
		$client->expects($this->once())
			->method('get')
			->with('https://example.com/image.png')
			->willReturn($response);

		$response->method('getHeader')->with('Content-Type')->willReturn('image/png');
		$response->method('getBody')->willReturn('fake_png_data');

		$this->validateHelper->expects($this->once())
			->method('validateBase64')
			->with('fake_png_data', ValidateHelper::TYPE_VISIBLE_ELEMENT_USER);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('testuser');

		$rootFolder = $this->createMock(Folder::class);
		$this->folderService->method('getFolder')->willReturn($rootFolder);
		$this->folderService->method('getFolderName')->willReturn('testuser_folder');
		$userFolder = $this->createMock(Folder::class);
		$rootFolder->method('newFolder')->willReturn($userFolder);
		$newFile = $this->createMock(File::class);
		$userFolder->method('newFile')->willReturn($newFile);

		$dataCreate = [
			'type' => 'signature',
			'file' => [
				'url' => 'https://example.com/image.png'
			]
		];
		$this->getService()->saveVisibleElement($dataCreate, 'session123', $user);
	}

	public function testGetFileRawWithUrlSuccessDecode(): void {
		$data = [
			'file' => [
				'url' => 'https://example.com/image.png'
			]
		];

		$client = $this->createMock(\OCP\Http\Client\IClient::class);
		$response = $this->createMock(\OCP\Http\Client\IResponse::class);

		$this->clientService->method('newClient')->willReturn($client);
		$client->expects($this->once())
			->method('get')
			->with('https://example.com/image.png')
			->willReturn($response);

		$response->method('getHeader')->with('Content-Type')->willReturn('image/png');
		$response->method('getBody')->willReturn('fake_png_data');

		$this->validateHelper->expects($this->once())
			->method('validateBase64')
			->with('fake_png_data', ValidateHelper::TYPE_VISIBLE_ELEMENT_USER);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('testuser');

		$rootFolder = $this->createMock(Folder::class);
		$this->folderService->method('getFolder')->willReturn($rootFolder);
		$this->folderService->method('getFolderName')->willReturn('testuser_folder');
		$userFolder = $this->createMock(Folder::class);
		$rootFolder->method('newFolder')->willReturn($userFolder);
		$newFile = $this->createMock(File::class);
		$userFolder->method('newFile')->willReturn($newFile);

		$dataCreate = [
			'type' => 'signature',
			'file' => [
				'url' => 'https://example.com/image.png'
			]
		];
		$this->getService()->saveVisibleElement($dataCreate, 'session123', $user);
	}

	public function testGetFileRawWithInvalidUrlThrows(): void {
		$data = [
			'type' => 'signature',
			'file' => [
				'url' => 'invalid-url'
			]
		];

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Invalid URL file');

		$this->getService()->saveVisibleElement($data, 'session123', null);
	}

	public function testGetFileRawWithNonPngUrlThrows(): void {
		$data = [
			'type' => 'signature',
			'file' => [
				'url' => 'https://example.com/image.jpg'
			]
		];

		$client = $this->createMock(\OCP\Http\Client\IClient::class);
		$response = $this->createMock(\OCP\Http\Client\IResponse::class);

		$this->clientService->method('newClient')->willReturn($client);
		$client->method('get')->willReturn($response);
		$response->method('getHeader')->with('Content-Type')->willReturn('image/jpeg');

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Visible element file must be png.');

		$this->getService()->saveVisibleElement($data, 'session123', null);
	}

	public function testGetFileRawWithEmptyUrlBodyThrows(): void {
		$data = [
			'type' => 'signature',
			'file' => [
				'url' => 'https://example.com/image.png'
			]
		];

		$client = $this->createMock(\OCP\Http\Client\IClient::class);
		$response = $this->createMock(\OCP\Http\Client\IResponse::class);

		$this->clientService->method('newClient')->willReturn($client);
		$client->method('get')->willReturn($response);
		$response->method('getHeader')->with('Content-Type')->willReturn('image/png');
		$response->method('getBody')->willReturn('');

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Empty file');

		$this->getService()->saveVisibleElement($data, 'session123', null);
	}

	public function testUploadPfxSuccess(): void {
		$tmpName = tempnam(sys_get_temp_dir(), 'libresign_pfx_test');
		file_put_contents($tmpName, 'fake_pfx_content');

		$file = [
			'tmp_name' => $tmpName,
			'size' => 100,
			'name' => 'cert.pfx',
		];

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('testuser');

		$this->uploadHelper->expects($this->once())
			->method('validateUploadedFile')
			->with($file);

		$this->mimeTypeDetector->expects($this->once())
			->method('detectString')
			->with('fake_pfx_content')
			->willReturn('application/octet-stream');

		$this->pkcs12Handler->expects($this->once())
			->method('savePfx')
			->with('testuser', 'fake_pfx_content');

		try {
			$this->getService()->uploadPfx($file, $user);
		} finally {
			if (file_exists($tmpName)) {
				unlink($tmpName);
			}
		}

		$this->assertFileDoesNotExist($tmpName);
	}

	public function testUploadPfxInvalidFileThrows(): void {
		$file = [
			'tmp_name' => 'nonexistent',
			'size' => 100,
			'name' => 'cert.pfx',
		];

		$user = $this->createMock(IUser::class);

		$this->uploadHelper->expects($this->once())
			->method('validateUploadedFile')
			->willThrowException(new \InvalidArgumentException('Invalid file'));

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid file provided. Need to be a .pfx file.');

		$this->getService()->uploadPfx($file, $user);
	}

	public function testUploadPfxTooBigThrows(): void {
		$file = [
			'tmp_name' => 'tmp',
			'size' => 11 * 1024,
			'name' => 'cert.pfx',
		];

		$user = $this->createMock(IUser::class);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('File is too big');

		$this->getService()->uploadPfx($file, $user);
	}

	public function testUploadPfxInvalidMimeThrows(): void {
		$tmpName = tempnam(sys_get_temp_dir(), 'libresign_pfx_test');
		file_put_contents($tmpName, 'plain text content');

		$file = [
			'tmp_name' => $tmpName,
			'size' => 100,
			'name' => 'cert.pfx',
		];

		$user = $this->createMock(IUser::class);

		$this->mimeTypeDetector->method('detectString')->willReturn('text/plain');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid file provided. Need to be a .pfx file.');

		try {
			$this->getService()->uploadPfx($file, $user);
		} finally {
			if (file_exists($tmpName)) {
				unlink($tmpName);
			}
		}
	}

	public function testUploadPfxInvalidExtensionThrows(): void {
		$tmpName = tempnam(sys_get_temp_dir(), 'libresign_pfx_test');
		file_put_contents($tmpName, 'fake_pfx_content');

		$file = [
			'tmp_name' => $tmpName,
			'size' => 100,
			'name' => 'cert.crt',
		];

		$user = $this->createMock(IUser::class);

		$this->mimeTypeDetector->method('detectString')->willReturn('application/octet-stream');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid file provided. Need to be a .pfx file.');

		try {
			$this->getService()->uploadPfx($file, $user);
		} finally {
			if (file_exists($tmpName)) {
				unlink($tmpName);
			}
		}
	}

	public function testUpdatePfxPasswordSuccess(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('testuser');

		$this->pkcs12Handler->expects($this->once())
			->method('updatePassword')
			->with('testuser', 'currentPass', 'newPass')
			->willReturn('updated_pfx');

		$this->getService()->updatePfxPassword($user, 'currentPass', 'newPass');
	}

	public function testUpdatePfxPasswordThrowsLibresignExceptionOnInvalidPassword(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('testuser');

		$this->pkcs12Handler->expects($this->once())
			->method('updatePassword')
			->willThrowException(new InvalidPasswordException());

		$this->expectException(\OCA\Libresign\Exception\LibresignException::class);
		$this->expectExceptionMessage('Invalid user or password');

		$this->getService()->updatePfxPassword($user, 'currentPass', 'newPass');
	}

	public function testReadPfxDataSuccess(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('testuser');

		$this->pkcs12Handler->expects($this->once())
			->method('getPfxOfCurrentSigner')
			->with('testuser')
			->willReturn('pfx_data');

		$this->pkcs12Handler->expects($this->once())
			->method('setCertificate')
			->with('pfx_data')
			->willReturn($this->pkcs12Handler);

		$this->pkcs12Handler->expects($this->once())
			->method('setPassword')
			->with('pass123')
			->willReturn($this->pkcs12Handler);

		$this->pkcs12Handler->expects($this->once())
			->method('readCertificate')
			->willReturn(['subject' => 'John Doe']);

		$result = $this->getService()->readPfxData($user, 'pass123');
		$this->assertSame(['subject' => 'John Doe'], $result);
	}

	public function testReadPfxDataThrowsLibresignExceptionOnInvalidPassword(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('testuser');

		$this->pkcs12Handler->method('getPfxOfCurrentSigner')->willReturn('pfx_data');
		$this->pkcs12Handler->method('setCertificate')->willReturn($this->pkcs12Handler);
		$this->pkcs12Handler->method('setPassword')->willReturn($this->pkcs12Handler);
		$this->pkcs12Handler->method('readCertificate')
			->willThrowException(new InvalidPasswordException());

		$this->expectException(\OCA\Libresign\Exception\LibresignException::class);
		$this->expectExceptionMessage('Invalid user or password');

		$this->readPfxDataThrowsInvalidPasswordException($user);
	}

	private function readPfxDataThrowsInvalidPasswordException(IUser $user): void {
		$this->getService()->readPfxData($user, 'wrongPass');
	}
}
