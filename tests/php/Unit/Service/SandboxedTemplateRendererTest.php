<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Service\SandboxedTemplateRenderer;
use OCA\Libresign\Vendor\Twig\Sandbox\SecurityError;
use OCA\Libresign\Tests\Unit\TestCase;
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
