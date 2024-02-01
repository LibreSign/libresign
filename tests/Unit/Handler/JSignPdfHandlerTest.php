<?php

namespace OCA\Libresign\Tests\Unit\Service;

use Jeidison\JSignPDF\JSignPDF;
use OCA\Libresign\Handler\JSignPdfHandler;
use OCP\AppFramework\Services\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @internal
 */
final class JSignPdfHandlerTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private JSignPdfHandler $class;
	private IAppConfig|MockObject $appConfig;
	public function setUp(): void {
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->class = new JSignPdfHandler(
			$this->appConfig
		);
	}

	public function testSignExistingFileSuccess() {
		$inputFile = $this->createMock(\OC\Files\Node\File::class);
		$mock = $this->createMock(JSignPDF::class);
		$mock->method('sign')->willReturn('content');
		$this->class->setJSignPdf($mock);
		$this->class->setInputFile($inputFile);
		$this->class->setCertificate('');
		$this->class->setPassword('password');
		$actual = $this->class->sign();
		$this->assertEquals('content', $actual);
	}
}
