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

class Version35000Date20260331175703 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {

		$schema = $schemaClosure();

		// ------------------------------------------------------------------
		// GoPaperless Products Table
		//
		// This table defines billable actions and their pricing.
		//
		// Design principles:
		// - Decouples pricing from payment execution
		// - Enables admin-controlled pricing without deployments
		// - Supports multiple pricing variants per product (e.g. promos)
		// - Keeps a stable "code" to represent the action/capability
		//
		// Key concept:
		// - code = WHAT the user is paying for (e.g. SIGN_DOCUMENT)
		// - row  = HOW MUCH it costs (can have multiple variants)
		//
		// Important:
		// - Payments MUST snapshot amount + currency
		// - Products are NOT a source of truth for historical payments
		// ------------------------------------------------------------------

		if (!$schema->hasTable('gopaperless_products')) {

			$table = $schema->createTable('gopaperless_products');

			// ------------------------------------------------------------------
			// Primary Key
			// ------------------------------------------------------------------

			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
			]);

			// ------------------------------------------------------------------
			// Product Identity
			// ------------------------------------------------------------------

			// Stable identifier (SKU-like, but represents an action/capability)
			// Examples:
			// - SIGN_DOCUMENT
			// - BULK_SIGN
			//
			// NOTE:
			// This is NOT unique because multiple pricing variants can exist
			// for the same code (e.g. promo vs standard pricing)
			$table->addColumn('code', 'string', [
				'length' => 64,
				'notnull' => true,
			]);

			// Human-readable name (used in admin UI)
			$table->addColumn('name', 'string', [
				'length' => 255,
				'notnull' => true,
			]);

			// ------------------------------------------------------------------
			// Pricing
			// ------------------------------------------------------------------

			// Price stored in the smallest currency unit
			// Example: KES 80.00 → 8000
			$table->addColumn('amount', 'integer', [
				'notnull' => true,
			]);

			// ISO currency code (KES, USD, etc.)
			$table->addColumn('currency', 'string', [
				'length' => 3,
				'notnull' => true,
			]);

			/**
			 * Defines how many times a user can perform the action.
			 * Snapshot into Payment at purchase time
			 * Consumed via Entitlement after payment
			 * EXAMPLE:
			 * - SIGN_DOCUMENT → 1
			 * - BULK_SIGN → 10
			 *
			 * NOTE:
			 * - Represents current value model (usage-based)
			 * - May evolve to support other product types in future
			 */
			$table->addColumn('uses', 'integer', [
				'notnull' => true,
				'default' => 1,
			]);

			// ------------------------------------------------------------------
			// Versioning (Latent / Reserved)
			// ------------------------------------------------------------------

			// Reserved for future product versioning
			//
			// Potential use cases:
			// - pricing history
			// - promotions
			// - A/B testing
			//
			// IMPORTANT:
			// - Currently NOT used in business logic
			// - Must remain dormant until versioning is explicitly implemented
			$table->addColumn('version', 'integer', [
				'notnull' => true,
				'default' => 1,
			]);

			// ------------------------------------------------------------------
			// Activation & Admin Control
			// ------------------------------------------------------------------

			// Whether this product is available for use
			// Allows admins to disable products without deleting them
			$table->addColumn('active', 'boolean', [
				'default' => true,
			]);

			// Marks the currently selected product for a given code
			//
			// Example:
			// SIGN_DOCUMENT:
			// - Product A (8000) → is_default = true
			// - Product B (5000 promo) → is_default = false
			//
			// IMPORTANT:
			// Only ONE product per code should have is_default = true
			// This must be enforced at the service/admin layer
			$table->addColumn('is_default', 'boolean', [
				'default' => false,
			]);

			// ------------------------------------------------------------------
			// Timestamps
			// ------------------------------------------------------------------

			$table->addColumn('created_at', 'datetime', [
				'notnull' => true,
			]);

			$table->addColumn('updated_at', 'datetime', [
				'notnull' => true,
			]);

			// ------------------------------------------------------------------
			// Keys & Indexes
			// ------------------------------------------------------------------

			$table->setPrimaryKey(['id']);

			// Used to resolve products by action (code)
			$table->addIndex(['code'], 'gp_products_code_idx');

			// Used to quickly fetch the active/default product for a code
			$table->addIndex(['code', 'is_default'], 'gp_products_code_default_idx');
		}
		return $schema;
	}
}
