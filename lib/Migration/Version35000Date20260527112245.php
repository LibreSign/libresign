<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

class Version35000Date20260527112245 extends SimpleMigrationStep
{

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		$schema = $schemaClosure();

		// ------------------------------------------------------------------
		// GoPaperless Payments Table
		//
		// This table tracks payment execution attempts within the
		// GoPaperless payment orchestration system.

		// Responsibilities:
		// - provider execution
		// - reconciliation
		// - retry coordination
		// - payment verification
		// - provider lifecycle tracking
		// - FX execution snapshots

		// This table is intentionally business-agnostic and does NOT
		// encode workflow or entitlement semantics.
		// ------------------------------------------------------------------

		if (!$schema->hasTable('gopaperless_payments')) {

			$table = $schema->createTable('gopaperless_payments');

			// Primary key
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
			]);

			// ------------------------------------------------------------------
			// Transaction Identification
			// ------------------------------------------------------------------

			// Internal transaction ownership reference.
			$table->addColumn('transaction_id', 'integer', [
				'notnull' => true,
			]);

			// Public-safe transaction reference for external consumers,
			// recovery flows and future API integrations.
			$table->addColumn('transaction_reference', 'string', [
				'notnull' => false,
				'length' => 64
			]);

			// ------------------------------------------------------------------
			// Payment Provider Abstraction
			// ------------------------------------------------------------------

			// Payment provider identifier.
			// Examples:
			// - dpo
			// - daraja
			$table->addColumn('provider', 'string', [
				'length' => 20,
				'notnull' => true,
			]);

			// External payment reference returned by the provider.
			// Examples:
			// - DPO → transaction token
			// - Daraja → CheckoutRequestID
			$table->addColumn('provider_reference', 'string', [
				'length' => 128,
				'notnull' => false,
			]);

			// Optional metadata from the provider.
			// Can store raw payloads, request IDs, or debugging information.
			// Example (Daraja):
			// - MerchantRequestID
			// - Callback payload
			$table->addColumn('provider_metadata', 'text', [
				'notnull' => false,
			]);

			// ------------------------------------------------------------------
			// Payment Details
			// ------------------------------------------------------------------

			// Payment amount stored in the smallest currency unit.
			// Example: KES 80.00 → stored as 8000
			$table->addColumn('amount', 'integer', [
				'notnull' => true,
			]);

			// ISO currency code (KES, USD, EUR, etc.)
			$table->addColumn('currency', 'string', [
				'length' => 3,
				'notnull' => true,
			]);

			// ------------------------------------------------------------------
			// Payment Lifecycle
			// ------------------------------------------------------------------

			// Payment lifecycle status:
			// - pending → payment started
			// - initiation_failed → when initiation with payment provider fails
			// - paid → payment confirmed by provider
			// - expired → payment reconciliation took too long (especially necessary for mobile payments)
			// - failed → payment failed or cancelled
			$table->addColumn('status', 'string', [
				'length' => 20,
				'default' => 'pending',
				'notnull' => true,
			]);

			// ------------------------------------------------------------------
			// Idempotency & Reliability
			// ------------------------------------------------------------------

			// Idempotency key for the payment attempt.
			// Prevents duplicate payments caused by:
			// - page refresh
			// - double-click
			// - network retries
			// Similar concept to Stripe's idempotency keys.
			$table->addColumn('payment_attempt_id', 'string', [
				'length' => 36,
				'notnull' => true,
			]);

			// ------------------------------------------------------------------
			// Timestamps
			// ------------------------------------------------------------------

			// Timestamp when payment attempt was created
			$table->addColumn('created_at', 'datetime', [
				'notnull' => true,
			]);

			// Timestamp when payment was last updated
			$table->addColumn('updated_at', 'datetime', [
				'notnull' => false,
			]);

			// Timestamp when payment was successfully completed
			$table->addColumn('paid_at', 'datetime', [
				'notnull' => false,
			]);

			// Timestamp when payment will expire
			$table->addColumn('expires_at', 'datetime', [
				'notnull' => false,
			]);

			// Optional ownership association for authenticated users.
			// Useful for auditing, purchase history and entitlement flows.
			$table->addColumn('user_id', 'string', [
				'length' => 64,
				'notnull' => false,
			]);

			// display_currency (e.g. TZS, UGX)
			$table->addColumn('display_currency', 'string', [
				'notnull' => false,
				'length' => 3,
			]);

			// display_amount (minor units of display currency)
			$table->addColumn('display_amount', 'bigint', [
				'notnull' => false,
			]);

			// fx_rate (KES → target currency)
			$table->addColumn('fx_rate', 'decimal', [
				'notnull' => false,
				'precision' => 18,
				'scale' => 6,
			]);

			// fx_rate_source (provider of FX rate)
			$table->addColumn('fx_rate_source', 'string', [
				'notnull' => false,
				'length' => 64,
			]);

			// fx_rate_locked_at (timestamp when FX was resolved)
			$table->addColumn('fx_rate_locked_at', 'datetime', [
				'notnull' => false,
			]);


			// Phone might be null if card payment
			$table->addColumn('phone_e164_digits', 'string', [
				'notnull' => false,
				'length' => 20,
			]);

			// Phone region
			$table->addColumn('phone_region', 'string', [
				'notnull' => false,
				'length' => 2,
			]);

			// Phone country
			$table->addColumn('phone_country', 'string', [
				'notnull' => false,
				'length' => 32,
			]);

			// Verification / Retry
			$table->addColumn('verification_status', 'string', [
				'notnull' => false,
				'length' => 32,
			]);

			$table->addColumn('verification_last_checked_at', 'datetime', [
				'notnull' => false,
			]);


			$table->addColumn('verification_retry_count', 'integer', [
				'notnull' => true,
				'default' => 0,
			]);

			$table->addColumn('next_verification_at', 'datetime', [
				'notnull' => false,
			]);

			$table->addColumn('verification_locked_at', 'datetime', [
				'notnull' => false,
			]);

			// Error tracking
			$table->addColumn('last_error_code', 'string', [
				'notnull' => false,
				'length' => 64,
			]);


			$table->addColumn('last_error_message', 'text', [
				'notnull' => false,
			]);

			$table->addColumn('last_error_at', 'datetime', [
				'notnull' => false,
			]);


			// ------------------------------------------------------------------
			// Keys & Indexes
			// ------------------------------------------------------------------

			$table->setPrimaryKey(['id']);

			// Ensures each payment attempt is processed only once.
			// Prevents duplicate execution caused by refresh/retry races.
			$table->addUniqueIndex(['payment_attempt_id'], 'gp_payment_attempt_unique');

			// Used for provider callback, reconciliation and verification lookups.
			$table->addIndex(['provider_reference'], 'gp_provider_reference_idx');

			// Used for joins between transactions and payments.
			$table->addIndex(['transaction_id'], 'gp_payment_transaction_idx');

			// Public-safe transaction reference lookup index.
			// Useful for external recovery and API-facing resolution.
			$table->addUniqueIndex(
				['transaction_reference'],
				'gp_payment_transaction_reference_unique'
			);

			// Composite Index
			// Provider-specific lookup optimisation.
			// Commonly used during provider reconciliation flows.
			$table->addIndex(['provider', 'provider_reference'], 'gp_provider_lookup_idx');

			// Payment lifecycle query index.
			// Used for pending, failed, expired and reconciliation queries.
			$table->addIndex(['status'], 'gp_payment_status_idx');

			// User payment history and audit lookup index.
			$table->addIndex(['user_id'], 'gp_payments_user_id_idx');

			// Expiry orchestration lookup index.
			// Used for stale payment cleanup and expiration jobs.
			$table->addIndex(['expires_at'], 'gp_payment_expires_idx');


			// Verification scheduling lookup index.
			// Used for payments awaiting reconciliation verification.
			$table->addIndex(
				['next_verification_at'],
				'gp_payment_next_verification_idx'
			);

			// Reconciliation queue optimisation index.
			// Used for verification workers and retry orchestration.
			$table->addIndex(
				['status', 'next_verification_at'],
				'gp_payment_verification_queue_idx'
			);
		}

		return $schema;
	}
}
