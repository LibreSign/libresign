<?php

declare(strict_types=1);

use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Handler\SignEngine\Pkcs7Handler;
use OCA\Libresign\Handler\SignEngine\SignEngineFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

final class SignEngineFactoryTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private ContainerInterface $container;

	public function setUp(): void {
		parent::setUp();
		$this->container = \OCP\Server::get(ContainerInterface::class);
	}

	private function getInstance(): SignEngineFactory {
		return new SignEngineFactory(
			$this->container,
		);
	}

	#[DataProvider('providerResolve')]
	public function testResolve(string $extension, string $instanceOf): void {
		$instance = $this->getInstance();

		$signEngine = $instance->resolve($extension);

		$this->assertInstanceOf($instanceOf, $signEngine);
	}

	public static function providerResolve(): array {
		return [
			['pdf', Pkcs12Handler::class],
			['PDF', Pkcs12Handler::class],
			['odt', Pkcs7Handler::class],
			['ODT', Pkcs7Handler::class],
			['jpg', Pkcs7Handler::class],
			['JPG', Pkcs7Handler::class],
			['png', Pkcs7Handler::class],
			['PNG', Pkcs7Handler::class],
			['txt', Pkcs7Handler::class],
			['TXT', Pkcs7Handler::class],
		];
	}
}
