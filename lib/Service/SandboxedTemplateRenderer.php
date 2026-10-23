<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Vendor\Twig\Environment;
use OCA\Libresign\Vendor\Twig\Extension\SandboxExtension;
use OCA\Libresign\Vendor\Twig\Loader\FilesystemLoader;
use OCA\Libresign\Vendor\Twig\Sandbox\SecurityPolicy;

/**
 * Render signing templates without granting access to PHP callables, object methods,
 * properties, Twig functions, or other templates.
 */
final class SandboxedTemplateRenderer {
	/** @param array<string, mixed> $variables */
	public static function render(string $template, array $variables): string {
		$policy = new SecurityPolicy(
			allowedTags: ['if', 'for', 'set'],
			allowedFilters: [
				'raw', 'escape', 'e', 'replace', 'date', 'default',
				'length', 'upper', 'lower', 'capitalize', 'title',
				'trim', 'join', 'number_format',
			],
			allowedMethods: [],
			allowedProperties: [],
			allowedFunctions: [],
		);
		// Twig 3.x otherwise implicitly permits a few template and function features.
		$policy->setStrict(true);

		$environment = new Environment(new FilesystemLoader());
		$environment->addExtension(new SandboxExtension($policy, true));

		return $environment->createTemplate($template)->render($variables);
	}
}
