<?php

/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\FileElementService;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\MailService;
use OCA\Libresign\Service\PdfParserService;
use OCA\Libresign\Service\RequestSignatureService;
use OCA\Libresign\Service\SignMethodService;
use OCP\Files\IMimeTypeDetector;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

final class RequestSignatureServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	/** @var IL10N|MockObject */
	private $l10n;
	/** @var MailService|MockObject */
	private $mailService;
	/** @var FileMapper|MockObject */
	private $fileMapper;
	/** @var FileUserMapper|MockObject */
	private $fileUserMapper;
	/** @var IdentifyMethodMapper|MockObject */
	private $identifyMethodMapper;
	/** @var IUser|MockObject */
	private $user;
	/** @var IClientService|MockObject */
	private $clientService;
	/** @var IUserManager|MockObject */
	private $userManager;
	/** @var FolderService|MockObject */
	private $folderService;
	/** @var IConfig */
	private $config;
	/** @var ValidateHelper|MockObject */
	private $validateHelper;
	/** @var FileElementMapper|MockObject */
	private $fileElementMapper;
	/** @var FileElementService|MockObject */
	private $fileElementService;
	/** @var SignMethodService|MockObject */
	private $signMethod;
	/** @var IdentifyMethodService|MockObject */
	private $identifyMethod;
	/** @var PdfParserService|MockObject */
	private $pdfParserService;
	/** @var IMimeTypeDetector|MockObject */
	private $mimeTypeDetector;
	/** @var LoggerInterface|MockObject */
	private $loggerInterface;

	public function setUp(): void {
		parent::setUp();
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));
		$this->mailService = $this->createMock(MailService::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->fileUserMapper = $this->createMock(FileUserMapper::class);
		$this->identifyMethodMapper = $this->createMock(IdentifyMethodMapper::class);
		$this->user = $this->createMock(IUser::class);
		$this->folderService = $this->createMock(FolderService::class);
		$this->clientService = $this->createMock(IClientService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->folderService = $this->createMock(FolderService::class);
		$this->config = $this->createMock(IConfig::class);
		$this->validateHelper = $this->createMock(ValidateHelper::class);
		$this->fileElementMapper = $this->createMock(FileElementMapper::class);
		$this->fileElementService = $this->createMock(FileElementService::class);
		$this->signMethod = $this->createMock(SignMethodService::class);
		$this->identifyMethod = $this->createMock(IdentifyMethodService::class);
		$this->pdfParserService = $this->createMock(PdfParserService::class);
		$this->mimeTypeDetector = $this->createMock(IMimeTypeDetector::class);
		$this->loggerInterface = $this->createMock(LoggerInterface::class);
	}

	private function getService(): RequestSignatureService {
		return new RequestSignatureService(
			$this->l10n,
			$this->mailService,
			$this->signMethod,
			$this->identifyMethod,
			$this->fileUserMapper,
			$this->userManager,
			$this->fileMapper,
			$this->identifyMethodMapper,
			$this->pdfParserService,
			$this->fileElementService,
			$this->fileElementMapper,
			$this->folderService,
			$this->mimeTypeDetector,
			$this->validateHelper,
			$this->loggerInterface
		);
	}

	public function testValidateNameIsMandatory() {
		$this->expectExceptionMessage('Name is mandatory');

		$this->getService()->validateNewRequestToFile([
			'file' => ['url' => 'qwert'],
			'userManager' => $this->user
		]);
	}

	public function testValidateEmptyUserCollection() {
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

	public function testValidateEmptyUsersCollection() {
		$this->expectExceptionMessage('Empty users list');

		$this->getService()->validateNewRequestToFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'userManager' => $this->user
		]);
	}

	public function testValidateUserCollectionNotArray() {
		$this->expectExceptionMessage('User list needs to be an array');

		$this->getService()->validateNewRequestToFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'users' => 'asdfg',
			'userManager' => $this->user
		]);
	}

	public function testValidateUserEmptyCollection() {
		$this->expectExceptionMessage('Empty users list');

		$this->getService()->validateNewRequestToFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'users' => null,
			'userManager' => $this->user
		]);
	}

	public function testValidateSuccess() {
		$actual = $this->getService()->validateNewRequestToFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'users' => [
				[
					'email' => 'jhondoe@test.coop'
				]
			],
			'userManager' => $this->user
		]);
		$this->assertNull($actual);
	}

	public function testSaveFileUserWhenUserExists() {
		$fileUser = $this->createMock(\OCA\Libresign\Db\FileUser::class);
		$fileUser
			->method('__call')
			->with('getId')
			->willReturn(123);
		$actual = $this->getService()->saveFileUser($fileUser);
		$this->assertNull($actual);
	}

	public function testSaveFileUserWhenUserDontExists() {
		$fileUser = $this->createMock(\OCA\Libresign\Db\FileUser::class);
		$fileUser
			->method('__call')
			->with('getId')
			->willReturn(null);
		$actual = $this->getService()->saveFileUser($fileUser);
		$this->assertNull($actual);
	}

	public function testSaveUsingUuid() {
		$file = $this->createMock(\OCA\Libresign\Db\File::class);
		$file
			->method('__call')
			->withConsecutive(
				[$this->equalTo('getUuid')],
				[$this->equalTo('getNodeId')],
				[$this->equalTo('getId')]
			)
			->will($this->returnValueMap([
				['getUuid', [], 'uuid-here'],
				['getNodeId', [], 123],
				['getId', [], 123]
			]));
		$this->fileMapper->method('getByUuid')->will($this->returnValue($file));

		$fileUser = $this->createMock(\OCA\Libresign\Db\FileUser::class);
		$fileUser
			->method('__call')
			->withConsecutive(
				[$this->equalTo('setFileId')],
				[$this->equalTo('getUuid')],
				[$this->equalTo('setUuid'), $this->callback(function ($subject) {
					$this->assertIsString($subject[0]);
					$this->assertEquals(36, strlen($subject[0]));
					return true;
				})],
				[$this->equalTo('setEmail'), $this->equalTo(['user@test.coop'])],
				[$this->equalTo('getDescription')],
				[$this->equalTo('setDescription'), $this->equalTo(['Please, sign'])],
				[$this->equalTo('setUserId')],
				[$this->equalTo('setDisplayName')],
				[$this->equalTo('getId')],
				[$this->equalTo('getId')],
				[$this->equalTo('getFileId')]
			)
			->will($this->returnValueMap([
				['setFileId', [], null],
				['getUuid', [], null],
				['setUuid', [], null],
				['setEmail', [], null],
				['getDescription', [], null],
				['setDescription', [], null],
				['setUserId', [], 123],
				['setDisplayName', [], 123],
				['getId', [], 123],
				['getId', [], 123],
				['getFileId', [], 123]
			]));
		$this->fileUserMapper
			->method('getByEmailAndFileId')
			->with('user@test.coop')
			->will($this->returnValue($fileUser));
		$user = $this->createMock(\OCP\IUser::class);
		$user->method('getDisplayName')->willReturn('John Doe');
		$this->userManager->method('getByEmail')->willReturn([$user]);
		$this->config->method('getAppValue')->will($this->returnValue('nextcloud'));
		$actual = $this->getService()->save([
			'uuid' => 'the-uuid-here',
			'users' => [
				[
					'email' => 'USER@TEST.COOP',
					'description' => 'Please, sign'
				]
			]
		]);
		$this->assertArrayHasKey('uuid', $actual);
		$this->assertEquals('uuid-here', $actual['uuid']);
		$this->assertArrayHasKey('users', $actual);
		$this->assertCount(1, $actual['users']);
		$this->assertInstanceOf('\OCA\Libresign\Db\FileUser', $actual['users'][0]);
	}

	/**
	 * @dataProvider dataSaveVisibleElements
	 */
	public function testSaveVisibleElements($elements) {
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

	public function dataSaveVisibleElements() {
		return [
			[[]],
			[[['uid' => 1]]],
			[[['uid' => 1], ['uid' => 1]]],
		];
	}
}
