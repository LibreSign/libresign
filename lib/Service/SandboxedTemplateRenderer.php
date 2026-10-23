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
		// Twig 3.30's native safe tests are allowed by its sandbox compiler.
		// All other features are explicitly reviewed before dependency upgrades.
		$policy = new SecurityPolicy(
			allowedTags: [
				'apply', 'block', 'do', 'for', 'if', 'set', 'types', 'with',
			],
			// Keep 'raw' for the existing HTML footer and 'date' for signature stamps.
			allowedFilters: [
				'abs', 'batch', 'capitalize', 'convert_encoding', 'date',
				'default', 'e', 'escape', 'first', 'format', 'join',
				'keys', 'last', 'length', 'lower', 'merge', 'nl2br',
				'number_format', 'raw', 'replace', 'reverse', 'round',
				'slice', 'split', 'striptags', 'title', 'trim', 'upper',
				'url_encode',
			],
			allowedMethods: [],
			allowedProperties: [],
			allowedFunctions: ['cycle', 'max', 'min'],
			allowedTests: [],
		);
		$policy->setStrict(true);

		$environment = new Environment(new FilesystemLoader());
		return [new Sandbox($environment, $policy), $environment];
	}
}
