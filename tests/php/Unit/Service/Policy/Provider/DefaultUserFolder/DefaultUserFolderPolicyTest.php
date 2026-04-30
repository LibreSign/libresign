<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\DefaultUserFolder;

use OCA\Libresign\Service\Policy\Provider\DefaultUserFolder\DefaultUserFolderPolicy;
use PHPUnit\Framework\TestCase;

final class DefaultUserFolderPolicyTest extends TestCase {
	public function testProviderBuildsDefinition(): void {
		$provider = new DefaultUserFolderPolicy();
		$this->assertSame([DefaultUserFolderPolicy::KEY], $provider->keys());

		$definition = $provider->get(DefaultUserFolderPolicy::KEY);
		$this->assertSame(DefaultUserFolderPolicy::KEY, $definition->key());
		$this->assertSame(DefaultUserFolderPolicy::DEFAULT_FOLDER, $definition->defaultSystemValue());
	}

	public function testNormalizesEmptyFolderToDefault(): void {
		$provider = new DefaultUserFolderPolicy();
		$definition = $provider->get(DefaultUserFolderPolicy::KEY);

		$this->assertSame('Team Certificates', $definition->normalizeValue('Team Certificates'));
		$this->assertSame(DefaultUserFolderPolicy::DEFAULT_FOLDER, $definition->normalizeValue(''));
	}
}
