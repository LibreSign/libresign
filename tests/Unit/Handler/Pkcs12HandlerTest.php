<?php

use OC\SystemConfig;
use OCA\Libresign\Handler\CertificateEngine\CfsslHandler;
use OCA\Libresign\Handler\CertificateEngine\Handler as CertificateEngineHandler;
use OCA\Libresign\Handler\CertificateEngine\OpenSslHandler;
use OCA\Libresign\Handler\JSignPdfHandler;
use OCA\Libresign\Handler\Pkcs12Handler;
use OCA\Libresign\Service\FolderService;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;

final class Pkcs12HandlerTest extends \OCA\Libresign\Tests\Unit\TestCase {
	protected Pkcs12Handler $pkcs12Handler;
	protected FolderService|MockObject $folderService;
	private IAppConfig|MockObject $appConfig;
	private IURLGenerator|MockObject $urlGenerator;
	private SystemConfig $systemConfig;
	private CfsslHandler|MockObject $cfsslHandler;
	private IL10N|MockObject $l10n;
	private JSignPdfHandler|MockObject $jSignPdfHandler;
	private OpenSslHandler|MockObject $openSslHandler;
	private CertificateEngineHandler|MockObject $certificateEngineHandler;
	private array $cfsslHandlerBuffer = [];

	public function setUp(): void {
		$this->folderService = $this->createMock(FolderService::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->systemConfig = $this->createMock(SystemConfig::class);
		$this->certificateEngineHandler = $this->createMock(CertificateEngineHandler::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));
		$this->jSignPdfHandler = $this->createMock(JSignPdfHandler::class);
		$this->pkcs12Handler = new Pkcs12Handler(
			$this->folderService,
			$this->appConfig,
			$this->urlGenerator,
			$this->systemConfig,
			$this->certificateEngineHandler,
			$this->l10n,
			$this->jSignPdfHandler,
		);
	}

	public function testSavePfxWhenPfxFileIsAFolder() {
		$node = $this->createMock(\OCP\Files\Folder::class);
		$node->method('nodeExists')->will($this->returnValue(true));
		$node->method('get')->will($this->returnValue($node));
		$this->folderService->method('getFolder')->will($this->returnValue($node));

		$this->expectExceptionMessage('path signature.pfx already exists and is not a file!');
		$this->pkcs12Handler->savePfx('userId', 'content');
	}

	public function testSavePfxWhenPfxFileExsitsAndIsAFile() {
		$node = $this->createMock(\OCP\Files\Folder::class);
		$node->method('nodeExists')->will($this->returnValue(true));
		$file = $this->createMock(\OCP\Files\File::class);
		$node->method('get')->will($this->returnValue($file));
		$this->folderService->method('getFolder')->will($this->returnValue($node));

		$actual = $this->pkcs12Handler->savePfx('userId', 'content');
		$this->assertEquals('content', $actual);
	}

	public function testGetPfxWithInvalidPfx() {
		$node = $this->createMock(\OCP\Files\Folder::class);
		$node->method('nodeExists')->will($this->returnValue(false));
		$this->folderService->method('getFolder')->will($this->returnValue($node));
		$this->expectExceptionMessage('Password to sign not defined. Create a password to sign');
		$this->expectExceptionCode(400);
		$this->pkcs12Handler->getPfx('userId');
	}

	public function testGetPfxOk() {
		$folder = $this->createMock(\OCP\Files\Folder::class);
		$folder->method('nodeExists')->will($this->returnValue(true));
		$file = $this->createMock(\OCP\Files\File::class);
		$file->method('getContent')
			->willReturn('valid pfx content');
		$folder->method('get')->will($this->returnValue($file));
		$this->folderService->method('getFolder')->will($this->returnValue($folder));
		$actual = $this->pkcs12Handler->getPfx('userId');
		$this->assertEquals('valid pfx content', $actual);
	}

	public function testGetFooterWithoutValidationSite() {
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->appConfig
			->method('getAppValue')
			->willReturn('');
		$this->pkcs12Handler = new Pkcs12Handler(
			$this->folderService,
			$this->appConfig,
			$this->urlGenerator,
			$this->systemConfig,
			$this->certificateEngineHandler,
			$this->l10n,
			$this->jSignPdfHandler,
		);
		$file = $this->createMock(\OCP\Files\File::class);
		$actual = $this->pkcs12Handler->getFooter($file, 'uuid');
		$this->assertEmpty($actual);
	}

	public function testGetFooterWithSuccess() {
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->appConfig
			->method('getAppValue')
			->willReturnCallback(function ($key, $default) {
				switch ($key) {
					case 'add_footer': return '1';
					case 'validation_site': return 'http://test.coop';
					case 'write_qrcode_on_footer': return '1';
					case 'footer_link_to_site': return 'https://libresign.coop';
					case 'footer_first_row': return 'Digital signed by LibreSign.';
					case 'footer_second_row': return 'Validate in %s.';
				}
			});
		$this->pkcs12Handler = new Pkcs12Handler(
			$this->folderService,
			$this->appConfig,
			$this->urlGenerator,
			$this->systemConfig,
			$this->certificateEngineHandler,
			$this->l10n,
			$this->jSignPdfHandler,
		);

		$file = $this->createMock(\OCP\Files\File::class);
		$file->method('getName')
			->willReturn('small_valid.pdf');
		$file->method('getContent')
			->willReturn(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'));
		$actual = $this->pkcs12Handler->getFooter($file, 'uuid');
		$this->assertEquals(18615, strlen($actual));
	}

	public function cfsslHandlerCallbackToGetSetArguments($functionName, $value = null) {
		if (strpos($functionName, 'set') === 0) {
			$this->cfsslHandlerBuffer[substr($functionName, 3)] = $value;
		}
		return true;
	}

	public function cfsslHandlerCallbackToGetSetReturn($functionName) {
		if (strpos($functionName, 'set') === 0) {
			return $this->cfsslHandler;
		}
		if (isset($this->cfsslHandlerBuffer[substr($functionName, 3)])) {
			return $this->cfsslHandlerBuffer[substr($functionName, 3)];
		}
		return null;
	}
}
