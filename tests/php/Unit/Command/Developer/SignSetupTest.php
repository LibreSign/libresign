<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Command\Developer;

use OCA\Libresign\Command\Developer\SignSetup;
use OCA\Libresign\Service\Install\InstallService;
use OCA\Libresign\Service\Install\SignSetupService;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\IConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class SignSetupTest extends TestCase {
	private SignSetupService&MockObject $signSetupService;
	private InstallService&MockObject $installService;
	private SignSetup $command;

	public function setUp(): void {
		parent::setUp();

		$this->signSetupService = $this->createMock(SignSetupService::class);
		$this->installService = $this->createMock(InstallService::class);
		$this->command = new SignSetup(
			$this->createMock(IConfig::class),
			$this->signSetupService,
			$this->installService,
		);
	}

	#[DataProvider('malformedCertificateProvider')]
	public function testRejectsMalformedCertificate(string $certificate): void {
		$fixture = file_get_contents(__DIR__ . '/../../Handler/mock/cert.json');
		$this->assertIsString($fixture);
		$privateKey = json_decode($fixture, true, flags: JSON_THROW_ON_ERROR)['private_key'];
		$this->assertIsString($privateKey);

		$privateKeyPath = tempnam(sys_get_temp_dir(), 'libresign-private-key-');
		$certificatePath = tempnam(sys_get_temp_dir(), 'libresign-certificate-');
		$this->assertIsString($privateKeyPath);
		$this->assertIsString($certificatePath);

		file_put_contents($privateKeyPath, $privateKey);
		file_put_contents($certificatePath, $certificate);

		$this->signSetupService->expects($this->never())
			->method('setCertificate');
		$this->signSetupService->expects($this->never())
			->method('setPrivateKey');
		$this->installService->expects($this->never())
			->method('getAvailableResources');

		try {
			$output = new BufferedOutput();
			$status = $this->command->run(new ArrayInput([
				'--privateKey' => $privateKeyPath,
				'--certificate' => $certificatePath,
			]), $output);

			$this->assertSame(Command::FAILURE, $status);
			$this->assertStringContainsString('Invalid certificate', $output->fetch());
		} finally {
			unlink($privateKeyPath);
			unlink($certificatePath);
		}
	}

	public static function malformedCertificateProvider(): array {
		return [
			'invalid PEM base64' => ['not a certificate'],
			'truncated DER' => ["\x30\x82"],
		];
	}
}
