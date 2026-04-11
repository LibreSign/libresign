<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Runtime;

use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinitionProvider;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicySpec;
use OCA\Libresign\Service\Policy\Provider\DocMdp\DocMdpPolicy;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicy;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicyValue;
use OCA\Libresign\Service\Policy\Provider\Signature\SignatureFlowPolicy;
use OCA\Libresign\Service\Policy\Runtime\PolicyRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class PolicyRegistryTest extends TestCase {
	public function testRegistryReturnsSignatureFlowDefinition(): void {
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->with(SignatureFlowPolicy::class)->willReturn(new SignatureFlowPolicy());
		$registry = new PolicyRegistry($container);
		$definition = $registry->get(SignatureFlowPolicy::KEY);

		$this->assertSame(SignatureFlowPolicy::KEY, $definition->key());
		$this->assertSame('none', $definition->defaultSystemValue());
		$this->assertSame(['none', 'parallel', 'ordered_numeric'], $definition->allowedValues(new PolicyContext()));
		$this->assertSame('ordered_numeric', $definition->normalizeValue('ordered_numeric'));
	}

	public function testRegistryReturnsDocMdpDefinition(): void {
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->with(DocMdpPolicy::class)->willReturn(new DocMdpPolicy());
		$registry = new PolicyRegistry($container);
		$definition = $registry->get(DocMdpPolicy::KEY);

		$this->assertSame(DocMdpPolicy::KEY, $definition->key());
		$this->assertSame(0, $definition->defaultSystemValue());
		$this->assertSame([0, 1, 2, 3], $definition->allowedValues(new PolicyContext()));
		$this->assertSame(2, $definition->normalizeValue(2));
	}

	public function testRegistryReturnsFooterDefinition(): void {
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->with(FooterPolicy::class)->willReturn(new FooterPolicy());
		$registry = new PolicyRegistry($container);
		$definition = $registry->get(FooterPolicy::KEY);

		$this->assertSame(FooterPolicy::KEY, $definition->key());
		$this->assertSame(
			FooterPolicyValue::encode(FooterPolicyValue::defaults()),
			$definition->defaultSystemValue(),
		);
		$this->assertSame([], $definition->allowedValues(new PolicyContext()));
		$this->assertSame(
			FooterPolicyValue::encode([
				'enabled' => false,
				'writeQrcodeOnFooter' => true,
				'validationSite' => '',
				'customizeFooterTemplate' => false,
			]),
			$definition->normalizeValue('0'),
		);
	}


	public function testRegistryThrowsForUnknownPolicy(): void {
		$this->expectException(\InvalidArgumentException::class);

		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->with(SignatureFlowPolicy::class)->willReturn(new SignatureFlowPolicy());
		$registry = new PolicyRegistry($container);
		$registry->get('unknown_policy');
	}

	public function testRegistryCachesDefinitionAfterFirstLookup(): void {
		$provider = new CountingPolicyDefinitionProvider();
		$container = $this->createMock(ContainerInterface::class);
		$container->expects($this->once())
			->method('get')
			->with(SignatureFlowPolicy::class)
			->willReturn($provider);
		$registry = new PolicyRegistry($container);

		$first = $registry->get(SignatureFlowPolicy::KEY);
		$second = $registry->get(SignatureFlowPolicy::KEY);

		$this->assertSame($first, $second);
		$this->assertSame(1, $provider->calls);
	}
}

final class CountingPolicyDefinitionProvider implements IPolicyDefinitionProvider {
	public int $calls = 0;

	public function keys(): array {
		return [SignatureFlowPolicy::KEY];
	}

	public function get(string|\BackedEnum $policyKey): IPolicyDefinition {
		++$this->calls;

		return new PolicySpec(
			key: SignatureFlowPolicy::KEY,
			defaultSystemValue: 'none',
			allowedValues: ['none', 'parallel', 'ordered_numeric'],
		);
	}
}
