<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Product;

use OCA\Libresign\Db\Product;
use OCA\Libresign\Db\ProductMapper;
use OCP\DB\Exception;
use RuntimeException;

class ProductService {

	private ProductMapper $productMapper;

	public function __construct(ProductMapper $productMapper) {
		$this->productMapper = $productMapper;
	}

	/**
	 * CRITICAL:
	 * Resolve the default product for a given code.
	 *
	 * Used by PaymentService to determine pricing.
	 *
	 * @param string $code
	 * @return Product
	 */
	public function getDefaultByCode(string $code): Product {

		if ($code === '') {
			throw new RuntimeException('Product code is required');
		}

		$product = $this->productMapper->findDefaultByCode($code);

		if (!$product) {
			throw new RuntimeException("No default product configured for code: {$code}");
		}

		return $product;
	}

	/**
	 * Create a new product
	 * @throws Exception
	 * @throws \Exception
	 */
	public function create(Product $product): Product {

		$product->setCreatedAt($this->now());
		$product->setUpdatedAt($this->now());
		$product->validate();

		return $this->productMapper->insert($product);
	}

	/**
	 * Update an existing product
	 * @throws Exception
	 * @throws \Exception
	 */
	public function update(int $productId, bool $active, int $uses): Product {

		$product = $this->productMapper->findById($productId);
		if (!$product) {
			throw new RuntimeException("No product with code: {$productId}");
		}
		if (!$uses || $uses < 1) {
			throw new RuntimeException('Uses must be greater than 0');
		}
		if (!$active && $product->getIsDefault()) {
			throw new RuntimeException('Cannot deactivate default product');
		}
		$product->setActive($active);
		$product->setUses($uses);
		$product->setUpdatedAt($this->now());
		$product->validate();

		return $this->productMapper->update($product);
	}

	/**
	 * Activate / deactivate product
	 *
	 * Prevents invalid state:
	 * - cannot deactivate default product
	 * @throws Exception
	 */
	public function setActive(int $productId, bool $active): Product {

		$product = $this->productMapper->findById($productId);

		if (!$product) {
			throw new RuntimeException('Product not found');
		}

		if (!$active && $product->getIsDefault()) {
			throw new RuntimeException('Cannot deactivate default product');
		}

		$product->setActive($active);
		return $this->productMapper->update($product);
	}

	/**
	 * CRITICAL:
	 * Set a product as default for its code.
	 *
	 * Ensures:
	 * - only ONE default per code
	 * - default must be active
	 * @throws Exception
	 */
	public function setDefaultProduct(int $productId): Product {

		$product = $this->productMapper->findById($productId);

		if (!$product) {
			throw new RuntimeException('Product not found');
		}

		if (!$product->getActive()) {
			throw new RuntimeException('Cannot set inactive product as default');
		}

		$code = $product->getCode();

		// Step 1: unset existing defaults
		$products = $this->productMapper->findByCode($code);

		foreach ($products as $p) {
			if ($p->getIsDefault()) {
				$p->setIsDefault(false);
				$this->productMapper->update($p);
			}
		}

		// Step 2: set new default
		$product->setIsDefault(true);
		return $this->productMapper->update($product);
	}

	/**
	 * Fetch all products for a given code (admin UI)
	 * @throws Exception
	 */
	public function listByCode(string $code): array {
		return $this->productMapper->findByCode($code);
	}

	/**
	 * Fetch ALL products (admin UI)
	 * @throws Exception
	 */
	public function listAll(): array {
		return $this->productMapper->findAll();
	}

	/**
	 * Fetch product by ID
	 */
	public function getById(int $id): ?Product {
		return $this->productMapper->findById($id);
	}

	/**
	 * @throws \Exception
	 */
	private function now(): string
	{
		return (new \DateTimeImmutable(
			'now',
			new \DateTimeZone('UTC'),
		))->format(DATE_ATOM);
	}
}
