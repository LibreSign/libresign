<?php

declare(strict_types=1);

namespace OCA\Libresign\Enum;

enum PaymentFlow: string
{
	case REDIRECT = 'redirect';
	case MOBILE_DIRECT = 'mobile_direct';
	case CALLBACK = 'callback';
	case UNKNOWN = 'unknown';
}
