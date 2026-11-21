<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Service\File\SettingsLoader;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\RequestMetadataService;
use OCA\Libresign\Service\SignerGeolocation\SignerGeolocationMetadataValidator;
use OCA\Libresign\Service\SignerIpGeolocation\SignerIpGeolocationPolicyService;
use OCA\Libresign\Service\SignFileService;
use OCA\Libresign\Service\SigningPreparationService;
use OCA\Libresign\Service\Validation\IdentityDocumentValidator;
use OCA\Libresign\Service\Validation\SignerValidator;
use OCA\Libresign\Service\Validation\VisibleElementValidator;
use OCP\IRequest;
use OCP\IUser;
use PHPUnit\Framework\TestCase;

final class SigningPreparationServiceTest extends TestCase {
	public function testReusesAllMandatorySecurityChecksAndCapturesMetadata(): void {
		$request = $this->createMock(IRequest::class);
		$identity = $this->createMock(IdentityDocumentValidator::class);
		$settings = $this->createMock(SettingsLoader::class);
		$visible = $this->createMock(VisibleElementValidator::class);
		$signerValidator = $this->createMock(SignerValidator::class);
		$identification = $this->createMock(IdentifyMethodService::class);
		$metadataService = $this->createMock(RequestMetadataService::class);
		$geolocation = $this->createMock(SignerGeolocationMetadataValidator::class);
		$ip = $this->createMock(SignerIpGeolocationPolicyService::class);
		$signer = $this->createMock(SignFileService::class);
		$file = new File();
		$signRequest = new SignRequest();
		$signRequest->setId(44);
		$signRequest->setDisplayName('Account signer');
		$user = $this->createMock(IUser::class);

		$settings->method('getIdentificationDocumentsStatus')->willReturn(0);
		$identity->expects(self::once())->method('canSignWithIdentificationDocumentStatus')->with($user, 0);
		$visible->expects(self::once())->method('validateVisibleElementsRelation')->with([], $signRequest, $user);
		$signerValidator->expects(self::once())->method('validateCredentials')
			->with($signRequest, 'clickToSign', '', '');
		$identification->method('getUserIdentifier')->with(44)->willReturn('account:signer');
		$metadataService->method('collectMetadata')->willReturn(['request' => true]);
		$geolocation->method('normalize')->with(null)->willReturn(null);
		$geolocation->expects(self::once())->method('validateSubmission')->with($signRequest, null);
		$ip->expects(self::once())->method('collectMetadata')->with($file, $request)->willReturn(['country' => 'BR']);
		$ip->expects(self::once())->method('mergeIntoMetadata')->willReturn(['request' => true, 'ip' => 'BR']);
		$signer->expects(self::once())->method('prepareForSigning')
			->with($file, $signRequest, $user, 'account:signer', 'Account signer', true, null, 'clickToSign');

		$preparation = new SigningPreparationService(
			$request, $identity, $settings, $visible, $signerValidator, $identification,
			$metadataService, $geolocation, $ip, $signer,
		);
		self::assertSame([
			'userIdentifier' => 'account:signer',
			'metadata' => ['request' => true, 'ip' => 'BR'],
		], $preparation->prepare($file, $signRequest, $user, 'clickToSign'));
	}

	public function testValidationFailureStopsBeforeCertificatePreparation(): void {
		$request = $this->createMock(IRequest::class);
		$identity = $this->createMock(IdentityDocumentValidator::class);
		$settings = $this->createMock(SettingsLoader::class);
		$visible = $this->createMock(VisibleElementValidator::class);
		$validator = $this->createMock(SignerValidator::class);
		$validator->method('validateCredentials')->willThrowException(new \RuntimeException('identity denied'));
		$signer = $this->createMock(SignFileService::class);
		$signer->expects(self::never())->method('prepareForSigning');
		$preparation = new SigningPreparationService(
			$request, $identity, $settings, $visible, $validator,
			$this->createMock(IdentifyMethodService::class),
			$this->createMock(RequestMetadataService::class),
			$this->createMock(SignerGeolocationMetadataValidator::class),
			$this->createMock(SignerIpGeolocationPolicyService::class),
			$signer,
		);
		$this->expectExceptionMessage('identity denied');
		$preparation->prepare(new File(), new SignRequest(), $this->createMock(IUser::class), 'clickToSign');
	}
}
