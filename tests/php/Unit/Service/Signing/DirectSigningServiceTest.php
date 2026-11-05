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
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\FolderService;
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
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\Files\File as NextcloudFile;
use OCP\IL10N;
use OCP\IUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DirectSigningServiceTest extends TestCase {
	private string $displayName = 'Account A';
	private string $sourceOwnerId = 'account-a';
	private string $sourceMime = 'application/pdf';
	private bool $sourceHasRequest = false;
	private IUser&MockObject $user;
	private RequestSignatureService&MockObject $requests;
	private SignFileService&MockObject $signing;
	private SignerValidator&MockObject $signerValidator;
	private IdentifyMethodService&MockObject $identify;
	private SigningPreparationService&MockObject $preparation;
	private SignedPdfProvider&MockObject $pdf;
	private PolicyService&MockObject $policies;
	private FileService&MockObject $files;
	private FolderService&MockObject $folder;
	private FileMapper&MockObject $mapper;
	private FileInputValidator&MockObject $fileInput;
	private NextcloudFile&MockObject $sourceNode;
	private DirectSigningService $service;

	protected function setUp(): void {
		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')->willReturn('account-a');
		$this->user->method('getDisplayName')->willReturnCallback(fn (): string => $this->displayName);
		$this->requests = $this->createMock(RequestSignatureService::class);
		$this->signing = $this->createMock(SignFileService::class);
		$this->signerValidator = $this->createMock(SignerValidator::class);
		$this->identify = $this->createMock(IdentifyMethodService::class);
		$this->preparation = $this->createMock(SigningPreparationService::class);
		$this->pdf = $this->createMock(SignedPdfProvider::class);
		$this->files = $this->createMock(FileService::class);
		$this->policies = $this->createMock(PolicyService::class);

		$this->fileInput = $this->createMock(FileInputValidator::class);
		$this->fileInput->method('normalizeNodeId')->willReturnArgument(0);
		$this->folder = $this->createMock(FolderService::class);
		$this->mapper = $this->createMock(FileMapper::class);
		$this->sourceNode = $this->createMock(NextcloudFile::class);
		$this->sourceNode->method('getId')->willReturn(4);
		$this->sourceNode->method('getMimeType')->willReturnCallback(fn (): string => $this->sourceMime);
		$owner = $this->createMock(IUser::class);
		$owner->method('getUID')->willReturnCallback(fn (): string => $this->sourceOwnerId);
		$this->sourceNode->method('getOwner')->willReturn($owner);
		$this->folder->method('getReadableNodeById')->willReturn($this->sourceNode);
		$this->folder->method('getFileByPath')->willReturn($this->sourceNode);
		$this->mapper->method('getByNodeId')->willReturnCallback(function (): File {
			if (!$this->sourceHasRequest) {
				throw new DoesNotExistException('No request');
			}
			$file = new File();
			$file->setId(100);
			return $file;
		});
		$workflow = new RequestSignatureWorkflowService(
			$this->createMock(IL10N::class),
			$this->requests,
			$this->createMock(SigningRequestValidator::class),
			$this->signerValidator,
			$this->createMock(VisibleElementValidator::class),
			$this->mapper,
			$this->fileInput,
		);
		$this->service = new DirectSigningService(
			$workflow,
			$this->signing,
			$this->signerValidator,
			$this->identify,
			$this->preparation,
			$this->pdf,
			$this->policies,
			$this->files,
			$this->folder,
			$this->mapper,
			$this->fileInput,
		);
	}

	#[DataProvider('protectedSourceCases')]
	public function testDirectSigningRejectsUnsafeSourceBeforeCreatingARequest(
		array $source, string $ownerId, string $mimeType, bool $hasRequest, int $status,
	): void {
		$this->setIdentificationPolicy([['name' => 'account', 'enabled' => true, 'signatureMethods' => ['clickToSign' => ['enabled' => true]]]]);
		$this->sourceOwnerId = $ownerId;
		$this->sourceMime = $mimeType;
		$this->sourceHasRequest = $hasRequest;
		$this->requests->expects(self::never())->method('save');
		$this->signing->expects(self::never())->method('sign');
		$this->files->expects(self::never())->method('delete');

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode($status);
		$this->service->sign($this->user, $source, 'contract.pdf');
	}

	public static function protectedSourceCases(): iterable {
		yield 'shared PDF by node ID' => [['nodeId' => 4], 'account-b', 'application/pdf', false, Http::STATUS_FORBIDDEN];
		yield 'shared PDF by path' => [['path' => '/contract.pdf'], 'account-b', 'application/pdf', false, Http::STATUS_FORBIDDEN];
		yield 'own draft by node ID' => [['nodeId' => 4], 'account-a', 'application/pdf', true, Http::STATUS_UNPROCESSABLE_ENTITY];
		yield 'own draft by path' => [['path' => '/contract.pdf'], 'account-a', 'application/pdf', true, Http::STATUS_UNPROCESSABLE_ENTITY];
		yield 'wrong MIME by node ID' => [['nodeId' => 4], 'account-a', 'text/plain', false, Http::STATUS_UNPROCESSABLE_ENTITY];
		yield 'wrong MIME by path' => [['path' => '/contract.pdf'], 'account-a', 'text/plain', false, Http::STATUS_UNPROCESSABLE_ENTITY];
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
	public function testCreatesAnOrdinarySigningRequestAndReturnsItsSignedPdf(string $method, string $token, string $displayName, string $expectedDisplayName): void {
		$this->displayName = $displayName;
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
				&& $request['signers'][0]['displayName'] === $expectedDisplayName
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
		yield 'click to sign' => ['clickToSign', '', 'Account A', 'Account A'];
		yield 'accented display name at limit' => ['clickToSign', '', str_repeat('é', 64), str_repeat('é', 64)];
		yield 'long multibyte display name' => ['clickToSign', '', str_repeat('界', 65), str_repeat('界', 64)];
		yield 'certificate password' => ['password', 'secret', 'Account A', 'Account A'];
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

	#[DataProvider('preflightFailureCases')]
	public function testFailedPreflightReleasesTheRequestForRetry(array $input, bool $deleteFile, string $stage): void {
		$this->setIdentificationPolicy([['name' => 'account', 'enabled' => true, 'signatureMethods' => ['clickToSign' => ['enabled' => true]]]]);
		$file = new File();
		$file->setId(45);
		$file->setParentFileId(100);
		$signRequest = new SignRequest();
		$signRequest->setUuid('signer-uuid');
		$active = false;
		$this->requests->method('save')->willReturnCallback(static function () use ($file, &$active): File {
			self::assertFalse($active, 'The previous failed request must not block the same source');
			$active = true;
			return $file;
		});
		$this->signing->method('getSignRequestToSign')->willReturn($signRequest);
		$attempt = 0;
		$failure = new LibresignException('Preflight rejected', Http::STATUS_UNPROCESSABLE_ENTITY);
		if ($stage === 'signer') {
			$this->signerValidator->method('validateSigner')->willReturnCallback(static function () use (&$attempt, $failure): void {
				if ($attempt++ === 0) {
					throw $failure;
				}
			});
		}
		$this->preparation->method('prepare')->willReturnCallback(static function () use (&$attempt, $failure, $stage): array {
			if ($stage === 'credentials' && $attempt++ === 0) {
				throw $failure;
			}
			return ['userIdentifier' => 'account:account-a', 'metadata' => []];
		});
		$this->files->expects(self::once())->method('delete')->with(45, $deleteFile)
			->willReturnCallback(static function () use (&$active): void {
				$active = false;
			});
		$this->signing->method('setVisibleElements')->willReturnSelf();
		$this->signing->method('storeUserMetadata')->willReturnSelf();
		$this->signing->expects(self::once())->method('sign');
		$expected = ['content' => '%PDF-1.7', 'filename' => 'signed.pdf', 'uuid' => 'file-uuid'];
		$this->pdf->method('getSignedPdf')->willReturn($expected);

		try {
			$this->service->sign($this->user, $input, 'contract.pdf');
			self::fail('The first attempt must fail before signing');
		} catch (LibresignException $e) {
			self::assertSame($failure, $e);
		}
		self::assertSame($expected, $this->service->sign($this->user, $input, 'contract.pdf'));
	}

	public static function preflightFailureCases(): iterable {
		yield 'node ID signer failure' => [['nodeId' => 4], false, 'signer'];
		yield 'node ID credential failure' => [['nodeId' => 4], false, 'credentials'];
		yield 'path preserves original file' => [['path' => '/contract.pdf'], false, 'credentials'];
		yield 'base64 removes uploaded file' => [['base64' => 'encoded'], true, 'credentials'];
	}

	public function testFailedCleanupDoesNotReportASafeValidationFailure(): void {
		$this->setIdentificationPolicy([['name' => 'account', 'enabled' => true, 'signatureMethods' => ['clickToSign' => ['enabled' => true]]]]);
		$file = new File();
		$file->setId(45);
		$file->setParentFileId(100);
		$this->requests->method('save')->willReturn($file);
		$this->signing->method('getSignRequestToSign')->willThrowException(new LibresignException('Preflight rejected'));
		$this->files->method('delete')->willThrowException(new LibresignException('Cleanup rejected'));
		$this->signing->expects(self::never())->method('sign');

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Unable to discard the failed direct signing request.');
		$this->service->sign($this->user, ['nodeId' => 4], 'contract.pdf');
	}

	#[DataProvider('signingFailureCases')]
	public function testFailuresAfterStartingSigningNeverAllowBlindRetry(string $stage, int $code): void {
		$this->setIdentificationPolicy([['name' => 'account', 'enabled' => true, 'signatureMethods' => ['clickToSign' => ['enabled' => true]]]]);
		$file = new File();
		$file->setId(45);
		$file->setParentFileId(100);
		$signRequest = new SignRequest();
		$signRequest->setUuid('signer-uuid');
		$this->requests->method('save')->willReturn($file);
		$this->signing->method('getSignRequestToSign')->willReturn($signRequest);
		$this->preparation->method('prepare')->willReturn(['userIdentifier' => 'account:account-a', 'metadata' => []]);
		$this->signing->method('setVisibleElements')->willReturnSelf();
		$this->signing->method('storeUserMetadata')->willReturnSelf();
		$this->files->expects(self::never())->method('delete');
		$target = $stage === 'engine' ? $this->signing : $this->pdf;
		$target->method($stage === 'engine' ? 'sign' : 'getSignedPdf')
			->willThrowException(new LibresignException('Failed after preflight', $code));

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(Http::STATUS_CONFLICT);
		$this->service->sign($this->user, ['base64' => 'encoded'], 'contract.pdf');
	}

	public static function signingFailureCases(): iterable {
		yield 'engine validation failure' => ['engine', 0];
		yield 'post-sign authorization error' => ['pdf', Http::STATUS_FORBIDDEN];
		yield 'post-sign read failure' => ['pdf', Http::STATUS_INTERNAL_SERVER_ERROR];
	}

	private function setIdentificationPolicy(array $factors): void {
		$this->policies->method('resolveForUser')->with('identify_methods', $this->user)
			->willReturn((new ResolvedPolicy())->setEffectiveValue(['factors' => $factors]));
	}
}
