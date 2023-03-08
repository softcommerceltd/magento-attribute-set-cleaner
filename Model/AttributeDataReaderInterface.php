<?php
/**
 * Copyright © Soft Commerce Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace SoftCommerce\AttributeSetCleaner\Model;

use Magento\Catalog\Model\Product;

/**
 * Interface AttributeDataReaderInterface
 * used to read EAV attribute data.
 */
interface AttributeDataReaderInterface
{
    /**
     * @param string $entityTypeCode
     * @return int
     */
    public function getEntityTypeId(string $entityTypeCode = Product::ENTITY): int;

    /**
     * @return array
     */
    public function getAttributeSetIdToAttributeId(): array;

    /**
     * @param int $attributeSetId
     * @return array
     */
    public function getAttributeIdByAttributeSetId(int $attributeSetId): array;

    /**
     * @param int|null $attributeSetId
     * @return array
     */
    public function getAttributeDataByAttributeSetId(int $attributeSetId = null): array;

    /**
     * @return array
     */
    public function getAttributeSetIds(): array;

    /**
     * @param int|null $attributeId
     * @param string|null $index
     * @return array|mixed|null
     */
    public function getAttributeData(?int $attributeId = null, ?string $index = null);

    /**
     * @param string|null $attributeCode
     * @param string|null $index
     * @return array|mixed|string|null
     */
    public function getAttributeDataByCode(string $attributeCode = null, ?string $index = null);

    /**
     * @param string|null $attributeCode
     * @return array|int|string|null
     */
    public function getAttributeCodeToId(?string $attributeCode = null);

    /**
     * @param $attribute
     * @return bool
     */
    public function isAttributeStatic($attribute): bool;

    /**
     * @return array
     */
    public function getProductEntityMetadata(): array;
}
