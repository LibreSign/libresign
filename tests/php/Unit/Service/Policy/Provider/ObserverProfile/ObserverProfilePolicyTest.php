<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\ObserverProfile;

use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Provider\Helper\SiblingPolicyEffectiveBoolReader;
use OCA\Libresign\Service\Policy\Provider\ObserverProfile\ObserverProfilePolicy;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ObserverProfilePolicyTest extends TestCase {
	private SiblingPolicyEffectiveBoolReader&MockObject $siblingReader;

	#[\Override]
	protected function setUp(): void {
		parent::setUp();
		$this->siblingReader = $this->createMock(SiblingPolicyEffectiveBoolReader::class);
	}

	public function testProviderBuildsObserverProfileDefinition(): void {
		$provider = new ObserverProfilePolicy($this->siblingReader);
		$this->assertSame([ObserverProfilePolicy::KEY], $provider->keys());

		$definition = $provider->get(ObserverProfilePolicy::KEY);
		$this->assertSame(ObserverProfilePolicy::KEY, $definition->key());
		$this->assertFalse($definition->normalizeValue(0));
		$this->assertTrue($definition->normalizeValue(1));
	}

	public function testResolvedStateMetaExposesPrivateValidationSibling(): void {
		$this->siblingReader
			->expects($this->once())
			->method('getEffectiveBool')
			->with('make_validation_url_private')
			->willReturn(true);

		$provider = new ObserverProfilePolicy($this->siblingReader);
		$meta = $provider->get(ObserverProfilePolicy::KEY)->resolvedStateMeta(new PolicyContext());

		$this->assertSame(['validationUrlIsPrivate' => true], $meta);
	}
}
