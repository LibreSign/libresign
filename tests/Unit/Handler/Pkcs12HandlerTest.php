<?php

use OC\SystemConfig;
use OCA\Libresign\Handler\CfsslHandler;
use OCA\Libresign\Handler\Pkcs12Handler;
use OCA\Libresign\Service\FolderService;
use OCP\IConfig;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;

final class Pkcs12HandlerTest extends \OCA\Libresign\Tests\Unit\TestCase {
	/** @var Pkcs12Handler */
	protected $pkcs12Handler;
	/** @var FolderService|MockObject */
	protected $folderService;
	/** @var IConfig|MockObject */
	private $config;
	/** @var SystemConfig */
	private $systemConfig;
	/** @var CfsslHandler|MockObject */
	private $cfsslHandler;
	/** @var IL10N|MockObject */
	private $l10n;

	public function setUp(): void {
		$this->folderService = $this->createMock(FolderService::class);
		$this->config = $this->createMock(IConfig::class);
		$this->systemConfig = $this->createMock(SystemConfig::class);
		$this->cfsslHandler = $this->createMock(CfsslHandler::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));
		$this->pkcs12Handler = new Pkcs12Handler(
			$this->folderService,
			$this->config,
			$this->systemConfig,
			$this->cfsslHandler,
			$this->l10n
		);
	}

	public function testSavePfxWhenPfxFileIsAFolder() {
		$node = $this->createMock(\OCP\Files\Folder::class);
		$node->method('nodeExists')->will($this->returnValue(true));
		$node->method('get')->will($this->returnValue($node));
		$this->folderService->method('getFolder')->will($this->returnValue($node));

		$this->expectErrorMessage('path signature.pfx already exists and is not a file!');
		$this->pkcs12Handler->savePfx('userId', 'content');
	}

	public function testSavePfxWhenPfxFileExsitsAndIsAFile() {
		$node = $this->createMock(\OCP\Files\Folder::class);
		$node->method('nodeExists')->will($this->returnValue(true));
		$file = $this->createMock(\OCP\Files\File::class);
		$node->method('get')->will($this->returnValue($file));
		$this->folderService->method('getFolder')->will($this->returnValue($node));

		$actual = $this->pkcs12Handler->savePfx('userId', 'content');
		$this->assertInstanceOf(\OCP\Files\File::class, $actual);
	}

	public function testGetPfxWithInvalidPfx() {
		$node = $this->createMock(\OCP\Files\Folder::class);
		$node->method('nodeExists')->will($this->returnValue(false));
		$this->folderService->method('getFolder')->will($this->returnValue($node));
		$this->expectErrorMessage('Password to sign not defined. Create a password to sign');
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
		$this->assertInstanceOf('\OCP\Files\Node', $actual);
	}

	public function testWriteFooterWithoutValidationSite() {
		$this->config = $this->createMock(IConfig::class);
		$this->config
			->method('getAppValue')
			->willReturn(null);
		$this->pkcs12Handler = new Pkcs12Handler(
			$this->folderService,
			$this->config,
			$this->systemConfig,
			$this->cfsslHandler,
			$this->l10n
		);
		$file = $this->createMock(\OCP\Files\File::class);
		$actual = $this->pkcs12Handler->writeFooter($file, 'uuid');
		$this->assertEmpty($actual);
	}

	public function testWriteFooterWithSuccess() {
		$this->config = $this->createMock(IConfig::class);
		$this->config
			->method('getAppValue')
			->willReturnCallback(function ($appid, $key, $default) {
				switch ($key) {
					case 'add_footer': return true;
					case 'validation_site': return 'http://test.coop';
					case 'write_qrcode_on_footer': return true;
				}
			});
		$this->pkcs12Handler = new Pkcs12Handler(
			$this->folderService,
			$this->config,
			$this->systemConfig,
			$this->cfsslHandler,
			$this->l10n
		);

		$file = $this->createMock(\OCP\Files\File::class);
		$file->method('getName')
			->willReturn('small_valid.pdf');
		$file->method('getContent')
			->willReturn(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'));
		$actual = $this->pkcs12Handler->writeFooter($file, 'uuid');
		$this->assertEquals(16627, strlen($actual));
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
