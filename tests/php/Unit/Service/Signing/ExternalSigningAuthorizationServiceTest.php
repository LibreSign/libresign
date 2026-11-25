<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Signing;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Service\ExperimentalFeaturesService;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Signing\ExternalSigningAuthorizationService;
use OCA\Libresign\Service\Validation\SignerValidator;
use OCA\Libresign\Service\Validation\SigningRequestValidator;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\IUser;
use OCP\IUserSession;

final class ExternalSigningAuthorizationServiceTest extends TestCase {
	private const REQUEST_UUID = 'e5a47a8c-7f34-4b8e-9ceb-282e454e61f1';
	private const GUEST_EMAIL = 'external@example.org';

	private ExternalSigningAuthorizationService $authorization;
	private SignerValidator $signerValidator;
	private SignRequest $signRequest;
	private File $file;
	private bool $enabled = true;
	private ?IUser $currentUser = null;
	/** @var array<string, list<IIdentifyMethod>> */
	private array $boundMethods = [];

	#[\Override]
	public function setUp(): void {
		parent::setUp();
		$this->enabled = true;
		$this->currentUser = null;
		$this->signRequest = new SignRequest();
		$this->signRequest->setId(12345);
		$this->signRequest->setUuid(self::REQUEST_UUID);
		$this->signRequest->setFileId(45678);
		$this->signRequest->setStatus(SignRequestStatus::ABLE_TO_SIGN->value);
		$this->file = new File();
		$this->file->setId(45678);
		$this->file->setStatus(FileStatus::ABLE_TO_SIGN->value);

		$features = $this->createMock(ExperimentalFeaturesService::class);
		$features->method('isEnabled')->willReturnCallback(fn (): bool => $this->enabled);
		$requests = $this->createMock(SignRequestMapper::class);
		$requests->method('getByUuid')->willReturnCallback(fn (): SignRequest => $this->signRequest);
		$files = $this->createMock(FileMapper::class);
		$files->method('getById')->willReturnCallback(fn (): File => $this->file);
		$this->signerValidator = $this->createMock(SignerValidator::class);
		$workflow = $this->createMock(SigningRequestValidator::class);
		$identification = $this->createMock(IdentifyMethodService::class);
		$identification->method('getIdentifyMethodsFromSignRequestId')->willReturnCallback(
			fn (): array => $this->boundMethods,
		);
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturnCallback(fn (): ?IUser => $this->currentUser);
		$this->authorization = new ExternalSigningAuthorizationService(
			$features, $requests, $files, $this->signerValidator, $workflow,
			$identification, $userSession,
		);
		$this->bind('email', self::GUEST_EMAIL);
	}

	private function bind(string $methodName, string $identity): void {
		$entity = new IdentifyMethod();
		$entity->setIdentifierKey($methodName);
		$entity->setIdentifierValue($identity);
		$method = $this->createMock(IIdentifyMethod::class);
		$method->method('getEntity')->willReturn($entity);
		$this->boundMethods = [$methodName => [$method]];
	}

	public function testInvitedSignerWithVerifiedMethodAndTokenHasStableRequestIdentity(): void {
		$this->signerValidator->expects($this->once())->method('validateSigner')
			->with(self::REQUEST_UUID, null);
		$this->signerValidator->expects($this->once())->method('validateCredentials')
			->with($this->signRequest, 'email', self::GUEST_EMAIL, 'verified-one-time-code');
		$context = $this->authorization->authorize(self::REQUEST_UUID, 'email',
			self::GUEST_EMAIL, 'verified-one-time-code');
		self::assertSame($this->signRequest, $context->signRequest);
		self::assertSame($this->file, $context->file);
		self::assertSame('sign-request:12345', $context->actorId);
	}

	public function testInvitationUuidWithoutSigningTokenCannotRevealCredentials(): void {
		$this->signerValidator->expects($this->never())->method('validateCredentials');
		$this->expectException(\DomainException::class);
		$this->expectExceptionMessage('requires verified signer authorization');
		$this->authorization->authorize(self::REQUEST_UUID, 'email', self::GUEST_EMAIL);
	}

