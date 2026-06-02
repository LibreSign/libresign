<?php

declare(strict_types=1);

namespace OCA\Libresign\Enum;

// system execution
enum PaymentCapability: string {

	case MOBILE_MONEY = 'mobile_money';
	case CARD = 'card';

}
