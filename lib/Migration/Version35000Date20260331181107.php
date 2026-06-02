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

class Version35000Date20260331181107 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {

		$schema = $schemaClosure();

		// ------------------------------------------------------------------
		// GoPaperless Entitlements Table
		//
		// This table defines what a user is allowed to do AFTER payment.
		//
		// Design principles:
		// - Decouples payment from access control
		// - Tracks usage rights granted by a product
		// - Enables future expansion (usage limits, expiry, subscriptions)
		//
		// Key concept:
		// - Payment = "user paid"
		// - Entitlement = "user can now do something"
		//
		// Example:
		// - Product: SIGN_DOCUMENT
		// - Entitlement: remaining_uses = 1
		//
		// IMPORTANT:
		// - Entitlements are created ONLY after successful payment
		// - Entitlements are checked BEFORE allowing an action
		// ------------------------------------------------------------------

		if (!$schema->hasTable('gopaperless_entitlements')) {

			$table = $schema->createTable('gopaperless_entitlements');

			// ------------------------------------------------------------------
			// Primary Key
			// ------------------------------------------------------------------

			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
			]);

			// ------------------------------------------------------------------
			// User Association
			// ------------------------------------------------------------------

			// Nextcloud user ID
			$table->addColumn('user_id', 'string', [
				'length' => 64,
				'notnull' => true,
			]);

			// ------------------------------------------------------------------
			// Product Reference
			// ------------------------------------------------------------------

			// Product code this entitlement was created from
			// Example: SIGN_DOCUMENT
			$table->addColumn('product_code', 'string', [
				'length' => 64,
				'notnull' => true,
			]);

			// ------------------------------------------------------------------
			// Usage Control (Minimal Implementation)
			// ------------------------------------------------------------------

			// Number of times this entitlement can be used
			// Example:
			// - 1 → single document signing
			// - 10 → bulk signing
			// - NULL → unlimited (future use)
			$table->addColumn('remaining_uses', 'integer', [
				'notnull' => false,
			]);

			// ------------------------------------------------------------------
			// Expiry (Future Support)
			// ------------------------------------------------------------------

			// When the entitlement expires (optional)
			// Used for subscriptions or time-limited access
			$table->addColumn('expires_at', 'datetime', [
				'notnull' => false,
			]);

			// ------------------------------------------------------------------
			// Timestamps
			// ------------------------------------------------------------------

			$table->addColumn('created_at', 'datetime', [
				'notnull' => true,
			]);

			// ------------------------------------------------------------------
			// Keys & Indexes
			// ------------------------------------------------------------------

			$table->setPrimaryKey(['id']);

			// Used to fetch entitlements for a user
			$table->addIndex(['user_id'], 'gp_entitlements_user_idx');

			// Used to check entitlements by action/product
			$table->addIndex(['product_code'], 'gp_entitlements_product_idx');

			// Composite index for fast entitlement lookup
			$table->addIndex(['user_id', 'product_code'], 'gp_entitlements_user_product_idx');
		}

		return $schema;
	}
}
