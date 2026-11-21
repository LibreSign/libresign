<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Signing;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\RequestSignatureService;
use OCA\Libresign\Service\RequestSignatureWorkflowService;
use OCA\Libresign\Service\SignFileService;
use OCA\Libresign\Service\Signing\DirectSigningService;
use OCA\Libresign\Service\Signing\SignedPdfProvider;
use OCA\Libresign\Service\Signing\SigningPreparationService;
use OCA\Libresign\Service\Validation\FileInputValidator;
use OCA\Libresign\Service\Validation\SignerValidator;
use OCA\Libresign\Service\Validation\SigningRequestValidator;
use OCA\Libresign\Service\Validation\VisibleElementValidator;
use OCP\AppFramework\Http;
use OCP\IL10N;
use OCP\IUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DirectSigningServiceTest extends TestCase {
	private IUser&MockObject $user;
	private RequestSignatureService&MockObject $requests;
	private SignFileService&MockObject $signing;
	private SignerValidator&MockObject $signerValidator;
	private IdentifyMethodService&MockObject $identify;
	private SigningPreparationService&MockObject $preparation;
	private SignedPdfProvider&MockObject $pdf;
	private PolicyService&MockObject $policies;
	private DirectSigningService $service;

	protected function setUp(): void {
		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')->willReturn('account-a');
		$this->user->method('getDisplayName')->willReturn('Account A');
		$this->requests = $this->createMock(RequestSignatureService::class);
		$this->signing = $this->createMock(SignFileService::class);
		$this->signerValidator = $this->createMock(SignerValidator::class);
		$this->identify = $this->createMock(IdentifyMethodService::class);
		$this->preparation = $this->createMock(SigningPreparationService::class);
		$this->pdf = $this->createMock(SignedPdfProvider::class);
		$this->policies = $this->createMock(PolicyService::class);

		$fileInput = $this->createMock(FileInputValidator::class);
		$fileInput->method('normalizeNodeId')->willReturnArgument(0);
		$workflow = new RequestSignatureWorkflowService(
			$this->createMock(IL10N::class),
			$this->requests,
			$this->createMock(SigningRequestValidator::class),
			$this->signerValidator,
			$this->createMock(VisibleElementValidator::class),
			$this->createMock(FileMapper::class),
			$fileInput,
		);
		$this->service = new DirectSigningService(
			$workflow,
			$this->signing,
			$this->signerValidator,
			$this->identify,
			$this->preparation,
			$this->pdf,
			$this->policies,
		);
	}

	#[DataProvider('invalidInputCases')]
	public function testRejectsInvalidRequestsBeforePersisting(array $input, string $name, string $method, string $token): void {
		$this->requests->expects(self::never())->method('save');
		$this->policies->expects(self::never())->method('resolveForUser');
		$this->signing->expects(self::never())->method('sign');

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(Http::STATUS_BAD_REQUEST);
		$this->service->sign($this->user, $input, $name, $method, $token);
	}

	public static function invalidInputCases(): iterable {
		yield 'no document' => [[], 'file.pdf', 'clickToSign', ''];
		yield 'multiple sources' => [['nodeId' => 4, 'base64' => 'encoded'], 'file.pdf', 'clickToSign', ''];
		yield 'remote URL' => [['url' => 'https://example.invalid/file.pdf'], 'file.pdf', 'clickToSign', ''];
		yield 'unsupported key' => [['nodeId' => 4, 'signers' => []], 'file.pdf', 'clickToSign', ''];
		yield 'not a PDF filename' => [['nodeId' => 4], 'file.txt', 'clickToSign', ''];
		yield 'empty filename' => [['nodeId' => 4], '', 'clickToSign', ''];
		yield 'unsupported signature method' => [['nodeId' => 4], 'file.pdf', 'emailToken', ''];
		yield 'password method without password' => [['nodeId' => 4], 'file.pdf', 'password', ''];
	}

	#[DataProvider('disallowedPolicyCases')]
	public function testRejectsForbiddenSigningMethodsBeforePersisting(array $factors, string $method): void {
		$this->setIdentificationPolicy($factors);
		$this->requests->expects(self::never())->method('save');
		$this->signing->expects(self::never())->method('sign');

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(Http::STATUS_FORBIDDEN);
		$this->service->sign($this->user, ['nodeId' => 4], 'contract.pdf', $method, $method === 'password' ? 'secret' : '');
	}

	public static function disallowedPolicyCases(): iterable {
		yield 'account factor disabled' => [[['name' => 'account', 'enabled' => false, 'signatureMethods' => ['clickToSign' => ['enabled' => true]]]], 'clickToSign'];
		yield 'signing method disabled' => [[['name' => 'account', 'enabled' => true, 'signatureMethods' => ['clickToSign' => ['enabled' => false]]]], 'clickToSign'];
		yield 'only email factor' => [[['name' => 'email', 'enabled' => true, 'signatureMethods' => ['clickToSign' => ['enabled' => true]]]], 'clickToSign'];
		yield 'password not permitted' => [[['name' => 'account', 'enabled' => true, 'signatureMethods' => ['clickToSign' => ['enabled' => true], 'password' => ['enabled' => false]]]], 'password'];
	}

	#[DataProvider('allowedSignatureMethods')]
	public function testCreatesAnOrdinarySigningRequestAndReturnsItsSignedPdf(string $method, string $token): void {
		$this->setIdentificationPolicy([['name' => 'account', 'enabled' => true, 'signatureMethods' => [$method => ['enabled' => true]]]]);
		$file = new File();
		$file->setId(45);
		$file->setParentFileId(100);
		$signRequest = new SignRequest();
		$signRequest->setUuid('signer-uuid');
		$signRequest->setId(67);
		$this->requests->expects(self::once())->method('validateNewRequestToFile')
			->with(self::callback(static fn (array $request): bool
				=> $request['userManager']?->getUID() === 'account-a'
				&& $request['status'] === FileStatus::ABLE_TO_SIGN->value
				&& $request['signers'][0]['identifyMethods'][0]['value'] === 'account-a'
				&& $request['signers'][0]['notify'] === 0
				&& $request['visibleElements'] === []));
		$this->requests->expects(self::once())->method('save')->willReturn($file);
		$this->signing->method('getSignRequestToSign')->with($file, null, $this->user)->willReturn($signRequest);
		$this->signerValidator->expects(self::once())->method('validateSigner')->with('signer-uuid', $this->user);
		$this->identify->expects(self::once())->method('clearCache');
		$this->preparation->method('prepare')->with($file, $signRequest, $this->user, $method, '', $token)
			->willReturn(['userIdentifier' => 'account:account-a', 'metadata' => ['audit' => true]]);
		$this->signing->method('setVisibleElements')->with([])->willReturnSelf();
		$this->signing->method('storeUserMetadata')->with(['audit' => true])->willReturnSelf();
		$this->signing->expects(self::once())->method('sign');
		$expected = ['content' => '%PDF-1.7', 'filename' => 'signed.pdf', 'uuid' => 'file-uuid'];
		$this->pdf->method('getSignedPdf')->with(45, $this->user)->willReturn($expected);

		self::assertSame($expected, $this->service->sign($this->user, ['nodeId' => 4], 'contract.pdf', $method, $token));
	}

	public static function allowedSignatureMethods(): iterable {
		yield 'click to sign' => ['clickToSign', ''];
		yield 'certificate password' => ['password', 'secret'];
	}

	public function testRejectedSignerDoesNotExecuteTheSignature(): void {
		$this->setIdentificationPolicy([['name' => 'account', 'enabled' => true, 'signatureMethods' => ['clickToSign' => ['enabled' => true]]]]);
		$file = new File();
		$file->setId(45);
		$file->setParentFileId(100);
		$signRequest = new SignRequest();
		$signRequest->setUuid('signer-uuid');
		$this->requests->method('save')->willReturn($file);
		$this->signing->method('getSignRequestToSign')->willReturn($signRequest);
		$this->signerValidator->method('validateSigner')->willThrowException(new LibresignException('Denied', Http::STATUS_FORBIDDEN));
		$this->signing->expects(self::never())->method('sign');
		$this->pdf->expects(self::never())->method('getSignedPdf');

		$this->expectException(LibresignException::class);
		$this->service->sign($this->user, ['nodeId' => 4], 'contract.pdf');
	}

	private function setIdentificationPolicy(array $factors): void {
		$this->policies->method('resolveForUser')->with('identify_methods', $this->user)
			->willReturn((new ResolvedPolicy())->setEffectiveValue(['factors' => $factors]));
	}
}
