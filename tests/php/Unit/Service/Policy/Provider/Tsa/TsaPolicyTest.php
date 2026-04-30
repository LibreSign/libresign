<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\Tsa;

use OCA\Libresign\Service\Policy\Provider\Tsa\TsaPolicy;
use OCA\Libresign\Service\Policy\Provider\Tsa\TsaPolicyValue;
use PHPUnit\Framework\TestCase;

final class TsaPolicyTest extends TestCase {
	public function testProviderBuildsTsaDefinition(): void {
		$provider = new TsaPolicy();
		$this->assertSame([TsaPolicy::KEY], $provider->keys());

		$definition = $provider->get(TsaPolicy::KEY);
		$this->assertSame(TsaPolicy::KEY, $definition->key());
		$this->assertSame(
			TsaPolicyValue::encode(TsaPolicyValue::defaults()),
			$definition->defaultSystemValue(),
		);
	}

	public function testProviderNormalizesTsaPayload(): void {
		$provider = new TsaPolicy();
		$definition = $provider->get(TsaPolicy::KEY);

		$normalized = $definition->normalizeValue([
			'url' => ' https://freetsa.org/tsr ',
			'policy_oid' => '1.2.3.4',
			'auth_type' => 'basic',
			'username' => ' admin ',
		]);

		$this->assertSame(
			'{"url":"https://freetsa.org/tsr","policy_oid":"1.2.3.4","auth_type":"basic","username":"admin"}',
			$normalized,
		);
	}
}
