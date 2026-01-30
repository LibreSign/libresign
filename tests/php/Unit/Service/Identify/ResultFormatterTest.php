<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Identify;

use OCA\Libresign\Collaboration\Collaborators\SignerPlugin as SignerCollaborator;
use OCA\Libresign\Collaboration\Collaborators\AccountPhonePlugin;
use OCA\Libresign\Collaboration\Collaborators\ContactPhonePlugin;
use OCA\Libresign\Collaboration\Collaborators\ManualPhonePlugin;
use OCA\Libresign\Service\Identify\ResultFormatter;
use OCP\Share\IShare;
use PHPUnit\Framework\TestCase;

class ResultFormatterTest extends TestCase {
	private ResultFormatter $formatter;

	protected function setUp(): void {
		parent::setUp();
		$this->formatter = new ResultFormatter();
	}

	public function testFormatEmailShareType(): void {
		$list = [
			[
				'value' => ['shareWith' => 'test@example.com', 'shareType' => IShare::TYPE_EMAIL],
				'label' => 'Test User',
				'shareWithDisplayNameUnique' => 'test@example.com',
			],
		];

		$result = $this->formatter->formatForNcSelect($list);

		$this->assertCount(1, $result);
		$this->assertEquals('test@example.com', $result[0]['id']);
		$this->assertTrue($result[0]['isNoUser']);
		$this->assertEquals('email', $result[0]['method']);
		$this->assertEquals('icon-mail', $result[0]['icon']);
	}

	public function testFormatUserShareType(): void {
		$list = [
			[
				'value' => ['shareWith' => 'john', 'shareType' => IShare::TYPE_USER],
				'label' => 'John Doe',
				'shareWithDisplayNameUnique' => 'john@company.com',
			],
		];

		$result = $this->formatter->formatForNcSelect($list);

		$this->assertCount(1, $result);
		$this->assertEquals('john', $result[0]['id']);
		$this->assertFalse($result[0]['isNoUser']);
		$this->assertEquals('account', $result[0]['method']);
		$this->assertEquals('icon-user', $result[0]['icon']);
	}

	public function testFormatSignerPhoneMethod(): void {
		$list = [
			[
				'value' => ['shareWith' => '+5521987776666', 'shareType' => SignerCollaborator::TYPE_SIGNER],
				'label' => '+55 21 98777-6666',
				'method' => 'sms',
			],
		];

		$result = $this->formatter->formatForNcSelect($list);

		$this->assertCount(1, $result);
		$this->assertEquals('+5521987776666', $result[0]['id']);
		$this->assertTrue($result[0]['isNoUser']);
		$this->assertEquals('sms', $result[0]['method']);
		$this->assertEquals('svgSms', $result[0]['iconSvg']);
		$this->assertEquals('sms', $result[0]['iconName']);
		$this->assertArrayNotHasKey('icon', $result[0]);
	}

	public function testFormatSignerEmailMethod(): void {
		$list = [
			[
				'value' => ['shareWith' => 'signer@example.com', 'shareType' => SignerCollaborator::TYPE_SIGNER],
				'label' => 'Signer Email',
				'method' => 'email',
			],
		];

		$result = $this->formatter->formatForNcSelect($list);

		$this->assertCount(1, $result);
		$this->assertEquals('signer@example.com', $result[0]['id']);
		$this->assertTrue($result[0]['isNoUser']);
		$this->assertEquals('email', $result[0]['method']);
		$this->assertEquals('icon-mail', $result[0]['icon']);
		$this->assertArrayNotHasKey('iconSvg', $result[0]);
	}

	public function testFormatSignerAccountMethod(): void {
		$list = [
			[
				'value' => ['shareWith' => 'john', 'shareType' => SignerCollaborator::TYPE_SIGNER],
				'label' => 'John Account',
				'method' => 'account',
			],
		];

		$result = $this->formatter->formatForNcSelect($list);

		$this->assertCount(1, $result);
		$this->assertEquals('john', $result[0]['id']);
		$this->assertFalse($result[0]['isNoUser']);
		$this->assertEquals('account', $result[0]['method']);
		$this->assertEquals('icon-user', $result[0]['icon']);
	}

	public function testReplaceShareTypeWithMethodEmailType(): void {
		$list = [
			['shareType' => IShare::TYPE_EMAIL],
		];

		$result = $this->formatter->replaceShareTypeWithMethod($list);

		$this->assertCount(1, $result);
		$this->assertEquals('email', $result[0]['method']);
		$this->assertArrayNotHasKey('shareType', $result[0]);
	}

	public function testReplaceShareTypeWithMethodUserType(): void {
		$list = [
			['shareType' => IShare::TYPE_USER],
		];

		$result = $this->formatter->replaceShareTypeWithMethod($list);

		$this->assertCount(1, $result);
		$this->assertEquals('account', $result[0]['method']);
		$this->assertArrayNotHasKey('shareType', $result[0]);
	}

	public function testReplaceShareTypeWithMethodPreservesExistingMethod(): void {
		$list = [
			['method' => 'whatsapp', 'shareType' => SignerCollaborator::TYPE_SIGNER],
		];

		$result = $this->formatter->replaceShareTypeWithMethod($list);

		$this->assertCount(1, $result);
		$this->assertEquals('whatsapp', $result[0]['method']);
		$this->assertArrayNotHasKey('shareType', $result[0]);
	}

