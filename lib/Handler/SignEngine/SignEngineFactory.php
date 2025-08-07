<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\SignEngine;

use Psr\Container\ContainerInterface;

class SignEngineFactory {
	public function __construct(
		private ContainerInterface $container,
	) {
	}

	public function resolve(string $extension): Pkcs12Handler|Pkcs7Handler {
		return match (strtolower($extension)) {
			'pdf' => $this->container->get(Pkcs12Handler::class),
			default => $this->container->get(Pkcs7Handler::class),
		};
	}
}
