<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Helper;

use OCA\Libresign\Helper\ConfigureCheckHelper;
use OCA\Libresign\Tests\Unit\TestCase;

class ConfigureCheckHelperTest extends TestCase {
	private ConfigureCheckHelper $helper;

	public function setUp(): void {
		parent::setUp();
		$this->helper = new ConfigureCheckHelper();
	}

	/**
	 * @dataProvider providerJsonSerialize()
	 */
	public function testJsonSerialize(string $type, string $message, string $resource, string $tip, array $expected): void {
		if ($type === 'success') {
			$this->helper->setSuccessMessage($message);
		} elseif ($type === 'error') {
			$this->helper->setErrorMessage($message);
		}
		$this->helper->setResource($resource);
		$this->helper->setTip($tip);
		$actual = $this->helper->jsonSerialize();
		$this->assertEqualsCanonicalizing($expected, $actual);
	}

	public static function providerJsonSerialize(): array {
		return [
			[
				'success',
				'message',
				'resource',
				'tip',
				[
					'status' => 'success',
					'message' => 'message',
					'resource' => 'resource',
					'tip' => 'tip',
				],
			],
			[
				'error',
				'message',
				'resource',
				'tip',
				[
					'status' => 'error',
					'message' => 'message',
					'resource' => 'resource',
					'tip' => 'tip',
				],
			],
			[
				'error',
				'message',
				'resource',
				'',
				[
					'status' => 'error',
					'message' => 'message',
					'resource' => 'resource',
					'tip' => '',
				],
			],
			[
				'error',
				'message',
				'',
				'tip',
				[
					'status' => 'error',
					'message' => 'message',
					'resource' => '',
					'tip' => 'tip',
				],
			],
			[
				'error',
				'',
				'resource',
				'tip',
				[
					'status' => 'error',
					'message' => '',
					'resource' => 'resource',
					'tip' => 'tip',
				],
			],
		];
	}
}
