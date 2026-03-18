<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Model;

use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use PHPUnit\Framework\TestCase;

final class PolicyLayerTest extends TestCase {
	public function testGettersReturnDefaults(): void {
		$layer = new PolicyLayer();

		$this->assertSame('', $layer->getScope());
		$this->assertNull($layer->getValue());
		$this->assertFalse($layer->isAllowChildOverride());
		$this->assertTrue($layer->isVisibleToChild());
		$this->assertSame([], $layer->getAllowedValues());
		$this->assertSame([], $layer->getNotes());
	}

	public function testSettersStoreValues(): void {
		$layer = new PolicyLayer();
		$layer
			->setScope('group')
			->setValue(['type' => 'ordered_numeric'])
			->setAllowChildOverride(true)
			->setVisibleToChild(false)
			->setAllowedValues([['type' => 'parallel'], ['type' => 'ordered_numeric']])
			->setNotes(['reason' => 'organization-default']);

		$this->assertSame('group', $layer->getScope());
		$this->assertSame(['type' => 'ordered_numeric'], $layer->getValue());
		$this->assertTrue($layer->isAllowChildOverride());
		$this->assertFalse($layer->isVisibleToChild());
		$this->assertSame([['type' => 'parallel'], ['type' => 'ordered_numeric']], $layer->getAllowedValues());
		$this->assertSame(['reason' => 'organization-default'], $layer->getNotes());
	}
}
