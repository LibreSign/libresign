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
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Service\File\EnvelopeAssembler;
use OCA\Libresign\Service\File\FileResponseOptions;
use OCA\Libresign\Service\File\SignersLoader;
use OCA\Libresign\Service\FileElementService;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethodService;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;

final class EnvelopeAssemblerTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private SignRequestMapper&MockObject $signRequestMapper;
	private IdentifyMethodService&MockObject $identifyMethodService;
	private FileMapper&MockObject $fileMapper;
	private IRootFolder&MockObject $root;
	private SignersLoader&MockObject $signersLoader;
	private Pkcs12Handler&MockObject $pkcs12Handler;
	private FileElementService&MockObject $fileElementService;

	public function setUp(): void {
		parent::setUp();
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->root = $this->createMock(IRootFolder::class);
		$this->signersLoader = $this->createMock(SignersLoader::class);
		$this->pkcs12Handler = $this->createMock(Pkcs12Handler::class);
		$this->fileElementService = $this->createMock(FileElementService::class);
	}

	private function getService(): EnvelopeAssembler {
		return new EnvelopeAssembler(
			$this->signRequestMapper,
			$this->identifyMethodService,
			$this->fileMapper,
			$this->root,
			$this->signersLoader,
			null,
			$this->pkcs12Handler,
			new NullLogger(),
			$this->fileElementService
		);
	}

	private function mockFileNode(): void {
		$folder = $this->createMock(Folder::class);
		$fileNode = $this->createMock(File::class);
		$folder->method('getFirstNodeById')->willReturn($fileNode);
		$this->root->method('getUserFolder')->willReturn($folder);
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

		$this->signRequestMapper->method('getByFileId')->willReturn([$signRequest]);

		$element = new \OCA\Libresign\Db\FileElement();
		$element->setId(1);
		$element->setSignRequestId(100);
		$element->setPage(1);
		$element->setLlx(100);
		$element->setLly(100);
		$element->setUrx(200);
		$element->setUry(200);
		$element->setMetadata([]);

		$this->signRequestMapper->method('getVisibleElementsFromSigners')->willReturn([
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
					'mandatory' => 1,
				],
			],
			$result->signers[0]->identifyMethods
		);
		$this->assertSame(['certificate_info' => ['serialNumber' => '1234']], $result->signers[0]->metadata);
	}

	private function createMockFileElement(
		int $id,
		int $signRequestId,
		int $page,
		int $llx,
		int $lly,
		int $urx,
		int $ury,
	): \OCA\Libresign\Db\FileElement {
		$element = $this->createMock(\OCA\Libresign\Db\FileElement::class);
		$element->method('getId')->willReturn($id);
		$element->method('getSignRequestId')->willReturn($signRequestId);
		$element->method('getPage')->willReturn($page);
		$element->method('getLlx')->willReturn($llx);
		$element->method('getLly')->willReturn($lly);
		$element->method('getUrx')->willReturn($urx);
		$element->method('getUry')->willReturn($ury);
		$element->method('getMetadata')->willReturn([]);
		return $element;
	}
}
