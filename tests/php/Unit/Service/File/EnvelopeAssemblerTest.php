<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\File;

use OCA\Libresign\Db\File as DbFile;
use OCA\Libresign\Db\SignRequest as DbSignRequest;
use OCA\Libresign\Service\File\EnvelopeAssembler;
use OCA\Libresign\Service\File\FileResponseOptions;
use OCA\Libresign\Service\IdentifyMethodService;
use OCP\Files\IRootFolder;
use Psr\Log\NullLogger;

final class EnvelopeAssemblerTest extends \OCA\Libresign\Tests\Unit\TestCase {
	public function testBuildsChildDataWithoutCertificateChain(): void {
		$signRequest = new DbSignRequest();
		$signRequest->setId(42);
		$signRequest->setSigned(null);
		$signRequest->setDisplayName('Alice');
		$signRequest->setStatus(1);

		$signRequestMapper = $this->createMock(\OCA\Libresign\Db\SignRequestMapper::class);
		$signRequestMapper->method('getByFileId')->willReturn([$signRequest]);

		$identify = $this->createMock(IdentifyMethodService::class);
		$identify->method('setIsRequest')->willReturnSelf();
		$identify->method('getIdentifyMethodsFromSignRequestId')->willReturn([]);

		$fileMapper = $this->createMock(\OCA\Libresign\Db\FileMapper::class);
		$fileMapper->method('getTextOfStatus')->willReturn('status-text');

		$root = $this->createMock(IRootFolder::class);

		$signersLoader = $this->createMock(\OCA\Libresign\Service\File\SignersLoader::class);
		$signersLoader->expects($this->never())->method('loadSignersFromCertData');

		$assembler = new EnvelopeAssembler(
			$signRequestMapper,
			$identify,
			$fileMapper,
			$root,
			$signersLoader,
			null,
			$this->createMock(\OCA\Libresign\Handler\SignEngine\Pkcs12Handler::class),
			new NullLogger()
		);

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
}
