<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Middleware\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class RequireSetupOk {
	public function __construct(
		protected string $template = 'external',
	) {
	}

	public function getTemplate(): string {
		return $this->template;
	}
}
