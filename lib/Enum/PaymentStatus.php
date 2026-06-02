<?php

declare(strict_types=1);

namespace OCA\Libresign\Enum;

/**
 * Represents the lifecycle status of a payment.
 */
enum PaymentStatus: string {

	case PENDING = 'pending';

	case INITIATION_FAILED = 'initiation_failed';
	case PAID = 'paid';

	case FAILED = 'failed';

	case EXPIRED = 'expired';

}