	public function testForgedIdentificationValueFailsBeforeTokenValidation(): void {
		$this->signerValidator->expects($this->never())->method('validateCredentials');
		$this->expectException(\DomainException::class);
		$this->authorization->authorize(self::REQUEST_UUID, 'email',
			'another-person@example.org', 'some-token');
	}

	public function testForgedIdentificationMethodDoesNotFallbackToFirstAvailableFactor(): void {
		$this->signerValidator->expects($this->never())->method('validateCredentials');
		$this->expectException(\DomainException::class);
		$this->authorization->authorize(self::REQUEST_UUID, 'sms', self::GUEST_EMAIL, 'some-token');
	}

	public function testClickToSignCannotBeUsedAsPersonalExternalCredentialAuthorization(): void {
		$this->bind('clickToSign', self::GUEST_EMAIL);
		$this->signerValidator->expects($this->never())->method('validateCredentials');
		$this->expectException(\DomainException::class);
		$this->authorization->authorize(self::REQUEST_UUID, 'clickToSign', self::GUEST_EMAIL, 'anything');
	}

	public function testAuthenticatedAccountMustMatchSignRequestAccountFactor(): void {
		$this->bind('account', 'alice');
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$this->currentUser = $user;
		$this->signerValidator->expects($this->once())->method('validateCredentials')
			->with($this->signRequest, 'account', 'alice', '');
		$context = $this->authorization->authorize(self::REQUEST_UUID, 'account', 'alice');
		self::assertSame('sign-request:12345', $context->actorId);
	}

	public function testAuthenticatedDifferentAccountIsDeniedBeforeCredentials(): void {
		$this->bind('account', 'alice');
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('bob');
		$this->currentUser = $user;
		$this->signerValidator->expects($this->never())->method('validateCredentials');
		$this->expectException(\DomainException::class);
		$this->authorization->authorize(self::REQUEST_UUID, 'account', 'alice');
	}

	public function testGuestCannotImpersonateAccountFactor(): void {
		$this->bind('account', 'alice');
		$this->signerValidator->expects($this->never())->method('validateCredentials');
		$this->expectException(\DomainException::class);
		$this->authorization->authorize(self::REQUEST_UUID, 'account', 'alice');
	}

	public function testInvalidTokenRejectedByExistingSignerValidation(): void {
		$this->signerValidator->expects($this->once())->method('validateCredentials')
			->willThrowException(new \DomainException('Invalid verification code'));
		$this->expectException(\DomainException::class);
		$this->expectExceptionMessage('External signing authorization denied');
		$this->authorization->authorize(self::REQUEST_UUID, 'email', self::GUEST_EMAIL, 'invalid');
	}

	public function testSignedRequestIsNeverReauthorized(): void {
		$this->signRequest->setStatus(SignRequestStatus::SIGNED->value);
		$this->signerValidator->expects($this->never())->method('validateCredentials');
		$this->expectException(\DomainException::class);
		$this->authorization->authorize(self::REQUEST_UUID, 'email', self::GUEST_EMAIL, 'valid');
	}

	public function testFeatureDisabledFailsClosedBeforeIdentityChecks(): void {
		$this->enabled = false;
		$this->signerValidator->expects($this->never())->method('validateSigner');
		$this->signerValidator->expects($this->never())->method('validateCredentials');
		$this->expectException(\DomainException::class);
		$this->authorization->authorize(self::REQUEST_UUID, 'email', self::GUEST_EMAIL, 'valid');
	}

	public function testGuestAccessMustBeRevalidatedEachTime(): void {
		$this->signerValidator->expects($this->exactly(2))->method('validateCredentials');
		$first = $this->authorization->authorize(self::REQUEST_UUID, 'email', self::GUEST_EMAIL, 'code');
		$second = $this->authorization->authorize(self::REQUEST_UUID, 'email', self::GUEST_EMAIL, 'code');
		self::assertSame($first->actorId, $second->actorId);
	}
}
