<?php

use OCA\Libresign\Handler\JSignPdfHandler;
use OCA\Libresign\Handler\Pkcs12Handler;
use OCA\Libresign\Service\FolderService;

final class Pkcs12HandlerTest extends \OCA\Libresign\Tests\Unit\TestCase {
	/** @var Pkcs12Handler */
	protected $pkcs12Handler;
	/** @var FolderService */
	protected $folderService;
	/** @var JSignPdfHandler */
	protected $jSignPdfHandler;

	public function setUp(): void {
		$this->folderService = $this->createMock(FolderService::class);
		$this->jSignPdfHandler = $this->createMock(JSignPdfHandler::class);
		$this->pkcs12Handler = new Pkcs12Handler(
			$this->folderService,
			$this->jSignPdfHandler
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
		$node = $this->createMock(\OCP\Files\Folder::class);
		$node->method('nodeExists')->will($this->returnValue(true));
		$node->method('get')->will($this->returnValue($node));
		$this->folderService->method('getFolder')->will($this->returnValue($node));
		$actual = $this->pkcs12Handler->getPfx('userId');
		$this->assertInstanceOf('\OCP\Files\Node', $actual);
	}
}
