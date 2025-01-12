<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use Jeidison\JSignPDF\JSignPDF;
use OCA\Libresign\Handler\JSignPdfHandler;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final class JSignPdfHandlerTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private JSignPdfHandler $class;
	private IAppConfig&MockObject $appConfig;
	private LoggerInterface&MockObject $loggerInterface;
	public function setUp(): void {
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->loggerInterface = $this->createMock(LoggerInterface::class);
		$this->class = new JSignPdfHandler(
			$this->appConfig,
			$this->loggerInterface,
		);
	}

	public function testSignExistingFileSuccess():void {
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
