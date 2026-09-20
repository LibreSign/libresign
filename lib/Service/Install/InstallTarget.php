<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Install;

use InvalidArgumentException;
use OCA\Libresign\Vendor\LibreSign\WhatOSAmI\OperatingSystem;

/**
 * Immutable description of the platform a dependency is installed for.
 *
 * Keeping architecture and distribution together prevents install, verification
 * and background processes from silently using different environment values.
 */
final readonly class InstallTarget {
	public const array SUPPORTED_ARCHITECTURES = [
		'x86_64',
		'aarch64',
	];

	public const array SUPPORTED_DISTROS = [
		'linux',
		'alpine-linux',
	];

	public function __construct(
		private string $architecture,
		private string $distro,
	) {
		if (!in_array($architecture, self::SUPPORTED_ARCHITECTURES, true)) {
			throw new InvalidArgumentException(sprintf(
				'Unsupported architecture "%s". Supported architectures: %s',
				$architecture,
				implode(', ', self::SUPPORTED_ARCHITECTURES),
			));
		}
		if (!in_array($distro, self::SUPPORTED_DISTROS, true)) {
			throw new InvalidArgumentException(sprintf(
				'Unsupported distribution "%s". Supported distributions: %s',
				$distro,
				implode(', ', self::SUPPORTED_DISTROS),
			));
		}
	}

	public static function current(): self {
		return new self(
			self::normalizeArchitecture(php_uname('m')),
			self::detectDistro(),
		);
	}

	public static function from(
		?string $architecture = null,
		?string $distro = null,
	): self {
		return new self(
			self::normalizeArchitecture($architecture ?: php_uname('m')),
			$distro ?: self::detectDistro(),
		);
	}

	public static function normalizeArchitecture(string $architecture): string {
		return match (strtolower(trim($architecture))) {
			'x86_64', 'amd64' => 'x86_64',
			'aarch64', 'arm64' => 'aarch64',
			default => throw new InvalidArgumentException(sprintf(
				'Unsupported architecture "%s". Supported architectures: %s',
				$architecture,
				implode(', ', self::SUPPORTED_ARCHITECTURES),
			)),
		};
	}

	private static function detectDistro(): string {
		try {
			$distribution = (new OperatingSystem())->getLinuxDistribution();
			if (is_string($distribution) && strtolower($distribution) === 'alpine') {
				return 'alpine-linux';
			}
		} catch (\Exception) {
		}

		return 'linux';
	}

	public function architecture(): string {
		return $this->architecture;
	}

	public function distro(): string {
		return $this->distro;
	}

	public function withArchitecture(string $architecture): self {
		return new self(self::normalizeArchitecture($architecture), $this->distro);
	}

	public function withDistro(string $distro): self {
		return new self($this->architecture, $distro);
	}
	public function cacheKey(string $resource): string {
		if ($resource === 'java') {
			return $resource . ':' . $this->architecture . ':' . $this->distro;
		}
		return $resource . ':' . $this->architecture;
	}

}
