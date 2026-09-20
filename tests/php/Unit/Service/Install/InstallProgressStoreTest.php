<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Install;

use OCA\Libresign\Service\Install\DependencyStorage;
use OCA\Libresign\Service\Install\InstallProgressStore;
use OCA\Libresign\Service\Install\InstallTarget;
use OCP\ICache;
use OCP\ICacheFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class InstallProgressStoreTest extends TestCase {
	private ICache&MockObject $cache;
	private InstallProgressStore $store;

	#[\Override]
	protected function setUp(): void {
		$this->cache = $this->createMock(ICache::class);
		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')
			->with('libresign-setup')
			->willReturn($this->cache);

		$this->store = new InstallProgressStore(
			$cacheFactory,
			$this->createMock(DependencyStorage::class),
			$this->createMock(LoggerInterface::class),
		);
	}

	#[DataProvider('cacheKeyProvider')]
	public function testSetUsesTargetScopedKey(
		string $resource,
		InstallTarget $target,
		string $expectedSuffix,
	): void {
		$this->cache->expects($this->once())
			->method('set')
			->with(
				'libresign-asyncDownloadProgress-' . $expectedSuffix,
				['pid' => 123],
			);

		$this->store->set($target, $resource, ['pid' => 123]);
	}

	public static function cacheKeyProvider(): array {
		return [
			'java includes distro' => [
				'java',
				InstallTarget::from('arm64', 'alpine-linux'),
				'java:aarch64:alpine-linux',
			],
			'jsignpdf only architecture' => [
				'jsignpdf',
				InstallTarget::from('amd64', 'linux'),
				'jsignpdf:x86_64',
			],
		];
	}

	public function testGetReturnsStoredArray(): void {
		$target = InstallTarget::from('x86_64', 'linux');
		$this->cache->expects($this->once())
			->method('get')
			->with('libresign-asyncDownloadProgress-cfssl:x86_64')
			->willReturn(['pid' => 321]);

		$this->assertSame(['pid' => 321], $this->store->get($target, 'cfssl'));
	}

	public function testGetIgnoresUnexpectedCacheType(): void {
		$this->cache->method('get')->willReturn('invalid');

		$this->assertSame(
			[],
			$this->store->get(InstallTarget::from('x86_64', 'linux'), 'cfssl'),
		);
	}

	public function testRemoveUsesTargetScopedKey(): void {
		$this->cache->expects($this->once())
			->method('remove')
			->with('libresign-asyncDownloadProgress-java:aarch64:linux');

		$this->store->remove(
			InstallTarget::from('aarch64', 'linux'),
			'java',
		);
	}
}
