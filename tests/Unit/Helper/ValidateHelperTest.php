<?php

declare(strict_types=1);

namespace OCA\Libresign\Tests\Unit\Helper;

use OCA\Libresign\Db\AccountFile;
use OCA\Libresign\Db\AccountFileMapper;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileTypeMapper;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Db\UserElementMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\IdentifyMethodService;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\IHasher;
use PHPUnit\Framework\MockObject\MockObject;

final class ValidateHelperTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IL10N|MockObject $l10n;
	private SignRequestMapper|MockObject $signRequestMapper;
	private FileMapper|MockObject $fileMapper;
	private FileTypeMapper|MockObject $fileTypeMapper;
	private FileElementMapper|MockObject $fileElementMapper;
	private AccountFileMapper|MockObject $accountFileMapper;
	private UserElementMapper|MockObject $userElementMapper;
	private IdentifyMethodMapper|MockObject $identifyMethodMapper;
	private IdentifyMethodService $identifyMethodService;
	private IMimeTypeDetector $mimeTypeDetector;
	private IHasher $hasher;
	private IAppConfig|MockObject $appConfig;
	private IGroupManager|MockObject $groupManager;
	private IUserManager $userManager;
	private IRootFolder|MockObject $root;
	private IUserMountCache|MockObject $userMountCache;

	public function setUp(): void {
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->fileTypeMapper = $this->createMock(FileTypeMapper::class);
		$this->fileElementMapper = $this->createMock(FileElementMapper::class);
		$this->accountFileMapper = $this->createMock(AccountFileMapper::class);
		$this->userElementMapper = $this->createMock(UserElementMapper::class);
		$this->identifyMethodMapper = $this->createMock(IdentifyMethodMapper::class);
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$this->mimeTypeDetector = \OCP\Server::get(IMimeTypeDetector::class);
		$this->hasher = $this->createMock(IHasher::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->root = $this->createMock(IRootFolder::class);
		$this->userMountCache = $this->createMock(IUserMountCache::class);
	}

	private function getValidateHelper(): ValidateHelper {
		$validateHelper = new ValidateHelper(
			$this->l10n,
			$this->signRequestMapper,
			$this->fileMapper,
			$this->fileTypeMapper,
			$this->fileElementMapper,
			$this->accountFileMapper,
			$this->userElementMapper,
			$this->identifyMethodMapper,
			$this->identifyMethodService,
			$this->mimeTypeDetector,
			$this->hasher,
			$this->appConfig,
			$this->groupManager,
			$this->userManager,
			$this->root,
			$this->userMountCache,
		);
		return $validateHelper;
	}

	public function testValidateFileWithoutAllNecessaryData() {
		$this->expectExceptionMessageMatches('/File type: %s. Specify a/');
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
		$this->userMountCache
			->method('getMountsForFileId')
			->willreturn([]);
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
			->method('getUserFolder')
			->willReturn($this->root);
		$this->root
			->method('getById')
			->willReturn([$file]);
		$this->userMountCache
			->method('getMountsForFileId')
			->willreturn([]);
		$actual = $this->getValidateHelper()->validateNewFile([
			'file' => ['fileId' => 123],
			'name' => 'test'
		]);
		$this->assertNull($actual);
	}

	public function testValidateNotRequestedSignWhenAlreadyAskedToSignThisDocument() {
		$this->signRequestMapper->method('getByNodeId')->will($this->returnValue('exists'));
		$this->expectExceptionMessage('Already asked to sign this document');
		$this->getValidateHelper()->validateNotRequestedSign(1);
	}

	public function testValidateNotRequestedSignWithSuccessWhenNotFound() {
		$this->signRequestMapper->method('getByNodeId')->will($this->returnCallback(function () {
			throw new \Exception('not found');
		}));
		$actual = $this->getValidateHelper()->validateNotRequestedSign(1);
		$this->assertNull($actual);
	}

	public function testValidateLibreSignNodeIdWhenFileIdNotExists() {
		$this->signRequestMapper->method('getByNodeId')->will($this->returnCallback(function () {
			throw new \Exception('not found');
		}));
		$this->expectExceptionMessage('Invalid fileID');
		$this->getValidateHelper()->validateLibreSignNodeId(1);
	}

	/**
	 * @dataProvider dataValidateMimeTypeAcceptedByNodeId
	 */
	public function testValidateMimeTypeAcceptedByNodeId(string $mimetype, int $destination, string $exception) {
		$file = $this->createMock(\OCP\Files\File::class);
		$file
			->method('getMimeType')
			->willReturn($mimetype);
		$this->root
			->method('getUserFolder')
			->willReturn($this->root);
		$this->root
			->method('getById')
			->willReturn([$file]);
		if ($exception) {
			$this->expectExceptionMessage($exception);
		}
		$this->userMountCache
			->method('getMountsForFileId')
			->willreturn([]);
		$actual = $this->getValidateHelper()->validateMimeTypeAcceptedByNodeId(171, $destination);
		if (!$exception) {
			$this->assertNull($actual);
		}
	}

	public function dataValidateMimeTypeAcceptedByNodeId() {
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

		$this->appConfig
			->method('getValueString')
			->willReturn('');
		$user = $this->createMock(\OCP\IUser::class);
		$this->getValidateHelper()->canRequestSign($user);
	}

	public function testCanRequestSignWithoutPermission() {
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

	public function testValidateFileWithEmptyFile() {
		$this->expectExceptionMessage('Empty file');

		$this->getValidateHelper()->validateFile([
			'file' => []
		]);
	}

	public function testValidateInvalidBase64File() {
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
	public function testValidateBase64($base64, $type, $valid) {
		if (!$valid) {
			$this->expectExceptionMessage('Invalid Base64 file');
		}
		$return = $this->getValidateHelper()->validateBase64($base64, $type);
		$this->assertNull($return);
	}

	public function dataValidateBase64(): array {
		return [
			[
				'invalid',
				ValidateHelper::TYPE_TO_SIGN,
				false
			],
			[
				base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf')),
				ValidateHelper::TYPE_TO_SIGN,
				true
			],
			[
				'data:application/pdf;base63,' . base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf')),
				ValidateHelper::TYPE_TO_SIGN,
				false
			],
			[
				'data:application/bla;base64,' . base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf')),
				ValidateHelper::TYPE_TO_SIGN,
				false
			],
			[
				'data:application/pdf;base64,' . base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf')),
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
		$this->signRequestMapper
			->method('getByFileUuid')
			->willReturn([]);
		$this->getValidateHelper()->signerWasAssociated([
			'email' => 'invalid@test.coop'
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
		$this->userMountCache
			->method('getMountsForFileId')
			->willreturn([]);
		$this->getValidateHelper()->validateIfNodeIdExists(171);
	}

	public function testValidateIfNodeIdExistsWithInvalidFile() {
		$this->expectExceptionMessage('Invalid fileID');
		$this->root
			->method('getById')
			->willReturn([0 => null]);
		$this->userMountCache
			->method('getMountsForFileId')
			->willreturn([]);
		$this->getValidateHelper()->validateIfNodeIdExists(171);
	}

	public function testValidateIfNodeIdExistsWithSuccess() {
		$this->root
			->method('getUserFolder')
			->willReturn($this->root);
		$this->root
			->method('getById')
			->willReturn(['file']);
		$this->userMountCache
			->method('getMountsForFileId')
			->willreturn([]);
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
			->willReturn(['IDENTIFICATION' => ['type' => 'IDENTIFICATION']]);
		$this->getValidateHelper()->validateFileTypeExists('0');
	}

	public function testValidFileType() {
		$this->fileTypeMapper
			->method('getTypes')
			->willReturn(['IDENTIFICATION' => ['type' => 'IDENTIFICATION']]);
		$actual = $this->getValidateHelper()->validateFileTypeExists('IDENTIFICATION');
		$this->assertNull($actual);
	}

	public function testUserHasFileWithType() {
		$this->expectExceptionMessage('A file of this type has been associated.');
		$file = $this->createMock(AccountFile::class);
		$this->accountFileMapper
			->method('getByUserAndType')
			->willReturn($file);
		$this->getValidateHelper()->validateUserHasNoFileWithThisType('username', (string)ValidateHelper::TYPE_TO_SIGN);
	}

	public function testUserHasNoFileWithThisType() {
		$this->accountFileMapper
			->method('getByUserAndType')
			->will($this->returnCallback(function () {
				throw new \Exception('not found');
			}));
		$actual = $this->getValidateHelper()->validateUserHasNoFileWithThisType('username', (string)ValidateHelper::TYPE_TO_SIGN);
		$this->assertNull($actual);
	}

	public function testIsSignerOfFile() {
		$actual = $this->getValidateHelper()->validateIsSignerOfFile(1, 1);
		$this->assertNull($actual);
	}

	public function testNotASignerOfFile() {
		$this->expectExceptionMessage('Signer not associated to this file');
		$this->signRequestMapper->method('getByFileIdAndSignRequestId')->will($this->returnCallback(function () {
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
				'base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))
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
		$user->method('getUID')->willReturn('fake_user');
		$data = [
			'userManager' => $user
		];
		$libresignFile = $this->createMock(\OCA\Libresign\Db\File::class);

		$this->fileMapper = $this->createMock(FileMapper::class);

		if (!empty($uuid)) {
			$libresignFile->method('__call')
				->willReturnCallback(fn ($method) =>
					match ($method) {
						'getNodeId' => 1,
					}
				);
			$libresignFile->method('getUserId')
				->willReturn('fake_user');
			$this->fileMapper->method('getByUuid')->will($this->returnValue($libresignFile));
			$this->fileMapper->method('getByFileId')->will($this->returnValue($libresignFile));

			$data['uuid'] = $uuid;
		} elseif (!empty($dataFile)) {
			$libresignFile->method('getUserId')
				->willReturn('fake_user');
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

	public function datavalidateIfIdentifyMethodExists(): array {
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
}
