<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Signing;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Service\File\SettingsLoader;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\SignFileService;
use OCA\Libresign\Service\Signing\SigningMetadataProvider;
use OCA\Libresign\Service\Signing\SigningPreparationService;
use OCA\Libresign\Service\Validation\IdentityDocumentValidator;
use OCA\Libresign\Service\Validation\SignerValidator;
use OCA\Libresign\Service\Validation\VisibleElementValidator;
use OCP\IUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SigningPreparationServiceTest extends TestCase {
	#[DataProvider('signatureMethods')]
	public function testPreparesAuthorizedSignerWithCollectedMetadata(string $method, string $token, bool $createCertificate, ?string $password): void {
		$file = new File();
		$signRequest = new SignRequest();
		$signRequest->setId(44);
		$signRequest->setDisplayName('Account signer');
		$user = $this->createMock(IUser::class);
		$settings = $this->createMock(SettingsLoader::class);
		$settings->method('getIdentificationDocumentsStatus')->willReturn(0);
		$identity = $this->createMock(IdentityDocumentValidator::class);
		$identity->expects(self::once())->method('canSignWithIdentificationDocumentStatus')->with($user, 0);
		$visible = $this->createMock(VisibleElementValidator::class);
		$visible->expects(self::once())->method('validateVisibleElementsRelation')->with([], $signRequest, $user);
		$credentials = $this->createMock(SignerValidator::class);
		$credentials->expects(self::once())->method('validateCredentials')->with($signRequest, $method, '', $token);
		$identify = $this->createMock(IdentifyMethodService::class);
		$identify->method('getUserIdentifier')->with(44)->willReturn('account:signer');
		$metadata = $this->createMock(SigningMetadataProvider::class);
		$metadata->expects(self::once())->method('collect')->with($file, $signRequest, [])->willReturn(['request' => true]);
		$signing = $this->createMock(SignFileService::class);
		$signing->expects(self::once())->method('prepareForSigning')
			->with($file, $signRequest, $user, 'account:signer', 'Account signer', $createCertificate, $password, $method);

		$service = new SigningPreparationService($identity, $settings, $visible, $credentials, $identify, $metadata, $signing);

		self::assertSame([
			'userIdentifier' => 'account:signer',
			'metadata' => ['request' => true],
		], $service->prepare($file, $signRequest, $user, $method, '', $token));
	}

	public static function signatureMethods(): iterable {
		yield 'click to sign' => ['clickToSign', '', true, null];
		yield 'certificate password' => ['password', 'secret', false, 'secret'];
	}

	public function testRejectedCredentialsNeverPrepareCertificateOrCollectMetadata(): void {
		$credentials = $this->createMock(SignerValidator::class);
		$credentials->method('validateCredentials')->willThrowException(new \RuntimeException('identity denied'));
		$metadata = $this->createMock(SigningMetadataProvider::class);
		$metadata->expects(self::never())->method('collect');
		$signing = $this->createMock(SignFileService::class);
		$signing->expects(self::never())->method('prepareForSigning');
		$service = new SigningPreparationService(
			$this->createMock(IdentityDocumentValidator::class),
			$this->createMock(SettingsLoader::class),
			$this->createMock(VisibleElementValidator::class),
			$credentials,
			$this->createMock(IdentifyMethodService::class),
			$metadata,
			$signing,
		);

		$this->expectExceptionMessage('identity denied');
		$service->prepare(new File(), new SignRequest(), $this->createMock(IUser::class), 'clickToSign');
	}
}
