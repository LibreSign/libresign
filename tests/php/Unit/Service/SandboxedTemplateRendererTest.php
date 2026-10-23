<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Service\SandboxedTemplateRenderer;
use OCA\Libresign\Tests\Unit\TestCase;
use OCA\Libresign\Vendor\Twig\Environment;
use OCA\Libresign\Vendor\Twig\Loader\FilesystemLoader;
use OCA\Libresign\Vendor\Twig\Sandbox\SecurityError;
use PHPUnit\Framework\Attributes\DataProvider;

final class SandboxedTemplateRendererTest extends TestCase {
	public function testSupportedFooterFormattingAndConditionsRemainAvailable(): void {
		$template = '{% if validationSite %}{{ signedBy|raw }}: {{ validateIn|replace({"%s": validationSite})|raw }}{% endif %}';
		$this->assertSame(
			'Signed: Validate at example.test',
			SandboxedTemplateRenderer::render($template, [
				'signedBy' => 'Signed',
				'validateIn' => 'Validate at %s',
				'validationSite' => 'example.test',
			]),
		);
	}

	public function testSupportedSignatureStampVariablesAndDateFormat(): void {
		$this->assertSame(
			'Ada 2026-10-22',
			SandboxedTemplateRenderer::render('{{ SignerCommonName }} {{ signedAt|date("Y-m-d") }}', [
				'SignerCommonName' => 'Ada',
				'signedAt' => '2026-10-22T12:00:00Z',
			]),
		);
	}

	public function testTwig330FormattingAndLocalTemplateFeatures(): void {
		$this->assertSame(
			'HELLO, Ada: 2 / 7',
			SandboxedTemplateRenderer::render(
				'{% with {"name": "Ada"} %}{% apply upper %}hello{% endapply %}, {{ name }}: {{ min(2, 7) }} / {{ max(2, 7) }}{% endwith %}',
				[],
			),
		);
		$this->assertSame(
			'FIRST',
			SandboxedTemplateRenderer::render('{{ "first second"|split(" ")|first|upper }}', []),
		);
	}

	public function testTwig330AllowsUpstreamSafeTestsButNotUnsafeTests(): void {
		$environment = new Environment(new FilesystemLoader());
		$this->assertTrue($environment->getTest('defined')->isAlwaysAllowedInSandbox());
		$this->assertTrue($environment->getTest('empty')->isAlwaysAllowedInSandbox());
		$this->assertFalse($environment->getTest('constant')->isAlwaysAllowedInSandbox());
		$this->assertFalse($environment->getFilter('upper')->isAlwaysAllowedInSandbox());
		$this->assertFalse($environment->getFunction('min')->isAlwaysAllowedInSandbox());
		$this->assertSame('yes', SandboxedTemplateRenderer::render('{{ missing is not defined and values is not empty ? "yes" : "no" }}', ['values' => ['x']]));
	}

	public function testValidationChecksUnreachableCodeWithoutRenderingIt(): void {
		$this->expectException(\InvalidArgumentException::class);
		SandboxedTemplateRenderer::validate('{% if false %}{{ range(1, 3) }}{% endif %}');
	}

	public function testValidationCompilesWithoutRenderingBranches(): void {
		SandboxedTemplateRenderer::validate('{% if enabled %}{{ "hello"|upper }}{% endif %}');
		$this->addToAssertionCount(1);
	}

	public function testValidationRejectsMalformedTemplates(): void {
		$this->expectException(\InvalidArgumentException::class);
		SandboxedTemplateRenderer::validate('{% if x %}');
	}

	#[DataProvider('disallowedTemplateCases')]
	public function testDisallowedTemplateOperationsAreRejected(string $template): void {
		$this->expectException(SecurityError::class);
		SandboxedTemplateRenderer::render($template, ['items' => ['value']]);
	}

	public static function disallowedTemplateCases(): array {
		return [
			'callable filter' => ['{{ items|map("strtoupper")|join }}'],
			'function' => ['{{ range(1, 3) }}'],
			'dynamic attribute function' => ['{{ attribute(items, "length") }}'],
			'include tag' => ['{% include "footer.twig" %}'],
			'blocked test' => ['{{ 8 is constant("PHP_INT_SIZE") }}'],
			'range operator' => ['{{ (1..3)|join(",") }}'],
			'recursive macro' => ['{% macro recurse() %}{{ _self.recurse() }}{% endmacro %}{{ _self.recurse() }}'],
		];
	}

	public function testObjectMethodCannotBeCalled(): void {
		$object = new class {
			public function getLabel(): string {
				return 'secret';
			}
		};
		$this->expectException(SecurityError::class);
		SandboxedTemplateRenderer::render('{{ item.getLabel() }}', ['item' => $object]);
	}
}
