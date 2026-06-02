<?php

declare(strict_types=1);

namespace OCA\Libresign\Enum;

/**
 * @deprecated
 * DO NOT USE THIS
 * Mobile money payment lifecycle state.
 *
 * IMPORTANT:
 * These states represent EXECUTION + ORCHESTRATION lifecycle,
 * NOT provider-specific behaviour.
 *
 * Examples:
 * - Daraja STK dispatched      => CHARGED
 * - DPO charge initiated       => CHARGED
 * - User must select provider  => REQUIRES_SELECTION
 * - Provider rejected request  => FAILED
 * - Background reconciliation  => RECONCILING
 *
 * This enum intentionally models runtime payment progression
 * independent of:
 * - provider
 * - transport
 * - orchestration flow
 */
enum MobileMoneyStatus: string
{
	/**
	 * Payment session created successfully.
	 *
	 * No provider execution has occurred yet.
	 *
	 * Example:
	 * - DPO flow awaiting provider selection
	 */
	case INITIATED = 'initiated';

	/**
	 * Provider execution already occurred.
	 *
	 * Examples:
	 * - Daraja STK push dispatched
	 * - DPO mobile charge submitted
	 *
	 * IMPORTANT:
	 * This does NOT mean payment succeeded.
	 * Only that provider execution has started.
	 */
	case CHARGED = 'charged';

	/**
	 * User input required before execution may continue.
	 *
	 * Examples:
	 * - ambiguous MNO
	 * - unsupported auto-detection
	 * - explicit provider confirmation required
	 */
	case REQUIRES_SELECTION = 'requires_selection';

	/**
	 * Payment currently undergoing reconciliation.
	 *
	 * Examples:
	 * - callback pending
	 * - polling active
	 * - background verification running
	 */
	case RECONCILING = 'reconciling';

	/**
	 * Payment completed successfully.
	 */
	case SUCCESS = 'success';

	/**
	 * Payment failed permanently.
	 *
	 * Examples:
	 * - provider rejection
	 * - insufficient funds
	 * - invalid account
	 * - expired checkout
	 */
	case FAILED = 'failed';

	/**
	 * Payment session expired before completion.
	 *
	 * Examples:
	 * - stale session
	 * - abandoned flow
	 * - retry window elapsed
	 */
	case EXPIRED = 'expired';

	/**
	 * Payment explicitly cancelled.
	 *
	 * Examples:
	 * - user cancelled STK
	 * - user aborted payment
	 * - provider cancellation
	 */
	case CANCELLED = 'cancelled';

	/**
	 * Lifecycle helpers
	 */

	public function isInitiated(): bool
	{
		return $this === self::INITIATED;
	}

	public function isCharged(): bool
	{
		return $this === self::CHARGED;
	}

	public function requiresSelection(): bool
	{
		return $this === self::REQUIRES_SELECTION;
	}

	public function isReconciling(): bool
	{
		return $this === self::RECONCILING;
	}

	public function isSuccess(): bool
	{
		return $this === self::SUCCESS;
	}

	public function isFailed(): bool
	{
		return $this === self::FAILED;
	}

	public function isExpired(): bool
	{
		return $this === self::EXPIRED;
	}

	public function isCancelled(): bool
	{
		return $this === self::CANCELLED;
	}

	/**
	 * Whether execution already reached provider layer.
	 *
	 * Useful for:
	 * - polling decisions
	 * - hydration recovery
	 * - retry orchestration
	 * - resume semantics
	 */
	public function hasExecutionStarted(): bool
	{
		return match ($this) {
			self::CHARGED,
			self::RECONCILING,
			self::SUCCESS,
			self::FAILED,
			self::EXPIRED,
			self::CANCELLED => true,

			default => false,
		};
	}

	/**
	 * Whether reconciliation may still continue.
	 */
	public function isTerminal(): bool
	{
		return match ($this) {
			self::SUCCESS,
			self::FAILED,
			self::EXPIRED,
			self::CANCELLED => true,

			default => false,
		};
	}
}
