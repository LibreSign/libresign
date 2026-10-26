<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\EmailSignerElementsService;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\IdentifyMethod\IdentifyService;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\ClickToSign;
use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\EmailToken;
use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\TokenService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\EmailSignerSignature\EmailSignerSignaturePolicy;
use OCA\Libresign\Service\SessionService;
use OCA\Libresign\Service\SignerElementsService;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IL10N;
use OCP\Security\IHasher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class EmailSignerElementsServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private PolicyService&MockObject $policyService;
	private IdentifyMethodService&MockObject $identifyMethodService;
	private SignerElementsService&MockObject $signerElementsService;
	private SessionService&MockObject $sessionService;
	private FolderService&MockObject $folderService;
	private IConfig&MockObject $config;
	private IHasher&MockObject $hasher;
	private IL10N&MockObject $l10n;
	private Folder&MockObject $rootFolder;
	private Folder&MockObject $storageFolder;
	private SignRequest $signRequest;

	#[\Override]
	public function setUp(): void {
		parent::setUp();
		$this->policyService = $this->createMock(PolicyService::class);
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$this->signerElementsService = $this->createMock(SignerElementsService::class);
		$this->sessionService = $this->createMock(SessionService::class);
		$this->folderService = $this->createMock(FolderService::class);
		$this->config = $this->createMock(IConfig::class);
		$this->config->method('getSystemValueString')->with('secret')->willReturn('instance-secret');
		$this->hasher = $this->createMock(IHasher::class);
		$this->hasher->method('verify')->willReturnCallback(
			static fn (string $token, string $hash): bool => $hash === 'hash-of-' . $token,
		);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnArgument(0);
		$this->rootFolder = $this->createMock(Folder::class);
		$this->folderService->method('getUnauthenticatedFolder')->willReturn($this->rootFolder);
		$this->storageFolder = $this->createMock(Folder::class);
		$this->rootFolder->method('getOrCreateFolder')->with('email_signer_elements')->willReturn($this->storageFolder);
		$this->signRequest = new SignRequest();
		$this->signRequest->setId(7);
	}

	private function getService(): EmailSignerElementsService {
		return new EmailSignerElementsService(
			$this->policyService,
			$this->identifyMethodService,
			$this->signerElementsService,
			$this->sessionService,
			$this->folderService,
			$this->config,
			$this->l10n,
		);
	}

	private function prepareAndSave(SignRequest $signRequest, string $token, array $elements, bool $saveSignature = false): void {
		$service = $this->getService();
		$signature = $service->prepareSignature($signRequest, $token, $elements, $saveSignature);
		if ($signature !== null) {
			$service->saveSignature($signature);
		}
	}

	private function setPolicy(bool $enabled): void {
		$this->policyService->method('resolve')
			->with(EmailSignerSignaturePolicy::KEY)
			->willReturn((new ResolvedPolicy())
				->setPolicyKey(EmailSignerSignaturePolicy::KEY)
				->setEffectiveValue($enabled));
	}

	/**
	 * @param string[] $enabledMethods
	 */
	private function setEmailSigner(array $enabledMethods, ?string $code = null, string $email = 'Signer@Example.com'): IIdentifyMethod&MockObject {
		$entity = new IdentifyMethod();
		$entity->setIdentifierKey('email');
		$entity->setIdentifierValue($email);
		if ($code !== null) {
			$entity->setCode($code);
		}
		$identifyService = $this->createMock(IdentifyService::class);
		$identifyService->method('getL10n')->willReturn($this->l10n);
		$identifyService->method('getHasher')->willReturn($this->hasher);
		$signatureMethods = [];
		foreach (['clickToSign', 'emailToken'] as $name) {
			$signatureMethod = $name === 'emailToken'
				? new EmailToken($identifyService, $this->createMock(TokenService::class))
				: new ClickToSign($identifyService);
			if (in_array($name, $enabledMethods, true)) {
				$signatureMethod->enable();
			}
			$signatureMethods[$name] = $signatureMethod;
		}
		$identifyMethod = $this->createMock(IIdentifyMethod::class);
		$identifyMethod->method('getEntity')->willReturn($entity);
		$identifyMethod->method('getSettings')->willReturn(['enabled' => true]);
		$identifyMethod->method('getSignatureMethods')->willReturn($signatureMethods);
		$this->identifyMethodService->method('getIdentifyMethodsFromSignRequestId')
			->with(7)
			->willReturn(['email' => [$identifyMethod]]);
		return $identifyMethod;
	}

	private static function keyOf(string $email): string {
		return hash_hmac('sha256', $email, 'instance-secret');
	}

	private function setStoredFolder(?Folder $folder, string $email = 'signer@example.com'): void {
		$getFolder = $this->rootFolder->method('get')->with('email_signer_elements/' . self::keyOf($email));
		if ($folder) {
			$getFolder->willReturn($folder);
			return;
		}
		$getFolder->willThrowException(new NotFoundException());
	}

	/**
	 * @param array<string, string> $files
	 */
	private function createStoredFolder(array $files): Folder&MockObject {
		$folder = $this->createMock(Folder::class);
		$folder->method('get')->willReturnCallback(function (string $name) use ($files): File {
			if (!isset($files[$name])) {
				throw new NotFoundException();
			}
			$file = $this->createMock(File::class);
			$file->method('getContent')->willReturn($files[$name]);
			return $file;
		});
		return $folder;
	}

	private function createSessionFile(int $id, string $type, string $content): File&MockObject {
		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn($id);
		$file->method('getContent')->willReturn($content);
		$this->signerElementsService->method('getSessionElementType')
			->willReturnCallback(static fn (File $element): string => $element->getName());
		$file->method('getName')->willReturn($type);
		return $file;
	}

	public function testSignerStateIsNullWhenPolicyIsDisabled(): void {
		$this->setPolicy(false);
		$this->identifyMethodService->expects($this->never())->method('getIdentifyMethodsFromSignRequestId');

		$this->assertNull($this->getService()->getSignerState($this->signRequest));
	}

	public function testSignerStateIsNullWithoutEmailIdentifyMethod(): void {
		$this->setPolicy(true);
		$this->identifyMethodService->method('getIdentifyMethodsFromSignRequestId')->willReturn(['account' => []]);

		$this->assertNull($this->getService()->getSignerState($this->signRequest));
	}

	#[DataProvider('provideConfiguredConfirmMethods')]
	public function testSignerStateUsesConfiguredConfirmMethod(array $enabledMethods, string $expected): void {
		$this->setPolicy(true);
		$this->setEmailSigner($enabledMethods);
		$this->folderService->expects($this->never())->method('getUnauthenticatedFolder');

		$this->assertSame(
			['confirmMethod' => $expected],
			$this->getService()->getSignerState($this->signRequest),
		);
	}

	public static function provideConfiguredConfirmMethods(): array {
		return [
			'email token' => [['emailToken'], 'emailToken'],
			'click to sign' => [['clickToSign'], 'clickToSign'],
			'both enabled requires the email token' => [['clickToSign', 'emailToken'], 'emailToken'],
		];
	}

	public function testSignerStateDoesNotLookUpSavedSignatures(): void {
		$this->setPolicy(true);
		$this->setEmailSigner(['emailToken']);
		$this->folderService->expects($this->never())->method('getUnauthenticatedFolder');

		$this->assertSame(
			['confirmMethod' => 'emailToken'],
			$this->getService()->getSignerState($this->signRequest),
		);
	}

	public function testUnlockWithValidEmailTokenCopiesSavedElementsIntoSession(): void {
		$this->setPolicy(true);
		$identifyMethod = $this->setEmailSigner(['emailToken'], 'hash-of-123456');
		$identifyMethod->expects($this->once())->method('validateToIdentify');
		$this->setStoredFolder($this->createStoredFolder(['signature.png' => 'signature-image', 'initial.png' => 'initial-image']));
		$copied = [];
		$this->signerElementsService->expects($this->exactly(2))->method('replaceSessionElement')
			->willReturnCallback(static function (string $type, string $content) use (&$copied): void {
				$copied[$type] = $content;
			});
		$this->signerElementsService->method('getElementsFromSessionAsArray')->willReturn([['type' => 'signature']]);
		$this->sessionService->expects($this->once())->method('setEmailSignatureKey')->with(self::keyOf('signer@example.com'));

		$elements = $this->getService()->unlock($this->signRequest, '123456');

		$this->assertSame(['signature' => 'signature-image', 'initial' => 'initial-image'], $copied);
		$this->assertSame([['type' => 'signature']], $elements);
	}

	public function testUnlockWithClickToSignNeedsOnlyTheSignLink(): void {
		$this->setPolicy(true);
		$this->setEmailSigner(['clickToSign']);
		$this->setStoredFolder($this->createStoredFolder(['signature.png' => 'signature-image']));
		$this->signerElementsService->expects($this->once())->method('replaceSessionElement')->with('signature', 'signature-image');

		$this->getService()->unlock($this->signRequest, '');
	}

	#[DataProvider('provideRejectedUnlocks')]
	public function testUnlockRejectsWhenConfirmMethodIsNotSatisfied(bool $enabled, array $enabledMethods, ?string $code, string $token, string $message): void {
		$this->setPolicy($enabled);
		$this->setEmailSigner($enabledMethods, $code);
		$this->setStoredFolder($this->createStoredFolder(['signature.png' => 'signature-image']));
		$this->signerElementsService->expects($this->never())->method('replaceSessionElement');
		$this->sessionService->expects($this->never())->method('setEmailSignatureKey');

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage($message);
		$this->getService()->unlock($this->signRequest, $token);
	}

	public static function provideRejectedUnlocks(): array {
		return [
			'policy disabled' => [false, ['clickToSign'], null, '', 'Saved signatures are disabled'],
			'wrong code' => [true, ['emailToken'], 'hash-of-123456', '654321', 'Invalid code.'],
			'missing code' => [true, ['emailToken'], 'hash-of-123456', '', 'Invalid code.'],
			'code never requested' => [true, ['emailToken'], null, '123456', 'Invalid code.'],
			'click to sign is not enough when email token is also enabled' => [true, ['clickToSign', 'emailToken'], null, '', 'Invalid code.'],
		];
	}

	public function testUnlockStopsWhenSignRequestIsNoLongerValid(): void {
		$this->setPolicy(true);
		$identifyMethod = $this->setEmailSigner(['clickToSign']);
		$identifyMethod->method('validateToIdentify')->willThrowException(new LibresignException('File already signed.'));
		$this->setStoredFolder($this->createStoredFolder(['signature.png' => 'signature-image']));
		$this->signerElementsService->expects($this->never())->method('replaceSessionElement');

		$this->expectExceptionMessage('File already signed.');
		$this->getService()->unlock($this->signRequest, '');
	}

	public function testUnlockFailsWhenNothingWasSaved(): void {
		$this->setPolicy(true);
		$this->setEmailSigner(['clickToSign']);
		$this->setStoredFolder(null);

		$this->expectExceptionMessage('No saved signature found');
		$this->getService()->unlock($this->signRequest, '');
	}

	public function testSaveAfterConfirmedSignStoresOnlySubmittedSessionElements(): void {
		$this->setPolicy(true);
		$this->setEmailSigner(['emailToken'], 'hash-of-123456', 'SIGNER@example.com ');
		$used = $this->createSessionFile(10, 'signature', 'drawn-signature');
		$unused = $this->createSessionFile(11, 'initial', 'drawn-initial');
		$this->signerElementsService->method('getElementsFromSession')->willReturn([$used, $unused]);
		$this->setStoredFolder(null);
		$stored = $this->createMock(Folder::class);
		$this->storageFolder->expects($this->once())->method('getOrCreateFolder')->with(self::keyOf('signer@example.com'))->willReturn($stored);
		$stored->method('get')->willThrowException(new NotFoundException());
		$stored->expects($this->once())->method('newFile')->with('signature.png', 'drawn-signature');
		$this->sessionService->expects($this->once())->method('setEmailSignatureKey');

		$this->prepareAndSave($this->signRequest, '123456', [
			['documentElementId' => 1, 'profileNodeId' => '10'],
			['documentElementId' => 2, 'profileNodeId' => 99],
		], true);
	}

	public function testSaveAfterConfirmedSignReplacesPreviouslySavedElement(): void {
		$this->setPolicy(true);
		$this->setEmailSigner(['clickToSign']);
		$this->signerElementsService->method('getElementsFromSession')->willReturn([$this->createSessionFile(10, 'signature', 'new-signature')]);
		$existing = $this->createMock(File::class);
		$existing->expects($this->once())->method('putContent')->with('new-signature');
		$stored = $this->createMock(Folder::class);
		$stored->method('get')->with('signature.png')->willReturn($existing);
		$stored->expects($this->never())->method('newFile');
		$this->storageFolder->method('getOrCreateFolder')->with(self::keyOf('signer@example.com'))->willReturn($stored);

		$this->prepareAndSave($this->signRequest, '', [['documentElementId' => 1, 'profileNodeId' => 10]], true);
	}

	#[DataProvider('provideUnconfirmedSaves')]
	public function testSaveAfterConfirmedSignStoresNothingWithoutConfirmation(bool $enabled, ?string $code, string $token): void {
		$this->setPolicy($enabled);
		$this->setEmailSigner(['emailToken'], $code);
		$this->signerElementsService->method('getElementsFromSession')->willReturn([$this->createSessionFile(10, 'signature', 'drawn')]);
		$this->folderService->expects($this->never())->method('getUnauthenticatedFolder');
		$this->sessionService->expects($this->never())->method('setEmailSignatureKey');

		$this->prepareAndSave($this->signRequest, $token, [['documentElementId' => 1, 'profileNodeId' => 10]], true);
	}

	public static function provideUnconfirmedSaves(): array {
		return [
			'policy disabled' => [false, 'hash-of-123456', '123456'],
			'wrong code' => [true, 'hash-of-123456', '000000'],
			'code never requested' => [true, null, ''],
		];
	}

	public function testForgetSessionElementDeletesSavedCopyOfThatType(): void {
		$this->setPolicy(true);
		$this->sessionService->method('getEmailSignatureKey')->willReturn(self::keyOf('signer@example.com'));
		$this->signerElementsService->method('getElementsFromSession')->willReturn([$this->createSessionFile(10, 'initial', 'drawn')]);
		$savedInitial = $this->createMock(File::class);
		$savedInitial->expects($this->once())->method('delete');
		$stored = $this->createMock(Folder::class);
		$stored->method('get')->with('initial.png')->willReturn($savedInitial);
		$stored->method('getDirectoryListing')->willReturn([$this->createMock(File::class)]);
		$stored->expects($this->never())->method('delete');
		$this->setStoredFolder($stored);

		$this->getService()->forgetSessionElement(10);
	}

	public function testForgetSessionElementWithoutSavedSignatureInSession(): void {
		$this->setPolicy(true);
		$this->sessionService->method('getEmailSignatureKey')->willReturn('');
		$this->folderService->expects($this->never())->method('getUnauthenticatedFolder');

		$this->getService()->forgetSessionElement(10);
	}

	public function testSigningDoesNotSaveWithoutExplicitConsent(): void {
		$this->policyService->expects($this->never())->method('resolve');
		$this->folderService->expects($this->never())->method('getUnauthenticatedFolder');
		$this->signerElementsService->expects($this->never())->method('getElementsFromSession');

		$this->prepareAndSave($this->signRequest, '123456', [['profileNodeId' => 10]]);
	}

	public function testSaveIgnoresElementsFromAnotherSession(): void {
		$this->setPolicy(true);
		$this->setEmailSigner(['clickToSign']);
		$this->signerElementsService->method('getElementsFromSession')->willReturn([$this->createSessionFile(10, 'signature', 'own-image')]);
		$this->folderService->expects($this->never())->method('getUnauthenticatedFolder');
		$this->sessionService->expects($this->never())->method('setEmailSignatureKey');

		$this->prepareAndSave($this->signRequest, '', [['profileNodeId' => 99]], true);
	}

	public function testUnlockOnlyReadsTheVerifiedEmailStorage(): void {
		$this->setPolicy(true);
		$this->setEmailSigner(['emailToken'], 'hash-of-123456', 'other@example.com');
		$this->rootFolder->expects($this->once())->method('get')
			->with('email_signer_elements/' . self::keyOf('other@example.com'))
			->willThrowException(new NotFoundException());
		$this->signerElementsService->expects($this->never())->method('replaceSessionElement');

		$this->expectExceptionMessage('No saved signature found');
		$this->getService()->unlock($this->signRequest, '123456');
	}

	public function testWrongCodeDoesNotLookUpWhetherAnImageExists(): void {
		$this->setPolicy(true);
		$this->setEmailSigner(['emailToken'], 'hash-of-123456');
		$this->folderService->expects($this->never())->method('getUnauthenticatedFolder');

		$this->expectExceptionMessage('Invalid code.');
		$this->getService()->unlock($this->signRequest, '000000');
	}

	public function testPreparedSignatureSurvivesCredentialAndSessionChangesDuringSigning(): void {
		$this->setPolicy(true);
		$method = $this->setEmailSigner(['emailToken'], 'hash-of-123456');
		$this->identifyMethodService->expects($this->once())->method('getIdentifyMethodsFromSignRequestId');
		$used = $this->createSessionFile(10, 'signature', 'captured-image');
		$this->signerElementsService->expects($this->once())->method('getElementsFromSession')->willReturn([$used]);
		$stored = $this->createMock(Folder::class);
		$this->storageFolder->method('getOrCreateFolder')->willReturn($stored);
		$stored->method('get')->willThrowException(new NotFoundException());
		$stored->expects($this->once())->method('newFile')->with('signature.png', 'captured-image');
		$service = $this->getService();

		$signature = $service->prepareSignature($this->signRequest, '123456', [['profileNodeId' => 10]], true);
		$this->assertNotNull($signature);
		$method->getEntity()->setCode('');
		$this->signRequest->setSigned(new \DateTime());
		$service->saveSignature($signature);
	}

	public function testUnlockRejectsNonEmailSigners(): void {
		$this->setPolicy(true);
		$this->identifyMethodService->method('getIdentifyMethodsFromSignRequestId')->willReturn(['account' => [$this->createMock(IIdentifyMethod::class)]]);
		$this->folderService->expects($this->never())->method('getUnauthenticatedFolder');

		$this->expectExceptionMessage('Saved signatures are only available to signers identified by email');
		$this->getService()->unlock($this->signRequest, '');
	}

	public function testMixedIdentificationDoesNotExposeEmailSignatureState(): void {
		$this->setPolicy(true);
		$this->identifyMethodService->method('getIdentifyMethodsFromSignRequestId')->willReturn([
			'email' => [$this->createMock(IIdentifyMethod::class)],
			'account' => [$this->createMock(IIdentifyMethod::class)],
		]);
		$this->folderService->expects($this->never())->method('getUnauthenticatedFolder');

		$this->assertNull($this->getService()->getSignerState($this->signRequest));
	}

	public function testForgetCannotDeleteAnElementOutsideTheCurrentSession(): void {
		$this->setPolicy(true);
		$this->sessionService->method('getEmailSignatureKey')->willReturn(self::keyOf('signer@example.com'));
		$this->signerElementsService->method('getElementsFromSession')->willReturn([$this->createSessionFile(10, 'signature', 'own-image')]);
		$stored = $this->createMock(Folder::class);
		$stored->expects($this->never())->method('get');
		$stored->expects($this->never())->method('delete');
		$this->setStoredFolder($stored);

		$this->getService()->forgetSessionElement(99);
	}

	public function testForgetDeletesEmptyStorageFolderEvenWhenPolicyIsDisabled(): void {
		$this->setPolicy(false);
		$this->sessionService->method('getEmailSignatureKey')->willReturn(self::keyOf('signer@example.com'));
		$this->signerElementsService->method('getElementsFromSession')->willReturn([$this->createSessionFile(10, 'signature', 'image')]);
		$saved = $this->createMock(File::class);
		$saved->expects($this->once())->method('delete');
		$stored = $this->createMock(Folder::class);
		$stored->method('get')->with('signature.png')->willReturn($saved);
		$stored->method('getDirectoryListing')->willReturn([]);
		$stored->expects($this->once())->method('delete');
		$this->setStoredFolder($stored);

		$this->getService()->forgetSessionElement(10);
	}

	public function testForgetDoesNotRequireThePolicyToBeEnabled(): void {
		$this->policyService->expects($this->never())->method('resolve');
		$this->sessionService->method('getEmailSignatureKey')->willReturn('');
		$this->folderService->expects($this->never())->method('getUnauthenticatedFolder');

		$this->getService()->forgetSessionElement(10);
	}
}