	public function testFormatMultipleMixedShareTypes(): void {
		$list = [
			[
				'value' => ['shareWith' => 'user1', 'shareType' => IShare::TYPE_USER],
				'label' => 'User One',
				'shareWithDisplayNameUnique' => 'user1@company.com',
			],
			[
				'value' => ['shareWith' => 'email@example.com', 'shareType' => IShare::TYPE_EMAIL],
				'label' => 'Email User',
				'shareWithDisplayNameUnique' => 'email@example.com',
			],
			[
				'value' => ['shareWith' => '+5521987776666', 'shareType' => SignerCollaborator::TYPE_SIGNER],
				'label' => '+55 21 98777-6666',
				'method' => 'whatsapp',
			],
		];

		$result = $this->formatter->formatForNcSelect($list);

		$this->assertCount(3, $result);

		$this->assertEquals('user1', $result[0]['id']);
		$this->assertFalse($result[0]['isNoUser']);
		$this->assertEquals('account', $result[0]['method']);

		$this->assertEquals('email@example.com', $result[1]['id']);
		$this->assertTrue($result[1]['isNoUser']);
		$this->assertEquals('email', $result[1]['method']);

		$this->assertEquals('+5521987776666', $result[2]['id']);
		$this->assertTrue($result[2]['isNoUser']);
		$this->assertEquals('whatsapp', $result[2]['method']);
	}

	public function testFormatAccountPhoneMethod(): void {
		$list = [
			[
				'value' => ['shareWith' => '+5521987776666', 'shareType' => AccountPhonePlugin::TYPE_SIGNER_ACCOUNT_PHONE],
				'label' => 'John Account Phone',
				'shareWithDisplayNameUnique' => '+5521987776666',
				'method' => 'sms',
			],
		];

		$result = $this->formatter->formatForNcSelect($list);

		$this->assertCount(1, $result);
		$this->assertEquals('+5521987776666', $result[0]['id']);
		$this->assertTrue($result[0]['isNoUser']);
		$this->assertEquals('sms', $result[0]['method']);
		$this->assertEquals('svgSms', $result[0]['iconSvg']);
		$this->assertEquals('sms', $result[0]['iconName']);
	}

	public function testFormatContactPhoneMethod(): void {
		$list = [
			[
				'value' => ['shareWith' => '+5521987776666', 'shareType' => ContactPhonePlugin::TYPE_SIGNER_CONTACT_PHONE],
				'label' => 'Contact Phone',
				'shareWithDisplayNameUnique' => '+5521987776666',
				'method' => 'whatsapp',
			],
		];

		$result = $this->formatter->formatForNcSelect($list);

		$this->assertCount(1, $result);
		$this->assertEquals('+5521987776666', $result[0]['id']);
		$this->assertTrue($result[0]['isNoUser']);
		$this->assertEquals('whatsapp', $result[0]['method']);
		$this->assertEquals('svgWhatsapp', $result[0]['iconSvg']);
		$this->assertEquals('whatsapp', $result[0]['iconName']);
	}

	public function testFormatManualPhoneMethod(): void {
		$list = [
			[
				'value' => ['shareWith' => '+5521987776666', 'shareType' => ManualPhonePlugin::TYPE_SIGNER_MANUAL_PHONE],
				'label' => '+55 21 98777-6666',
				'shareWithDisplayNameUnique' => '+5521987776666',
				'method' => 'telegram',
			],
		];

		$result = $this->formatter->formatForNcSelect($list);

		$this->assertCount(1, $result);
		$this->assertEquals('+5521987776666', $result[0]['id']);
		$this->assertTrue($result[0]['isNoUser']);
		$this->assertEquals('telegram', $result[0]['method']);
		$this->assertEquals('svgTelegram', $result[0]['iconSvg']);
		$this->assertEquals('telegram', $result[0]['iconName']);
	}

	public function testFormatSignalPhoneMethod(): void {
		$list = [
			[
				'value' => ['shareWith' => '+5521987776666', 'shareType' => AccountPhonePlugin::TYPE_SIGNER_ACCOUNT_PHONE],
				'label' => 'Signal User',
				'shareWithDisplayNameUnique' => '+5521987776666',
				'method' => 'signal',
			],
		];

		$result = $this->formatter->formatForNcSelect($list);

		$this->assertCount(1, $result);
		$this->assertEquals('+5521987776666', $result[0]['id']);
		$this->assertEquals('signal', $result[0]['method']);
		$this->assertEquals('svgSignal', $result[0]['iconSvg']);
	}

	public function testFormatMultiplePhoneTypesCorrectly(): void {
		$list = [
			[
				'value' => ['shareWith' => '+5521987776666', 'shareType' => AccountPhonePlugin::TYPE_SIGNER_ACCOUNT_PHONE],
				'label' => 'John SMS',
				'method' => 'sms',
			],
			[
				'value' => ['shareWith' => '+5521987776666', 'shareType' => ContactPhonePlugin::TYPE_SIGNER_CONTACT_PHONE],
				'label' => 'Contact WhatsApp',
				'method' => 'whatsapp',
			],
			[
				'value' => ['shareWith' => '+5521987776666', 'shareType' => ManualPhonePlugin::TYPE_SIGNER_MANUAL_PHONE],
				'label' => 'Manual Telegram',
				'method' => 'telegram',
			],
		];

		$result = $this->formatter->formatForNcSelect($list);

		$this->assertCount(3, $result);
		$this->assertEquals('sms', $result[0]['method']);
		$this->assertEquals('whatsapp', $result[1]['method']);
		$this->assertEquals('telegram', $result[2]['method']);
	}
}
