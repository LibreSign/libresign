<?php

namespace OCA\Libresign\Tests\Unit\Helper;

use OCA\Libresign\Db\AccountFile;
use OCA\Libresign\Db\AccountFileMapper;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileTypeMapper;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Db\UserElementMapper;
use OCA\Libresign\Helper\ValidateHelper;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\IHasher;
use PHPUnit\Framework\MockObject\MockObject;

final class ValidateHelperTest extends \OCA\Libresign\Tests\Unit\TestCase {
	/** @var IL10N\|MockObject */
	private $l10n;
	/** @var FileUserMapper|MockObject */
	private $fileUserMapper;
	/** @var FileMapper|MockObject */
	private $fileMapper;
	/** @var FileTypeMapper|MockObject */
	private $fileTypeMapper;
	/** @var FileElementMapper|MockObject */
	private $fileElementMapper;
	/** @var AccountFileMapper|MockObject */
	private $accountFileMapper;
	/** @var UserElementMapper|MockObject */
	private $userElementMapper;
	/** @var IHasher */
	private $hasher;
	/** @var IConfig|MockObject */
	private $config;
	/** @var IGroupManager|MockObject */
	private $groupManager;
	/** @var IUserManager */
	private $userManager;
	/** @var IRootFolder|MockObject */
	private $root;

