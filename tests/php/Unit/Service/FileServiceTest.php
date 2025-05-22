<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

/**
 * Overwrite is_uploaded_file in the OCA\Libresign\Service namespace.
 */
function is_uploaded_file($filename) {
	return file_exists($filename);
}

namespace OCA\Libresign\Tests\Unit\Service;

use bovigo\vfs\vfsDirectory;
use bovigo\vfs\vfsStream;
use InvalidArgumentException;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\FileElementService;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\PdfParserService;
use OCA\Libresign\Tests\lib\AppConfigOverwrite;
use OCP\Accounts\IAccountManager;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\IDateTimeFormatter;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory as IL10NFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final class FileServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	protected FileMapper $fileMapper;
	protected SignRequestMapper $signRequestMapper;
	protected FileElementMapper $fileElementMapper;
	protected FileElementService $fileElementService;
	protected FolderService|MockObject $folderService;
	protected ValidateHelper $validateHelper;
	protected PdfParserService $pdfParserService;
	private AccountService&MockObject $accountService;
	private IdentifyMethodService $identifyMethodService;
	private IUserSession $userSession;
	private IUserManager $userManager;
	private IAccountManager&MockObject $accountManager;
	protected IClientService $client;
	private IDateTimeFormatter $dateTimeFormatter;
	private IAppConfig $appConfig;
	private IURLGenerator $urlGenerator;
	protected IMimeTypeDetector $mimeTypeDetector;
	protected Pkcs12Handler $pkcs12Handler;
	private IRootFolder $root;
	protected LoggerInterface $logger;
	protected IL10N $l10n;
	protected vfsDirectory $tempFolder;

	public function setUp(): void {
		$this->tempFolder = vfsStream::setup('uploaded');
		$appConfig = $this->getMockAppConfig();
		$appConfig->setValueInt(Application::APP_ID, 'length_of_page', 100);
		$appConfig->setValueBool(Application::APP_ID, 'identification_documents', false);
	}

	private function getService(): FileService {
		$this->fileMapper = \OCP\Server::get(FileMapper::class);
		$this->signRequestMapper = \OCP\Server::get(SignRequestMapper::class);
		$this->fileElementMapper = \OCP\Server::get(FileElementMapper::class);
		$this->fileElementService = \OCP\Server::get(FileElementService::class);
		$this->folderService = \OCP\Server::get(FolderService::class);
		$this->validateHelper = \OCP\Server::get(ValidateHelper::class);
		$this->pdfParserService = \OCP\Server::get(PdfParserService::class);
		$this->accountService = $this->createMock(AccountService::class);
		$this->identifyMethodService = \OCP\Server::get(IdentifyMethodService::class);
		$this->userSession = \OCP\Server::get(IUserSession::class);
		$this->userManager = \OCP\Server::get(IUserManager::class);
		$this->accountManager = $this->createMock(IAccountManager::class);
		$this->client = \OCP\Server::get(IClientService::class);
		$this->dateTimeFormatter = \OCP\Server::get(IDateTimeFormatter::class);
		$this->appConfig = new AppConfigOverwrite(
			$this->createMock(\OCP\IDBConnection::class),
			\OCP\Server::get(\Psr\Log\LoggerInterface::class),
			\OCP\Server::get(\OCP\Security\ICrypto::class),
		);
		$this->urlGenerator = \OCP\Server::get(IURLGenerator::class);
		$this->mimeTypeDetector = \OCP\Server::get(IMimeTypeDetector::class);
		$this->pkcs12Handler = \OCP\Server::get(Pkcs12Handler::class);
		$this->root = \OCP\Server::get(IRootFolder::class);
		$this->logger = \OCP\Server::get(LoggerInterface::class);
		$this->l10n = \OCP\Server::get(IL10NFactory::class)->get(Application::APP_ID);
		return new FileService(
			$this->fileMapper,
			$this->signRequestMapper,
			$this->fileElementMapper,
			$this->fileElementService,
			$this->folderService,
			$this->validateHelper,
			$this->pdfParserService,
			$this->accountService,
			$this->identifyMethodService,
			$this->userSession,
			$this->userManager,
			$this->accountManager,
			$this->client,
			$this->dateTimeFormatter,
			$this->appConfig,
			$this->urlGenerator,
			$this->mimeTypeDetector,
			$this->pkcs12Handler,
			$this->root,
			$this->logger,
			$this->l10n,
		);
	}

	#[DataProvider('dataToArray')]
	public function testToArray(callable $arguments, array $expected): void {
		if (shell_exec('which pdfsig') === null) {
			$this->markTestSkipped();
			return;
		}
		$service = $this->getService();
		if (is_callable($arguments)) {
			$arguments = $arguments($this, $service);
		}
		$actual = $service
			->toArray();
		$this->assertEquals($expected, $actual);
	}

	public static function dataToArray(): array {
		return [
			'empty' => [fn () => null, []],
			'No file provided' => [
				function (self $self, FileService $service): void {
					$self->expectException(InvalidArgumentException::class);
					$self->expectExceptionMessage('No file provided');
					$service
						->setFileFromRequest(null);
				},
				[]
			],
			'error when upload' => [
				function (self $self, FileService $service): void {
					$self->expectException(InvalidArgumentException::class);
					$self->expectExceptionMessage('Invalid file provided');
					$service
						->setFileFromRequest(['tmp_name' => tempnam(sys_get_temp_dir(), 'empty_file'), 'error' => 1]);
				},
				[]
			],
			'blacklisted file' => [
				function (self $self, FileService $service): void {
					$path = 'vfs://uploaded/.htaccess';
					file_put_contents($path, '');
					$self->expectException(InvalidArgumentException::class);
					$self->expectExceptionMessage('Invalid file provided');
					$service
						->setFileFromRequest(['tmp_name' => $path, 'error' => 0]);
				},
				[]
			],
			'File is too big' => [
				function (self $self, FileService $service): void {
					$path = 'vfs://uploaded/file.pdf';
					file_put_contents($path, '');
					$self->expectException(InvalidArgumentException::class);
					$self->expectExceptionMessage('File is too big');
					$service
						->setFileFromRequest(['tmp_name' => $path, 'error' => 0, 'size' => \OCP\Util::uploadLimit() + 1]);
				},
				[]
			],
			'Invalid file provided' => [
				function (self $self, FileService $service): void {
					$path = 'vfs://uploaded/file.php';
					file_put_contents($path, '');
					$self->expectException(InvalidArgumentException::class);
					$self->expectExceptionMessage('Invalid file provided');
					$service
						->setFileFromRequest([
							'tmp_name' => $path,
							'error' => 0,
							'size' => 0,
						]);
				},
				[]
			],
			'not signed file' => [
				function (self $self, FileService $service): void {
					$notSigned = tempnam(sys_get_temp_dir(), 'not_signed');
					copy(realpath(__DIR__ . '/../../fixtures/small_valid.pdf'), $notSigned);
					$service
						->setFileFromRequest([
							'tmp_name' => $notSigned,
							'error' => 0,
							'size' => 0,
							'name' => 'small_valid.pdf',
						]);
				},
				[
					'status' => File::STATUS_NOT_LIBRESIGN_FILE,
					'size' => filesize(__DIR__ . '/../../fixtures/small_valid.pdf'),
					'hash' => hash_file('sha256', __DIR__ . '/../../fixtures/small_valid.pdf'),
					'pdfVersion' => '1.6',
					'totalPages' => 1,
					'name' => 'small_valid.pdf',
				]
			],
			'signed file outside LibreSign' => [
				function (self $self, FileService $service): void {
					$notSigned = tempnam(sys_get_temp_dir(), 'not_signed');
					copy(realpath(__DIR__ . '/../../fixtures/small_valid-signed.pdf'), $notSigned);
					$service
						->setFileFromRequest([
							'tmp_name' => $notSigned,
							'error' => 0,
							'size' => 0,
							'name' => 'small_valid.pdf',
						]);
				},
				[
					'status' => File::STATUS_NOT_LIBRESIGN_FILE,
					'size' => filesize(__DIR__ . '/../../fixtures/small_valid-signed.pdf'),
					'hash' => hash_file('sha256', __DIR__ . '/../../fixtures/small_valid-signed.pdf'),
					'pdfVersion' => '1.6',
					'totalPages' => 1,
					'name' => 'small_valid.pdf',
				]
			],
			'signed file outside LibreSign and display signers' => [
				function (self $self, FileService $service): void {
					$notSigned = tempnam(sys_get_temp_dir(), 'not_signed');
					copy(realpath(__DIR__ . '/../../fixtures/small_valid-signed.pdf'), $notSigned);
					$service
						->setFileFromRequest([
							'tmp_name' => $notSigned,
							'error' => 0,
							'size' => 0,
							'name' => 'small_valid.pdf',
						])
						->showSigners();
				},
				[
					'status' => File::STATUS_NOT_LIBRESIGN_FILE,
					'size' => filesize(__DIR__ . '/../../fixtures/small_valid-signed.pdf'),
					'hash' => hash_file('sha256', __DIR__ . '/../../fixtures/small_valid-signed.pdf'),
					'pdfVersion' => '1.6',
					'totalPages' => 1,
					'name' => 'small_valid.pdf',
					'signers' => [
						[
							'displayName' => 'admin',
							'subject' => '/C=BR/ST=State of Company/L=City Name/O=Organization/OU=Organization Unit/UID=account:admin/CN=admin',
							'valid_from' => '2025-01-04T21:09:00+00:00',
							'valid_to' => '2026-01-04T21:09:00+00:00',
							'signed' => '2025-01-04T21:09:02+00:00',
							'uid' => 'account:admin',
							'signature_validation' => [
								'id' => 1,
								'label' => 'Signature is valid.',
							],
							'certificate_validation' => [
								'id' => 3,
								'label' => 'Certificate issuer is unknown.',
							],
							'hash_algorithm' => 'RSA-SHA1',
						],
					],
				]
			],
		];
	}
}
