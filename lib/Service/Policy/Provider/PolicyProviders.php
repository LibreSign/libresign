<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider;

use OCA\Libresign\Service\Policy\Provider\DocMdp\DocMdpPolicy;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicy;
use OCA\Libresign\Service\Policy\Provider\IdentificationDocuments\IdentificationDocumentsPolicy;
use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicy;
use OCA\Libresign\Service\Policy\Provider\Signature\SignatureFlowPolicy;

final class PolicyProviders {
	/** @var array<string, class-string> */
	public const BY_KEY = [
		FooterPolicy::KEY => FooterPolicy::class,
		DocMdpPolicy::KEY => DocMdpPolicy::class,
		RequestSignGroupsPolicy::KEY => RequestSignGroupsPolicy::class,
		SignatureFlowPolicy::KEY => SignatureFlowPolicy::class,
		IdentificationDocumentsPolicy::KEY => IdentificationDocumentsPolicy::class,
	];
}
