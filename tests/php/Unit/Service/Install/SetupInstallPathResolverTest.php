<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Install;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\InvalidSignatureException;
use OCA\Libresign\Service\Install\DependencyStorage;
use OCA\Libresign\Service\Install\InstallTarget;
use OCA\Libresign\Service\Install\JSignPdfRelease;
use OCA\Libresign\Service\Install\SetupInstallPathResolver;
use OCP\IAppConfig;
use OCP\IConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SetupInstallPathResolverTest extends TestCase {
	private IConfig&MockObject $config;
	private IAppConfig&MockObject $appConfig;
	private DependencyStorage&MockObject $storage;
	private SetupInstallPathResolver $resolver;

	#[\Override]
	protected function setUp(): void {
		$this->config = $this->createMock(IConfig::class);
		$this->config->method('getSystemValue')
			->with('instanceid')
			->willReturn('1');
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->storage = $this->createMock(DependencyStorage::class);
		$this->resolver = new SetupInstallPathResolver(
			$this->config,
			$this->appConfig,
			$this->storage,
		);
	}

	#[DataProvider('configuredPathProvider')]
	public function testResolveConfiguredPath(
		string $architecture,
		string $distro,
		string $resource,
		string $expected,
	): void {
		$paths = [
			'java_path' => 'vfs://home/data/appdata_1/libresign/x86_64/linux/java/jdk-21.0.2+13-jre/bin/java',
			'jsignpdf_path' => 'vfs://home/data/appdata_1/libresign/x86_64/jsignpdf/jsignpdf-' . JSignPdfRelease::VERSION . '/JSignPdf.jar',
			'pdftk_path' => 'vfs://home/data/appdata_1/libresign/x86_64/pdftk/pdftk.jar',
			'cfssl_bin' => 'vfs://home/data/appdata_1/libresign/x86_64/cfssl/cfssl',
		];
		$this->appConfig->method('getValueString')
			->willReturnCallback(
				static fn (string $app, string $key): string
					=> $app === Application::APP_ID ? ($paths[$key] ?? '') : '',
			);

		$actual = $this->resolver->resolve(
			InstallTarget::from($architecture, $distro),
			$resource,
		);

		$this->assertSame($expected, $actual);
	}

	public static function configuredPathProvider(): array {
		return [
			'java x86 linux' => ['x86_64', 'linux', 'java', 'vfs://home/data/appdata_1/libresign/x86_64/linux/java/jdk-21.0.2+13-jre'],
			'java x86 alpine' => ['x86_64', 'alpine-linux', 'java', 'vfs://home/data/appdata_1/libresign/x86_64/alpine-linux/java/jdk-21.0.2+13-jre'],
			'java arm linux' => ['aarch64', 'linux', 'java', 'vfs://home/data/appdata_1/libresign/aarch64/linux/java/jdk-21.0.2+13-jre'],
			'java arm alpine' => ['aarch64', 'alpine-linux', 'java', 'vfs://home/data/appdata_1/libresign/aarch64/alpine-linux/java/jdk-21.0.2+13-jre'],
			'jsignpdf x86' => ['x86_64', 'linux', 'jsignpdf', 'vfs://home/data/appdata_1/libresign/x86_64/jsignpdf/jsignpdf-' . JSignPdfRelease::VERSION],
			'jsignpdf arm' => ['aarch64', 'linux', 'jsignpdf', 'vfs://home/data/appdata_1/libresign/aarch64/jsignpdf/jsignpdf-' . JSignPdfRelease::VERSION],
			'pdftk x86' => ['x86_64', 'linux', 'pdftk', 'vfs://home/data/appdata_1/libresign/x86_64/pdftk'],
			'pdftk arm' => ['aarch64', 'linux', 'pdftk', 'vfs://home/data/appdata_1/libresign/aarch64/pdftk'],
			'cfssl x86' => ['x86_64', 'linux', 'cfssl', 'vfs://home/data/appdata_1/libresign/x86_64/cfssl'],
			'cfssl arm' => ['aarch64', 'linux', 'cfssl', 'vfs://home/data/appdata_1/libresign/aarch64/cfssl'],
		];
	}

	public function testRejectsUnsupportedResource(): void {
		$this->expectException(InvalidSignatureException::class);
		$this->expectExceptionMessage('Unsupported setup resource');

		$this->resolver->resolve(InstallTarget::from('x86_64', 'linux'), 'unknown');
	}
}
