<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider;

use OCA\Libresign\Service\Policy\Provider\ApprovalGroups\ApprovalGroupsPolicy;
use OCA\Libresign\Service\Policy\Provider\CollectMetadata\CollectMetadataPolicy;
use OCA\Libresign\Service\Policy\Provider\Confetti\ConfettiPolicy;
use OCA\Libresign\Service\Policy\Provider\CrlValidation\CrlValidationPolicy;
use OCA\Libresign\Service\Policy\Provider\DocMdp\DocMdpPolicy;
use OCA\Libresign\Service\Policy\Provider\Envelope\EnvelopePolicy;
use OCA\Libresign\Service\Policy\Provider\ExpirationRules\ExpirationRulesPolicy;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicy;
use OCA\Libresign\Service\Policy\Provider\IdentificationDocuments\IdentificationDocumentsPolicy;
use OCA\Libresign\Service\Policy\Provider\DefaultUserFolder\DefaultUserFolderPolicy;
use OCA\Libresign\Service\Policy\Provider\LegalInformation\LegalInformationPolicy;
use OCA\Libresign\Service\Policy\Provider\Reminder\ReminderPolicy;
use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicy;
use OCA\Libresign\Service\Policy\Provider\Signature\SignatureFlowPolicy;
use OCA\Libresign\Service\Policy\Provider\SignatureBackground\SignatureBackgroundPolicy;
use OCA\Libresign\Service\Policy\Provider\SignatureHashAlgorithm\SignatureHashAlgorithmPolicy;
use OCA\Libresign\Service\Policy\Provider\SignatureText\SignatureTextPolicy;
use OCA\Libresign\Service\Policy\Provider\ValidationAccess\ValidationAccessPolicy;

final class PolicyProviders {
	/** @var array<string, class-string> */
	public const BY_KEY = [
		ApprovalGroupsPolicy::KEY => ApprovalGroupsPolicy::class,
		CollectMetadataPolicy::KEY => CollectMetadataPolicy::class,
		ConfettiPolicy::KEY => ConfettiPolicy::class,
		CrlValidationPolicy::KEY => CrlValidationPolicy::class,
		FooterPolicy::KEY => FooterPolicy::class,
		DocMdpPolicy::KEY => DocMdpPolicy::class,
		EnvelopePolicy::KEY => EnvelopePolicy::class,
		ExpirationRulesPolicy::KEY_MAXIMUM_VALIDITY => ExpirationRulesPolicy::class,
		ExpirationRulesPolicy::KEY_RENEWAL_INTERVAL => ExpirationRulesPolicy::class,
		ExpirationRulesPolicy::KEY_EXPIRY_IN_DAYS => ExpirationRulesPolicy::class,
		RequestSignGroupsPolicy::KEY => RequestSignGroupsPolicy::class,
		ReminderPolicy::KEY => ReminderPolicy::class,
		DefaultUserFolderPolicy::KEY => DefaultUserFolderPolicy::class,
		LegalInformationPolicy::KEY => LegalInformationPolicy::class,
		SignatureHashAlgorithmPolicy::KEY => SignatureHashAlgorithmPolicy::class,
		ValidationAccessPolicy::KEY => ValidationAccessPolicy::class,
		SignatureFlowPolicy::KEY => SignatureFlowPolicy::class,
		SignatureBackgroundPolicy::KEY => SignatureBackgroundPolicy::class,
		IdentificationDocumentsPolicy::KEY => IdentificationDocumentsPolicy::class,
		SignatureTextPolicy::KEY => SignatureTextPolicy::class,
		SignatureTextPolicy::KEY_TEMPLATE => SignatureTextPolicy::class,
		SignatureTextPolicy::KEY_TEMPLATE_FONT_SIZE => SignatureTextPolicy::class,
		SignatureTextPolicy::KEY_SIGNATURE_WIDTH => SignatureTextPolicy::class,
		SignatureTextPolicy::KEY_SIGNATURE_HEIGHT => SignatureTextPolicy::class,
		SignatureTextPolicy::KEY_SIGNATURE_FONT_SIZE => SignatureTextPolicy::class,
		SignatureTextPolicy::KEY_RENDER_MODE => SignatureTextPolicy::class,
	];
}
