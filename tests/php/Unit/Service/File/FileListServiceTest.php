<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\File;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\SignatureFlow;
use OCA\Libresign\Service\File\FileListService;
use OCA\Libresign\Service\FileElementService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\Files\File as NodeFile;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class FileListServiceTest extends TestCase {
	private SignRequestMapper&MockObject $signRequestMapper;
	private IdentifyMethodService&MockObject $identifyMethodService;
	private FileElementService&MockObject $fileElementService;
	private FileMapper&MockObject $fileMapper;
	private IURLGenerator&MockObject $urlGenerator;
	private IAppConfig&MockObject $appConfig;
	private IL10N&MockObject $l10n;
	private IUserManager&MockObject $userManager;
	private IRootFolder&MockObject $rootFolder;
	private Folder&MockObject $userFolder;
	private IUser&MockObject $user;

	public function setUp(): void {
		parent::setUp();

		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$this->fileElementService = $this->createMock(FileElementService::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->userFolder = $this->createMock(Folder::class);

		$this->user = $this->createMock(IUser::class);
		$this->rootFolder->method('getUserFolder')->willReturn($this->userFolder);
	}

	private function getService(): FileListService {
		return new FileListService(
			$this->signRequestMapper,
			$this->identifyMethodService,
			$this->fileElementService,
			$this->fileMapper,
			$this->urlGenerator,
			$this->appConfig,
			$this->l10n,
			$this->userManager,
			$this->rootFolder,
		);
	}

	#[DataProvider('provideNodeTypeScenarios')]
	public function testFormatSingleFileStructureByNodeType(
		string $nodeType,
		array $metadata,
		int $expectedFilesCount,
		bool $expectFilesArrayEmpty,
	): void {
		$file = self::createFileEntity(1, $nodeType, 'document.pdf', $metadata);

		$this->signRequestMapper->method('getByMultipleFileId')->willReturn([]);
		$this->signRequestMapper->method('getIdentifyMethodsFromSigners')->willReturn([]);
		$this->signRequestMapper->method('getVisibleElementsFromSigners')->willReturn([]);

		$service = $this->getService();
		$result = $service->formatSingleFile($this->user, $file);

		$this->assertArrayHasKey('nodeType', $result);
		$this->assertEquals($nodeType, $result['nodeType']);
		$this->assertEquals($expectedFilesCount, $result['filesCount']);
		$this->assertArrayHasKey('files', $result);

		if ($expectFilesArrayEmpty) {
			$this->assertEmpty($result['files']);
		} else {
			$this->assertNotEmpty($result['files']);
			$this->assertEquals($file->getId(), $result['files'][0]['fileId']);
		}
	}

	public static function provideNodeTypeScenarios(): array {
		return [
			'simple file has single file in array' => ['file', [], 1, false],
			'envelope without metadata has zero count' => ['envelope', [], 0, true],
			'envelope with metadata reflects count' => ['envelope', ['filesCount' => 3], 3, true],
		];
	}

	public function testIdFieldUsesNodeIdInsteadOfFileId(): void {
		$file = self::createFileEntity(123, 'file', 'doc.pdf');
		$file->setNodeId(456);

		$this->signRequestMapper->method('getByMultipleFileId')->willReturn([]);
		$this->signRequestMapper->method('getIdentifyMethodsFromSigners')->willReturn([]);
		$this->signRequestMapper->method('getVisibleElementsFromSigners')->willReturn([]);

		$service = $this->getService();
		$result = $service->formatSingleFile($this->user, $file);

		$this->assertEquals(123, $result['id']);
		$this->assertEquals(456, $result['nodeId']);
		$this->assertArrayNotHasKey('fileId', $result);
	}

	#[DataProvider('provideSignatureFlowScenarios')]
	public function testSignatureFlowConvertedToEnum(int $numericFlow, string $expectedEnumValue): void {
		$file = self::createFileEntity(1, 'file', 'doc.pdf');
		$file->setSignatureFlow($numericFlow);

		$this->signRequestMapper->method('getByMultipleFileId')->willReturn([]);
		$this->signRequestMapper->method('getIdentifyMethodsFromSigners')->willReturn([]);
		$this->signRequestMapper->method('getVisibleElementsFromSigners')->willReturn([]);

		$service = $this->getService();
		$result = $service->formatSingleFile($this->user, $file);

		$this->assertEquals($expectedEnumValue, $result['signatureFlow']);
	}

	public static function provideSignatureFlowScenarios(): array {
		return [
			'none' => [0, 'none'],
			'parallel' => [1, 'parallel'],
			'ordered numeric' => [2, 'ordered_numeric'],
		];
	}

	public function testFileWithoutSignersUsesStatusMapping(): void {
		$file = self::createFileEntity(1, 'file', 'doc.pdf');
		$file->setStatus(0); // DRAFT status

		$this->signRequestMapper->method('getByMultipleFileId')->willReturn([]);
		$this->signRequestMapper->method('getIdentifyMethodsFromSigners')->willReturn([]);
		$this->signRequestMapper->method('getVisibleElementsFromSigners')->willReturn([]);

		$this->fileMapper->method('getTextOfStatus')->with(0)->willReturn('Draft');

		$service = $this->getService();
		$result = $service->formatSingleFile($this->user, $file);

		$this->assertEmpty($result['signers']);
		$this->assertEquals('Draft', $result['statusText']);
		$this->assertEquals(0, $result['signersCount']);
		$this->assertEmpty($result['visibleElements']);
	}

	public function testDocMdpLevelIncludedInSingleFileResponse(): void {
		$file = self::createFileEntity(1, 'file', 'doc.pdf');
		$file->setDocmdpLevel(1);

		$this->signRequestMapper->method('getByMultipleFileId')->willReturn([]);
		$this->signRequestMapper->method('getIdentifyMethodsFromSigners')->willReturn([]);
		$this->signRequestMapper->method('getVisibleElementsFromSigners')->willReturn([]);

		$service = $this->getService();
		$result = $service->formatSingleFile($this->user, $file);

		$this->assertEquals(1, $result['docmdpLevel']);
	}

	public function testDraftFileWithSignersHasConsistentStatusText(): void {
		$file = self::createFileEntity(1, 'file', 'doc.pdf');
		$file->setStatus(0); // DRAFT status

		$signer = $this->createSigner(100, 1);

		$identifyMethod = $this->createIdentifyMethod(IdentifyMethodService::IDENTIFY_ACCOUNT, 'test-user');

		$this->user->method('getUID')->willReturn('other-user');

		$this->signRequestMapper->method('getByMultipleFileId')->willReturn([$signer]);
		$this->signRequestMapper->method('getIdentifyMethodsFromSigners')->willReturn([100 => [$identifyMethod]]);
		$this->signRequestMapper->method('getVisibleElementsFromSigners')->willReturn([]);
		$this->signRequestMapper->method('getTextOfSignerStatus')->willReturn('pending');

		$this->fileMapper->method('getTextOfStatus')->with(0)->willReturn('Draft');

		$service = $this->getService();
		$result = $service->formatSingleFile($this->user, $file);

		$this->assertNotEmpty($result['signers']);
		$this->assertEquals('Draft', $result['statusText']);
		$this->assertEquals(1, $result['signersCount']);
	}

	public function testStatusTextIsConsistentBetweenFormattingMethods(): void {
		$file = self::createFileEntity(1, 'file', 'doc.pdf');
		$file->setStatus(0); // DRAFT status

		$signer = $this->createSigner(100, 1);

		$identifyMethod = $this->createIdentifyMethod(IdentifyMethodService::IDENTIFY_ACCOUNT, 'test-user');

		$this->signRequestMapper->method('getByMultipleFileId')->willReturn([$signer]);
		$this->signRequestMapper->method('getIdentifyMethodsFromSigners')->willReturn([100 => [$identifyMethod]]);
		$this->signRequestMapper->method('getVisibleElementsFromSigners')->willReturn([]);
		$this->signRequestMapper->method('getTextOfSignerStatus')->willReturn('pending');

		$this->fileMapper->method('getTextOfStatus')->with(0)->willReturn('Draft');

		$service = $this->getService();

		$result1 = $service->formatSingleFile($this->user, $file);

		$result2 = $service->formatFileWithChildren($file, [], $this->user);

		$this->assertEquals('Draft', $result1['statusText']);
		$this->assertEquals('Draft', $result2['statusText']);
	}

	#[DataProvider('provideSignersCountValues')]
	public function testSignersCountIsAccurateWithVariousQuantities(int $signerCount): void {
		$file = self::createFileEntity(1, 'file', 'doc.pdf');

		$signers = [];
		for ($i = 0; $i < $signerCount; $i++) {
			$signers[] = $this->createSigner(100 + $i, 1, $i + 1);
		}

		$this->signRequestMapper->method('getByMultipleFileId')->willReturn($signers);
		$this->signRequestMapper->method('getIdentifyMethodsFromSigners')->willReturn([]);
		$this->signRequestMapper->method('getVisibleElementsFromSigners')->willReturn([]);

		$service = $this->getService();
		$result = $service->formatSingleFile($this->user, $file);

		$this->assertCount($signerCount, $result['signers']);
		$this->assertEquals($signerCount, $result['signersCount']);
	}

	public static function provideSignersCountValues(): array {
		return [
			'no signers' => [0],
			'single signer' => [1],
			'two signers' => [2],
			'three signers' => [3],
			'five signers' => [5],
			'ten signers' => [10],
		];
	}

	public function testSignersAreSortedBySigningOrderThenById(): void {
		$file = self::createFileEntity(1, 'file', 'doc.pdf');

		$signers = [
			$this->createSigner(100, 1, 2), // Last by order
			$this->createSigner(101, 1, 1), // First by order, first by ID
			$this->createSigner(102, 1, 1), // First by order, second by ID
		];

		$this->signRequestMapper->method('getByMultipleFileId')->willReturn($signers);
		$this->signRequestMapper->method('getIdentifyMethodsFromSigners')->willReturn([]);
		$this->signRequestMapper->method('getVisibleElementsFromSigners')->willReturn([]);
		$this->signRequestMapper->method('getTextOfSignerStatus')->willReturn('pending');

		$service = $this->getService();
		$result = $service->formatSingleFile($this->user, $file);

		$this->assertCount(3, $result['signers']);
		$this->assertEquals(101, $result['signers'][0]['signRequestId']);
		$this->assertEquals(102, $result['signers'][1]['signRequestId']);
		$this->assertEquals(100, $result['signers'][2]['signRequestId']);
	}

	public function testVisibleElementsAreMergedFromMultipleSigners(): void {
		$file = self::createFileEntity(1, 'file', 'doc.pdf', ['d' => [['h' => 800]]]);

		$signers = [
			$this->createSigner(100, 1),
			$this->createSigner(101, 1),
		];

		$element1 = $this->createFileElement(100, 1);
		$element2 = $this->createFileElement(101, 1);

		$this->signRequestMapper->method('getByMultipleFileId')->willReturn($signers);
		$this->signRequestMapper->method('getIdentifyMethodsFromSigners')->willReturn([]);
		$this->signRequestMapper->method('getVisibleElementsFromSigners')->willReturn([
			100 => [$element1],
			101 => [$element2],
		]);
		$this->signRequestMapper->method('getTextOfSignerStatus')->willReturn('pending');

		$this->fileElementService->method('formatVisibleElements')->willReturnCallback(
			fn ($elements) => array_map(fn ($el) => ['signRequestId' => $el->getSignRequestId()], $elements)
		);

		$service = $this->getService();
		$result = $service->formatSingleFile($this->user, $file);

		$this->assertCount(2, $result['visibleElements']);
	}

	public function testSignerIdentifiedAsCurrentUserHasUrlField(): void {
		$file = self::createFileEntity(1, 'file', 'doc.pdf');

		$signer = $this->createSigner(100, 1);
		$signer->setUuid('uuid-signer');

		$identifyMethod = $this->createIdentifyMethod(
			IdentifyMethodService::IDENTIFY_ACCOUNT,
			'test-user'
		);

		$this->user->method('getUID')->willReturn('test-user');

		$this->signRequestMapper->method('getByMultipleFileId')->willReturn([$signer]);
		$this->signRequestMapper->method('getIdentifyMethodsFromSigners')->willReturn([100 => [$identifyMethod]]);
		$this->signRequestMapper->method('getVisibleElementsFromSigners')->willReturn([]);
		$this->signRequestMapper->method('getTextOfSignerStatus')->willReturn('pending');

		$this->identifyMethodService->method('setCurrentIdentifyMethod')->willReturnSelf();
		$this->identifyMethodService->method('setIsRequest')->willReturnSelf();
		$mockIdentifyMethod = $this->createMock(\OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod::class);
		$mockIdentifyMethod->method('getSignatureMethods')->willReturn([]);
		$this->identifyMethodService->method('getInstanceOfIdentifyMethod')->willReturn($mockIdentifyMethod);

		$this->urlGenerator->method('linkToRoute')->willReturn('https://example.com/sign?uuid=uuid-signer');

		$service = $this->getService();
		$result = $service->formatSingleFile($this->user, $file);

		$this->assertArrayHasKey('url', $result);
		$this->assertStringContainsString('uuid-signer', $result['url']);
	}

	#[DataProvider('provideSignerUuidExposureScenarios')]
	public function testSignRequestUuidIsScopedToCurrentSigner(
		string $currentUserId,
		bool $expectSignerScopedUuid,
	): void {
		$file = self::createFileEntity(1, 'file', 'doc.pdf');

		$signer = $this->createSigner(100, 1);
		$signer->setUuid('sign-request-uuid');

		$identifyMethod = $this->createIdentifyMethod(
			IdentifyMethodService::IDENTIFY_ACCOUNT,
			'signer-user'
		);

		$this->user->method('getUID')->willReturn($currentUserId);

		$this->signRequestMapper->method('getByMultipleFileId')->willReturn([$signer]);
		$this->signRequestMapper->method('getIdentifyMethodsFromSigners')->willReturn([100 => [$identifyMethod]]);
		$this->signRequestMapper->method('getVisibleElementsFromSigners')->willReturn([]);
		$this->signRequestMapper->method('getTextOfSignerStatus')->willReturn('pending');

		if ($expectSignerScopedUuid) {
			$this->mockSignatureMethodsResolution();
			$this->urlGenerator->method('linkToRoute')->willReturn('https://example.com/sign?uuid=sign-request-uuid');
		}

		$service = $this->getService();
		$result = $service->formatSingleFile($this->user, $file);

		$this->assertArrayNotHasKey('signUuid', $result);
		$this->assertCount(1, $result['signers']);
		$this->assertArrayNotHasKey('sign_uuid', $result['signers'][0]);

		if ($expectSignerScopedUuid) {
			$this->assertSame('sign-request-uuid', $result['signers'][0]['sign_request_uuid']);
			$this->assertArrayHasKey('url', $result);
			$this->assertStringContainsString('sign-request-uuid', $result['url']);
			return;
		}

		$this->assertArrayNotHasKey('sign_request_uuid', $result['signers'][0]);
	}

	public static function provideSignerUuidExposureScenarios(): array {
		return [
			'current signer gets signer-scoped sign request uuid' => ['signer-user', true],
			'other viewer does not get signer-scoped sign request uuid' => ['other-user', false],
		];
	}

	public function testDetailedFileDoesNotExposeSignRequestUuidAtRoot(): void {
		$file = self::createFileEntity(1, 'file', 'doc.pdf');

		$signer = $this->createSigner(100, 1);
		$signer->setUuid('sign-request-uuid');

		$identifyMethod = $this->createIdentifyMethod(
			IdentifyMethodService::IDENTIFY_ACCOUNT,
			'signer-user'
		);

		$this->user->method('getUID')->willReturn('signer-user');

		$this->signRequestMapper->method('getByFileId')->willReturn([$signer]);
		$this->signRequestMapper->method('getIdentifyMethodsFromSigners')->willReturn([100 => [$identifyMethod]]);
		$this->signRequestMapper->method('getVisibleElementsFromSigners')->willReturn([]);
		$this->signRequestMapper->method('getTextOfSignerStatus')->willReturn('pending');

		$this->mockSignatureMethodsResolution();
		$this->urlGenerator->method('linkToRoute')->willReturn('https://example.com/sign?uuid=sign-request-uuid');

		$service = $this->getService();
		$result = $service->formatFileWithChildren($file, [], $this->user);

		$this->assertArrayNotHasKey('signUuid', $result);
		$this->assertSame('sign-request-uuid', $result['signers'][0]['sign_request_uuid']);
		$this->assertArrayNotHasKey('sign_uuid', $result['signers'][0]);
		$this->assertArrayHasKey('url', $result);
		$this->assertStringContainsString('sign-request-uuid', $result['url']);
	}

	public function testSummaryListDoesNotExposeSignRequestUuidAtRoot(): void {
		$file = self::createFileEntity(1, 'file', 'doc.pdf');

		$signer = $this->createSigner(100, 1);
		$signer->setUuid('sign-request-uuid');

		$identifyMethod = $this->createIdentifyMethod(
			IdentifyMethodService::IDENTIFY_ACCOUNT,
			'signer-user'
		);

		$this->user->method('getUID')->willReturn('signer-user');
		$this->appConfig->method('getValueInt')->willReturn(100);
		$this->signRequestMapper->method('getFilesAssociatedFilesWithMe')->willReturn([
			'data' => [$file],
			'pagination' => new class {
				public function setRouteName(string $routeName): void {
				}

				public function getPagination(int $page, int $length, array $filter): array {
					return [
						'total' => 1,
						'current' => null,
						'next' => null,
						'prev' => null,
						'last' => null,
						'first' => null,
					];
				}
			},
		]);
		$this->signRequestMapper->method('getByMultipleFileId')->willReturn([$signer]);
		$this->signRequestMapper->method('getIdentifyMethodsFromSigners')->willReturn([100 => [$identifyMethod]]);
		$this->fileMapper->method('getTextOfStatus')->willReturn('Status text');

		$service = $this->getService();
		$result = $service->listAssociatedFilesOfSignFlow($this->user, 1, 100, [], [], false);

		$this->assertCount(1, $result['data']);
		$this->assertArrayNotHasKey('signUuid', $result['data'][0]);
	}

	public function testRequestedByIncludesUserDisplayName(): void {
		$file = self::createFileEntity(1, 'file', 'doc.pdf');
		$file->setUserId('creator-user');

		$mockUser = $this->createMock(IUser::class);
		$mockUser->method('getDisplayName')->willReturn('Creator Name');

		$this->userManager->method('get')->with('creator-user')->willReturn($mockUser);

		$this->signRequestMapper->method('getByMultipleFileId')->willReturn([]);
		$this->signRequestMapper->method('getIdentifyMethodsFromSigners')->willReturn([]);
		$this->signRequestMapper->method('getVisibleElementsFromSigners')->willReturn([]);

		$service = $this->getService();
		$result = $service->formatSingleFile($this->user, $file);

		$this->assertEquals('creator-user', $result['requested_by']['userId']);
		$this->assertEquals('Creator Name', $result['requested_by']['displayName']);
	}

	public function testCreatedAtIsFormattedInUTC(): void {
		$file = self::createFileEntity(1, 'file', 'doc.pdf');
		$file->setCreatedAt(new \DateTime('2025-01-15 10:30:00', new \DateTimeZone('America/New_York')));

		$this->signRequestMapper->method('getByMultipleFileId')->willReturn([]);
		$this->signRequestMapper->method('getIdentifyMethodsFromSigners')->willReturn([]);
		$this->signRequestMapper->method('getVisibleElementsFromSigners')->willReturn([]);

		$service = $this->getService();
		$result = $service->formatSingleFile($this->user, $file);

		$this->assertStringContainsString('+00:00', $result['created_at']);
	}

	public function testSignerDisplayNamePreservedWhenIdentifyMethodIsAccount(): void {
		$file = self::createFileEntity(1, 'file', 'doc.pdf');

		$signer = $this->createSigner(100, 1);
		$signer->setDisplayName('Admin Display');

		$identifyMethod = $this->createIdentifyMethod(
			IdentifyMethodService::IDENTIFY_ACCOUNT,
			'admin'
		);

		$mockCreatorUser = $this->createMock(IUser::class);
		$mockCreatorUser->method('getDisplayName')->willReturn('Creator Display');
		$mockAdminUser = $this->createMock(IUser::class);
		$mockAdminUser->method('getDisplayName')->willReturn('Admin Name');

		$this->userManager->method('get')->willReturnMap([
			['creator123', $mockCreatorUser],
			['admin', $mockAdminUser],
		]);

		$this->signRequestMapper->method('getByMultipleFileId')->willReturn([$signer]);
		$this->signRequestMapper->method('getIdentifyMethodsFromSigners')->willReturn([100 => [$identifyMethod]]);
		$this->signRequestMapper->method('getVisibleElementsFromSigners')->willReturn([]);
		$this->signRequestMapper->method('getTextOfSignerStatus')->willReturn('pending');

		$service = $this->getService();
		$result = $service->formatSingleFile($this->user, $file);

		$this->assertSame('Admin Display', $result['signers'][0]['displayName']);
	}

	public function testSignerDisplayNameFallsBackToUserWhenEmpty(): void {
		$file = self::createFileEntity(1, 'file', 'doc.pdf');

		$signer = $this->createSigner(100, 1);
		$signer->setDisplayName('');

		$identifyMethod = $this->createIdentifyMethod(
			IdentifyMethodService::IDENTIFY_ACCOUNT,
			'admin'
		);

		$mockCreatorUser = $this->createMock(IUser::class);
		$mockCreatorUser->method('getDisplayName')->willReturn('Creator Display');
		$mockAdminUser = $this->createMock(IUser::class);
		$mockAdminUser->method('getDisplayName')->willReturn('Admin Name');

		$this->userManager->method('get')->willReturnMap([
			['creator123', $mockCreatorUser],
			['admin', $mockAdminUser],
		]);

		$this->signRequestMapper->method('getByMultipleFileId')->willReturn([$signer]);
		$this->signRequestMapper->method('getIdentifyMethodsFromSigners')->willReturn([100 => [$identifyMethod]]);
		$this->signRequestMapper->method('getVisibleElementsFromSigners')->willReturn([]);
		$this->signRequestMapper->method('getTextOfSignerStatus')->willReturn('pending');

		$service = $this->getService();
		$result = $service->formatSingleFile($this->user, $file);

		$this->assertSame('Admin Name', $result['signers'][0]['displayName']);
	}

	public function testSignerDisplayNameFallsBackWhenMatchesAccountIdentifier(): void {
		$file = self::createFileEntity(1, 'file', 'doc.pdf');

		$signer = $this->createSigner(100, 1);
		$signer->setDisplayName('admin');

		$identifyMethod = $this->createIdentifyMethod(
			IdentifyMethodService::IDENTIFY_ACCOUNT,
			'admin'
		);

		$mockCreatorUser = $this->createMock(IUser::class);
		$mockCreatorUser->method('getDisplayName')->willReturn('Creator Display');
		$mockAdminUser = $this->createMock(IUser::class);
		$mockAdminUser->method('getDisplayName')->willReturn('Admin Name');

		$this->userManager->method('get')->willReturnMap([
			['creator123', $mockCreatorUser],
			['admin', $mockAdminUser],
		]);

		$this->signRequestMapper->method('getByMultipleFileId')->willReturn([$signer]);
		$this->signRequestMapper->method('getIdentifyMethodsFromSigners')->willReturn([100 => [$identifyMethod]]);
		$this->signRequestMapper->method('getVisibleElementsFromSigners')->willReturn([]);
		$this->signRequestMapper->method('getTextOfSignerStatus')->willReturn('pending');

		$service = $this->getService();
		$result = $service->formatSingleFile($this->user, $file);

		$this->assertSame('Admin Name', $result['signers'][0]['displayName']);
	}

	public function testDocMdpLevelIncludedInChildFilesResponse(): void {
		$main = self::createFileEntity(1, 'envelope', 'envelope.pdf');
		$main->setDocmdpLevel(1);
		$child = self::createFileEntity(2, 'file', 'child.pdf');
		$child->setDocmdpLevel(2);

		$this->signRequestMapper->method('getByMultipleFileId')->willReturn([]);
		$this->signRequestMapper->method('getIdentifyMethodsFromSigners')->willReturn([]);
		$this->signRequestMapper->method('getVisibleElementsFromSigners')->willReturn([]);
		$this->fileElementService->method('getByFileIds')->willReturn([]);
		$this->fileMapper->method('getTextOfStatus')->willReturn('Draft');

		$mockUser = $this->createMock(IUser::class);
		$mockUser->method('getDisplayName')->willReturn('Requester');
		$this->userManager->method('get')->willReturn($mockUser);

		$service = $this->getService();
		$result = $service->formatFileWithChildren($main, [$child], $this->user);

		$this->assertEquals(1, $result['docmdpLevel']);
		$this->assertEquals(2, $result['files'][0]['docmdpLevel']);
	}

	public function testDetailedFileIncludesSize(): void {
		$file = self::createFileEntity(1, 'file', 'doc.pdf');

		$this->signRequestMapper->method('getByMultipleFileId')->willReturn([]);
		$this->signRequestMapper->method('getIdentifyMethodsFromSigners')->willReturn([]);
		$this->signRequestMapper->method('getVisibleElementsFromSigners')->willReturn([]);

		$fileNode = $this->createMock(NodeFile::class);
		$fileNode->method('getSize')->willReturn(4096);
		$this->userFolder->method('getFirstNodeById')->with(100)->willReturn($fileNode);

		$service = $this->getService();
		$result = $service->formatSingleFile($this->user, $file);

		$this->assertSame(4096, $result['size']);
		$this->assertSame(4096, $result['files'][0]['size']);
	}

	public function testEnvelopeDetailedFileIncludesAggregateSize(): void {
		$main = self::createFileEntity(1, 'envelope', 'envelope.pdf', ['filesCount' => 2]);
		$childA = self::createFileEntity(2, 'file', 'child-a.pdf');
		$childB = self::createFileEntity(3, 'file', 'child-b.pdf');

		$this->signRequestMapper->method('getByFileId')->with(1)->willReturn([]);
		$this->signRequestMapper->method('getByMultipleFileId')->with([2, 3])->willReturn([]);
		$this->signRequestMapper->method('getIdentifyMethodsFromSigners')->willReturn([]);
		$this->fileMapper->method('getTextOfStatus')->willReturn('Draft');

		$fileNodeA = $this->createMock(NodeFile::class);
		$fileNodeA->method('getSize')->willReturn(1024);
		$fileNodeB = $this->createMock(NodeFile::class);
		$fileNodeB->method('getSize')->willReturn(2048);
		$this->userFolder->method('getFirstNodeById')->willReturnMap([
			[200, $fileNodeA],
			[300, $fileNodeB],
		]);

		$mockUser = $this->createMock(IUser::class);
		$mockUser->method('getDisplayName')->willReturn('Requester');
		$this->userManager->method('get')->willReturn($mockUser);

		$service = $this->getService();
		$result = $service->formatFileWithChildren($main, [$childA, $childB], $this->user);

		$this->assertSame(3072, $result['size']);
		$this->assertSame(1024, $result['files'][0]['size']);
		$this->assertSame(2048, $result['files'][1]['size']);
	}

	private static function createFileEntity(
		int $id,
		string $nodeType,
		string $name,
		?array $metadata = null,
	): File {
		$file = new File();
		$file->setId($id);
		$file->setNodeId($id * 100);
		$file->setUuid('uuid-' . $id);
		$file->setName($name);
		$file->setStatus(1);
		$file->setMetadata($metadata ?? []);
		$file->setCreatedAt(new \DateTime('2025-01-01 12:00:00'));
		$file->setUserId('creator123');
		$file->setSignatureFlow(SignatureFlow::PARALLEL->toNumeric());
		$file->setNodeType($nodeType);
		return $file;
	}

	private function createSigner(int $id, int $fileId, ?int $signingOrder = null): SignRequest {
		$signer = new SignRequest();
		$signer->setId($id);
		$signer->setFileId($fileId);
		$signer->setUuid('uuid-' . $id);
		$signer->setDisplayName('Signer ' . $id);
		$signer->setCreatedAt(new \DateTime('2025-01-01'));
		$signer->setStatus(1);
		$signer->setSigningOrder($signingOrder ?? 1);
		return $signer;
	}

	private function createFileElement(int $signRequestId, int $page): \OCA\Libresign\Db\FileElement {
		$element = new \OCA\Libresign\Db\FileElement();
		$element->setSignRequestId($signRequestId);
		$element->setPage($page);
		$element->setUrx(200);
		$element->setUry(300);
		$element->setLlx(100);
		$element->setLly(200);
		$element->setMetadata([]);
		return $element;
	}

	private function createIdentifyMethod(string $key, string $value): \OCA\Libresign\Db\IdentifyMethod {
		$method = new \OCA\Libresign\Db\IdentifyMethod();
		$method->setIdentifierKey($key);
		$method->setIdentifierValue($value);
		$method->setMandatory(true);
		return $method;
	}

	private function mockSignatureMethodsResolution(): void {
		$this->identifyMethodService->method('setCurrentIdentifyMethod')->willReturnSelf();
		$this->identifyMethodService->method('setIsRequest')->willReturnSelf();
		$mockIdentifyMethod = $this->createMock(\OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod::class);
		$mockIdentifyMethod->method('getSignatureMethods')->willReturn([]);
		$this->identifyMethodService->method('getInstanceOfIdentifyMethod')->willReturn($mockIdentifyMethod);
	}
}
