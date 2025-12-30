<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\FileElement;
use OCA\Libresign\Service\FileElementService;
use OCA\Libresign\Tests\Unit\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class FileElementServiceTest extends TestCase {
	private function getService(): FileElementService {
		$fileMapper = $this->createMock(\OCA\Libresign\Db\FileMapper::class);
		$fileElementMapper = $this->createMock(\OCA\Libresign\Db\FileElementMapper::class);
		$timeFactory = $this->createMock(\OCP\AppFramework\Utility\ITimeFactory::class);

		return new FileElementService($fileMapper, $fileElementMapper, $timeFactory);
	}

	#[DataProvider('dataFormatVisibleElements')]
	public function testFormatVisibleElements(array $visibleElements, array $expectedChecks): void {
		$service = $this->getService();

		$fileElements = array_map(function ($data) {
			$element = new FileElement();
			$element->setId($data['id']);
			$element->setSignRequestId($data['sign_request_id']);
			$element->setType($data['type']);
			$element->setFileId($data['file_id']);
			$element->setPage($data['page']);
			$element->setUrx((int)$data['urx']);
			$element->setUry((int)$data['ury']);
			$element->setLlx((int)$data['llx']);
			$element->setLly((int)$data['lly']);
			$element->setMetadata($data['metadata']);
			return $element;
		}, $visibleElements);

		$result = $service->formatVisibleElements($fileElements);

		$this->assertIsArray($result);

		foreach ($expectedChecks as $index => $checks) {
			$this->assertArrayHasKey($index, $result);
			$coords = $result[$index]['coordinates'];
			foreach ($checks as $key => $value) {
				$this->assertEquals($value, $coords[$key], "unexpected {$key} for element {$index}");
			}
		}
	}

	public static function dataFormatVisibleElements(): array {
		return [
			'single with string coords' => [
				[
					[
						'id' => 123,
						'sign_request_id' => 45,
						'type' => 'signature',
						'file_id' => 67,
						'page' => 2,
						'urx' => '300',
						'ury' => '400',
						'llx' => '100',
						'lly' => '200',
						'metadata' => [ 'd' => [ ['w' => 0, 'h' => 800], ['w' => 0, 'h' => 900] ] ],
					],
				],
				[
					0 => [ 'page' => 2, 'urx' => 300, 'ury' => 400, 'llx' => 100, 'lly' => 200, 'left' => 100, 'top' => 500, 'width' => 200, 'height' => 200 ],
				],
			],
			'multiple elements different sizes' => [
				[
					[
						'id' => 1,
						'sign_request_id' => 10,
						'type' => 'text',
						'file_id' => 5,
						'page' => 1,
						'urx' => 50,
						'ury' => 150,
						'llx' => 10,
						'lly' => 100,
						'metadata' => [ 'd' => [ ['w' => 0, 'h' => 200] ] ],
					],
					[
						'id' => 2,
						'sign_request_id' => 11,
						'type' => 'checkbox',
						'file_id' => 5,
						'page' => 1,
						'urx' => 120,
						'ury' => 180,
						'llx' => 100,
						'lly' => 160,
						'metadata' => [ 'd' => [ ['w' => 0, 'h' => 200] ] ],
					],
				],
				[
					0 => [ 'page' => 1, 'width' => 40, 'height' => 50 ],
					1 => [ 'page' => 1, 'width' => 20, 'height' => 20 ],
				],
			],
			'no metadata fallback uses given dimension' => [
				[
					[
						'id' => 9,
						'sign_request_id' => 99,
						'type' => 'stamp',
						'file_id' => 8,
						'page' => 1,
						'urx' => 200,
						'ury' => 300,
						'llx' => 50,
						'lly' => 100,
						'metadata' => [ 'd' => [ ['w' => 0, 'h' => 350] ] ],
					],
				],
				[
					0 => [ 'page' => 1, 'width' => 150, 'height' => 200, 'top' => 50 ],
				],
			],
		];
	}
}
