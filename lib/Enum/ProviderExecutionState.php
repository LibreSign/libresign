<?php

declare(strict_types=1);

namespace OCA\Libresign\Enum;

/**
 * Provider execution lifecycle state.
 *
 * IMPORTANT:
 * This enum models runtime execution progression
 * across ALL payment orchestration flows.
 *
 * This is NOT:
 * - business payment status
 * - database persistence state
 * - raw provider-native status
 *
 * Instead, this represents:
 * "what stage has provider execution reached?"
 *
 * Examples:
 * - STK push dispatched
 * - card redirect initiated
 * - provider selection pending
 * - callback reconciliation active
 * - verification polling running
 */
enum ProviderExecutionState: string
{
	/**
	 * Payment session initialized.
	 *
	 * No provider execution has occurred yet.
	 */
	case INITIATED = 'initiated';

	/**
	 * Additional user/provider selection required
	 * before execution may continue.
	 */
	case REQUIRES_SELECTION = 'requires_selection';

	/**
	 * Provider execution has started.
	 *
	 * Examples:
	 * - Daraja STK dispatched
	 * - DPO mobile charge submitted
	 * - Card redirect generated
	 * - Provider-side auth initiated
	 */
	case EXECUTING = 'executing';

	/**
	 * Execution completed and system is actively
	 * reconciling final payment state.
	 *
	 * Examples:
	 * - polling active
	 * - callback pending
	 * - webhook verification
	 * - background reconciliation
	 */
	case RECONCILING = 'reconciling';

	/**
	 * Provider execution completed successfully.
	 */
	case SUCCESS = 'success';

	/**
	 * Provider execution failed permanently.
	 *
	 * Examples:
	 * - insufficient funds
	 * - rejected authorisation
	 * - invalid account
	 * - failed STK push
	 */
	case FAILED = 'failed';

	/**
	 * Execution expired before completion.
	 *
	 * Examples:
	 * - abandoned flow
	 * - stale checkout
	 * - expired session
	 */
	case EXPIRED = 'expired';

	/**
	 * Execution explicitly cancelled.
	 *
	 * Examples:
	 * - user cancelled
	 * - provider cancellation
	 * - orchestration abort
	 */
	case CANCELLED = 'cancelled';


	/**
	 * Execution could not proceed because the
	 * orchestration request/context was invalid.
	 *
	 * Examples:
	 * - missing provider selection
	 * - incomplete runtime payload
	 * - invalid execution preconditions
	 * - malformed orchestration state
	 */
	case INVALID_REQUEST = 'invalid_request';

	/**
	 * Lifecycle helpers
	 */

	public function isInitiated(): bool
	{
		return $this === self::INITIATED;
	}

	public function requiresSelection(): bool
	{
		return $this === self::REQUIRES_SELECTION;
	}

	public function isExecuting(): bool
	{
		return $this === self::EXECUTING;
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

	public function isInvalidRequest(): bool
	{
		return $this === self::INVALID_REQUEST;
	}

	/**
	 * Whether provider-side execution already began.
	 *
	 * Useful for:
	 * - hydration recovery
	 * - polling decisions
	 * - retry semantics
	 * - resume orchestration
	 */
	public function hasExecutionStarted(): bool
	{
		return match ($this) {
			self::EXECUTING,
			self::RECONCILING,
			self::SUCCESS,
			self::FAILED,
			self::EXPIRED,
			self::CANCELLED => true,

			default => false,
		};
	}

	/**
	 * Whether execution lifecycle is terminal.
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
