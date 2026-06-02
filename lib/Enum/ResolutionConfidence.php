<?php

declare(strict_types=1);

namespace OCA\Libresign\Enum;

enum ResolutionConfidence: string
{
	case HIGH = 'high';
	case AMBIGUOUS = 'ambiguous';
	case UNKNOWN = 'unknown';

	public function isHigh(): bool
	{
		return $this === self::HIGH;
	}

	public function isAmbiguous(): bool
	{
		return $this === self::AMBIGUOUS;
	}

	public function isUnknown(): bool
	{
		return $this === self::UNKNOWN;
	}

	/**
	 * Whether user input is required
	 */
	public function requiresUserSelection(): bool
	{
		return $this !== self::HIGH;
	}

	public function merge(self $other): self
	{
		return match (true) {
			$this === self::UNKNOWN || $other === self::UNKNOWN => self::UNKNOWN,
			$this === self::AMBIGUOUS || $other === self::AMBIGUOUS => self::AMBIGUOUS,
			default => self::HIGH,
		};
	}
}
