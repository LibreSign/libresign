<?php

declare(strict_types=1);

use bovigo\vfs\vfsStream;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\CertificateHelper;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

final class CertificateHelperTest extends \OCA\Libresign\Tests\Unit\TestCase {
	public function testSaveFileWithSuccess(): void {
		vfsStream::setup('home');
		$filename = vfsStream::url('home/test.txt');
		$content = 'This is a test content.';

		CertificateHelper::saveFile($filename, $content);

		$this->assertFileExists($filename);
		$this->assertSame($content, file_get_contents($filename));
	}

	public function testSaveFileWithError(): void {
		$this->expectException(LibresignException::class);

		vfsStream::setup('home', 0444);
		$filename = vfsStream::url('home/test.txt');

		@CertificateHelper::saveFile($filename, '');
	}

	#[DataProvider('dataProviderArrayToIni')]
	public function testArrayToIni(array $array, string $expected): void {
		$result = CertificateHelper::arrayToIni($array);
		$this->assertSame($expected, $result);
	}

	public static function dataProviderArrayToIni(): array {
		return [
			'flat key-value' => [
				['key' => 'value'],
				"key = value\n",
			],
			'multiple keys' => [
				['key' => 'value', 'key2' => 'value2'],
				"key = value\nkey2 = value2\n",
			],
			'nested section' => [
				['key' => 'value', 'key2' => ['subkey' => 'subvalue']],
				"key = value\n\n[key2]\nsubkey = subvalue\n",
			],
			'value with newlines' => [
				['key' => "value\nwith\nnewlines"],
				"key = \"value\nwith\nnewlines\"\n",
			],
			'named section' => [
				['section' => ['key1' => 'value1', 'key2' => 'value2']],
				"[section]\nkey1 = value1\nkey2 = value2\n",
			],
			'section with subsection' => [
				['section' => ['subsection' => ['key' => 'value']]],
				"[section]\n[subsection]\nkey = value\n",
			],
			'section with newline value' => [
				['section' => ['key' => "value\nwith\nnewlines"]],
				"[section]\nkey = \"value\nwith\nnewlines\"\n",
			],
			'nested with newlines' => [
				['section' => ['subsection' => ['key1' => 'value1', 'key2' => "value2\nwith\nnewlines"]]],
				"[section]\n[subsection]\nkey1 = value1\nkey2 = \"value2\nwith\nnewlines\"\n",
			],
			'duplicate key override' => [
				['key' => 'value1', 'key' => 'value2'],
				"key = value2\n",
			],
			'empty section' => [
				['section' => []],
				"[section]\n",
			],
			'integer value' => [
				['key' => 123],
				"key = 123\n",
			],
			'float value' => [
				['key' => 123.45],
				"key = 123.45\n",
			],
			'boolean true' => [
				['key' => true],
				"key = 1\n",
			],
			'boolean false' => [
				['key' => false],
				"key = 0\n",
			],
			'null value in section' => [
				['section' => ['key' => null]],
				"[section]\nkey = \n",
			],
		];
	}
}
