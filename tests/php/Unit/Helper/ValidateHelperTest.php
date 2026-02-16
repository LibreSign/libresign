<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Helper;

use OC\User\NoUserException;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileTypeMapper;
use OCA\Libresign\Db\IdDocs;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Db\UserElementMapper;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\ISignatureMethod;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\SequentialSigningService;
use OCA\Libresign\Service\SignerElementsService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\IHasher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class ValidateHelperTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IL10N&MockObject $l10n;
	private SignRequestMapper&MockObject $signRequestMapper;
	private FileMapper&MockObject $fileMapper;
	private FileTypeMapper&MockObject $fileTypeMapper;
	private FileElementMapper&MockObject $fileElementMapper;
	private IdDocsMapper&MockObject $idDocsMapper;
	private UserElementMapper&MockObject $userElementMapper;
	private IdentifyMethodMapper&MockObject $identifyMethodMapper;
	private IdentifyMethodService&MockObject $identifyMethodService;
	private SequentialSigningService&MockObject $sequentialSigningService;
	private SignerElementsService&MockObject $signerElementsService;
	private IMimeTypeDetector $mimeTypeDetector;
	private IHasher&MockObject $hasher;
	private IAppConfig&MockObject $appConfig;
	private IGroupManager&MockObject $groupManager;
	private IUserManager&MockObject $userManager;
	private IRootFolder&MockObject $root;

	public function setUp(): void {
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->method('t')
			->willReturnArgument(0);
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->fileTypeMapper = $this->createMock(FileTypeMapper::class);
		$this->fileElementMapper = $this->createMock(FileElementMapper::class);
		$this->idDocsMapper = $this->createMock(IdDocsMapper::class);
		$this->userElementMapper = $this->createMock(UserElementMapper::class);
		$this->identifyMethodMapper = $this->createMock(IdentifyMethodMapper::class);
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$this->sequentialSigningService = $this->createMock(SequentialSigningService::class);
		$this->signerElementsService = $this->createMock(SignerElementsService::class);
		$this->mimeTypeDetector = \OCP\Server::get(IMimeTypeDetector::class);
		$this->hasher = $this->createMock(IHasher::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->root = $this->createMock(IRootFolder::class);
	}

	private function getValidateHelper(): ValidateHelper {
		$validateHelper = new ValidateHelper(
			$this->l10n,
			$this->signRequestMapper,
			$this->fileMapper,
			$this->fileTypeMapper,
			$this->fileElementMapper,
			$this->idDocsMapper,
			$this->userElementMapper,
			$this->identifyMethodMapper,
			$this->identifyMethodService,
			$this->sequentialSigningService,
			$this->signerElementsService,
			$this->mimeTypeDetector,
			$this->hasher,
			$this->appConfig,
			$this->groupManager,
			$this->userManager,
			$this->root,
		);
		return $validateHelper;
	}

	#[DataProvider('validateSignerLowerOrderScenarios')]
	public function testValidateSignerBlocksWhenLowerOrderPending(
		bool $isOrderedFlow,
		bool $hasPendingLowerOrder,
		bool $expectsBlock,
	): void {
		$uuid = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
		$signRequest = new \OCA\Libresign\Db\SignRequest();
		$file = new \OCA\Libresign\Db\File();

		$this->signRequestMapper
			->method('getByUuid')
			->with($uuid)
			->willReturn($signRequest);
		$this->fileMapper
			->method('getById')
			->willReturn($file);

		$signRequest->setStatusEnum(SignRequestStatus::ABLE_TO_SIGN);
		$signRequest->setId(99);
		$signRequest->setFileId(10);
		$signRequest->setSigningOrder(3);

		$this->identifyMethodService
			->method('getIdentifyMethodsFromSignRequestId')
			->willReturn([]);

		$this->sequentialSigningService
			->expects($this->once())
			->method('setFile')
			->with($file);
		$this->sequentialSigningService
			->method('isOrderedNumericFlow')
			->willReturn($isOrderedFlow);
		$this->sequentialSigningService
			->method('hasPendingLowerOrderSigners')
			->with(10, 3)
			->willReturn($hasPendingLowerOrder);

		if ($expectsBlock) {
			$this->expectException(LibresignException::class);
		}

		$this->getValidateHelper()->validateSigner($uuid);
		if (!$expectsBlock) {
			$this->assertTrue(true);
		}
	}

	public static function validateSignerLowerOrderScenarios(): array {
		return [
			'ordered_pending' => [true, true, true],
			'ordered_no_pending' => [true, false, false],
			'parallel_pending' => [false, true, false],
		];
	}

	public function testValidateFileWithoutAllNecessaryData():void {
		$this->expectExceptionMessageMatches('/File type: %s. Specify a/');
		$this->getValidateHelper()->validateFile([
			'file' => ['invalid'],
			'name' => 'test'
		]);
	}

	public function testValidateFileWithInvalidFileId():void {
		$this->expectExceptionMessage('Invalid fileID');
		$this->getValidateHelper()->validateFile([
			'file' => ['fileId' => 'invalid'],
			'name' => 'test'
		]);
	}

	public function testValidateNewFileUsingFileIdWithSuccess():void {
		$file = $this->createMock(\OCP\Files\File::class);
		$file
			->method('getMimeType')
			->willReturn('application/pdf');
		$this->root
			->method('getUserFolder')
			->willReturn($this->root);
		$this->root
			->method('getFirstNodeById')
			->willReturn($file);

		$user = $this->createMock(\OCP\IUser::class);
		$user->method('getUID')->willReturn('john.doe');
		$actual = $this->getValidateHelper()->validateNewFile([
			'file' => ['fileId' => 123],
			'name' => 'test',
			'userManager' => $user,
		]);
		$this->assertNull($actual);
	}

	public function testValidateNewFileUsingNodeIdWithSuccess():void {
		$file = $this->createMock(\OCP\Files\File::class);
		$file
			->method('getMimeType')
			->willReturn('application/pdf');
		$this->root
			->method('getUserFolder')
			->willReturn($this->root);
		$this->root
			->method('getFirstNodeById')
			->willReturn($file);

		$user = $this->createMock(\OCP\IUser::class);
		$user->method('getUID')->willReturn('john.doe');
		$actual = $this->getValidateHelper()->validateNewFile([
			'file' => ['nodeId' => 35523],
			'name' => 'test',
			'userManager' => $user,
		]);
		$this->assertNull($actual);
	}

	public function testValidateFileWithInvalidNodeId():void {
		$this->expectExceptionMessage('Invalid fileID');
		$user = $this->createMock(\OCP\IUser::class);
		$user->method('getUID')->willReturn('john.doe');
		$this->getValidateHelper()->validateFile([
			'file' => ['nodeId' => 'invalid'],
			'name' => 'test',
			'userManager' => $user,
		]);
	}

	public function testValidateFileWithNodeIdWithoutUser():void {
		$this->expectExceptionMessage('User not found');
		$this->getValidateHelper()->validateFile([
			'file' => ['nodeId' => 35523],
			'name' => 'test',
		]);
	}

	public function testValidateNotRequestedSignWhenAlreadyAskedToSignThisDocument():void {
		$this->signRequestMapper->method('getByNodeId')->willReturn('exists');
		$this->expectExceptionMessage('Already asked to sign this document');
		$this->getValidateHelper()->validateNotRequestedSign(1);
	}

	public function testValidateNotRequestedSignWithSuccessWhenNotFound():void {
		$this->signRequestMapper->method('getByNodeId')->willReturnCallback(function ():void {
			throw new \Exception('not found');
		});
		$actual = $this->getValidateHelper()->validateNotRequestedSign(1);
		$this->assertNull($actual);
	}

	/**
	 * @dataProvider dataValidateMimeTypeAcceptedByNodeId
	 */
	public function testValidateMimeTypeAcceptedByNodeId(string $mimetype, int $destination, string $exception):void {
		$file = $this->createMock(\OCP\Files\File::class);
		$file
			->method('getMimeType')
			->willReturn($mimetype);
		$this->root
			->method('getUserFolder')
			->willReturn($this->root);
		$this->root
			->method('getFirstNodeById')
			->willReturn($file);
		if ($exception) {
			$this->expectExceptionMessage($exception);
		}
		$actual = $this->getValidateHelper()->validateMimeTypeAcceptedByNodeId(171, '', $destination);
		if (!$exception) {
			$this->assertNull($actual);
		}
	}

	public static function dataValidateMimeTypeAcceptedByNodeId():array {
		return [
			['invalid',         ValidateHelper::TYPE_TO_SIGN,             'Must be a fileID of %s format'],
			['application/pdf', ValidateHelper::TYPE_TO_SIGN,             ''],
			['invalid',         ValidateHelper::TYPE_VISIBLE_ELEMENT_PDF, 'Must be a fileID of %s format'],
			['image/png',       ValidateHelper::TYPE_VISIBLE_ELEMENT_PDF, ''],
		];
	}

	public function testValidateMimeTypeAcceptedByNodeIdWhenSuccess():void {
		$libresignFile = $this->createMock(\OCA\Libresign\Db\File::class);
		$libresignFile->method('getUserId')->willReturn('user1');
		$this->fileMapper
			->method('getByNodeId')
			->willReturn($libresignFile);
		$file = $this->createMock(\OCP\Files\File::class);
		$file
			->method('getMimeType')
			->willReturn('application/pdf');
		$folder = $this->createMock(\OCP\Files\Folder::class);
		$folder
			->method('getFirstNodeById')
			->willReturn($file);
		$this->root
			->method('getUserFolder')
			->with('user1')
			->willReturn($folder);
		$actual = $this->getValidateHelper()->validateMimeTypeAcceptedByNodeId(1);
		$this->assertNull($actual);
	}

	public function testCanRequestSignWithoutUserManager():void {
		$this->expectExceptionMessage('You are not allowed to request signing');

		$this->appConfig
			->method('getValueString')
			->willReturn('');
		$user = $this->createMock(\OCP\IUser::class);
		$this->getValidateHelper()->canRequestSign($user);
	}

	public function testCanRequestSignWithoutPermission():void {
		$this->expectExceptionMessage('You are not allowed to request signing');

		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->appConfig
			->method('getValueString')
			->willReturn('["admin"]');
		$this->groupManager
			->method('getUserGroupIds')
			->willReturn([]);
		$user = $this->createMock(\OCP\IUser::class);
		$this->getValidateHelper()->canRequestSign($user);
	}

	public function testValidateFileWithEmptyFile():void {
		$this->expectExceptionMessage('Empty file');

		$this->getValidateHelper()->validateFile([
			'file' => []
		]);
	}

	public function testValidateInvalidBase64File():void {
		$this->expectExceptionMessage('Invalid Base64 file');

		$user = $this->createMock(\OCP\IUser::class);
		$this->getValidateHelper()->validateFile([
			'file' => ['base64' => 'qwert'],
			'name' => 'test',
			'userManager' => $user
		]);
	}

	/**
	 * @dataProvider dataValidateBase64
	 */
	public function testValidateBase64($base64, $type, $valid):void {
		if (!$valid) {
			$this->expectExceptionMessage('Invalid Base64 file');
		}
		$return = $this->getValidateHelper()->validateBase64($base64, $type);
		$this->assertNull($return);
	}

	public static function dataValidateBase64(): array {
		return [
			[
				'invalid',
				ValidateHelper::TYPE_TO_SIGN,
				false
			],
			[
				base64_encode(file_get_contents(__DIR__ . '/../../fixtures/pdfs/small_valid.pdf')),
				ValidateHelper::TYPE_TO_SIGN,
				true
			],
			[
				'data:application/pdf;base63,' . base64_encode(file_get_contents(__DIR__ . '/../../fixtures/pdfs/small_valid.pdf')),
				ValidateHelper::TYPE_TO_SIGN,
				false
			],
			[
				'data:application/bla;base64,' . base64_encode(file_get_contents(__DIR__ . '/../../fixtures/pdfs/small_valid.pdf')),
				ValidateHelper::TYPE_TO_SIGN,
				false
			],
			[
				'data:application/pdf;base64,' . base64_encode(file_get_contents(__DIR__ . '/../../fixtures/pdfs/small_valid.pdf')),
				ValidateHelper::TYPE_TO_SIGN,
				true
			],
			[
				'data:application/pdf;base64,invalid',
				ValidateHelper::TYPE_TO_SIGN,
				false
			],
		];
	}

	public function testIRequestedSignThisFileWithInvalidRequester():void {
		$this->expectExceptionMessage('You do not have permission for this action.');
		$libresignFile = $this->createMock(\OCA\Libresign\Db\File::class);
		$libresignFile
			->method('__call')
			->willReturn('user1');
		$this->fileMapper
			->method('getById')
			->willReturn($libresignFile);
		$user = $this->createMock(\OCP\IUser::class);
		$user
			->method('getUID')
			->willReturn('user2');
		$this->getValidateHelper()->iRequestedSignThisFile($user, 171);
	}

	/**
	 * @dataProvider dataProviderHaveValidMail
	 */
	public function testHaveValidMailWithDataProvider($data, $errorMessage):void {
		$this->expectExceptionMessage($errorMessage);
		$this->getValidateHelper()->haveValidMail($data, ValidateHelper::TYPE_VISIBLE_ELEMENT_PDF);
	}

	public static function dataProviderHaveValidMail():array {
		return [
			[[], 'No user data'],
			[[''], 'Email required'],
			[['email' => 'invalid'], 'Invalid email']
		];
	}

	public function testValidateIfNodeIdExistsWhenUserNotFound():void {
		$this->expectExceptionMessage('User not found');
		$userFolder = $this->createMock(\OCP\Files\Folder::class);
		$userFolder->method('getFirstNodeById')->willThrowException(new NoUserException());
		$this->root->method('getUserFolder')->willReturn($userFolder);
		$this->getValidateHelper()->validateIfNodeIdExists(171);
	}

	public function testValidateIfNodeIdExistsWhenNotPermission():void {
		$this->expectExceptionMessage('You do not have permission');
		$userFolder = $this->createMock(\OCP\Files\Folder::class);
		$userFolder->method('getFirstNodeById')->willThrowException(new NotPermittedException());
		$this->root->method('getUserFolder')->willReturn($userFolder);
		$this->getValidateHelper()->validateIfNodeIdExists(171);
	}

	public function testValidateIfNodeIdExistsWhenNotFound():void {
		$this->expectExceptionMessage('Invalid fileID');
		$userFolder = $this->createMock(\OCP\Files\Folder::class);
		$userFolder->method('getFirstNodeById')->willReturn(null);
		$this->root->method('getUserFolder')->willReturn($userFolder);
		$this->getValidateHelper()->validateIfNodeIdExists(171);
	}

	public function testValidateIfNodeIdExistsWithSuccess():void {
		$this->root
			->method('getUserFolder')
			->willReturn($this->root);
		$this->root
			->method('getFirstNodeById')
			->willReturn($this->createMock(\OCP\Files\File::class));
		$actual = $this->getValidateHelper()->validateIfNodeIdExists(171);
		$this->assertNull($actual);
	}

	public function testValidateFileUuidWithInvalidUuid():void {
		$this->expectExceptionMessage('Invalid UUID file');
		$this->fileMapper->method('getByUuid')->willReturnCallback(function ():void {
			throw new \Exception('not found');
		});
		$this->getValidateHelper()->validateFileUuid(['uuid' => 'invalid']);
	}

	public function testValidateFileUuidWithValidUuid():void {
		$file = $this->createMock(\OCA\Libresign\Db\File::class);
		$this->fileMapper->method('getByUuid')->willReturn($file);
		$actual = $this->getValidateHelper()->validateFileUuid(['uuid' => 'valid']);
		$this->assertNull($actual);
	}

	public function testInvalidFileType():void {
		$this->expectExceptionMessage('Invalid file type.');
		$this->fileTypeMapper
			->method('getTypes')
			->willReturn(['IDENTIFICATION' => ['type' => 'IDENTIFICATION']]);
		$this->getValidateHelper()->validateFileTypeExists('0');
	}

	public function testValidFileType():void {
		$this->fileTypeMapper
			->method('getTypes')
			->willReturn(['IDENTIFICATION' => ['type' => 'IDENTIFICATION']]);
		$actual = $this->getValidateHelper()->validateFileTypeExists('IDENTIFICATION');
		$this->assertNull($actual);
	}

	public function testUserHasFileWithType():void {
		$this->expectExceptionMessage('A file of this type has been associated.');
		$file = $this->createMock(IdDocs::class);
		$this->idDocsMapper
			->method('getByUserAndType')
			->willReturn($file);
		$this->getValidateHelper()->validateUserHasNoFileWithThisType('username', (string)ValidateHelper::TYPE_TO_SIGN);
	}

	public function testUserHasNoFileWithThisType():void {
		$this->idDocsMapper
			->method('getByUserAndType')
			->willReturn(null);
		$actual = $this->getValidateHelper()->validateUserHasNoFileWithThisType('username', (string)ValidateHelper::TYPE_TO_SIGN);
		$this->assertNull($actual);
	}

	public function testIsSignerOfFile():void {
		$actual = $this->getValidateHelper()->validateIsSignerOfFile(1, 1);
		$this->assertNull($actual);
	}

	public function testNotASignerOfFile():void {
		$this->expectExceptionMessage('Signer not associated to this file');
		$this->signRequestMapper->method('getByFileIdAndSignRequestId')->willReturnCallback(function ():void {
			throw new \Exception('not found');
		});
		$this->getValidateHelper()->validateIsSignerOfFile(1, 1);
	}

	public function testValidateVisibleElementsWithInvalidElementType():void {
		$this->expectExceptionMessage('Visible elements need to be an array');
		$actual = $this->getValidateHelper()->validateVisibleElements(null, ValidateHelper::TYPE_TO_SIGN);
		$this->assertNull($actual);
	}

	public function testValidateVisibleElementsWithSuccess():void {
		$elements = [[
			'type' => 'signature',
			'file' => [
				'base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/pdfs/small_valid.pdf'))
			]
		]];
		$actual = $this->getValidateHelper()->validateVisibleElements($elements, ValidateHelper::TYPE_TO_SIGN);
		$this->assertNull($actual);
	}

	public function testValidateElementSignRequestIdRequiresAssociation(): void {
		$this->expectExceptionMessage('Element must be associated with a user');

		$validateHelper = $this->getValidateHelper();
		$validateHelper->validateElementSignRequestId(
			['type' => 'signature'],
			ValidateHelper::TYPE_VISIBLE_ELEMENT_PDF,
		);
	}

	public function testValidateFileWithPathNotFound(): void {
		$this->expectExceptionMessage('Invalid data to validate file');

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('john.doe');

		$folder = $this->createMock(\OCP\Files\Folder::class);
		$folder->method('get')->willThrowException(new \OCP\Files\NotFoundException());
		$this->root->method('getUserFolder')->willReturn($folder);

		$this->getValidateHelper()->validateFile([
			'file' => ['path' => '/missing.pdf'],
			'userManager' => $user,
		]);
	}

	/**
	 * @dataProvider dataElementType
	 */
	public function testValidateElementType(array $element, string $exception):void {
		if ($exception) {
			$this->expectExceptionMessage($exception);
		}
		$actual = $this->getValidateHelper()->validateElementType($element);
		$this->assertNull($actual);
	}

	public static function dataElementType():array {
		return [
			[['type' => 'signature'], ''],
			[['type' => 'initial'], ''],
			[['type' => 'date'], ''],
			[['type' => 'datetime'], ''],
			[['type' => 'text'], ''],
			[['type' => 'INVALID'], 'Invalid element type'],
			[['file' => []], 'Element needs a type']
		];
	}

	/**
	 * @dataProvider dataValidateElementCoordinates
	 */
	public function testValidateElementCoordinates(array $element):void {
		$actual = $this->getValidateHelper()->validateElementCoordinates($element);
		$this->assertNull($actual);
	}

	public static function dataValidateElementCoordinates():array {
		return [
			[[]],
			[['coordinates' => ['page' => 1]]]
		];
	}

	/**
	 * @dataProvider dataValidateElementPage
	 */
	public function testValidateElementPage(array $element, string $exception):void {
		if ($exception) {
			$this->expectExceptionMessage($exception);
		}
		$actual = $this->getValidateHelper()->validateElementPage($element);
		$this->assertNull($actual);
	}

	public static function dataValidateElementPage():array {
		return [
			[['coordinates' => ['page' => '']], 'Page number must be an integer'],
			[['coordinates' => ['page' => 0]], 'Page must be equal to or greater than 1']
		];
	}

	public static function dataValidateVisibleElementsRelation(): array {
		return [
			'requires documentElementId when canCreateSignature is true' => [
				'canCreateSignature' => true,
				'visibleElements' => [['profileNodeId' => 1]],
				'hasUser' => true,
				'signRequestId' => 10,
				'fileElement' => null,
				'userElementExists' => true,
				'fileElements' => [],
				'expectedException' => 'Field %s not found',
			],
			'requires profileNodeId when canCreateSignature is true' => [
				'canCreateSignature' => true,
				'visibleElements' => [['documentElementId' => 99]],
				'hasUser' => true,
				'signRequestId' => 10,
				'fileElement' => null,
				'userElementExists' => true,
				'fileElements' => [],
				'expectedException' => 'Field %s not found',
			],
			'rejects not owned documentElement' => [
				'canCreateSignature' => false,
				'visibleElements' => [['documentElementId' => 99]],
				'hasUser' => false,
				'signRequestId' => 10,
				'fileElement' => ['id' => 99, 'signRequestId' => 11],
				'userElementExists' => true,
				'fileElements' => [['id' => 99, 'signRequestId' => 11]],
				'expectedException' => 'Invalid data to sign file',
			],
			'rejects profileNode not owned by user' => [
				'canCreateSignature' => true,
				'visibleElements' => [['documentElementId' => 99, 'profileNodeId' => 123]],
				'hasUser' => true,
				'signRequestId' => 10,
				'fileElement' => ['id' => 99, 'signRequestId' => 10],
				'userElementExists' => false,
				'fileElements' => [],
				'expectedException' => 'does not belong to user',
			],
			'requires userElement when missing' => [
				'canCreateSignature' => true,
				'visibleElements' => [],
				'hasUser' => true,
				'signRequestId' => 10,
				'fileElement' => null,
				'userElementExists' => false,
				'fileElements' => [['id' => 99, 'type' => 'signature']],
				'expectedException' => 'You need to define a visible signature or initials to sign this document.',
			],
		];
	}

	#[DataProvider('dataValidateVisibleElementsRelation')]
	public function testValidateVisibleElementsRelation(
		bool $canCreateSignature,
		array $visibleElements,
		bool $hasUser,
		int $signRequestId,
		?array $fileElement,
		bool $userElementExists,
		array $fileElements,
		string $expectedException,
	): void {
		$this->expectExceptionMessage($expectedException);

		$this->signerElementsService
			->method('canCreateSignature')
			->willReturn($canCreateSignature);

		$signRequest = new \OCA\Libresign\Db\SignRequest();
		$signRequest->setId($signRequestId);
		$signRequest->setFileId(20);

		if ($fileElement !== null) {
			$fileElementEntity = new \OCA\Libresign\Db\FileElement();
			$fileElementEntity->setId($fileElement['id']);
			$fileElementEntity->setSignRequestId($fileElement['signRequestId']);
			$this->fileElementMapper->method('getById')->willReturn($fileElementEntity);
		}

		if (!empty($fileElements)) {
			$fileElementEntities = [];
			foreach ($fileElements as $fe) {
				$entity = new \OCA\Libresign\Db\FileElement();
				$entity->setId($fe['id']);
				if (isset($fe['signRequestId'])) {
					$entity->setSignRequestId($fe['signRequestId']);
				}
				if (isset($fe['type'])) {
					$entity->setType($fe['type']);
				}
				$fileElementEntities[] = $entity;
			}
			$this->fileElementMapper->method('getByFileIdAndSignRequestId')->willReturn($fileElementEntities);
		}

		if (!$userElementExists) {
			if (empty($fileElements)) {
				$this->userElementMapper->method('findOne')->willThrowException(new \Exception('not found'));
			} else {
				$this->userElementMapper->method('findMany')->willThrowException(new \Exception('missing'));
			}
		}

		$user = $hasUser ? $this->createMock(IUser::class) : null;
		if ($hasUser && $user !== null) {
			$user->method('getUID')->willReturn('user1');
		}

		$this->getValidateHelper()->validateVisibleElementsRelation($visibleElements, $signRequest, $user);
	}

	public static function dataValidateAuthenticatedUserIsOwnerOfPdfVisibleElement(): array {
		return [
			'validates owner successfully' => [
				'fileElementId' => 77,
				'signRequestId' => 55,
				'fileId' => 33,
				'fileOwner' => 'owner',
				'authenticatedUser' => 'owner',
				'shouldThrowException' => false,
			],
			'rejects different owner' => [
				'fileElementId' => 77,
				'signRequestId' => 55,
				'fileId' => 33,
				'fileOwner' => 'owner',
				'authenticatedUser' => 'other',
				'shouldThrowException' => true,
			],
		];
	}

	#[DataProvider('dataValidateAuthenticatedUserIsOwnerOfPdfVisibleElement')]
	public function testValidateAuthenticatedUserIsOwnerOfPdfVisibleElement(
		int $fileElementId,
		int $signRequestId,
		int $fileId,
		string $fileOwner,
		string $authenticatedUser,
		bool $shouldThrowException,
	): void {
		if ($shouldThrowException) {
			$this->expectExceptionMessage('does not belong to user');
		}

		$fileElement = new \OCA\Libresign\Db\FileElement();
		$fileElement->setId($fileElementId);
		$fileElement->setSignRequestId($signRequestId);
		$this->fileElementMapper->method('getById')->willReturn($fileElement);

		$signRequest = new \OCA\Libresign\Db\SignRequest();
		$signRequest->setId($signRequestId);
		$signRequest->setFileId($fileId);
		$this->signRequestMapper->method('getById')->willReturn($signRequest);

		$file = new \OCA\Libresign\Db\File();
		$file->setId($fileId);
		$file->setUserId($fileOwner);
		$this->fileMapper->method('getById')->willReturn($file);

		$this->getValidateHelper()->validateAuthenticatedUserIsOwnerOfPdfVisibleElement($fileElementId, $authenticatedUser);
		if (!$shouldThrowException) {
			$this->assertTrue(true);
		}
	}

	/**
	 * @dataProvider dataValidateExistingFile
	 */
	public function testValidateExistingFile($dataFile, $uuid, $exception):void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('fake_user');
		$data = [
			'userManager' => $user
		];
		$libresignFile = $this->createMock(\OCA\Libresign\Db\File::class);

		$this->fileMapper = $this->createMock(FileMapper::class);

		if (!empty($uuid)) {
			$libresignFile->method('__call')
				->willReturnCallback(fn ($method)
					=> match ($method) {
						'getNodeId' => 1,
						'getId' => 1,
					}
				);
			$libresignFile->method('getUserId')
				->willReturn('fake_user');
			$this->fileMapper->method('getByUuid')->willReturn($libresignFile);
			$this->fileMapper->method('getById')->willReturn($libresignFile);

			$data['uuid'] = $uuid;
		} elseif (!empty($dataFile)) {
			$libresignFile->method('getUserId')
				->willReturn('fake_user');
			$this->fileMapper->method('getById')->willReturn($libresignFile);

			$file = $this->createMock(\OCP\Files\File::class);
			$file
				->method('getMimeType')
				->willReturn('application/pdf');
			$folder = $this->createMock(\OCP\Files\Folder::class);
			$folder
				->method('getById')
				->willReturn([$file]);
			$this->root
				->method('getUserFolder')
				->willReturn($folder);
			$data['file'] = $dataFile['file'];
		}
		if ($exception) {
			$this->expectExceptionMessage($exception);
		}

		$actual = $this->getValidateHelper()->validateExistingFile($data);
		$this->assertNull($actual);
	}

	public static function dataValidateExistingFile():array {
		return [
			[[],                            'uuid', ''],
			[['file' => []],                '',     'Invalid fileID'],
			[[],                            [],     'Please provide either UUID or File objec'],
			[['file' => ['fileId' => 171]], '',     ''],
		];
	}

	/**
	 * @dataProvider datavalidateIfIdentifyMethodExists
	 */
	public function testValidateIfIdentifyMethodExists(string $identifyMethod, bool $throwException): void {
		if ($throwException) {
			$this->expectException(LibresignException::class);
		}
		$return = $this->getValidateHelper()->validateIfIdentifyMethodExists($identifyMethod);
		$this->assertNull($return);
	}

	public static function datavalidateIfIdentifyMethodExists(): array {
		return [
			['', true],
			['invalid', true],
			['password', false],
			['account', false],
			['email', false],
			['sms', false],
			['signal', false],
			['telegram', false],
		];
	}

	public function testValidateIdentifyMethodForRequestWithNoSignatureMethods(): void {
		$identifyMethod = $this->createMock(IIdentifyMethod::class);
		$identifyMethod->method('getSignatureMethods')->willReturn([]);
		$identifyMethod->method('validateToRequest');

		$this->identifyMethodService
			->method('getInstanceOfIdentifyMethod')
			->willReturn($identifyMethod);

		$validateHelper = $this->getValidateHelper();

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('No signature methods for identify method account');

		$method = new \ReflectionMethod($validateHelper, 'validateIdentifyMethodForRequest');
		$method->invoke($validateHelper, 'account', 'user@example.com');
	}

	public function testValidateIdentifyMethodForRequestWithValidSignatureMethods(): void {
		$signatureMethod = $this->createMock(ISignatureMethod::class);
		$identifyMethod = $this->createMock(IIdentifyMethod::class);
		$identifyMethod->method('getSignatureMethods')->willReturn([$signatureMethod]);
		$identifyMethod->method('validateToRequest');

		$this->identifyMethodService
			->method('getInstanceOfIdentifyMethod')
			->willReturn($identifyMethod);

		$validateHelper = $this->getValidateHelper();

		$method = new \ReflectionMethod($validateHelper, 'validateIdentifyMethodForRequest');
		$result = $method->invoke($validateHelper, 'account', 'user@example.com');

		$this->assertNull($result);
	}

	public function testValidateIdentifySignersIntegration(): void {
		$signatureMethod = $this->createMock(ISignatureMethod::class);
		$identifyMethod = $this->createMock(IIdentifyMethod::class);
		$identifyMethod->method('getSignatureMethods')->willReturn([$signatureMethod]);
		$identifyMethod->method('validateToRequest');

		$this->identifyMethodService
			->method('getInstanceOfIdentifyMethod')
			->willReturn($identifyMethod);

		$data = [
			'users' => [
				['identify' => ['account' => 'user@example.com']]
			]
		];

		$validateHelper = $this->getValidateHelper();
		$result = $validateHelper->validateIdentifySigners($data);

		$this->assertNull($result);
	}

	#[DataProvider('providerValidateIdentifySigners')]
	public function testValidateIdentifySigners(array $data, bool $shouldThrow = false, string $expectedMessage = ''): void {
		// Mock signature method for valid cases
		$signatureMethod = $this->createMock(ISignatureMethod::class);
		$identifyMethod = $this->createMock(IIdentifyMethod::class);
		$identifyMethod->method('getSignatureMethods')->willReturn([$signatureMethod]);
		$identifyMethod->method('validateToRequest');

		$this->identifyMethodService
			->method('getInstanceOfIdentifyMethod')
			->willReturn($identifyMethod);

		$validateHelper = $this->getValidateHelper();

		if ($shouldThrow) {
			$this->expectException(LibresignException::class);
			if ($expectedMessage) {
				$this->expectExceptionMessage($expectedMessage);
			}
		}

		$validateHelper->validateIdentifySigners($data);

		if (!$shouldThrow) {
			$this->addToAssertionCount(1); // If we get here without exception, test passed
		}
	}

	public static function providerValidateIdentifySigners(): array {
		return [
			'valid data with identify structure single method' => [
				[
					'users' => [
						[
							'identify' => [
								'account' => 'user@example.com'
							]
						]
					]
				],
				false, // should not throw
			],
			'valid data with identify structure multiple methods' => [
				[
					'users' => [
						[
							'identify' => [
								'account' => 'user@example.com',
								'email' => 'user@example.com'
							]
						]
					]
				],
				false, // should not throw
			],
			'valid data with identifyMethods structure single method' => [
				[
					'users' => [
						[
							'identifyMethods' => [
								['method' => 'account', 'value' => 'user@example.com']
							]
						]
					]
				],
				false, // should not throw
			],
			'valid data with identifyMethods structure multiple methods' => [
				[
					'users' => [
						[
							'identifyMethods' => [
								['method' => 'account', 'value' => 'user@example.com'],
								['method' => 'email', 'value' => 'user@example.com']
							]
						]
					]
				],
				false, // should not throw
			],
			'mixed structures in same data' => [
				[
					'users' => [
						[
							'identify' => [
								'account' => 'user1@example.com'
							]
						],
						[
							'identifyMethods' => [
								['method' => 'email', 'value' => 'user2@example.com']
							]
						]
					]
				],
				false, // should not throw
			],
			'empty data structure' => [
				[],
				false, // should not throw
				''
			],
			'missing users key' => [
				['someOtherKey' => 'value'],
				false, // should not throw
				''
			],
			'empty users array' => [
				['users' => []],
				false, // should not throw
				''
			],
			'users not array' => [
				['users' => 'not-an-array'],
				true, // should throw
				'No signers'
			],
			'empty signer' => [
				['users' => [[]]],
				true, // should throw
				'No signers'
			],
			'signer not array' => [
				['users' => ['not-an-array']],
				true, // should throw
				'No signers'
			],
			'signer without identify methods' => [
				['users' => [['someKey' => 'value']]],
				true, // should throw
				'No identify methods for signer'
			],
			'signer with empty identify' => [
				['users' => [['identify' => []]]],
				true, // should throw
				'No identify methods for signer'
			],
			'signer with empty identifyMethods' => [
				['users' => [['identifyMethods' => []]]],
				true, // should throw
				'No identify methods for signer'
			],
			'invalid identifyMethods structure - missing method' => [
				[
					'users' => [
						[
							'identifyMethods' => [
								['value' => 'user@example.com'] // missing 'method'
							]
						]
					]
				],
				true, // should throw
				'Invalid identify method structure'
			],
			'invalid identifyMethods structure - missing value' => [
				[
					'users' => [
						[
							'identifyMethods' => [
								['method' => 'email'] // missing 'value'
							]
						]
					]
				],
				true, // should throw
				'Invalid identify method structure'
			],
			'valid displayName within 64 characters' => [
				[
					'users' => [
						[
							'displayName' => 'Valid Display Name',
							'identify' => [
								'account' => 'user@example.com'
							]
						]
					]
				],
				false, // should not throw
			],
			'displayName exactly 64 characters' => [
				[
					'users' => [
						[
							'displayName' => str_repeat('A', 64),
							'identify' => [
								'account' => 'user@example.com'
							]
						]
					]
				],
				false, // should not throw
			],
			'displayName too long - 65 characters' => [
				[
					'users' => [
						[
							'displayName' => str_repeat('A', 65),
							'identify' => [
								'account' => 'user@example.com'
							]
						]
					]
				],
				true, // should throw
				'Display name must not be longer than 64 characters'
			],
			'displayName too long - 100 characters' => [
				[
					'users' => [
						[
							'displayName' => str_repeat('B', 100),
							'identify' => [
								'account' => 'user@example.com'
							]
						]
					]
				],
				true, // should throw
				'Display name must not be longer than 64 characters'
			],
		];
	}

	public static function canSignWithIdentificationDocumentStatusProvider(): array {
		return [
			'disabled identification documents allows signing' => [
				'status' => FileService::IDENTIFICATION_DOCUMENTS_DISABLED,
				'canSign' => true,
			],
			'approved identification documents allows signing' => [
				'status' => FileService::IDENTIFICATION_DOCUMENTS_APPROVED,
				'canSign' => true,
			],
			'need send identification documents blocks signing with correct error code' => [
				'status' => FileService::IDENTIFICATION_DOCUMENTS_NEED_SEND,
				'canSign' => false,
				'expectedCode' => JSActions::ACTION_SIGN_ID_DOC,
			],
			'need approval identification documents blocks signing with correct error code' => [
				'status' => FileService::IDENTIFICATION_DOCUMENTS_NEED_APPROVAL,
				'canSign' => false,
				'expectedCode' => JSActions::ACTION_SIGN_ID_DOC,
			],
		];
	}

	#[DataProvider('canSignWithIdentificationDocumentStatusProvider')]
	public function testCanSignWithIdentificationDocumentStatus(
		int $status,
		bool $canSign,
		?int $expectedCode = null,
	): void {
		$user = $this->createMock(IUser::class);
		$validateHelper = $this->getValidateHelper();

		if ($canSign) {
			try {
				$validateHelper->canSignWithIdentificationDocumentStatus($user, $status);
				$this->assertTrue(true, 'Expected no exception when can sign');
			} catch (LibresignException $e) {
				$this->fail(sprintf('Unexpected exception when user can sign: %s', $e->getMessage()));
			}
		} else {
			try {
				$validateHelper->canSignWithIdentificationDocumentStatus($user, $status);
				$this->fail('Expected LibresignException for pending identification document status');
			} catch (LibresignException $e) {
				$this->assertSame(
					$expectedCode,
					$e->getCode(),
					sprintf(
						'Expected error code %d but got %d: %s',
						$expectedCode,
						$e->getCode(),
						$e->getMessage(),
					),
				);
			}
		}
	}

	public function testCanSignWithIdentificationDocumentStatusThrowsWithCorrectMessage(): void {
		$user = $this->createMock(IUser::class);
		$validateHelper = $this->getValidateHelper();

		try {
			$validateHelper->canSignWithIdentificationDocumentStatus($user, FileService::IDENTIFICATION_DOCUMENTS_NEED_APPROVAL);
			$this->fail('Expected LibresignException');
		} catch (LibresignException $e) {
			$this->assertStringContainsString(
				'approved identification document',
				$e->getMessage(),
				'Error message should mention identification document requirement'
			);
		}
	}

	public static function providerValidateIdDocBelongsToSignRequest(): array {
		return [
			'success - document belongs to sign request' => [
				'nodeId' => 123,
				'signRequestId' => 456,
				'exception' => null,
				'shouldThrow' => false,
			],
			'throws when document not found' => [
				'nodeId' => 123,
				'signRequestId' => 456,
				'exception' => new DoesNotExistException('Not found'),
				'shouldThrow' => true,
			],
			'throws on any database error' => [
				'nodeId' => 123,
				'signRequestId' => 456,
				'exception' => new \Exception('Database error'),
				'shouldThrow' => true,
			],
		];
	}

	#[DataProvider('providerValidateIdDocBelongsToSignRequest')]
	public function testValidateIdDocBelongsToSignRequest(
		int $nodeId,
		int $signRequestId,
		?\Throwable $exception,
		bool $shouldThrow,
	): void {
		if ($exception === null) {
			$idDoc = new IdDocs();
			$idDoc->setFileId($nodeId);
			$idDoc->setSignRequestId($signRequestId);

			$this->idDocsMapper
				->expects($this->once())
				->method('getBySignRequestIdAndNodeId')
				->with($signRequestId, $nodeId)
				->willReturn($idDoc);
		} else {
			$this->idDocsMapper
				->expects($this->once())
				->method('getBySignRequestIdAndNodeId')
				->with($signRequestId, $nodeId)
				->willThrowException($exception);
		}

		$validateHelper = $this->getValidateHelper();

		if ($shouldThrow) {
			$this->expectException(LibresignException::class);
			$this->expectExceptionMessage('Not allowed');
		}

		$validateHelper->validateIdDocBelongsToSignRequest($nodeId, $signRequestId);

		if (!$shouldThrow) {
			$this->assertTrue(true);
		}
	}
}
