<?php

namespace OCA\Libresign\Tests\Unit\Helper;

use OCA\Libresign\Helper\ConfigureCheckHelper;
use OCA\Libresign\Tests\Unit\TestCase;

class ConfigureCheckHelperTest extends TestCase {
	/** @var ConfigureCheckHelper */
	private $helper;

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

	public function providerJsonSerialize(): array {
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
