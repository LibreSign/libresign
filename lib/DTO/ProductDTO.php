<?php

namespace OCA\Libresign\DTO;

use OCA\Libresign\Db\Product;

class ProductDTO {
	public static function fromEntity(Product $product): array {
		return [
			'id' => $product->getId(),
			'code' => $product->getCode(),
			'name' => $product->getName(),
			'amount' => $product->getAmount(),
			'currency' => $product->getCurrency(),
			'uses' => $product->getUses(),
			'active' => $product->getActive(),
			'isDefault' => $product->getIsDefault(),
			'createdAt' => $product->getCreatedAt()?->format('Y-m-d H:i:s'),
			'updatedAt' => $product->getUpdatedAt()?->format('Y-m-d H:i:s'),
		];
	}
}
