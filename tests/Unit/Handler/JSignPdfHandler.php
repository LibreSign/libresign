<?php

namespace OCA\Libresign\Tests\Unit\Service;

use Jeidison\JSignPDF\JSignPDF;
use OCA\Libresign\Handler\JSignPdfHandler;

/**
 * @internal
 */
final class JSignPdfHandlerTest extends \OCA\Libresign\Tests\Unit\TestCase {
	/** @var JSignPdfHandler */
	private $class;
	public function setUp(): void {
		$this->class = new JSignPdfHandler();
	}

	public function testSignExistingFileSuccess() {
		$inputFile = $this->createMock(\OC\Files\Node\File::class);
		$certificate = $this->createMock(\OC\Files\Node\File::class);
		$mock = $this->createMock(JSignPDF::class);
		$mock->method('sign')->willReturn('content');
		$this->class->setJSignPdf($mock);
		$actual = $this->class->sign($inputFile, $certificate, 'password');
		$this->assertIsArray($actual);
		$this->assertCount(2, $actual);
		$this->assertArrayHasKey(1, $actual);
		$this->assertEquals('content', $actual[1]);
	}
}
