<?php

declare(strict_types=1);

namespace OCA\Libresign\Enum;

// user intent
enum PaymentMethod: string {

	case MOBILE = 'mobile';
	case CARD = 'card';

}
