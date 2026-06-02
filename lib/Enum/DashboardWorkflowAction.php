<?php

declare(strict_types=1);

namespace OCA\Libresign\Enum;

enum DashboardWorkflowAction: string {
	case SIGN = 'SIGN';
	case VIEW = 'VIEW';
	case WAIT = 'WAIT';
	case COMPLETE_PAYMENT = 'COMPLETE_PAYMENT';
	case NONE = 'NONE';
}
