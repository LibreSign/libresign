<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCP\IRequest;

class RequestMetadataService {
	public function __construct(
		private IRequest $request,
	) {
	}

	/**
	 * @return array{user-agent: string|null, remote-address: string|null}
	 */
	public function collectMetadata(): array {
		return [
			'user-agent' => $this->request->getHeader('User-Agent'),
			'remote-address' => $this->request->getRemoteAddress(),
		];
	}
}
