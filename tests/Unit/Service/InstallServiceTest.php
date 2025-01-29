<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022, Vitor Mattos <vitor@php.rio>
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
 *
 */

namespace OCA\Libresign\Tests\Unit\Service;

use bovigo\vfs\vfsStream;
use OCA\Libresign\Handler\CertificateEngine\Handler as CertificateEngineHandler;
use OCA\Libresign\Service\Install\InstallService;
use OCA\Libresign\Service\Install\SignSetupService;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IRootFolder;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\ICacheFactory;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\BufferedOutput;

final class InstallServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private ICacheFactory|MockObject $cacheFactory;
	private IClientService|MockObject $clientService;
	private CertificateEngineHandler|MockObject $certificateEngineHandler;
	private IConfig|MockObject $config;
	private IAppConfig|MockObject $appConfig;
	private IRootFolder|MockObject $rootFolder;
	private LoggerInterface|MockObject $logger;
	private SignSetupService|MockObject $ignSetupService;
	private IAppDataFactory|MockObject $appDataFactory;

	public function setUp(): void {
		parent::setUp();
	}

	protected function getInstallService(): InstallService {
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->clientService = $this->createMock(IClientService::class);
		$this->certificateEngineHandler = $this->createMock(CertificateEngineHandler::class);
		$this->config = $this->createMock(IConfig::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->ignSetupService = $this->createMock(SignSetupService::class);
		$this->appDataFactory = $this->createMock(IAppDataFactory::class);
		return new InstallService(
			$this->cacheFactory,
			$this->clientService,
			$this->certificateEngineHandler,
			$this->config,
			$this->appConfig,
			$this->rootFolder,
			$this->logger,
			$this->ignSetupService,
			$this->appDataFactory
		);
	}

	/**
	 * @dataProvider providerDownloadCli
	 */
	public function testDownloadCli(string $url, string $filename, string $content, string $hash, string $algorithm, string $expectedOutput): void {
		$installService = $this->getInstallService();
		$output = new BufferedOutput();
		$installService->setOutput($output);

		if ($content) {
			vfsStream::setup('download');
			$path = 'vfs://download/dummy.svg';
			file_put_contents($path, $content);
		} else {
			$path = '';
		}

		self::invokePrivate($installService, 'downloadCli', [$url, $filename, $path, $hash, $algorithm]);
		$actual = $output->fetch();
		$this->assertEquals($expectedOutput, $actual);
	}

	public function providerDownloadCli(): array {
		return [
			[
				'url' => 'http://localhost/apps/libresign/img/app.svg',
				'filename' => 'app.svg',
				'content' => '',
				'hash' => '',
				'algorithm' => 'md5',
				'expectedOutput' => <<<EXPECTEDOUTPUT
					Downloading app.svg...
					    0 [>---------------------------]
					Failure on download app.svg, empty file, try again

					EXPECTEDOUTPUT
			],
			[
				'url' => 'http://localhost/apps/libresign/img/appInvalid.svg',
				'filename' => 'appInvalid.svg',
				'content' => 'content',
				'hash' => 'invalidContent',
				'algorithm' => 'md5',
				'expectedOutput' => <<<EXPECTEDOUTPUT
					Downloading appInvalid.svg...
					    0 [>---------------------------]
					Failure on download appInvalid.svg try again
					Invalid md5

					EXPECTEDOUTPUT
			],
			[
				'url' => 'http://localhost/apps/libresign/img/appInvalid.svg',
				'filename' => 'appInvalid.svg',
				'content' => 'content',
				'hash' => 'invalidContent',
				'algorithm' => 'sha256',
				'expectedOutput' => <<<EXPECTEDOUTPUT
					Downloading appInvalid.svg...
					    0 [>---------------------------]
					Failure on download appInvalid.svg try again
					Invalid sha256

					EXPECTEDOUTPUT
			],
			[
				'url' => 'http://localhost/apps/libresign/img/validContent.svg',
				'filename' => 'validContent.svg',
				'content' => 'content',
				'hash' => hash('sha256', 'content'),
				'algorithm' => 'sha256',
				'expectedOutput' => <<<EXPECTEDOUTPUT
					Downloading validContent.svg...
					    0 [>---------------------------]

					EXPECTEDOUTPUT
			],
		];
	}

	/**
	 * @dataProvider providerGetFolder
	 * @runInSeparateProcess
	 */
	public function testGetFolder(string $architecture, string $path, string $expectedFolderName): void {
		$install = \OCP\Server::get(\OCA\Libresign\Service\Install\InstallService::class);
		if (!empty($architecture)) {
			$install->setArchitecture($architecture);
		}
		$folder = self::invokePrivate($install, 'getFolder', [$path]);
		$this->assertEquals($folder->getName(), $expectedFolderName);
	}

	public static function providerGetFolder(): array {
		return [
			['', '', php_uname('m')],
			['', 'test', 'test'],
			['', 'test/folder1', 'folder1'],
			['', 'test/folder1/folder2', 'folder2'],
			['aarch64', '', 'aarch64'],
			['aarch64', 'test', 'test'],
			['aarch64', 'test/folder1', 'folder1'],
			['aarch64', 'test/folder1/folder2', 'folder2'],
			['x86_64', '', 'x86_64'],
			['x86_64', 'test', 'test'],
			['x86_64', 'test/folder1', 'folder1'],
			['x86_64', 'test/folder1/folder2', 'folder2'],
		];
	}
}
