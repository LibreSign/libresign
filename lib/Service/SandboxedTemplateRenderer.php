<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Vendor\Twig\Environment;
use OCA\Libresign\Vendor\Twig\Error\SyntaxError;
use OCA\Libresign\Vendor\Twig\Loader\FilesystemLoader;
use OCA\Libresign\Vendor\Twig\Sandbox\Sandbox;
use OCA\Libresign\Vendor\Twig\Sandbox\SecurityError;
use OCA\Libresign\Vendor\Twig\Sandbox\SecurityPolicy;

/**
 * Render signing templates without granting access to arbitrary PHP callables,
 * object members or external templates.
 */
final class SandboxedTemplateRenderer {
	/** @param array<string, mixed> $variables */
	public static function render(string $template, array $variables): string {
		[$sandbox] = self::createSandbox();
		return $sandbox->createTemplate($template)->render($variables);
	}

	/**
	 * Validate Twig syntax and its sandbox permissions without rendering user code.
	 * Used at policy persistence boundaries, including delegated policies.
	 */
	public static function validate(string $template): void {
		[$sandbox, $environment] = self::createSandbox();
		try {
			$sandbox->createTemplate($template)->unwrap($environment)->ensureSecurityChecked();
		} catch (SecurityError|SyntaxError $exception) {
			throw new \InvalidArgumentException($exception->getMessage(), 0, $exception);
		}
	}

	/** @return array{Sandbox, Environment} */
	private static function createSandbox(): array {
		// This profile is generated from pinned Twig 4.x sandbox metadata.
		// Deny macros on Twig 3.30: recursive calls lack a resource limit.
		$policy = new SecurityPolicy(
			allowedTags: array_values(array_diff(TwigSandboxSafeFeatures::TAGS, ['macro'])),
			// Legacy LibreSign formatting needs these two non-default filters.
			allowedFilters: [...TwigSandboxSafeFeatures::FILTERS, 'date', 'raw'],
			allowedMethods: [],
			allowedProperties: [],
			allowedFunctions: TwigSandboxSafeFeatures::FUNCTIONS,
			// Twig 3.30 already recognizes its built-in safe tests natively.
			allowedTests: [],
		);
		$policy->setStrict(true);

		$environment = new Environment(new FilesystemLoader());
		return [new Sandbox($environment, $policy), $environment];
	}
}
