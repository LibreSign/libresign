<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider;

use OCA\Libresign\Service\Policy\Provider\Signature\SignatureFlowPolicy;

final class PolicyProviders {
	/** @var array<string, class-string> */
	public const BY_KEY = [
		SignatureFlowPolicy::KEY => SignatureFlowPolicy::class,
	];
}
