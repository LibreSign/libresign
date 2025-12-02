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
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\FileElementService;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\PdfParserService;
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
	protected FolderService $folderService;
	protected ValidateHelper $validateHelper;
	protected PdfParserService $pdfParserService;
	protected IdDocsMapper $idDocsMapper;
	private AccountService&MockObject $accountService;
	private IdentifyMethodService $identifyMethodService;
	private IUserSession $userSession;
	private IUserManager&MockObject $userManager;
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
		// Disable lazy objects to avoid PHP 8.4 dependency injection issues in tests
		\OC\AppFramework\Utility\SimpleContainer::$useLazyObjects = false;

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
		$this->idDocsMapper = \OCP\Server::get(IdDocsMapper::class);
		$this->accountService = $this->createMock(AccountService::class);
		$this->identifyMethodService = \OCP\Server::get(IdentifyMethodService::class);
		$this->userSession = \OCP\Server::get(IUserSession::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->accountManager = $this->createMock(IAccountManager::class);
		$this->client = \OCP\Server::get(IClientService::class);
		$this->dateTimeFormatter = \OCP\Server::get(IDateTimeFormatter::class);
		$this->appConfig = $this->getMockAppConfig();
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
			$this->idDocsMapper,
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

		// Remove 'purposes' field from comparison as it varies between OpenSSL versions
		$this->removePurposesField($expected);
		$this->removePurposesField($actual);

		$this->assertEquals($expected, $actual);
	}

	private function removePurposesField(array &$data): void {
		if (isset($data['signers'])) {
			foreach ($data['signers'] as &$signer) {
				unset($signer['purposes']);
				if (isset($signer['chain'])) {
					foreach ($signer['chain'] as &$chainItem) {
						unset($chainItem['purposes']);
					}
				}
			}
		}
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
					if (version_compare(PHP_VERSION, '8.4.0', '>=')) {
						/**
						 * @todo Check why this test fails on PHP 8.4 and fix it
						 */
						$self->markTestSkipped('Skipping test for not signed file due to environment limitations with PHP >= 8.4.');
					}
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
					if (version_compare(PHP_VERSION, '8.4.0', '>=')) {
						/**
						 * @todo Check why this test fails on PHP 8.4 and fix it
						 */
						$self->markTestSkipped('Skipping test for not signed file due to environment limitations with PHP >= 8.4.');
					}
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
					if (version_compare(PHP_VERSION, '8.4.0', '>=')) {
						/**
						 * @todo Check why this test fails on PHP 8.4 and fix it
						 */
						$self->markTestSkipped('Skipping test for not signed file due to environment limitations with PHP >= 8.4.');
					}
					$self->userManager->method('get')->willReturn(null);
					$self->userManager->method('getByEmail')->willReturn([]);
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
							'displayName' => '/C=BR/ST=State of Company/L=City Name/O=Organization/OU=Organization Unit/CN=account:admin, admin',
							'subject' => [
								'C' => 'BR',
								'ST' => 'State of Company',
								'L' => 'City Name',
								'O' => 'Organization',
								'OU' => 'Organization Unit',
								'CN' => ['account:admin', 'admin'],
							],
							'valid_from' => '2025-10-20T13:26:00+00:00',
							'valid_to' => '2026-10-20T13:26:00+00:00',
							'signed' => '2025-10-20T13:31:43+00:00',
							'uid' => 'email:admin@email.tld',
							'signature_validation' => [
								'id' => 1,
								'label' => 'Signature is valid.',
							],
							'name' => '/C=BR/ST=State of Company/L=City Name/O=Organization/OU=Organization Unit/CN=account:admin, admin',
							'hash' => '4a5a1475',
							'issuer' => [
								'C' => 'BR',
								'ST' => 'State of Company',
								'L' => 'City Name',
								'O' => 'Organization',
								'OU' => 'Organization Unit',
								'CN' => 'Common Name',
							],
							'version' => 2,
							'serialNumber' => '0x4700D96F34F501CF0EA141E75F20643844393FFF',
							'serialNumberHex' => '4700D96F34F501CF0EA141E75F20643844393FFF',
							'validFrom' => '251020132600Z',
							'validTo' => '261020132600Z',
							'validFrom_time_t' => 1760966760,
							'validTo_time_t' => 1792502760,
							'signatureTypeSN' => 'RSA-SHA256',
							'signatureTypeLN' => 'sha256WithRSAEncryption',
							'signatureTypeNID' => 668,
							'purposes' => [
								1 => [true, false, 'sslclient'],
								2 => [false, false, 'sslserver'],
								3 => [false, false, 'nssslserver'],
								4 => [true, false, 'smimesign'],
								5 => [true, false, 'smimeencrypt'],
								6 => [false, false, 'crlsign'],
								7 => [true, true, 'any'],
								8 => [true, false, 'ocsphelper'],
								9 => [false, false, 'timestampsign'],
							],
							'extensions' => [
								'subjectAltName' => 'email:admin@email.tld',
								'basicConstraints' => 'CA:FALSE',
								'keyUsage' => 'Digital Signature, Non Repudiation, Key Encipherment',
								'extendedKeyUsage' => 'TLS Web Client Authentication, E-mail Protection',
								'subjectKeyIdentifier' => '76:21:81:44:79:1F:DC:85:E0:24:A1:1D:AA:8C:43:5B:0B:45:F9:48',
								'authorityKeyIdentifier' => '9D:6C:97:12:5D:29:8B:6D:C3:63:C0:0C:DF:28:99:18:81:17:61:69',
							],
							'isLibreSignRootCA' => false,
							'signingTime' => [
								'date' => '2025-10-20 13:31:43.000000',
								'timezone_type' => 1,
								'timezone' => '+00:00',
							],
							'chain' => [
								0 => [
									'name' => '/C=BR/ST=State of Company/L=City Name/O=Organization/OU=Organization Unit/CN=account:admin, admin',
									'subject' => [
										'C' => 'BR',
										'ST' => 'State of Company',
										'L' => 'City Name',
										'O' => 'Organization',
										'OU' => 'Organization Unit',
										'CN' => ['account:admin', 'admin'],
									],
									'hash' => '4a5a1475',
									'issuer' => [
										'C' => 'BR',
										'ST' => 'State of Company',
										'L' => 'City Name',
										'O' => 'Organization',
										'OU' => 'Organization Unit',
										'CN' => 'Common Name',
									],
									'version' => 2,
									'serialNumber' => '0x4700D96F34F501CF0EA141E75F20643844393FFF',
									'serialNumberHex' => '4700D96F34F501CF0EA141E75F20643844393FFF',
									'validFrom' => '251020132600Z',
									'validTo' => '261020132600Z',
									'validFrom_time_t' => 1760966760,
									'validTo_time_t' => 1792502760,
									'signatureTypeSN' => 'RSA-SHA256',
									'signatureTypeLN' => 'sha256WithRSAEncryption',
									'signatureTypeNID' => 668,
									'purposes' => [
										1 => [true, false, 'sslclient'],
										2 => [false, false, 'sslserver'],
										3 => [false, false, 'nssslserver'],
										4 => [true, false, 'smimesign'],
										5 => [true, false, 'smimeencrypt'],
										6 => [false, false, 'crlsign'],
										7 => [true, true, 'any'],
										8 => [true, false, 'ocsphelper'],
										9 => [false, false, 'timestampsign'],
									],
									'extensions' => [
										'keyUsage' => 'Digital Signature, Non Repudiation, Key Encipherment',
										'extendedKeyUsage' => 'TLS Web Client Authentication, E-mail Protection',
										'basicConstraints' => 'CA:FALSE',
										'subjectKeyIdentifier' => '76:21:81:44:79:1F:DC:85:E0:24:A1:1D:AA:8C:43:5B:0B:45:F9:48',
										'authorityKeyIdentifier' => '9D:6C:97:12:5D:29:8B:6D:C3:63:C0:0C:DF:28:99:18:81:17:61:69',
										'subjectAltName' => 'email:admin@email.tld',
									],
									'signature_validation' => [
										'id' => 1,
										'label' => 'Signature is valid.',
									],
									'isLibreSignRootCA' => false,
									'valid_from' => '2025-10-20T13:26:00+00:00',
									'valid_to' => '2026-10-20T13:26:00+00:00',
									'displayName' => '/C=BR/ST=State of Company/L=City Name/O=Organization/OU=Organization Unit/CN=account:admin, admin',
									'crl_validation' => 'missing',
									'crl_urls' => [],
								],
							],
							'crl_validation' => 'missing',
							'crl_urls' => [],
						],
					],
				],
			],
		];
	}
}
