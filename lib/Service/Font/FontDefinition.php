<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Font;

final class FontDefinition {
	public function __construct(
		private string $family,
		private string $directory,
		private string $regular,
		private string $bold,
		private string $italic,
		private string $boldItalic,
	) {
	}

	public function getFamily(): string {
		return $this->family;
	}

	public function getDirectory(): string {
		return $this->directory;
	}

	public function getRegular(): string {
		return $this->regular;
	}

	public function getBold(): string {
		return $this->bold;
	}

	public function getItalic(): string {
		return $this->italic;
	}

	public function getBoldItalic(): string {
		return $this->boldItalic;
	}
}
