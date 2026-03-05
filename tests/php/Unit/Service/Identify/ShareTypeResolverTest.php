<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Identify;

use OCA\Libresign\Collaboration\Collaborators\AccountPhonePlugin;
use OCA\Libresign\Collaboration\Collaborators\ContactPhonePlugin;
use OCA\Libresign\Collaboration\Collaborators\ManualPhonePlugin;
use OCA\Libresign\Collaboration\Collaborators\SignerPlugin;
use OCA\Libresign\Service\Identify\ShareTypeResolver;
use OCA\Libresign\Service\IdentifyMethod\Account;
use OCA\Libresign\Service\IdentifyMethod\Email;
use OCP\Share\IShare;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ShareTypeResolverTest extends TestCase {
	private Email&MockObject $identifyEmailMethod;
	private Account&MockObject $identifyAccountMethod;
	private ShareTypeResolver $resolver;

	protected function setUp(): void {
		$this->identifyEmailMethod = $this->createMock(Email::class);
		$this->identifyAccountMethod = $this->createMock(Account::class);

		$this->resolver = new ShareTypeResolver(
			$this->identifyEmailMethod,
			$this->identifyAccountMethod,
		);
	}

	/**
	 * @dataProvider resolveScenariosProvider
	 */
	public function testResolveScenarios(
		string $method,
		bool $emailEnabled,
		bool $accountEnabled,
		int $emailSettingsCalls,
		int $accountSettingsCalls,
		array $expectedShareTypes,
	): void {
		$emailExpectation = $this->identifyEmailMethod
			->expects($this->exactly($emailSettingsCalls))
			->method('getSettings');
		if ($emailSettingsCalls > 0) {
			$emailExpectation->willReturn(['enabled' => $emailEnabled]);
		}

		$accountExpectation = $this->identifyAccountMethod
			->expects($this->exactly($accountSettingsCalls))
			->method('getSettings');
		if ($accountSettingsCalls > 0) {
			$accountExpectation->willReturn(['enabled' => $accountEnabled]);
		}

		$shareTypes = $this->resolver->resolve($method);

		$this->assertSame($expectedShareTypes, $shareTypes);
	}

	public static function resolveScenariosProvider(): array {
		$phoneShareTypes = [
			AccountPhonePlugin::TYPE_SIGNER_ACCOUNT_PHONE,
			ContactPhonePlugin::TYPE_SIGNER_CONTACT_PHONE,
			ManualPhonePlugin::TYPE_SIGNER_MANUAL_PHONE,
		];

		return [
			'whatsapp uses signer and phone types' => [
				'whatsapp',
				true,
				true,
				0,
				0,
				[
					SignerPlugin::TYPE_SIGNER,
					...$phoneShareTypes,
				],
			],
			'trim and case insensitive phone method' => [
				'  WhAtSaPp  ',
				true,
				true,
				0,
				0,
				[
					SignerPlugin::TYPE_SIGNER,
					...$phoneShareTypes,
				],
			],
			'account enabled adds account type only' => [
				'account',
				true,
				true,
				0,
				1,
				[
					IShare::TYPE_USER,
					SignerPlugin::TYPE_SIGNER,
				],
			],
			'account disabled keeps signer only' => [
				'account',
				true,
				false,
				0,
				1,
				[
					SignerPlugin::TYPE_SIGNER,
				],
			],
			'email enabled adds email type only' => [
				'email',
				true,
				true,
				1,
				0,
				[
					IShare::TYPE_EMAIL,
					SignerPlugin::TYPE_SIGNER,
				],
			],
			'email disabled keeps signer only' => [
				'email',
				false,
				true,
				1,
				0,
				[
					SignerPlugin::TYPE_SIGNER,
				],
			],
			'all enabled includes email account signer and phone' => [
				'all',
				true,
				true,
				1,
				1,
				[
					IShare::TYPE_EMAIL,
					IShare::TYPE_USER,
					SignerPlugin::TYPE_SIGNER,
					...$phoneShareTypes,
				],
			],
			'all with disabled settings keeps signer and phone' => [
				'all',
				false,
				false,
				1,
				1,
				[
					SignerPlugin::TYPE_SIGNER,
					...$phoneShareTypes,
				],
			],
			'empty method behaves as all' => [
				'',
				true,
				true,
				1,
				1,
				[
					IShare::TYPE_EMAIL,
					IShare::TYPE_USER,
					SignerPlugin::TYPE_SIGNER,
					...$phoneShareTypes,
				],
			],
			'unknown method keeps signer only' => [
				'unknown',
				true,
				true,
				0,
				0,
				[
					SignerPlugin::TYPE_SIGNER,
				],
			],
		];
	}
}
