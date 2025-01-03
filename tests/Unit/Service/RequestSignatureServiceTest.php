<?php

/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\FileElementService;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\PdfParserService;
use OCA\Libresign\Service\RequestSignatureService;
use OCP\Files\IMimeTypeDetector;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

final class RequestSignatureServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IL10N&MockObject $l10n;
	private FileMapper&MockObject $fileMapper;
	private SignRequestMapper&MockObject $signRequestMapper;
	private IdentifyMethodMapper&MockObject $identifyMethodMapper;
	private IUser&MockObject $user;
	private IClientService&MockObject $clientService;
	private IUserManager&MockObject $userManager;
	private FolderService&MockObject $folderService;
	private ValidateHelper&MockObject $validateHelper;
	private FileElementMapper&MockObject $fileElementMapper;
	private FileElementService&MockObject $fileElementService;
	private IdentifyMethodService&MockObject $identifyMethodService;
	private PdfParserService&MockObject $pdfParserService;
	private IMimeTypeDetector&MockObject $mimeTypeDetector;
	private IClientService $client;
	private LoggerInterface&MockObject $loggerInterface;

	public function setUp(): void {
		parent::setUp();
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->identifyMethodMapper = $this->createMock(IdentifyMethodMapper::class);
		$this->user = $this->createMock(IUser::class);
		$this->folderService = $this->createMock(FolderService::class);
		$this->clientService = $this->createMock(IClientService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->folderService = $this->createMock(FolderService::class);
		$this->validateHelper = $this->createMock(ValidateHelper::class);
		$this->fileElementMapper = $this->createMock(FileElementMapper::class);
		$this->fileElementService = $this->createMock(FileElementService::class);
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$this->pdfParserService = $this->createMock(PdfParserService::class);
		$this->mimeTypeDetector = $this->createMock(IMimeTypeDetector::class);
		$this->client = $this->createMock(IClientService::class);
		$this->loggerInterface = $this->createMock(LoggerInterface::class);
	}

	private function getService(): RequestSignatureService {
		return new RequestSignatureService(
			$this->l10n,
			$this->identifyMethodService,
			$this->signRequestMapper,
			$this->userManager,
			$this->fileMapper,
			$this->identifyMethodMapper,
			$this->pdfParserService,
			$this->fileElementService,
			$this->fileElementMapper,
			$this->folderService,
			$this->mimeTypeDetector,
			$this->validateHelper,
			$this->client,
			$this->loggerInterface
		);
	}

	public function testValidateNameIsMandatory():void {
		$this->expectExceptionMessage('Name is mandatory');

		$this->getService()->validateNewRequestToFile([
			'file' => ['url' => 'qwert'],
			'userManager' => $this->user
		]);
	}

	public function testValidateEmptyUserCollection():void {
		$this->expectExceptionMessage('Empty users list');

		$response = $this->createMock(IResponse::class);
		$response
			->method('getHeaders')
			->will($this->returnValue(['Content-Type' => ['application/pdf']]));
		$client = $this->createMock(IClient::class);
		$client
			->method('get')
			->will($this->returnValue($response));
		$this->clientService
			->method('newClient')
			->will($this->returnValue($client));

		$this->getService()->validateNewRequestToFile([
			'file' => ['url' => 'http://test.coop'],
			'name' => 'test',
			'userManager' => $this->user
		]);
	}

	public function testValidateEmptyUsersCollection():void {
		$this->expectExceptionMessage('Empty users list');

		$this->getService()->validateNewRequestToFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'userManager' => $this->user
		]);
	}

	public function testValidateUserCollectionNotArray():void {
		$this->expectExceptionMessage('User list needs to be an array');

		$this->getService()->validateNewRequestToFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'users' => 'asdfg',
			'userManager' => $this->user
		]);
	}

	public function testValidateUserEmptyCollection():void {
		$this->expectExceptionMessage('Empty users list');

		$this->getService()->validateNewRequestToFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'users' => null,
			'userManager' => $this->user
		]);
	}

	public function testValidateSuccess():void {
		$actual = $this->getService()->validateNewRequestToFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'users' => [
				['identify' => ['email' => 'jhondoe@test.coop']]
			],
			'userManager' => $this->user
		]);
		$this->assertNull($actual);
	}

	public function testSaveSignRequestWhenUserExists():void {
		$signRequest = $this->createMock(\OCA\Libresign\Db\SignRequest::class);
		$signRequest
			->method('__call')
			->with('getId')
			->willReturn(123);
		$actual = $this->getService()->saveSignRequest($signRequest);
		$this->assertNull($actual);
	}

	public function testSaveSignRequestWhenUserDontExists():void {
		$signRequest = $this->createMock(\OCA\Libresign\Db\SignRequest::class);
		$signRequest
			->method('__call')
			->with('getId')
			->willReturn(null);
		$actual = $this->getService()->saveSignRequest($signRequest);
		$this->assertNull($actual);
	}

	/**
	 * @dataProvider dataGetFileMetadata
	 */
	public function testGetFileMetadata(string $extension, array $expected): void {
		$inputFile = $this->createMock(\OC\Files\Node\File::class);
		$inputFile
			->method('getExtension')
			->willReturn($extension);
		$this->pdfParserService
			->method('setFile')
			->willReturn($this->pdfParserService);
		$this->pdfParserService
			->method('getPageDimensions')
			->willReturn(['isValid' => true]);
		$actual = self::invokePrivate($this->getService(), 'getFileMetadata', [$inputFile]);
		$this->assertEquals($expected, $actual);
	}

	public static function dataGetFileMetadata(): array {
		return [
			['pdfff', ['extension' => 'pdfff']],
			['', []],
			['PDF', ['extension' => 'pdf', 'isValid' => true]],
		];
	}

	/**
	 * @dataProvider dataSaveVisibleElements
	 */
	public function testSaveVisibleElements($elements):void {
		$libreSignFile = new \OCA\Libresign\Db\File();
		if (!empty($elements)) {
			$libreSignFile->setId(1);
			$this->fileElementService
				->expects($this->exactly(count($elements)))
				->method('saveVisibleElement');
		}
		$actual = self::invokePrivate($this->getService(), 'saveVisibleElements', [
			['visibleElements' => $elements], $libreSignFile
		]);
		$this->assertSameSize($elements, $actual);
	}

	public static function dataSaveVisibleElements():array {
		return [
			[[]],
			[[['uid' => 1]]],
			[[['uid' => 1], ['uid' => 1]]],
		];
	}
}
