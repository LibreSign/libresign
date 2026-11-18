<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Command\OrganizationSeal;

use OCA\Libresign\Command\OrganizationSeal\Add;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\OrganizationSeal\OrganizationSealStore;
use OCA\Libresign\Tests\Unit\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class AddTest extends TestCase {
	private OrganizationSealStore&MockObject $sealStore;
	private string $pfxPath = '';

	public function setUp(): void {
		parent::setUp();
		$this->sealStore = $this->createMock(OrganizationSealStore::class);
		$this->pfxPath = (string)tempnam(sys_get_temp_dir(), 'libresign-seal-');
		file_put_contents($this->pfxPath, 'pfx content');
	}

	public function tearDown(): void {
		@unlink($this->pfxPath);
		putenv(Add::PASSWORD_ENV);
		parent::tearDown();
	}

	public function testAddsTheSealWithThePasswordFromTheEnvironment(): void {
		putenv(Add::PASSWORD_ENV . '=env secret');
		$this->sealStore->expects($this->once())
			->method('add')
			->with('Main seal', 'pfx content', 'env secret')
			->willReturn(['id' => 'abc', 'label' => 'Main seal', 'subject' => 'Example Seal', 'validTo' => '2030-01-01T00:00:00+00:00']);

		$tester = new CommandTester(new Add($this->sealStore));
		$status = $tester->execute(['label' => 'Main seal', 'pfx' => $this->pfxPath, '--password-from-env' => true]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertStringContainsString('abc', $tester->getDisplay());
		$this->assertStringNotContainsString('env secret', $tester->getDisplay());
	}

	public function testAsksForThePasswordWhenInteractive(): void {
		$this->sealStore->expects($this->once())
			->method('add')
			->with('Main seal', 'pfx content', 'typed secret')
			->willReturn(['id' => 'abc', 'label' => 'Main seal', 'subject' => '', 'validTo' => '']);

		$tester = new CommandTester(new Add($this->sealStore));
		$tester->setInputs(['typed secret']);
		$status = $tester->execute(['label' => 'Main seal', 'pfx' => $this->pfxPath]);

		$this->assertSame(Command::SUCCESS, $status);
	}

	public function testFailsWithoutPasswordWhenNotInteractive(): void {
		$this->sealStore->expects($this->never())->method('add');

		$tester = new CommandTester(new Add($this->sealStore));
		$status = $tester->execute(['label' => 'Main seal', 'pfx' => $this->pfxPath], ['interactive' => false]);

		$this->assertSame(Command::FAILURE, $status);
	}

	public function testFailsWhenTheFileCannotBeRead(): void {
		$this->sealStore->expects($this->never())->method('add');

		$tester = new CommandTester(new Add($this->sealStore));
		$status = $tester->execute(['label' => 'Main seal', 'pfx' => $this->pfxPath . '-missing', '--password-from-env' => true]);

		$this->assertSame(Command::FAILURE, $status);
	}

	public function testReportsAnUnusableCertificate(): void {
		putenv(Add::PASSWORD_ENV . '=wrong');
		$this->sealStore->method('add')->willThrowException(new LibresignException('The certificate file cannot be read with this password.'));

		$tester = new CommandTester(new Add($this->sealStore));
		$status = $tester->execute(['label' => 'Main seal', 'pfx' => $this->pfxPath, '--password-from-env' => true]);

		$this->assertSame(Command::FAILURE, $status);
		$this->assertStringContainsString('cannot be read with this password', $tester->getDisplay());
	}
}
