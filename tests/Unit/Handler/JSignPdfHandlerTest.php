<?php

namespace OCA\Libresign\Tests\Unit\Service;

use Jeidison\JSignPDF\JSignPDF;
use OCA\Libresign\Handler\JSignPdfHandler;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @internal
 */
final class JSignPdfHandlerTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private JSignPdfHandler $class;
	private IConfig|MockObject $config;
	public function setUp(): void {
		$this->config = $this->createMock(IConfig::class);
		$this->class = new JSignPdfHandler(
			$this->config
		);
	}

	public function testSignExistingFileSuccess() {
		$inputFile = $this->createMock(\OC\Files\Node\File::class);
		$certificate = $this->createMock(\OC\Files\Node\File::class);
		$mock = $this->createMock(JSignPDF::class);
		$mock->method('sign')->willReturn('content');
		$this->class->setJSignPdf($mock);
		$this->class->setInputFile($inputFile);
		$this->class->setCertificate($certificate);
		$this->class->setPassword('password');
		$actual = $this->class->sign();
		$this->assertEquals('content', $actual);
	}
}
