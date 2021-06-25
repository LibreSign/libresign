<?php

use OCA\Libresign\Handler\PkcsHandler;
use OCA\Libresign\Service\FolderService;

final class PkcsHandlerTest extends \OCA\Libresign\Tests\Unit\TestCase {
	/** @var PkcsHandler */
	protected $pkcsHandler;
	/** @var FolderService */
	protected $folderService;

	public function setUp(): void {
		$this->folderService = $this->createMock(FolderService::class);
		$this->pkcsHandler = new PkcsHandler(
			$this->folderService
		);
	}

	public function testGetPfxWithInvalidUser() {
		$this->expectErrorMessage('Backends provided no user object for invalidUser');
		$this->pkcsHandler->getPfx('invalidUser');
	}

	public function testGetPfxWithInvalidPfx() {
		$backend = $this->createMock(\OC\User\Database::class);
		$backend->method('implementsActions')
			->willReturn(true);
		$backend->method('userExists')
			->willReturn(true);
		$backend->method('getRealUID')
			->willReturn('userId');
		$userManager = \OC::$server->getUserManager();
		$userManager->clearBackends();
		$userManager->registerBackend($backend);

		$node = $this->createMock(\OCP\Files\Folder::class);
		$node->method('nodeExists')->will($this->returnValue(false));
		$this->folderService->method('getFolder')->will($this->returnValue($node));
		$this->expectErrorMessage('Password to sign not defined. Create a password to sign');
		$this->expectExceptionCode(400);
		$this->pkcsHandler->getPfx('userId');
	}

	public function testGetPfxOk() {
		$backend = $this->createMock(\OC\User\Database::class);
		$backend->method('implementsActions')
			->willReturn(true);
		$backend->method('userExists')
			->willReturn(true);
		$backend->method('getRealUID')
			->willReturn('userId');
		$userManager = \OC::$server->getUserManager();
		$userManager->clearBackends();
		$userManager->registerBackend($backend);

		$node = $this->createMock(\OCP\Files\Folder::class);
		$node->method('nodeExists')->will($this->returnValue(true));
		$node->method('get')->will($this->returnValue($node));
		$this->folderService->method('getFolder')->will($this->returnValue($node));
		$actual = $this->pkcsHandler->getPfx('userId');
		$this->assertInstanceOf('\OCP\Files\Node', $actual);
	}
}
