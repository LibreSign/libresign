<?php

declare(strict_types=1);

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\Product;
use OCA\Libresign\DTO\ProductDTO;
use OCA\Libresign\Service\Product\ProductService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired; // TODO: replace with AdminRequired
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class ProductController extends AEnvironmentAwareController {

	private ProductService $productService;
	private LoggerInterface $logger;

	public function __construct(
		IRequest $request,
		ProductService $productService,
		LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->productService = $productService;
		$this->logger = $logger;
	}

	/**
	 * Create product
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(
		verb: 'POST',
		url: '/api/{apiVersion}/product/create',
		requirements: ['apiVersion' => '(v1)']
	)]
	public function create(
		string $code,
		string $name,
		int $amount,
		int $uses,
		string $currency,
		bool $active = true,
	): DataResponse {

		try {
			// Input validation (basic)
			if ($code === '' || $currency === '') {
				return new DataResponse([
					'success' => false,
					'error' => 'Missing required fields'
				], Http::STATUS_BAD_REQUEST);
			}

			if ($amount <= 0) {
				return new DataResponse([
					'success' => false,
					'error' => 'Amount must be greater than zero'
				], Http::STATUS_BAD_REQUEST);
			}

			if (!$uses || $uses <= 0) {
				return new DataResponse([
					'success' => false,
					'error' => 'Product usage must be defined'
				], Http::STATUS_BAD_REQUEST);
			}

			$product = new Product();
			$product->setCode($code);
			$product->setName($name);
			$product->setAmount($amount);
			$product->setCurrency('KES');
			$product->setActive($active);
			$product->setUses($uses);

			$created = $this->productService->create($product);

			return new DataResponse([
				'success' => true,
				'product' => $created
			], Http::STATUS_OK);

		} catch (\RuntimeException $e) {

			return new DataResponse([
				'success' => false,
				'error' => $e->getMessage()
			], Http::STATUS_BAD_REQUEST);

		} catch (\Throwable $e) {

			$this->logger->error('Product creation failed', [
				'exception' => $e,
				'code' => $code
			]);

			return new DataResponse([
				'success' => false,
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * List products by code
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(
		verb: 'GET',
		url: '/api/{apiVersion}/product/list',
		requirements: ['apiVersion' => '(v1)']
	)]
	public function list(string $code): DataResponse {

		try {
			if ($code === '') {
				return new DataResponse([
					'products' => [],
					'error' => 'Invalid product code'
				], Http::STATUS_BAD_REQUEST);
			}

			$products = $this->productService->listByCode($code);

			return new DataResponse([
				'products' => array_map(
					[ProductDTO::class, 'fromEntity'],
					$products
				)
			], Http::STATUS_OK);

		} catch (\Throwable $e) {

			$this->logger->error('Product list failed', [
				'exception' => $e,
				'code' => $code
			]);

			return new DataResponse([
				'products' => [],
				'error' => 'Failed to fetch products'
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * List ALL products
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(
		verb: 'GET',
		url: '/api/{apiVersion}/product/list-all',
		requirements: ['apiVersion' => '(v1)']
	)]
	public function listAll(): DataResponse {

		try {
			$products = $this->productService->listAll();

			return new DataResponse([
				'products' => array_map(
					[ProductDTO::class, 'fromEntity'],
					$products
				)
			], Http::STATUS_OK);

		} catch (\Throwable $e) {

			$this->logger->error('Product list all failed', [
				'exception' => $e,
			]);

			return new DataResponse([
				'products' => [],
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Set default product
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(
		verb: 'POST',
		url: '/api/{apiVersion}/product/set-default',
		requirements: ['apiVersion' => '(v1)']
	)]
	public function setDefault(int $productId): DataResponse {

		try {
			if (!$productId) {
				return new DataResponse([
					'success' => false,
					'error' => 'Invalid product id',
				], statusCode: Http::STATUS_BAD_REQUEST);
			}

			$product = $this->productService->setDefaultProduct($productId);

			return new DataResponse([
				'success' => true,
				'product' => ProductDTO::fromEntity($product),
			], Http::STATUS_OK);

		} catch (\RuntimeException $e) {

			return new DataResponse([
				'success' => false,
				'error' => $e->getMessage()
			], Http::STATUS_BAD_REQUEST);

		} catch (\Throwable $e) {

			$this->logger->error('Set default product failed', [
				'exception' => $e,
				'productId' => $productId
			]);

			return new DataResponse([
				'success' => false,
				'error' => 'Failed to set default product'
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Activate / deactivate product
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(
		verb: 'POST',
		url: '/api/{apiVersion}/product/update',
		requirements: ['apiVersion' => '(v1)']
	)]
	public function update(int $productId, bool $active, int $uses): DataResponse {

		try {
			if (!$productId) {
				return new DataResponse([
					'success' => false,
					'error' => 'Invalid product id',
				], statusCode: Http::STATUS_BAD_REQUEST);
			}

			if (!$uses || $uses <= 0) {
				return new DataResponse([
					'success' => false,
					'error' => 'Uses cannot be less than zero',
				], statusCode: Http::STATUS_BAD_REQUEST);
			}

			$product = $this->productService->update($productId, $active, $uses);

			return new DataResponse([
				'success' => true,
				'product' => ProductDTO::fromEntity($product),
			], Http::STATUS_OK);

		} catch (\RuntimeException $e) {

			return new DataResponse([
				'success' => false,
				'error' => $e->getMessage()
			], Http::STATUS_BAD_REQUEST);

		} catch (\Throwable $e) {

			$this->logger->error('Set active failed', [
				'exception' => $e,
				'productId' => $productId,
				'active' => $active
			]);

			return new DataResponse([
				'success' => false,
				'error' => 'Failed to update product state'
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Activate / deactivate product
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(
		verb: 'POST',
		url: '/api/{apiVersion}/product/set-active',
		requirements: ['apiVersion' => '(v1)']
	)]
	public function setActive(int $productId, bool $active): DataResponse {

		try {
			if (!$productId) {
				return new DataResponse([
					'success' => false,
					'error' => 'Invalid product id',
				], statusCode: Http::STATUS_BAD_REQUEST);
			}

			$product = $this->productService->setActive($productId, $active);

			return new DataResponse([
				'success' => true,
				'product' => ProductDTO::fromEntity($product),
			], Http::STATUS_OK);

		} catch (\RuntimeException $e) {

			return new DataResponse([
				'success' => false,
				'error' => $e->getMessage()
			], Http::STATUS_BAD_REQUEST);

		} catch (\Throwable $e) {

			$this->logger->error('Set active failed', [
				'exception' => $e,
				'productId' => $productId,
				'active' => $active
			]);

			return new DataResponse([
				'success' => false,
				'error' => 'Failed to update product state'
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(
		verb: 'GET',
		url: '/api/{apiVersion}/product/by-code',
		requirements: ['apiVersion' => '(v1)']
	)]
	public function getByCode(string $code): DataResponse {
		try {
			if ($code === '') {
				return new DataResponse([
					'success' => false,
					'error' => 'Product code is required'
				], Http::STATUS_BAD_REQUEST);
			}

			$product = $this->productService->getDefaultByCode($code);

			return new DataResponse([
				'success' => true,
				'product' => ProductDTO::fromEntity($product)
			], Http::STATUS_OK);

		} catch (\RuntimeException $e) {
			return new DataResponse([
				'success' => false,
				'error' => $e->getMessage()
			], Http::STATUS_NOT_FOUND);

		} catch (\Throwable $e) {
			$this->logger->error('Failed to fetch product by code', [
				'exception' => $e
			]);

			return new DataResponse([
				'success' => false,
				'error' => 'Unable to fetch product'
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
