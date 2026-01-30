<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Identify;

use OCA\Libresign\Service\Identify\ResultFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ResultFilterTest extends TestCase {
	public function testUnifyPrefersCustomLabelWhenDuplicateShareWith(): void {
		$filter = new ResultFilter();
		$items = [
			[
				'label' => '+5521993408474',
				'shareWithDisplayNameUnique' => '+5521993408474',
				'value' => [
					'shareWith' => '+5521993408474',
				],
			],
			[
				'label' => 'admin',
				'shareWithDisplayNameUnique' => '+5521993408474',
				'value' => [
					'shareWith' => '+5521993408474',
				],
			],
		];

		$result = $filter->unify([$items]);

		$this->assertCount(1, $result);
		$this->assertSame('admin', $result[0]['label']);
	}

	public function testUnifyFallsBackToNumericLabelWhenNoCustomLabel(): void {
		$filter = new ResultFilter();
		$items = [
			[
				'label' => '+5521993408474',
				'shareWithDisplayNameUnique' => '+5521993408474',
				'value' => [
					'shareWith' => '+5521993408474',
				],
			],
			[
				'label' => '+5521993408474',
				'shareWithDisplayNameUnique' => '+5521993408474',
				'value' => [
					'shareWith' => '+5521993408474',
				],
			],
		];

		$result = $filter->unify([$items]);

		$this->assertCount(1, $result);
		$this->assertSame('+5521993408474', $result[0]['label']);
	}

	#[DataProvider('providerScoreAndSelectBestResult')]
	public function testUnifySelectsItemWithHighestScore(
		array $items,
		string $expectedLabel,
		string $expectedShareWith,
	): void {
		$filter = new ResultFilter();
		$result = $filter->unify([$items]);

		$this->assertCount(1, $result, 'Should have exactly 1 result after unifying duplicates');
		$this->assertSame($expectedLabel, $result[0]['label']);
		$this->assertSame($expectedShareWith, $result[0]['value']['shareWith']);
	}

	public function testUnifySkipsItemsWithoutShareWith(): void {
		$filter = new ResultFilter();
		$items = [
			[
				'label' => 'Valid Item',
				'shareWithDisplayNameUnique' => 'valid',
				'value' => [
					'shareWith' => 'valid',
				],
			],
			[
				'label' => 'Invalid Item',
				'shareWithDisplayNameUnique' => 'invalid',
				'value' => [
					// Missing shareWith
				],
			],
		];

		$result = $filter->unify([$items]);

		$this->assertCount(1, $result);
		$this->assertSame('Valid Item', $result[0]['label']);
	}

	public function testUnifyHandlesMultipleListsCorrectly(): void {
		$filter = new ResultFilter();
		$list1 = [
			[
				'label' => 'User 1',
				'shareWithDisplayNameUnique' => 'user1',
				'value' => ['shareWith' => 'user1'],
			],
		];
		$list2 = [
			[
				'label' => 'User 2',
				'shareWithDisplayNameUnique' => 'user2',
				'value' => ['shareWith' => 'user2'],
			],
		];

		$result = $filter->unify([$list1, $list2]);

		$this->assertCount(2, $result);
	}

	public function testUnifyRemovesDuplicatesAcrossMultipleLists(): void {
		$filter = new ResultFilter();
		$list1 = [
			[
				'label' => 'Numeric',
				'shareWithDisplayNameUnique' => '+5521993408474',
				'value' => ['shareWith' => '+5521993408474'],
			],
		];
		$list2 = [
			[
				'label' => 'Contact Name',
				'shareWithDisplayNameUnique' => '+5521993408474',
				'value' => ['shareWith' => '+5521993408474'],
			],
		];

		$result = $filter->unify([$list1, $list2]);

		$this->assertCount(1, $result, 'Should merge duplicates from different lists');
		$this->assertSame('Contact Name', $result[0]['label'], 'Should keep last item when scores tie');
	}

	public function testUnifyHandlesEmptyLabelAsZeroScore(): void {
		$filter = new ResultFilter();
		$items = [
			[
				'label' => '',
				'shareWithDisplayNameUnique' => 'test',
				'value' => ['shareWith' => 'test'],
			],
			[
				'label' => 'Test Label',
				'shareWithDisplayNameUnique' => 'test',
				'value' => ['shareWith' => 'test'],
			],
		];

		$result = $filter->unify([$items]);

		$this->assertCount(1, $result);
		$this->assertSame('Test Label', $result[0]['label']);
	}

	public function testExcludeEmptyFiltersOutEmptyShareWith(): void {
		$filter = new ResultFilter();
		$items = [
			['label' => 'Valid', 'value' => ['shareWith' => 'valid']],
			['label' => 'Empty', 'value' => ['shareWith' => '']],
			['label' => 'None', 'value' => ['shareWith' => null]],
		];

		$result = $filter->excludeEmpty($items);

		$this->assertCount(1, $result);
		$this->assertSame('Valid', $result[0]['label']);
	}

	public static function providerScoreAndSelectBestResult(): array {
		return [
			'numeric label vs custom label' => [
				'items' => [
					[
						'label' => '+5521993408474',
						'shareWithDisplayNameUnique' => '+5521993408474',
						'value' => ['shareWith' => '+5521993408474'],
					],
					[
						'label' => 'John Doe',
						'shareWithDisplayNameUnique' => '+5521993408474',
						'value' => ['shareWith' => '+5521993408474'],
					],
				],
				'expectedLabel' => 'John Doe',
				'expectedShareWith' => '+5521993408474',
			],
			'label matches shareWithDisplayNameUnique vs custom' => [
				'items' => [
					[
						'label' => 'john@example.com',
						'shareWithDisplayNameUnique' => 'john@example.com',
						'value' => ['shareWith' => 'john@example.com'],
					],
					[
						'label' => 'John Doe',
						'shareWithDisplayNameUnique' => 'john@example.com',
						'value' => ['shareWith' => 'john@example.com'],
					],
				],
				'expectedLabel' => 'John Doe',
				'expectedShareWith' => 'john@example.com',
			],
			'label matches shareWith vs custom' => [
				'items' => [
					[
						'label' => 'test@example.com',
						'shareWithDisplayNameUnique' => 'unique@example.com',
						'value' => ['shareWith' => 'test@example.com'],
					],
					[
						'label' => 'Custom Name',
						'shareWithDisplayNameUnique' => 'unique@example.com',
						'value' => ['shareWith' => 'test@example.com'],
					],
				],
				'expectedLabel' => 'Custom Name',
				'expectedShareWith' => 'test@example.com',
			],
			'all same label should keep first' => [
				'items' => [
					[
						'label' => 'Same Label',
						'shareWithDisplayNameUnique' => 'same',
						'value' => ['shareWith' => 'same'],
					],
					[
						'label' => 'Same Label',
						'shareWithDisplayNameUnique' => 'same',
						'value' => ['shareWith' => 'same'],
					],
				],
				'expectedLabel' => 'Same Label',
				'expectedShareWith' => 'same',
			],
			'empty label has lowest score' => [
				'items' => [
					[
						'label' => '',
						'shareWithDisplayNameUnique' => 'phone',
						'value' => ['shareWith' => 'phone'],
					],
					[
						'label' => '+5521987776666',
						'shareWithDisplayNameUnique' => 'phone',
						'value' => ['shareWith' => 'phone'],
					],
					[
						'label' => 'Mobile User',
						'shareWithDisplayNameUnique' => 'phone',
						'value' => ['shareWith' => 'phone'],
					],
				],
				'expectedLabel' => 'Mobile User',
				'expectedShareWith' => 'phone',
			],
		];
	}
}
