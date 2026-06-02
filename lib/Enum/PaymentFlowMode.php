<?php

declare(strict_types=1);

namespace OCA\Libresign\Enum;

enum PaymentFlowMode: string
{
	case STK_PUSH = 'stk_push';
	case INSTRUCTIONS = 'instructions';
	case BOTH = 'both';
}
