<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Enum;

/**
 * What happens to the signing workflow once a signer rejects.
 */
enum SignatureRejectionBehavior: string {
	/** The workflow stops: no remaining signer may sign. */
	case CANCEL = 'cancel';
	/** The rejected signer no longer blocks the workflow and the others may keep signing. */
	case CONTINUE = 'continue';
}
