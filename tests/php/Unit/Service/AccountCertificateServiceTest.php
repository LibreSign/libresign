<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use InvalidArgumentException;
use OCA\Libresign\Enum\CRLReason;
use OCA\Libresign\Exception\InvalidPasswordException;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\AEngineHandler;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Helper\FileUploadHelper;
use OCA\Libresign\Service\AccountCertificateService;
use OCA\Libresign\Service\Crl\CrlService;
use OCP\Files\IMimeTypeDetector;
use OCP\IL10N;
use OCP\IUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class AccountCertificateServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IL10N&MockObject $l10n;
	private CertificateEngineFactory&MockObject $certificateEngineFactory;
	private Pkcs12Handler&MockObject $pkcs12Handler;
	private FileUploadHelper&MockObject $uploadHelper;
	private IMimeTypeDetector&MockObject $mimeTypeDetector;
	private CrlService&MockObject $crlService;

	public function setUp(): void {
		parent::setUp();
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnArgument(0);
		$this->certificateEngineFactory = $this->createMock(CertificateEngineFactory::class);
		$this->pkcs12Handler = $this->createMock(Pkcs12Handler::class);
		$this->uploadHelper = $this->createMock(FileUploadHelper::class);
		$this->mimeTypeDetector = $this->createMock(IMimeTypeDetector::class);
		$this->crlService = $this->createMock(CrlService::class);
	}

	private function getService(): AccountCertificateService {
		return new AccountCertificateService(
			$this->l10n, $this->certificateEngineFactory, $this->pkcs12Handler,
			$this->uploadHelper, $this->mimeTypeDetector, $this->crlService,
		);
	}

	#[DataProvider('provideValidateCertificateDataCases')]
	public function testValidateCertificateDataUsingDataProvider($arguments, $expectedErrorMessage):void {
		if (is_callable($arguments)) {
			$arguments = $arguments($this);
		}

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(1);
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

	public function testValidateCertificateDataWithSuccess(): void {
		$this->getService()->validateCertificateData(['user' => ['email' => 'signer@example.com'], 'signPassword' => 'secret']);
		$this->addToAssertionCount(1);
	}

	public function testDeletePfxRevokesCertificatesWithReasonAndDeletesPfx(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');
		$revoked = false;

		$this->crlService->expects($this->once())
			->method('revokeUserCertificates')
			->with(
				'admin',
				CRLReason::CESSATION_OF_OPERATION,
				'Certificate deleted by account owner.',
				'admin'
			)
			->willReturnCallback(static function () use (&$revoked): int {
				$revoked = true;
				return 1;
			});

		$this->pkcs12Handler->expects($this->once())
			->method('deletePfx')
			->with('admin')
			->willReturnCallback(static function () use (&$revoked): void {
				self::assertTrue($revoked);
			});

		$this->getService()->deletePfx($user);
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

	#[DataProvider('provideEngineSetupStates')]
	public function testCertificateEngineSettings(bool $setup): void {
		$engine = $this->createMock(AEngineHandler::class);
		$engine->method('getName')->willReturn('openssl');
		$engine->method('isSetupOk')->willReturn($setup);
		$this->certificateEngineFactory->method('getEngine')->willReturn($engine);
		$this->assertSame('openssl', $this->getService()->getCertificateEngineName());
		$this->assertSame($setup, $this->getService()->isSetupOk());
	}

	public static function provideEngineSetupStates(): array {
		return [[true], [false]];
	}

	public function testCertificatePasswordIsRequired(): void {
		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(1);
		$this->expectExceptionMessage('Password to sign is mandatory');
		$this->getService()->validateCertificateData(['user' => []]);
	}

	public function testCreateForUserPreservesCertificateSubjectAndStorageKey(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('internal-uid');
		$user->method('getPrimaryEMailAddress')->willReturn('signer@example.com');
		$user->method('getDisplayName')->willReturn('Signer Name');
		$this->pkcs12Handler->expects($this->once())->method('generateCertificate')
			->with(['host' => 'signer@example.com', 'uid' => 'account:internal-uid', 'name' => 'Signer Name'], 'secret', 'Signer Name')
			->willReturn('generated-pfx');
		$this->pkcs12Handler->expects($this->once())->method('savePfx')->with('signer@example.com', 'generated-pfx');
		$this->getService()->createForUser($user, 'secret');
	}

	public function testCreateForUserDoesNotStoreCertificateWhenGenerationFails(): void {
		$user = $this->createMock(IUser::class);
		$error = new LibresignException('Certificate generation failed');
		$this->pkcs12Handler->method('generateCertificate')->willThrowException($error);
		$this->pkcs12Handler->expects($this->never())->method('savePfx');
		$this->expectExceptionObject($error);
		$this->getService()->createForUser($user, 'secret');
	}

	public function testUploadPfxPropagatesStorageFailureAfterRemovingTemporaryFile(): void {
		$path = tempnam(sys_get_temp_dir(), 'libresign-pfx-');
		file_put_contents($path, 'pfx-content');
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('signer');
		$file = ['tmp_name' => $path, 'size' => 11, 'name' => 'certificate.pfx', 'error' => 0];
		$this->uploadHelper->expects($this->once())->method('validateUploadedFile')->with($file);
		$this->mimeTypeDetector->method('detectString')->with('pfx-content')->willReturn('application/octet-stream');
		$error = new LibresignException('Certificate storage failed');
		$this->pkcs12Handler->expects($this->once())->method('savePfx')
			->with('signer', 'pfx-content')->willReturnCallback(static function () use ($path, $error): never {
				self::assertFileDoesNotExist($path);
				throw $error;
			});
		$this->expectExceptionObject($error);
		try {
			$this->getService()->uploadPfx($file, $user);
		} finally {
			if (is_file($path)) {
				unlink($path);
			}
		}
	}

	#[DataProvider('provideMissingCertificateOperations')]
	public function testMissingCertificateErrorsAreNotReportedAsWrongPasswords(string $method, string $handlerMethod): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('signer');
		$error = new LibresignException('No certificate');
		$this->pkcs12Handler->method($handlerMethod)->willThrowException($error);
		$this->expectExceptionObject($error);
		$arguments = $method === 'readPfxData' ? [$user, 'password'] : [$user, 'old', 'new'];
		$this->getService()->$method(...$arguments);
	}

	public static function provideMissingCertificateOperations(): array {
		return [
			'read' => ['readPfxData', 'getPfxOfCurrentSigner'],
			'change password' => ['updatePfxPassword', 'updatePassword'],
		];
	}

	public function testHasSignatureFilePropagatesUnexpectedHandlerErrors(): void {
		$error = new \RuntimeException('Storage unavailable');
		$this->pkcs12Handler->method('getPfxOfCurrentSigner')->willThrowException($error);
		$this->expectExceptionObject($error);
		$this->getService()->hasSignatureFile($this->createMock(IUser::class));
	}

	#[DataProvider('providePfxUploads')]
	public function testUploadPfxPreservesValidationAndCleanup(int $size, string $name, string $mime, bool $validUpload, ?string $error): void {
		$path = tempnam(sys_get_temp_dir(), 'libresign-pfx-');
		file_put_contents($path, 'pfx-content');
		$file = ['tmp_name' => $path, 'size' => $size, 'name' => $name, 'error' => 0];
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('signer');
		$this->uploadHelper->expects($this->once())->method('validateUploadedFile')->with($file);
		if (!$validUpload) {
			$this->uploadHelper->method('validateUploadedFile')->willThrowException(new InvalidArgumentException('Upload failed'));
		}
		$this->mimeTypeDetector->method('detectString')->with('pfx-content')->willReturn($mime);
		if ($error === null) {
			$this->pkcs12Handler->expects($this->once())->method('savePfx')
				->with('signer', 'pfx-content')->willReturnCallback(function () use ($path): string {
					$this->assertFileDoesNotExist($path);
					return 'stored';
				});
		} else {
			$this->pkcs12Handler->expects($this->never())->method('savePfx');
			$this->expectException(InvalidArgumentException::class);
			$this->expectExceptionMessage($error);
		}
		try {
			$this->getService()->uploadPfx($file, $user);
		} finally {
			if (is_file($path)) {
				unlink($path);
			}
		}
	}

	public static function providePfxUploads(): array {
		$invalid = 'Invalid file provided. Need to be a .pfx file.';
		return [
			'valid' => [10, 'certificate.pfx', 'application/octet-stream', true, null],
			'upper case extension at size limit' => [10240, 'certificate.PFX', 'application/octet-stream', true, null],
			'too large' => [10241, 'certificate.pfx', 'application/octet-stream', true, 'File is too big'],
			'wrong extension' => [10, 'certificate.pem', 'application/octet-stream', true, $invalid],
			'wrong MIME' => [10, 'certificate.pfx', 'text/plain', true, $invalid],
			'invalid upload' => [10, 'certificate.pfx', 'application/octet-stream', false, $invalid],
		];
	}

	public function testDeletePfxDoesNotDeleteWhenRevocationFails(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('signer');
		$error = new \RuntimeException('Revocation failed');
		$this->crlService->method('revokeUserCertificates')->willThrowException($error);
		$this->pkcs12Handler->expects($this->never())->method('deletePfx');
		$this->expectExceptionObject($error);
		$this->getService()->deletePfx($user);
	}

	public function testUpdatePfxPasswordForwardsCredentials(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('signer');
		$this->pkcs12Handler->expects($this->once())->method('updatePassword')->with('signer', 'old', 'new')->willReturn('updated-pfx');
		$this->getService()->updatePfxPassword($user, 'old', 'new');
	}

	public function testReadPfxDataUsesCurrentSignerAndPassword(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('signer');
		$this->pkcs12Handler->expects($this->once())->method('getPfxOfCurrentSigner')->with('signer')->willReturn('pfx');
		$this->pkcs12Handler->expects($this->once())->method('setCertificate')->with('pfx')->willReturnSelf();
		$this->pkcs12Handler->expects($this->once())->method('setPassword')->with('password')->willReturnSelf();
		$this->pkcs12Handler->method('readCertificate')->willReturn(['subject' => 'signer']);
		$this->assertSame(['subject' => 'signer'], $this->getService()->readPfxData($user, 'password'));
	}

	#[DataProvider('provideInvalidPasswordOperations')]
	public function testInvalidPasswordKeepsPublicError(string $operation): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('signer');
		$this->pkcs12Handler->method('setCertificate')->willReturnSelf();
		$this->pkcs12Handler->method('setPassword')->willReturnSelf();
		$this->pkcs12Handler->method($operation)->willThrowException(new InvalidPasswordException('Engine error'));
		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Invalid user or password');
		if ($operation === 'updatePassword') {
			$this->getService()->updatePfxPassword($user, 'old', 'new');
		} else {
			$this->getService()->readPfxData($user, 'password');
		}
	}

	public static function provideInvalidPasswordOperations(): array {
		return [['getPfxOfCurrentSigner'], ['setCertificate'], ['setPassword'], ['readCertificate'], ['updatePassword']];
	}
}
