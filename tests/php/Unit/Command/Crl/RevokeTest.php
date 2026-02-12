<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Command\Crl;

use OCA\Libresign\Command\Crl\Revoke;
use OCA\Libresign\Enum\CRLReason;
use OCA\Libresign\Service\Crl\CrlService;
use OCA\Libresign\Tests\Unit\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class RevokeTest extends TestCase {
	private CrlService&MockObject $crlService;
	private Revoke $command;

	public function setUp(): void {
		parent::setUp();
		$this->crlService = $this->createMock(CrlService::class);
		$this->command = new Revoke($this->crlService);
	}

	#[DataProvider('providerInvalidSerialNumbers')]
	public function testRejectsInvalidSerialNumber(string $serialNumber): void {
		$this->crlService->expects($this->never())
			->method('getCertificateStatus');

		[$status, $output] = $this->runCommand([
			'serial-number' => $serialNumber,
		]);

		$this->assertSame(Command::FAILURE, $status);
		$this->assertStringContainsString('Invalid serial number', $output);
	}

	public static function providerInvalidSerialNumbers(): array {
		return [
			'hex-prefix' => ['0x123'],
			'invalid-char' => ['12-3'],
			'empty' => [''],
		];
	}

	#[DataProvider('providerInvalidReasonCodes')]
	public function testRejectsInvalidReasonCode(int $reasonCode): void {
		$this->crlService->expects($this->never())
			->method('getCertificateStatus');

		[$status, $output] = $this->runCommand([
			'serial-number' => 'ABC123',
			'--reason' => (string)$reasonCode,
		]);

		$this->assertSame(Command::FAILURE, $status);
		$this->assertStringContainsString('Invalid reason code', $output);
	}

	public static function providerInvalidReasonCodes(): array {
		return [
			'negative' => [-1],
			'unassigned-7' => [7],
			'out-of-range' => [11],
			'unassigned-99' => [99],
		];
	}

	public function testFailsWhenCertificateIsUnknown(): void {
		$this->crlService->expects($this->once())
			->method('getCertificateStatus')
			->with('ABC123')
			->willReturn(['status' => 'unknown']);

		[$status, $output] = $this->runCommand([
			'serial-number' => 'ABC123',
		]);

		$this->assertSame(Command::FAILURE, $status);
		$this->assertStringContainsString('not found in the database', $output);
	}

	public function testReturnsSuccessWhenAlreadyRevoked(): void {
		$this->crlService->expects($this->once())
			->method('getCertificateStatus')
			->with('ABC123')
			->willReturn([
				'status' => 'revoked',
				'reason_code' => 1,
				'revoked_at' => '2025-01-01T00:00:00Z',
			]);
		$this->crlService->expects($this->never())
			->method('revokeCertificate');

		[$status, $output] = $this->runCommand([
			'serial-number' => 'ABC123',
		]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertStringContainsString('already revoked', $output);
		$this->assertStringContainsString('Current reason', $output);
		$this->assertStringContainsString('Revoked at', $output);
	}

	public function testDryRunDoesNotRevoke(): void {
		$this->crlService->expects($this->once())
			->method('getCertificateStatus')
			->with('ABC123')
			->willReturn(['status' => 'valid']);
		$this->crlService->expects($this->never())
			->method('revokeCertificate');

		[$status, $output] = $this->runCommand([
			'serial-number' => 'ABC123',
			'--reason' => '4',
			'--reason-text' => 'rotation',
			'--dry-run' => true,
		]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertStringContainsString('DRY-RUN', $output);
		$this->assertStringContainsString('Dry Run Results', $output);
		$this->assertStringContainsString('Would revoke certificate', $output);
		$this->assertStringContainsString('superseded (code: 4)', $output);
	}

	public function testRevokesCertificateSuccessfully(): void {
		$this->crlService->expects($this->once())
			->method('getCertificateStatus')
			->with('ABC123')
			->willReturn(['status' => 'valid']);
		$this->crlService->expects($this->once())
			->method('revokeCertificate')
			->with('ABC123', CRLReason::KEY_COMPROMISE, 'rotated', 'cli-admin')
			->willReturn(true);

		[$status, $output] = $this->runCommand([
			'serial-number' => 'ABC123',
			'--reason' => '1',
			'--reason-text' => 'rotated',
		]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertStringContainsString('has been revoked successfully', $output);
		$this->assertStringContainsString('keyCompromise (code: 1)', $output);
	}

	public function testRevokeFailureReturnsFailureCode(): void {
		$this->crlService->expects($this->once())
			->method('getCertificateStatus')
			->with('ABC123')
			->willReturn(['status' => 'valid']);
		$this->crlService->expects($this->once())
			->method('revokeCertificate')
			->with('ABC123', CRLReason::UNSPECIFIED, null, 'cli-admin')
			->willReturn(false);

		[$status, $output] = $this->runCommand([
			'serial-number' => 'ABC123',
		]);

		$this->assertSame(Command::FAILURE, $status);
		$this->assertStringContainsString('Failed to revoke certificate', $output);
	}

	public function testHandlesGetStatusException(): void {
		$this->crlService->expects($this->once())
			->method('getCertificateStatus')
			->with('ABC123')
			->willThrowException(new \RuntimeException('boom'));
		$this->crlService->expects($this->never())
			->method('revokeCertificate');

		[$status, $output] = $this->runCommand([
			'serial-number' => 'ABC123',
		]);

		$this->assertSame(Command::FAILURE, $status);
		$this->assertStringContainsString('Error checking certificate status: boom', $output);
	}

	public function testHandlesRevokeException(): void {
		$this->crlService->expects($this->once())
			->method('getCertificateStatus')
			->with('ABC123')
			->willReturn(['status' => 'valid']);
		$this->crlService->expects($this->once())
			->method('revokeCertificate')
			->with('ABC123', CRLReason::UNSPECIFIED, null, 'cli-admin')
			->willThrowException(new \RuntimeException('boom'));

		[$status, $output] = $this->runCommand([
			'serial-number' => 'ABC123',
		]);

		$this->assertSame(Command::FAILURE, $status);
		$this->assertStringContainsString('Error revoking certificate: boom', $output);
	}

	private function runCommand(array $input): array {
		$output = new BufferedOutput();
		$status = $this->command->run(new ArrayInput($input), $output);

		return [$status, $output->fetch()];
	}
}
