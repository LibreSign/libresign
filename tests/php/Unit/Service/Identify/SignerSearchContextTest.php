<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Identify;

use OCA\Libresign\Service\Identify\SignerSearchContext;
use PHPUnit\Framework\TestCase;

class SignerSearchContextTest extends TestCase {
	public function testSetAndGetValues(): void {
		$context = new SignerSearchContext();
		$context->set('email', 'test@example.com', 'Test@Example.com');

		$this->assertSame('email', $context->getMethod());
		$this->assertSame('test@example.com', $context->getSearch());
		$this->assertSame('Test@Example.com', $context->getRawSearch());
	}

	public function testRawSearchDefaultsToSearch(): void {
		$context = new SignerSearchContext();
		$context->set('email', 'test@example.com');

		$this->assertSame('test@example.com', $context->getRawSearch());
	}

	public function testRawSearchDefaultsToSearchWhenEmpty(): void {
		$context = new SignerSearchContext();
		$context->set('email', 'test@example.com', '');

		$this->assertSame('test@example.com', $context->getRawSearch());
	}

	public function testSetOverwritesValues(): void {
		$context = new SignerSearchContext();
		$context->set('email', 'first@example.com', 'First@Example.com');
		$this->assertSame('email', $context->getMethod());
		$this->assertSame('first@example.com', $context->getSearch());
		$this->assertSame('First@Example.com', $context->getRawSearch());
		$context->set('sms', '+5521987776666', '(21) 98777-6666');
		$this->assertSame('sms', $context->getMethod());
		$this->assertSame('+5521987776666', $context->getSearch());
		$this->assertSame('(21) 98777-6666', $context->getRawSearch());
	}

	public function testGettersReturnEmptyStringByDefault(): void {
		$context = new SignerSearchContext();
		$this->assertSame('', $context->getMethod());
		$this->assertSame('', $context->getSearch());
		$this->assertSame('', $context->getRawSearch());
	}
}
