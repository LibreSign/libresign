<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\File;

use OCA\Libresign\Service\File\FileResponseData;
use OCA\Libresign\Service\File\FileResponseFormatter;
use PHPUnit\Framework\TestCase;
use stdClass;

class FileResponseFormatterTest extends TestCase {
	private FileResponseFormatter $formatter;

	protected function setUp(): void {
		parent::setUp();
		$this->formatter = new FileResponseFormatter();
	}

	public function testFormatsStdClassToArray(): void {
		$fileData = new stdClass();
		$fileData->uuid = 'test-uuid';
		$fileData->name = 'test.pdf';
		$fileData->status = 2;

		$data = new FileResponseData($this->createMock(\OCA\Libresign\Db\File::class), $fileData);
		$result = $this->formatter->toArray($data);

		$this->assertIsArray($result);
		$this->assertEquals('test-uuid', $result['uuid']);
		$this->assertEquals('test.pdf', $result['name']);
		$this->assertEquals(2, $result['status']);
	}

	public function testSortsArrayByKeys(): void {
		$fileData = new stdClass();
		$fileData->zeta = 'last';
		$fileData->alpha = 'first';
		$fileData->beta = 'middle';

		$data = new FileResponseData($this->createMock(\OCA\Libresign\Db\File::class), $fileData);
		$result = $this->formatter->toArray($data);

		$keys = array_keys($result);
		$this->assertEquals(['alpha', 'beta', 'zeta'], $keys);
	}

	public function testPreservesNestedObjects(): void {
		$fileData = new stdClass();
		$fileData->uuid = 'test-uuid';
		$requested_by = new stdClass();
		$requested_by->userId = 'admin';
		$requested_by->displayName = 'Administrator';
		$fileData->requested_by = $requested_by;

		$data = new FileResponseData($this->createMock(\OCA\Libresign\Db\File::class), $fileData);
		$result = $this->formatter->toArray($data);

		$this->assertIsArray($result['requested_by']);
		$this->assertEquals('admin', $result['requested_by']['userId']);
		$this->assertEquals('Administrator', $result['requested_by']['displayName']);
	}
}
