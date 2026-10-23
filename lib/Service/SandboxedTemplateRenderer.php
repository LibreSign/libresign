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
		// Twig 3.30 requires explicit permission for safe built-in tags,
		// filters and functions. Keep macros disabled to prevent recursion.
		$policy = new SecurityPolicy(
			allowedTags: ['apply', 'block', 'do', 'for', 'if', 'set', 'types', 'with'],
			allowedFilters: [
				'abs', 'batch', 'capitalize', 'convert_encoding', 'default',
				'e', 'escape', 'first', 'format', 'join', 'keys', 'last',
				'length', 'lower', 'merge', 'nl2br', 'number_format', 'replace',
				'reverse', 'round', 'slice', 'split', 'striptags', 'title',
				'trim', 'upper', 'url_encode',
				// Existing LibreSign templates use date and raw.
				'date', 'raw',
			],
			allowedMethods: [],
			allowedProperties: [],
			allowedFunctions: ['cycle', 'max', 'min'],
			// Twig 3.30 natively identifies its always-safe tests.
			allowedTests: [],
		);
		$policy->setStrict(true);

		$environment = new Environment(new FilesystemLoader());
		return [new Sandbox($environment, $policy), $environment];
	}
}
