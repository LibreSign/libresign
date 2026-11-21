<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\OrganizationSeal;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Handler\DocMdpHandler;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Service\OrganizationSeal\OrganizationSealService;
use OCA\Libresign\Service\OrganizationSeal\OrganizationSealStore;
use OCA\Libresign\Service\Policy\Provider\OrganizationSeals\OrganizationSealsPolicy;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\File;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

final class OrganizationSealServiceTest extends TestCase {
	private FileMapper&MockObject $fileMapper;
	private OrganizationSealStore&MockObject $sealStore;
	private DocMdpHandler&MockObject $docMdpHandler;
	private ITimeFactory&MockObject $timeFactory;
	private LoggerInterface&MockObject $logger;
	/** @var list<Pkcs12Handler&MockObject> */
	private array $engines = [];
	/** @var list<string> */
	private array $signedAs = [];

	public function setUp(): void {
		parent::setUp();
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->sealStore = $this->createMock(OrganizationSealStore::class);
		$this->docMdpHandler = $this->createMock(DocMdpHandler::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->timeFactory->method('getDateTime')->willReturn(new \DateTime('2026-01-01T00:00:00Z'));
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	private function getService(): OrganizationSealService&MockObject {
		$service = $this->getMockBuilder(OrganizationSealService::class)
			->setConstructorArgs([
				$this->fileMapper,
				$this->sealStore,
				$this->createMock(Pkcs12Handler::class),
				$this->docMdpHandler,
				$this->timeFactory,
				$this->logger,
			])
			->onlyMethods(['getEngine'])
			->getMock();
		$service->method('getEngine')->willReturnCallback(function (): Pkcs12Handler {
			$engine = $this->createMock(Pkcs12Handler::class);
			$engine->expects($this->once())->method('sign');
			$engine->method('setSignatureParams')->willReturnCallback(function (array $params) use ($engine): Pkcs12Handler {
				$this->signedAs[] = $params['SignerCommonName'];
				return $engine;
			});
			$this->engines[] = $engine;
			return $engine;
		});
		return $service;
	}

	private function requestWithSeals(array $seals): FileEntity {
		$file = new FileEntity();
		$file->setUuid('file-uuid');
		$file->setMetadata([
			'policy_snapshot' => [
				OrganizationSealsPolicy::KEY => [
					'effectiveValue' => json_encode($seals),
					'sourceScope' => 'request',
				],
			],
		]);
		return $file;
	}

	private function signedFile(): File&MockObject {
		$signedFile = $this->createMock(File::class);
		$signedFile->method('fopen')->willReturnCallback(static fn () => fopen('php://memory', 'r+'));
		return $signedFile;
	}

	private function credentials(string $subject): array {
		return ['pfx' => 'pfx-of-' . $subject, 'password' => 'password-of-' . $subject, 'subject' => $subject];
	}

	public function testRequestWithoutSealsIsLeftUnchanged(): void {
		$file = new FileEntity();
		$file->setMetadata(['extension' => 'pdf']);
		$this->sealStore->expects($this->never())->method('getCredentials');

		$service = $this->getService();
		$this->assertSame([], $service->getRequestedSeals($file));
		$this->assertFalse($service->apply($file, $this->signedFile()));
		$this->assertSame(['extension' => 'pdf'], $file->getMetadata());
		$this->assertSame([], $this->engines);
	}

	public function testEverySealIsAppliedInOrder(): void {
		$file = $this->requestWithSeals(['first', 'second']);
		$signedFile = $this->signedFile();
		$this->sealStore->method('getCredentials')->willReturnCallback(fn (string $id): array => $this->credentials($id . ' seal'));
		$this->docMdpHandler->method('allowsAdditionalSignatures')->willReturn(true);

		$this->assertTrue($this->getService()->apply($file, $signedFile));

		$this->assertSame(['first seal', 'second seal'], $this->signedAs);
		$this->assertSame([
			'first' => ['status' => 'sealed', 'at' => '2026-01-01T00:00:00+00:00'],
			'second' => ['status' => 'sealed', 'at' => '2026-01-01T00:00:00+00:00'],
		], $file->getMetadata()[OrganizationSealService::RESULT_METADATA_KEY]);
	}

	public function testTheSealEngineGetsTheSealCredentialsWithoutVisibleElements(): void {
		$file = $this->requestWithSeals(['first']);
		$signedFile = $this->signedFile();
		$this->sealStore->method('getCredentials')->willReturn($this->credentials('Example Seal'));
		$this->docMdpHandler->method('allowsAdditionalSignatures')->willReturn(true);

		$engine = $this->createMock(Pkcs12Handler::class);
		$engine->expects($this->once())->method('setInputFile')->with($this->identicalTo($signedFile))->willReturnSelf();
		$engine->expects($this->once())->method('setCertificate')->with('pfx-of-Example Seal')->willReturnSelf();
		$engine->expects($this->once())->method('setPassword')->with('password-of-Example Seal')->willReturnSelf();
		$engine->expects($this->once())->method('setVisibleElements')->with([])->willReturnSelf();
		$engine->expects($this->once())->method('setSignatureParams')->with(['SignerCommonName' => 'Example Seal'])->willReturnSelf();
		$engine->expects($this->once())->method('sign')->willReturn($signedFile);

		$service = $this->getMockBuilder(OrganizationSealService::class)
			->setConstructorArgs([$this->fileMapper, $this->sealStore, $engine, $this->docMdpHandler, $this->timeFactory, $this->logger])
			->onlyMethods(['getEngine'])
			->getMock();
		$service->method('getEngine')->willReturn($engine);

		$this->assertTrue($service->apply($file, $signedFile));
	}

	public function testFailuresAreRecordedAndTheOtherSealsStillApplied(): void {
		$file = $this->requestWithSeals(['missing', 'broken', 'good']);
		$this->sealStore->method('getCredentials')->willReturnCallback(fn (string $id): ?array => $id === 'missing' ? null : $this->credentials($id));
		$this->docMdpHandler->method('allowsAdditionalSignatures')->willReturn(true);
		$this->logger->expects($this->once())->method('warning');
		$this->logger->expects($this->once())->method('error');

		$service = $this->getMockBuilder(OrganizationSealService::class)
			->setConstructorArgs([$this->fileMapper, $this->sealStore, $this->createMock(Pkcs12Handler::class), $this->docMdpHandler, $this->timeFactory, $this->logger])
			->onlyMethods(['getEngine'])
			->getMock();
		$broken = $this->createMock(Pkcs12Handler::class);
		$broken->method('sign')->willThrowException(new \RuntimeException('engine failure'));
		$good = $this->createMock(Pkcs12Handler::class);
		$good->expects($this->once())->method('sign');
		$service->method('getEngine')->willReturnOnConsecutiveCalls($broken, $good);

		$this->assertTrue($service->apply($file, $this->signedFile()));

		$results = $file->getMetadata()[OrganizationSealService::RESULT_METADATA_KEY];
		$this->assertSame(['status' => 'failed', 'error' => OrganizationSealService::ERROR_NOT_FOUND], array_diff_key($results['missing'], ['at' => true]));
		$this->assertSame(['status' => 'failed', 'error' => OrganizationSealService::ERROR_SIGN], array_diff_key($results['broken'], ['at' => true]));
		$this->assertSame('sealed', $results['good']['status']);
	}

	public function testNothingIsSignedWhenTheDocumentForbidsChanges(): void {
		$file = $this->requestWithSeals(['first']);
		$this->sealStore->method('getCredentials')->willReturn($this->credentials('Example Seal'));
		$this->docMdpHandler->method('allowsAdditionalSignatures')->willReturn(false);

		$service = $this->getService();
		$this->assertFalse($service->apply($file, $this->signedFile()));

		$this->assertSame([], $this->engines);
		$this->assertSame(
			OrganizationSealService::ERROR_DOCMDP,
			$file->getMetadata()[OrganizationSealService::RESULT_METADATA_KEY]['first']['error'],
		);
	}

	public function testDocumentOfAnEnvelopeUsesTheSealsOfTheEnvelope(): void {
		$envelope = $this->requestWithSeals(['first']);
		$envelope->setId(10);
		$envelope->setNodeType('envelope');
		$this->fileMapper->method('getById')->with(10)->willReturn($envelope);
		$child = new FileEntity();
		$child->setParentFileId(10);

		$this->assertSame(['first'], $this->getService()->getRequestedSeals($child));
	}
}
