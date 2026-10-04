<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
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
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Policy\RequestSignAuthorizationService;
use OCA\Libresign\Service\SignerElementsService;
use OCA\Settings\Mailer\NewUserMailHelper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\File;
use OCP\Files\NotFoundException;
use OCP\IAppConfig;
use OCP\IL10N;
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
	private AccountFileService&MockObject $accountFileService;
	private AccountCertificateService&MockObject $accountCertificateService;
	private AccountSettingsProvider&MockObject $accountSettingsProvider;
	private IUserManager&MockObject $userManager;
	private IAppConfig&MockObject $appConfig;
	private NewUserMailHelper&MockObject $newUserMail;
	private IdentifyMethodService&MockObject $identifyMethodService;
	private IdentifyMethodMapper&MockObject $identifyMethodMapper;
	private IdDocsService&MockObject $idDocsService;
	private SignerElementsService&MockObject $signerElementsService;
	private RequestSignAuthorizationService&MockObject $requestSignAuthorizationService;

	public function setUp(): void {
		parent::setUp();
		$this->accountFileService = $this->createMock(AccountFileService::class);
		$this->accountCertificateService = $this->createMock(AccountCertificateService::class);
		$this->accountSettingsProvider = $this->createMock(AccountSettingsProvider::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->method('t')
			->willReturnArgument(0);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->newUserMail = $this->createMock(NewUserMailHelper::class);
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$this->identifyMethodMapper = $this->createMock(IdentifyMethodMapper::class);
		$this->idDocsService = $this->createMock(IdDocsService::class);
		$this->signerElementsService = $this->createMock(SignerElementsService::class);
		$this->requestSignAuthorizationService = $this->createMock(RequestSignAuthorizationService::class);
	}

	private function getService(): AccountService {
		return new AccountService(
			$this->l10n,
			$this->accountFileService,
			$this->accountCertificateService,
			$this->accountSettingsProvider,
			$this->userManager,
			$this->appConfig,
			$this->newUserMail,
			$this->identifyMethodService,
			$this->identifyMethodMapper,
			$this->idDocsService,
			$this->signerElementsService,
			$this->requestSignAuthorizationService,
		);
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

	public function testCreateToSignStopsWhenSendingEmailFails(): void {
		$signRequest = new SignRequest();
		$signRequest->setId(1);
		$signRequest->setDisplayName('John Doe');
		$this->accountFileService->method('getSignRequestByUuid')->with('uuid')->willReturn($signRequest);

		$userToSign = $this->createMock(IUser::class);
		$userToSign->method('getUID')->willReturn('username');
		$this->userManager->method('createUser')->with('username', 'passwordOfUser')->willReturn($userToSign);
		$this->identifyMethodService->method('getIdentifyMethodsFromSignRequestId')->with(1)->willReturn([]);

		$this->appConfig->method('getValueString')->with('core', 'newUser.sendEmail', 'yes')->willReturn('yes');
		$template = $this->createMock(\OCP\Mail\IEMailTemplate::class);
		$this->newUserMail->method('generateTemplate')->with($userToSign, false)->willReturn($template);
		$this->newUserMail->method('sendMail')->with($userToSign, $template)
			->willThrowException(new \Exception('Error Processing Request', 1));
		$this->accountCertificateService->expects($this->never())->method('createForUser');

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(1);
		$this->expectExceptionMessage('Unable to send the invitation');

		$this->getService()->createToSign('uuid', 'username', 'passwordOfUser', 'passwordToSign');
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

	#[DataProvider('provideAccountCreationOptions')]
	public function testCreateToSignPreservesIdentityMailAndCertificateContracts(string $sendEmail, ?string $signPassword): void {
		$request = new SignRequest();
		$request->setId(77);
		$request->setDisplayName('Signer Name');
		$this->accountFileService->method('getSignRequestByUuid')->with('request-uuid')->willReturn($request);
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

	public function testAccountCreationRejectsInvalidUuidBeforeLookup(): void {
		$this->accountFileService->expects($this->never())->method('getSignRequestByUuid');
		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(1);
		$this->expectExceptionMessage('Invalid UUID');
		$this->getService()->validateCreateToSign(['uuid' => 'invalid']);
	}

	public function testAccountCreationNormalizesUnknownUuid(): void {
		$uuid = '12345678-1234-1234-1234-123456789012';
		$this->accountFileService->method('getSignRequestByUuid')->with($uuid)
			->willThrowException(new DoesNotExistException('Missing request'));
		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(1);
		$this->expectExceptionMessage('UUID not found');
		$this->getService()->validateCreateToSign(['uuid' => $uuid]);
	}

	#[DataProvider('provideInvalidIdentificationMethods')]
	public function testAccountCreationRejectsInvalidIdentificationMethods(array $user): void {
		$uuid = '12345678-1234-1234-1234-123456789012';
		$request = new SignRequest();
		$request->setId(77);
		$this->accountFileService->method('getSignRequestByUuid')->with($uuid)->willReturn($request);
		$this->identifyMethodService->method('getIdentifyMethodsFromSignRequestId')->with(77)->willReturn([]);
		$this->accountFileService->expects($this->never())->method('getFileByUuid');
		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(1);
		$this->expectExceptionMessage('Invalid identification method');
		$this->getService()->validateCreateToSign(['uuid' => $uuid, 'user' => $user]);
	}

	public static function provideInvalidIdentificationMethods(): array {
		return [
			'missing methods' => [[]],
			'unknown method' => [['identify' => ['email' => 'signer@example.com']]],
		];
	}

	#[DataProvider('provideIdentityValidationFailures')]
	public function testAccountCreationPreservesIdentityValidationErrors(string $message): void {
		$uuid = '12345678-1234-1234-1234-123456789012';
		$request = new SignRequest();
		$request->setId(77);
		$this->accountFileService->method('getSignRequestByUuid')->with($uuid)->willReturn($request);
		$error = new LibresignException($message);
		$method = $this->createMock(IIdentifyMethod::class);
		$method->expects($this->once())->method('validateToCreateAccount')->with('signer@example.com')->willThrowException($error);
		$this->identifyMethodService->method('getIdentifyMethodsFromSignRequestId')->with(77)->willReturn(['email' => [$method]]);
		$this->accountFileService->expects($this->never())->method('getFileByUuid');
		$this->expectExceptionObject($error);
		$this->getService()->validateCreateToSign(['uuid' => $uuid, 'user' => ['identify' => ['email' => 'signer@example.com']]]);
	}

	public static function provideIdentityValidationFailures(): array {
		return [['This is not your file'], ['User already exists']];
	}

	#[DataProvider('provideAccountValidationResults')]
	public function testAccountCreationRequiresPasswordAndFile(string $password, bool $hasFile, ?string $error, int $code): void {
		$uuid = '12345678-1234-1234-1234-123456789012';
		$request = new SignRequest();
		$request->setId(77);
		$this->accountFileService->method('getSignRequestByUuid')->with($uuid)->willReturn($request);
		$methods = [];
		foreach (['email' => 'signer@example.com', 'account' => 'signer'] as $name => $value) {
			$method = $this->createMock(IIdentifyMethod::class);
			$method->expects($this->once())->method('validateToCreateAccount')->with($value);
			$methods[$name] = [$method];
		}
		$this->identifyMethodService->method('getIdentifyMethodsFromSignRequestId')->with(77)->willReturn($methods);
		$node = $hasFile ? $this->createMock(File::class) : null;
		$this->accountFileService->expects($password === '' ? $this->never() : $this->once())
			->method('getFileByUuid')->with($uuid)->willReturn(['fileToSign' => $node]);
		if ($error !== null) {
			$this->expectException(LibresignException::class);
			$this->expectExceptionCode($code);
			$this->expectExceptionMessage($error);
		}
		$this->getService()->validateCreateToSign([
			'uuid' => $uuid,
			'user' => ['identify' => ['email' => 'signer@example.com', 'account' => 'signer']],
			'password' => $password,
		]);
	}

	public static function provideAccountValidationResults(): array {
		return [
			'missing password' => ['', true, 'Password is mandatory', 1],
			'missing file' => ['password', false, 'File not found', 0],
			'valid request' => ['password', true, null, 0],
		];
	}

	#[DataProvider('provideFileLookupDelegation')]
	public function testFileLookupDelegates(string $method, string|int $argument): void {
		$expected = match ($method) {
			'getSignRequestByUuid' => new SignRequest(),
			'getFileByUuid' => ['fileData' => new \OCA\Libresign\Db\File(), 'fileToSign' => $this->createMock(File::class)],
			default => $this->createMock(File::class),
		};
		$this->accountFileService->expects($this->once())->method($method)->with($argument)->willReturn($expected);
		$this->assertSame($expected, $this->getService()->$method($argument));
	}

	public static function provideFileLookupDelegation(): array {
		return [
			['getSignRequestByUuid', 'request-uuid'], ['getFileByUuid', 'request-uuid'],
			['getPdfByUuid', 'document-uuid'], ['getFileByNodeId', 42],
		];
	}

	#[DataProvider('provideFileLookupDelegation')]
	public function testFileLookupPreservesServiceErrors(string $method, string|int $argument): void {
		$error = new DoesNotExistException('File lookup failed');
		$this->accountFileService->expects($this->once())->method($method)->with($argument)->willThrowException($error);
		$this->expectExceptionObject($error);
		$this->getService()->$method($argument);
	}

	public function testIdentityDocumentsDelegateToTheirOwner(): void {
		$user = $this->createMock(IUser::class);
		$files = [['nodeId' => 42]];
		$this->idDocsService->expects($this->once())->method('addIdDocs')->with($files, $user);
		$this->idDocsService->expects($this->once())->method('deleteIdDoc')->with(42, $user);
		$service = $this->getService();
		$service->addFilesToAccount($files, $user);
		$service->deleteFileFromAccount(42, $user);
	}
}
