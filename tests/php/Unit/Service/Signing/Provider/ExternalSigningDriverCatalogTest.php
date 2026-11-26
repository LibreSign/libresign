<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Signing\Provider;

use OCA\Libresign\Service\ExperimentalFeaturesService;
use OCA\Libresign\Service\Signing\Provider\Drivers\Csc\CscDriver;
use OCA\Libresign\Service\Signing\Provider\ExternalSigningDriverCatalog;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

final class ExternalSigningDriverCatalogTest extends TestCase {
	public function testDisabledExperimentalCatalogIsEmpty(): void {
		$catalog = $this->catalog(false);

		$this->assertSame([], $catalog->listDrivers());
	}

	public function testEnabledCatalogReturnsOnlyOneDriverTypeAndNoInstances(): void {
		$catalog = $this->catalog(true);

		$drivers = $catalog->listDrivers();
		$this->assertCount(1, $drivers);
		$this->assertSame([CscDriver::ID], array_column($drivers, 'id'));
		$this->assertCount(1, array_unique(array_column($drivers, 'id')));
		$this->assertArrayNotHasKey('instanceId', $drivers[0]);
		$this->assertArrayNotHasKey('config', $drivers[0]);
	}

	private function catalog(bool $enabled): ExternalSigningDriverCatalog {
		$feature = $this->createMock(ExperimentalFeaturesService::class);
		$feature->method('isEnabled')->willReturn($enabled);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $label): string => $label);
		return new ExternalSigningDriverCatalog($feature, new CscDriver($l10n));
	}
}