	public function setUp(): void {
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));
		$this->fileUserMapper = $this->createMock(FileUserMapper::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->fileTypeMapper = $this->createMock(FileTypeMapper::class);
		$this->fileElementMapper = $this->createMock(FileElementMapper::class);
		$this->accountFileMapper = $this->createMock(AccountFileMapper::class);
		$this->userElementMapper = $this->createMock(UserElementMapper::class);
		$this->hasher = $this->createMock(IHasher::class);
		$this->config = $this->createMock(IConfig::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->root = $this->createMock(IRootFolder::class);
	}

	private function getValidateHelper(): ValidateHelper {
		$validateHelper = new ValidateHelper(
			$this->l10n,
			$this->fileUserMapper,
			$this->fileMapper,
			$this->fileTypeMapper,
			$this->fileElementMapper,
			$this->accountFileMapper,
			$this->userElementMapper,
			$this->hasher,
			$this->config,
			$this->groupManager,
			$this->userManager,
			$this->root
		);
		return $validateHelper;
	}

	public function testValidateFileWithoutAllNecessaryData() {
		$this->expectExceptionMessage('File type: %s. Inform URL or base64 or fileID.');
		$this->getValidateHelper()->validateFile([
			'file' => ['invalid'],
			'name' => 'test'
		]);
	}

	public function testValidateFileWithInvalidFileId() {
		$this->expectExceptionMessage('Invalid fileID');
		$this->getValidateHelper()->validateFile([
			'file' => ['fileId' => 'invalid'],
			'name' => 'test'
		]);
	}

	public function testValidateFileWhenFileIdDoesNotExist() {
		$this->expectExceptionMessage('Invalid fileID');
		$this->root->method('getById')->will($this->returnCallback(function () {
			throw new \Exception('not found');
		}));
		$this->getValidateHelper()->validateFile([
			'file' => ['fileId' => 123],
			'name' => 'test'
		]);
	}

	public function testValidateNewFileUsingFileIdWithSuccess() {
		$file = $this->createMock(\OCP\Files\File::class);
		$file
			->method('getMimeType')
			->willReturn('application/pdf');
		$this->root
			->method('getById')
			->willReturn([$file]);
		$actual = $this->getValidateHelper()->validateNewFile([
			'file' => ['fileId' => 123],
			'name' => 'test'
		]);
		$this->assertNull($actual);
	}

	public function testValidateNotRequestedSignWhenAlreadyAskedToSignThisDocument() {
		$this->fileUserMapper->method('getByNodeId')->will($this->returnValue('exists'));
		$this->expectExceptionMessage('Already asked to sign this document');
		$this->getValidateHelper()->validateNotRequestedSign(1);
	}

	public function testValidateNotRequestedSignWithSuccessWhenNotFound() {
		$this->fileUserMapper->method('getByNodeId')->will($this->returnCallback(function () {
			throw new \Exception('not found');
		}));
		$actual = $this->getValidateHelper()->validateNotRequestedSign(1);
		$this->assertNull($actual);
	}

	public function testValidateLibreSignNodeIdWhenFileIdNotExists() {
		$this->fileUserMapper->method('getByNodeId')->will($this->returnCallback(function () {
			throw new \Exception('not found');
		}));
		$this->expectExceptionMessage('Invalid fileID');
		$this->getValidateHelper()->validateLibreSignNodeId(1);
	}

	/**
	 * @dataProvider dataValidateMimeTypeAccepted
	 */
	public function testValidateMimeTypeAccepted(string $mimetype, int $destination, string $exception) {
		$file = $this->createMock(\OCP\Files\File::class);
		$file
			->method('getMimeType')
			->willReturn($mimetype);
		$this->root
			->method('getById')
			->willReturn([$file]);
		if ($exception) {
			$this->expectExceptionMessage($exception);
		}
		$actual = $this->getValidateHelper()->validateMimeTypeAccepted(171, $destination);
		if (!$exception) {
			$this->assertNull($actual);
		}
	}

	public function dataValidateMimeTypeAccepted() {
		return [
			['invalid',         ValidateHelper::TYPE_TO_SIGN,             'Must be a fileID of %s format'],
			['application/pdf', ValidateHelper::TYPE_TO_SIGN,             ''],
			['invalid',         ValidateHelper::TYPE_VISIBLE_ELEMENT_PDF, 'Must be a fileID of %s format'],
			['image/png',       ValidateHelper::TYPE_VISIBLE_ELEMENT_PDF, ''],
		];
	}

	public function testValidateLibreSignNodeIdWhenSuccess() {
		$libresignFile = $this->createMock(\OCA\Libresign\Db\File::class);
		$this->fileMapper
			->method('getByFileId')
			->willReturn($libresignFile);
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
		$actual = $this->getValidateHelper()->validateLibreSignNodeId(1);
		$this->assertNull($actual);
	}

	public function testCanRequestSignWithoutUserManager() {
		$this->expectExceptionMessage('You are not allowed to request signing');

		$user = $this->createMock(\OCP\IUser::class);
		$this->getValidateHelper()->canRequestSign($user);
	}

	public function testCanRequestSignWithoutPermission() {
		$this->expectExceptionMessage('You are not allowed to request signing');

		$this->config = $this->createMock(IConfig::class);
		$this->config
			->method('getAppValue')
			->willReturn('["admin"]');
		$this->groupManager
			->method('getUserGroupIds')
			->willReturn([]);
		$user = $this->createMock(\OCP\IUser::class);
		$this->getValidateHelper()->canRequestSign($user);
	}

	public function testValidateFileWithEmptyFile() {
		$this->expectExceptionMessage('Empty file');

		$this->getValidateHelper()->validateFile([
			'file' => []
		]);
	}

	public function testValidateInvalidBase64File() {
		$this->expectExceptionMessage('Invalid base64 file');

		$user = $this->createMock(\OCP\IUser::class);
		$this->getValidateHelper()->validateFile([
			'file' => ['base64' => 'qwert'],
			'name' => 'test',
			'userManager' => $user
		]);
	}

	public function testIRequestedSignThisFileWithInvalidRequester() {
		$this->expectExceptionMessage('You do not have permission for this action.');
		$libresignFile = $this->createMock(\OCA\Libresign\Db\File::class);
		$libresignFile
			->method('__call')
			->willReturn('user1');
		$this->fileMapper
			->method('getByFileId')
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
	public function testHaveValidMailWithDataProvider($data, $errorMessage) {
		$this->expectExceptionMessage($errorMessage);
		$this->getValidateHelper()->haveValidMail($data, ValidateHelper::TYPE_VISIBLE_ELEMENT_PDF);
	}

	public function dataProviderHaveValidMail() {
		return [
			[[], 'No user data'],
			[[''], 'Email required'],
			[['email' => 'invalid'], 'Invalid email']
		];
	}

	public function testSignerWasAssociatedWithNotLibreSignFileLoaded() {
		$this->expectExceptionMessage('File not loaded');
		$this->fileMapper
			->method('getByFileId')
			->will($this->returnCallback(function () {
				throw new \Exception('not found');
			}));
		$this->getValidateHelper()->signerWasAssociated([
			'email' => 'invalid@test.coop'
		]);
	}

	public function testSignerWasAssociatedWithUnassociatedSigner() {
		$this->expectExceptionMessage('No signature was requested to %s');
		$libresignFile = $this->createMock(\OCA\Libresign\Db\File::class);
		$libresignFile
			->method('__call')
			->willReturn('uuid');
		$this->fileMapper
			->method('getByFileId')
			->willReturn($libresignFile);
		$this->fileUserMapper
			->method('getByFileUuid')
			->willReturn([]);
		$this->getValidateHelper()->signerWasAssociated([
			'email' => 'invalid@test.coop'
		]);
	}

	public function testNotSignedWithError() {
		$this->expectExceptionMessage('%s already signed this file');
		$libresignFile = $this->createMock(\OCA\Libresign\Db\File::class);
		$libresignFile
			->method('__call')
			->willReturn('uuid');
		$this->fileMapper
			->method('getByFileId')
			->willReturn($libresignFile);
		$fileUser = $this->createMock(\OCA\Libresign\Db\FileUser::class);
		$fileUser
			->method('__call')
			->withConsecutive(
				[$this->equalTo('getEmail'), $this->anything()],
				[$this->equalTo('getSigned'), $this->anything()]
			)
			->will($this->returnValueMap([
				['getEmail', [], 'signed@test.coop'],
				['getSigned', [], date('Y-m-d H:i:s')]
			]));
		$this->fileUserMapper
			->method('getByFileUuid')
			->willReturn([$fileUser]);
		$this->getValidateHelper()->notSigned([
			'email' => 'signed@test.coop'
		]);
	}

	public function testNotSignedWithFileNotLoaded() {
		$this->expectExceptionMessage('File not loaded');
		$this->fileMapper
			->method('getByFileId')
			->will($this->returnCallback(function () {
				throw new \Exception('not found');
			}));
		$this->getValidateHelper()->notSigned([]);
	}

	public function testValidateIfNodeIdExistsWhenGetFileThrowException() {
		$this->expectExceptionMessage('Invalid fileID');
		$this->root
			->method('getById')
			->will($this->returnCallback(function () {
				throw new \Exception('not found');
			}));
		$this->getValidateHelper()->validateIfNodeIdExists(171);
	}

	public function testValidateIfNodeIdExistsWithInvalidFile() {
		$this->expectExceptionMessage('Invalid fileID');
		$this->root
			->method('getById')
			->willReturn([0 => null]);
		$this->getValidateHelper()->validateIfNodeIdExists(171);
	}

	public function testValidateIfNodeIdExistsWithSuccess() {
		$this->root
			->method('getById')
			->willReturn(['file']);
		$actual = $this->getValidateHelper()->validateIfNodeIdExists(171);
		$this->assertNull($actual);
	}

	public function testValidateFileUuidWithInvalidUuid() {
		$this->expectExceptionMessage('Invalid UUID file');
		$this->fileMapper->method('getByUuid')->will($this->returnCallback(function () {
			throw new \Exception('not found');
		}));
		$this->getValidateHelper()->validateFileUuid(['uuid' => 'invalid']);
	}

	public function testValidateFileUuidWithValidUuid() {
		$file = $this->createMock(\OCA\Libresign\Db\File::class);
		$this->fileMapper->method('getByUuid')->will($this->returnValue($file));
		$actual = $this->getValidateHelper()->validateFileUuid(['uuid' => 'valid']);
		$this->assertNull($actual);
	}

	public function testInvalidFileType() {
		$this->expectExceptionMessage('Invalid file type.');
		$this->fileTypeMapper
			->method('getTypes')
			->willReturn(["IDENTIFICATION" => ["type" => "IDENTIFICATION"]]);
		$this->getValidateHelper()->validateFileTypeExists(0);
	}

	public function testValidFileType() {
		$this->fileTypeMapper
			->method('getTypes')
			->willReturn(["IDENTIFICATION" => ["type" => "IDENTIFICATION"]]);
		$actual = $this->getValidateHelper()->validateFileTypeExists('IDENTIFICATION');
		$this->assertNull($actual);
	}

	public function testUserHasFileWithType() {
		$this->expectExceptionMessage('A file of this type has been associated.');
		$file = $this->createMock(AccountFile::class);
		$this->accountFileMapper
			->method('getByUserAndType')
			->willReturn($file);
		$this->getValidateHelper()->validateUserHasNoFileWithThisType('username', ValidateHelper::TYPE_TO_SIGN);
	}

	public function testUserHasNoFileWithThisType() {
		$this->accountFileMapper
			->method('getByUserAndType')
			->will($this->returnCallback(function () {
				throw new \Exception('not found');
			}));
		$actual = $this->getValidateHelper()->validateUserHasNoFileWithThisType('username', ValidateHelper::TYPE_TO_SIGN);
		$this->assertNull($actual);
	}

	public function testIsSignerOfFile() {
		$actual = $this->getValidateHelper()->validateIsSignerOfFile(1, 1);
		$this->assertNull($actual);
	}

	public function testNotASignerOfFile() {
		$this->expectExceptionMessage('Signer not associated to this file');
		$this->fileUserMapper->method('getByFileIdAndFileUserId')->will($this->returnCallback(function () {
			throw new \Exception('not found');
		}));
		$this->getValidateHelper()->validateIsSignerOfFile(1, 1);
	}

	public function testValidateVisibleElementsWithInvalidElementType() {
		$this->expectExceptionMessage('Visible elements need to be an array');
		$actual = $this->getValidateHelper()->validateVisibleElements(null, ValidateHelper::TYPE_TO_SIGN);
		$this->assertNull($actual);
	}

	public function testValidateVisibleElementsWithSuccess() {
		$elements = [[
			'type' => 'signature',
			'file' => [
				'base64' => 'dGVzdA=='
			]
		]];
		$actual = $this->getValidateHelper()->validateVisibleElements($elements, ValidateHelper::TYPE_TO_SIGN);
		$this->assertNull($actual);
	}

	/**
	 * @dataProvider dataElementType
	 */
	public function testValidateElementType(array $element, string $exception) {
		if ($exception) {
			$this->expectExceptionMessage($exception);
		}
		$actual = $this->getValidateHelper()->validateElementType($element);
		$this->assertNull($actual);
	}

	public function dataElementType() {
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
	public function testValidateElementCoordinates(array $element) {
		$actual = $this->getValidateHelper()->validateElementCoordinates($element);
		$this->assertNull($actual);
	}

	public function dataValidateElementCoordinates() {
		return [
			[[]],
			[['coordinates' => ['page' => 1]]]
		];
	}

	/**
	 * @dataProvider dataValidateElementPage
	 */
	public function testValidateElementPage(array $element, string $exception) {
		if ($exception) {
			$this->expectExceptionMessage($exception);
		}
		$actual = $this->getValidateHelper()->validateElementPage($element);
		$this->assertNull($actual);
	}

	public function dataValidateElementPage() {
		return [
			[['coordinates' => ['page' => '']], 'Page number must be an integer'],
			[['coordinates' => ['page' => 0]], 'Page must be equal to or greater than 1']
		];
	}

	/**
	 * @dataProvider dataValidateExistingFile
	 */
	public function testValidateExistingFile($dataFile, $uuid, $exception) {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn(1);
		$data = [
			'userManager' => $user
		];
		$libresignFile = $this->createMock(\OCA\Libresign\Db\File::class);

		$this->fileMapper = $this->createMock(FileMapper::class);

		if (!empty($uuid)) {
			$libresignFile->method('__call')
				->withConsecutive(
					[$this->equalTo('getNodeId')],
					[$this->equalTo('getUserId')],
				)
				->will($this->returnValueMap([
					['getNodeId', [], 1],
					['getUserId', [], 1],
				]));
			$this->fileMapper->method('getByUuid')->will($this->returnValue($libresignFile));
			$this->fileMapper->method('getByFileId')->will($this->returnValue($libresignFile));

			$data['uuid'] = $uuid;
		} elseif (!empty($dataFile)) {
			$libresignFile->method('__call')
				->withConsecutive(
					[$this->equalTo('getUserId')]
				)
				->will($this->returnValueMap([
					['getUserId', [], 1]
				]));
			$this->fileMapper->method('getByFileId')->will($this->returnValue($libresignFile));

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

	public function dataValidateExistingFile() {
		return [
			[[],                            'uuid', ''],
			[['file' => []],                '',     'Invalid fileID'],
			[[],                            [],     'Inform or UUID or a File object'],
			[['file' => ['fileId' => 171]], '',     ''],
		];
	}
}
