<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\SetupCheck;

use OCA\Libresign\SetupCheck\PopplerSetupCheck;
use OCA\Libresign\Tests\Unit\SetupCheck\Mock\ExecMock;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PopplerSetupCheckTest extends TestCase {
	/** @var IL10N|MockObject */
	private $l10n;

	/** @var PopplerSetupCheck */
	private $check;

	protected function setUp(): void {
		parent::setUp();

		ExecMock::$commands = [];

		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')
			->willReturnCallback(fn ($text, $params = []) => vsprintf($text, $params));

		$this->check = new PopplerSetupCheck($this->l10n);
	}

	public function testBothToolsInstalled(): void {
		ExecMock::$commands['pdfsig -v 2>&1'] = [
			'output' => ['pdfsig version 25.03.0', 'extra line'],
			'result_code' => 0,
		];
		ExecMock::$commands['pdfinfo -v 2>&1'] = [
			'output' => ['pdfinfo version 25.03.0'],
			'result_code' => 0,
		];

		$result = $this->check->run();

		$this->assertEquals('success', $result->getSeverity());
		$this->assertEquals(
			'pdfsig version: 25.03.0, pdfinfo version: 25.03.0',
			$result->getDescription()
		);
	}

	public function testOnlyPdfsigInstalled(): void {
		ExecMock::$commands['pdfsig -v 2>&1'] = [
			'output' => ['pdfsig version 25.03.0'],
			'result_code' => 0,
		];
		ExecMock::$commands['pdfinfo -v 2>&1'] = [
			'output' => [],
			'result_code' => 127,
		];

		$result = $this->check->run();

		$this->assertEquals('info', $result->getSeverity());
		$this->assertStringContainsString('pdfinfo not installed or not working', $result->getDescription());
		$this->assertStringContainsString('Install the package poppler-utils', $result->getLinkToDoc());
	}

	public function testNoToolsInstalled(): void {
		ExecMock::$commands['pdfsig -v 2>&1'] = [
			'output' => [],
			'result_code' => 127,
		];
		ExecMock::$commands['pdfinfo -v 2>&1'] = [
			'output' => [],
			'result_code' => 127,
		];

		$result = $this->check->run();

		$this->assertEquals('info', $result->getSeverity());
		$this->assertStringContainsString(
			'pdfsig not installed or not working; pdfinfo not installed or not working',
			$result->getDescription()
		);
	}

	public function testPdfsigVersionParseFails(): void {
		ExecMock::$commands['pdfsig -v 2>&1'] = [
			'output' => ['pdfsig version 25.03.0'],
			'result_code' => 0,
		];
		ExecMock::$commands['pdfinfo -v 2>&1'] = [
			'output' => ['pdfinfo: unknown option'],
			'result_code' => 0,
		];

		$result = $this->check->run();

		$this->assertEquals('info', $result->getSeverity());
		$this->assertStringContainsString('pdfinfo not installed or not working', $result->getDescription());
		$this->assertStringNotContainsString('pdfsig', $result->getDescription());
	}

	public function testOnlyPdfinfoInstalledButParseFails(): void {
		ExecMock::$commands['pdfsig -v 2>&1'] = [
			'output' => [],
			'result_code' => 127,
		];
		ExecMock::$commands['pdfinfo -v 2>&1'] = [
			'output' => ['pdfinfo: unknown option'],
			'result_code' => 0,
		];

		$result = $this->check->run();

		$this->assertEquals('info', $result->getSeverity());
		$this->assertStringContainsString('pdfsig not installed or not working', $result->getDescription());
		$this->assertStringContainsString('pdfinfo not installed or not working', $result->getDescription());
	}

	public function testBothToolsParseFails(): void {
		ExecMock::$commands['pdfsig -v 2>&1'] = [
			'output' => ['pdfsig: error'],
			'result_code' => 0,
		];
		ExecMock::$commands['pdfinfo -v 2>&1'] = [
			'output' => ['pdfinfo: error'],
			'result_code' => 0,
		];

		$result = $this->check->run();

		$this->assertEquals('info', $result->getSeverity());
		$this->assertStringContainsString('pdfsig not installed or not working', $result->getDescription());
		$this->assertStringContainsString('pdfinfo not installed or not working', $result->getDescription());
	}

	public function testGetName(): void {
		$this->l10n->expects($this->once())
			->method('t')
			->with('Poppler utils')
			->willReturn('Poppler utils');
		$this->assertEquals('Poppler utils', $this->check->getName());
	}

	public function testGetCategory(): void {
		$this->assertEquals('system', $this->check->getCategory());
	}
}
