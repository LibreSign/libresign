<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Identify;

class SignerSearchContext {
	private string $method = '';
	private string $search = '';
	private string $rawSearch = '';

	public function set(string $method, string $search, string $rawSearch = ''): void {
		$this->method = $method;
		$this->search = $search;
		$this->rawSearch = $rawSearch !== '' ? $rawSearch : $search;
	}

	public function getMethod(): string {
		return $this->method;
	}

	public function getSearch(): string {
		return $this->search;
	}

	public function getRawSearch(): string {
		return $this->rawSearch;
	}
}
