<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\File;

use OCA\Libresign\Db\File as DbFile;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\SignRequest as DbSignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Service\File\EnvelopeAssembler;
use OCA\Libresign\Service\File\FileResponseOptions;
use OCA\Libresign\Service\File\SignersLoader;
use OCA\Libresign\Service\FileElementService;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicyValue;
use OCA\Libresign\Service\SignatureRejection\SignatureRejectionPolicyService;
use OCA\Libresign\Service\SignatureRejection\SignatureRejectionVisibilityService;
use OCP\Files\File;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;

final class EnvelopeAssemblerTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private SignRequestMapper&MockObject $signRequestMapper;
	private IdentifyMethodService&MockObject $identifyMethodService;
	private FileMapper&MockObject $fileMapper;
	private FolderService&MockObject $folderService;
	private IURLGenerator&MockObject $urlGenerator;
	private SignersLoader&MockObject $signersLoader;
	private Pkcs12Handler&MockObject $pkcs12Handler;
	private FileElementService&MockObject $fileElementService;

	public function setUp(): void {
		parent::setUp();
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->folderService = $this->createMock(FolderService::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->signersLoader = $this->createMock(SignersLoader::class);
		$this->pkcs12Handler = $this->createMock(Pkcs12Handler::class);
		$this->fileElementService = $this->createMock(FileElementService::class);
	}

	private function getService(): EnvelopeAssembler {
		return new EnvelopeAssembler(
			$this->signRequestMapper,
			$this->identifyMethodService,
			$this->fileMapper,
			$this->folderService,
			$this->urlGenerator,
			$this->signersLoader,
			null,
			$this->pkcs12Handler,
			new NullLogger(),
			$this->fileElementService,
			$this->realVisibilityService(),
		);
	}

	/** @var array<string, mixed> */
	private array $rejectionPolicy = [];

	private function realVisibilityService(): SignatureRejectionVisibilityService {
		$policyService = $this->createMock(SignatureRejectionPolicyService::class);
		$policyService->method('getPolicyValue')->willReturn(SignatureRejectionPolicyValue::normalize($this->rejectionPolicy));
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text, array $params = []): string => vsprintf($text, $params));
		return new SignatureRejectionVisibilityService($policyService, $l10n);
	}

	private function mockFileNode(): void {
		$fileNode = $this->createMock(File::class);
		$this->folderService->method('getReadableNodeById')->willReturn($fileNode);
		$this->urlGenerator->method('linkToRoute')->willReturn('http://example.com/page.pdf');
	}

	public function testBuildsChildDataWithoutCertificateChain(): void {
		$this->mockFileNode();

		$signRequest = new DbSignRequest();
		$signRequest->setId(42);
		$signRequest->setSigned(null);
		$signRequest->setDisplayName('Alice');
		$signRequest->setStatus(1);

		$this->signRequestMapper->method('getByFileId')->willReturn([$signRequest]);

		$this->identifyMethodService->method('setIsRequest')->willReturnSelf();
		$this->identifyMethodService->method('getIdentifyMethodsFromSignRequestIds')->willReturn([]);

		$this->fileMapper->method('getTextOfStatus')->willReturn('status-text');

		$this->signersLoader->expects($this->never())->method('loadSignersFromCertData');

		$assembler = $this->getService();

		$childFile = new DbFile();
		$childFile->setId(7);
		$childFile->setUuid('uuid-7');
		$childFile->setName('child.pdf');
		$childFile->setStatus(2);
		$childFile->setNodeId(123);
		$childFile->setMetadata(['p' => 1]);
		$childFile->setSignedNodeId(null);
		$childFile->setUserId('user1');

		$options = new FileResponseOptions();
		$result = $assembler->buildEnvelopeChildData($childFile, $options);

		$this->assertIsObject($result);
		$this->assertEquals(7, $result->id);
		$this->assertEquals('child.pdf', $result->name);
		$this->assertSame('http://example.com/page.pdf', $result->file);
		$this->assertIsArray($result->signers);
		$this->assertCount(1, $result->signers);
		$this->assertEquals(42, $result->signers[0]->signRequestId);
	}

	public function testBuildsChildDataWithVisibleElements(): void {
		$this->mockFileNode();

		$signRequest = new DbSignRequest();
		$signRequest->setId(100);
		$signRequest->setDisplayName('Signer A');
		$signRequest->setStatus(1);

		$otherSignRequest = new DbSignRequest();
		$otherSignRequest->setId(101);
		$otherSignRequest->setDisplayName('Signer without elements');
		$otherSignRequest->setStatus(1);
		$this->signRequestMapper->method('getByFileId')->willReturn([$otherSignRequest, $signRequest]);

		$element = new \OCA\Libresign\Db\FileElement();
		$element->setId(1);
		$element->setSignRequestId(100);
		$element->setPage(1);
		$element->setLlx(100);
		$element->setLly(100);
		$element->setUrx(200);
		$element->setUry(200);
		$element->setMetadata([]);

		$this->signRequestMapper->expects($this->once())->method('getVisibleElementsFromSigners')->willReturn([
			100 => [$element],
		]);

		$this->identifyMethodService->method('setIsRequest')->willReturnSelf();
		$this->identifyMethodService->method('getIdentifyMethodsFromSignRequestIds')->willReturn([]);

		$this->fileMapper->method('getTextOfStatus')->willReturn('pending');

		$this->fileElementService->method('formatVisibleElements')->willReturn([
			['signRequestId' => 100, 'type' => 'signature', 'coordinates' => ['page' => 1]],
		]);

		$assembler = $this->getService();

		$childFile = new DbFile();
		$childFile->setId(10);
		$childFile->setUuid('uuid-10');
		$childFile->setName('doc.pdf');
		$childFile->setStatus(1);
		$childFile->setNodeId(200);
		$childFile->setMetadata(['p' => 3, 'd' => [['h' => 800], ['h' => 800], ['h' => 800]]]);
		$childFile->setUserId('user1');

		$options = new FileResponseOptions();
		$options->showVisibleElements();

		$result = $assembler->buildEnvelopeChildData($childFile, $options);

		$this->assertIsArray($result->visibleElements);
		$this->assertNotEmpty($result->visibleElements);
		$this->assertCount(1, $result->visibleElements);
		$this->assertSame([], $result->signers[0]->visibleElements);
		$this->assertSame($result->visibleElements, $result->signers[1]->visibleElements);
	}

	public function testBuildsChildDataWithoutVisibleElementsWhenNotRequested(): void {
		$this->mockFileNode();

		$signRequest = new DbSignRequest();
		$signRequest->setId(50);
		$signRequest->setDisplayName('Signer B');
		$signRequest->setStatus(1);

		$this->signRequestMapper->method('getByFileId')->willReturn([$signRequest]);
		$this->signRequestMapper->expects($this->never())->method('getVisibleElementsFromSigners');

		$this->identifyMethodService->method('setIsRequest')->willReturnSelf();
		$this->identifyMethodService->method('getIdentifyMethodsFromSignRequestIds')->willReturn([]);

		$this->fileMapper->method('getTextOfStatus')->willReturn('pending');

		$assembler = $this->getService();

		$childFile = new DbFile();
		$childFile->setId(20);
		$childFile->setUuid('uuid-20');
		$childFile->setName('file.pdf');
		$childFile->setStatus(1);
		$childFile->setNodeId(300);
		$childFile->setMetadata(['p' => 1]);
		$childFile->setUserId('user1');

		$options = new FileResponseOptions();
		// NOT calling showVisibleElements()

		$result = $assembler->buildEnvelopeChildData($childFile, $options);

		$this->assertIsArray($result->visibleElements);
		$this->assertEmpty($result->visibleElements);
		$this->assertSame([], $result->signers[0]->visibleElements);
	}

	/**
	 * Regression #8388: a child document of an envelope follows the same
	 * rejection visibility rules as a single file.
	 */
	#[DataProvider('childRejectionViewers')]
	public function testAHiddenRejectionRedactsEveryUnsignedSignerOfTheChild(?string $viewerUid, array $expectedByIndex): void {
		$this->rejectionPolicy = ['enabled' => true, 'comment_mode' => 'optional', 'public_status' => false];
		$this->mockFileNode();

		$signers = [];
		$identifyMethods = [];
		foreach ([[1, 'rejecter', SignRequestStatus::REJECTED], [2, 'pending', SignRequestStatus::ABLE_TO_SIGN], [3, 'done', SignRequestStatus::SIGNED]] as [$id, $uid, $status]) {
			$signer = new DbSignRequest();
			$signer->setId($id);
			$signer->setDisplayName($uid);
			$signer->setStatusEnum($status);
			$signer->setSigningOrder($id);
			if ($status === SignRequestStatus::REJECTED) {
				$signer->setRejectedAt(new \DateTime('2026-09-13T12:00:00Z'));
			}
			if ($status === SignRequestStatus::SIGNED) {
				$signer->setSigned(new \DateTime('2026-09-12T12:00:00Z'));
			}
			$signers[] = $signer;
			$entity = new IdentifyMethod();
			$entity->setId($id + 100);
			$entity->setIdentifierKey(IdentifyMethodService::IDENTIFY_ACCOUNT);
			$entity->setIdentifierValue($uid);
			$identifyMethod = $this->createMock(IIdentifyMethod::class);
			$identifyMethod->method('getEntity')->willReturn($entity);
			$identifyMethods[$id] = [IdentifyMethodService::IDENTIFY_ACCOUNT => [$identifyMethod]];
		}
		$this->signRequestMapper->method('getByFileId')->willReturn($signers);
		$this->identifyMethodService->method('setIsRequest')->willReturnSelf();
		$this->identifyMethodService->method('getIdentifyMethodsFromSignRequestIds')->willReturn($identifyMethods);
		$this->fileMapper->method('getTextOfStatus')->willReturn('partial');

		$childFile = new DbFile();
		$childFile->setId(30);
		$childFile->setUuid('uuid-30');
		$childFile->setName('contract.pdf');
		$childFile->setStatus(1);
		$childFile->setNodeId(400);
		$childFile->setMetadata(['p' => 2]);
		$childFile->setUserId('requester');

		$options = new FileResponseOptions();
		if ($viewerUid !== null) {
			$viewer = $this->createMock(\OCP\IUser::class);
			$viewer->method('getUID')->willReturn($viewerUid);
			$viewer->method('getEMailAddress')->willReturn($viewerUid . '@example.com');
			$options->setMe($viewer);
		}

		$result = $this->getService()->buildEnvelopeChildData($childFile, $options);

		$presented = array_map(static fn (\stdClass $signer): array => [
			'displayStatus' => $signer->displayStatus,
			'status' => $signer->status ?? null,
			'statusText' => $signer->statusText,
			'rejection' => $signer->rejection ?? null,
		], $result->signers);
		$this->assertSame($expectedByIndex, $presented);
	}

	public static function childRejectionViewers(): array {
		$redacted = ['displayStatus' => 'not_signed', 'status' => null, 'statusText' => 'Not signed', 'rejection' => null];
		$signed = ['displayStatus' => 'signed', 'status' => 2, 'statusText' => 'Signed', 'rejection' => null];
		$pending = ['displayStatus' => 'ready_to_sign', 'status' => 1, 'statusText' => 'Ready to sign', 'rejection' => null];
		$rejected = ['displayStatus' => 'rejected', 'status' => 3, 'statusText' => 'Rejected', 'rejection' => ['rejectedAt' => '2026-09-13T12:00:00+00:00']];
		return [
			'anonymous' => [null, [$redacted, $redacted, $signed]],
			'another user' => ['someone', [$redacted, $redacted, $signed]],
			'the pending signer is redacted like the others' => ['pending', [$redacted, $redacted, $signed]],
			'the requester' => ['requester', [$rejected, $pending, $signed]],
			'the rejecter' => ['rejecter', [$rejected, $pending, $signed]],
		];
	}

	public function testBuildsChildDataWithMultipleSigners(): void {
		$this->mockFileNode();

		$signer1 = new DbSignRequest();
		$signer1->setId(1);
		$signer1->setDisplayName('Alice');
		$signer1->setStatus(1);
		$signer1->setSigningOrder(1);

		$signer2 = new DbSignRequest();
		$signer2->setId(2);
		$signer2->setDisplayName('Bob');
		$signer2->setStatus(2);
		$signer2->setSigningOrder(2);

		$this->signRequestMapper->method('getByFileId')->willReturn([$signer1, $signer2]);

		$this->identifyMethodService->method('setIsRequest')->willReturnSelf();
		$this->identifyMethodService->method('getIdentifyMethodsFromSignRequestIds')->willReturn([]);

		$this->fileMapper->method('getTextOfStatus')->willReturn('partial');

		$assembler = $this->getService();

		$childFile = new DbFile();
		$childFile->setId(30);
		$childFile->setUuid('uuid-30');
		$childFile->setName('contract.pdf');
		$childFile->setStatus(1);
		$childFile->setNodeId(400);
		$childFile->setMetadata(['p' => 2]);
		$childFile->setUserId('user1');

		$options = new FileResponseOptions();
		$result = $assembler->buildEnvelopeChildData($childFile, $options);

		$this->assertCount(2, $result->signers);
		$this->assertEquals(1, $result->signers[0]->signRequestId);
		$this->assertEquals(2, $result->signers[1]->signRequestId);
	}

	public function testBuildsChildDataIncludesIdentifyMethodsAndMetadata(): void {
		$this->mockFileNode();

		$signRequest = new DbSignRequest();
		$signRequest->setId(99);
		$signRequest->setDisplayName('Signer');
		$signRequest->setStatus(1);
		$signRequest->setMetadata(['certificate_info' => ['serialNumber' => '1234']]);

		$this->signRequestMapper->method('getByFileId')->willReturn([$signRequest]);

		$identifyEntity = new IdentifyMethod();
		$identifyEntity->setIdentifierKey(IdentifyMethodService::IDENTIFY_EMAIL);
		$identifyEntity->setIdentifierValue('signer@example.com');
		$identifyEntity->setMandatory(1);

		$identifyMethod = $this->createMock(IIdentifyMethod::class);
		$identifyMethod->method('getEntity')->willReturn($identifyEntity);

		$this->identifyMethodService->method('setIsRequest')->willReturnSelf();
		$this->identifyMethodService->method('getIdentifyMethodsFromSignRequestIds')->willReturn([
			99 => [
				IdentifyMethodService::IDENTIFY_EMAIL => [$identifyMethod],
			],
		]);

		$this->fileMapper->method('getTextOfStatus')->willReturn('pending');

		$assembler = $this->getService();

		$childFile = new DbFile();
		$childFile->setId(44);
		$childFile->setUuid('uuid-44');
		$childFile->setName('agreement.pdf');
		$childFile->setStatus(1);
		$childFile->setNodeId(500);
		$childFile->setMetadata(['p' => 1]);
		$childFile->setUserId('user1');

		$options = new FileResponseOptions();
		$result = $assembler->buildEnvelopeChildData($childFile, $options);

		$this->assertCount(1, $result->signers);
		$this->assertSame('email:signer@example.com', $result->signers[0]->uid);
		$this->assertSame(
			[
				[
					'method' => IdentifyMethodService::IDENTIFY_EMAIL,
					'value' => 'signer@example.com',
					'requirement' => 'required',
				],
			],
			$result->signers[0]->identifyMethods
		);
		$this->assertSame(['certificate_info' => ['serialNumber' => '1234']], $result->signers[0]->metadata);
	}

	#[DataProvider('provideChildMetadataContractScenarios')]
	public function testBuildEnvelopeChildNormalizesMetadataContract(
		string $filename,
		array $initialMetadata,
		int $expectedP,
		string $expectedExtension,
	): void {
		$this->mockFileNode();

		$this->signRequestMapper->method('getByFileId')->willReturn([]);
		$this->fileMapper->method('getTextOfStatus')->willReturn('pending');

		$childFile = new DbFile();
		$childFile->setId(1);
		$childFile->setUuid('uuid-1');
		$childFile->setName($filename);
		$childFile->setStatus(1);
		$childFile->setNodeId(100);
		$childFile->setUserId('user1');
		$childFile->setMetadata($initialMetadata);

		$options = new FileResponseOptions();
		$result = $this->getService()->buildEnvelopeChildData($childFile, $options);

		$this->assertIsArray($result->metadata);
		$this->assertSame($expectedP, $result->metadata['p']);
		$this->assertSame($expectedExtension, $result->metadata['extension']);
	}

	public static function provideChildMetadataContractScenarios(): array {
		return [
			'extension absent → derived from filename lowercased' => ['contract.PDF', [], 0, 'pdf'],
			'filename without extension → pdf fallback' => ['contract', [], 0, 'pdf'],
			'empty extension in metadata → derived from filename' => ['doc.pdf', ['extension' => ''], 0, 'pdf'],
			'non-string extension in metadata → derived from filename' => ['doc.pdf', ['extension' => 42], 0, 'pdf'],
			'extension already set → preserved' => ['renamed.PDF', ['extension' => 'docx'], 0, 'docx'],
			'p in metadata preserved as totalPages' => ['doc.pdf', ['p' => 5], 5, 'pdf'],
		];
	}
}
