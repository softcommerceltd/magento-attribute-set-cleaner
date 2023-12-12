<?php
/**
 * Copyright © Soft Commerce Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace SoftCommerce\AttributeSetCleaner\Model;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use SoftCommerce\Core\Model\Eav\GetEntityTypeIdInterface;
use function array_filter;
use function array_keys;
use function current;
use function in_array;
use function is_string;
use function mb_strtolower;
use function trim;

/**
 * @inheritDoc
 */
class AttributeDataReader implements AttributeDataReaderInterface
{
    /**
     * @var array
     */
    private array $attributeData = [];

    /**
     * @var array
     */
    private array $attributeSetIdToAttributeId = [];

    /**
     * @var array
     */
    private array $attributeCodeToId = [];

    /**
     * @var AdapterInterface
     */
    private AdapterInterface $connection;

    /**
     * @var GetEntityTypeIdInterface
     */
    private GetEntityTypeIdInterface $getEntityTypeId;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $productAttributeCollectionFactory;

    /**
     * @var array|null
     */
    private ?array $productEntityMetadata = null;

    /**
     * @param CollectionFactory $productAttributeCollectionFactory
     * @param GetEntityTypeIdInterface $getEntityTypeId
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        CollectionFactory $productAttributeCollectionFactory,
        GetEntityTypeIdInterface $getEntityTypeId,
        ResourceConnection $resourceConnection
    ) {
        $this->productAttributeCollectionFactory = $productAttributeCollectionFactory;
        $this->getEntityTypeId = $getEntityTypeId;
        $this->connection = $resourceConnection->getConnection();
        $this->initialize();
    }

    /**
     * @inheritDoc
     */
    public function getEntityTypeId(string $entityTypeCode = Product::ENTITY): int
    {
        return $this->getEntityTypeId->execute($entityTypeCode);
    }

    /**
     * @inheritDoc
     */
    public function getAttributeSetIdToAttributeId(): array
    {
        return $this->attributeSetIdToAttributeId ?: [];
    }

    /**
     * @inheritDoc
     */
    public function getAttributeIdByAttributeSetId(int $attributeSetId): array
    {
        return $this->attributeSetIdToAttributeId[$attributeSetId] ?? [];
    }

    /**
     * @inheritDoc
     */
    public function getAttributeDataByAttributeSetId(int $attributeSetId = null): array
    {
        $result = [];
        foreach ($this->getAttributeIdByAttributeSetId($attributeSetId) as $attributeId) {
            if ($attribute = $this->getAttributeData((int) $attributeId)) {
                $result[$attributeId] = $attribute;
            }
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getAttributeSetIds(): array
    {
        return array_keys($this->getAttributeSetIdToAttributeId());
    }

    /**
     * @inheritDoc
     */
    public function getAttributeData(?int $attributeId = null, ?string $index = null)
    {
        if (null === $attributeId) {
            return $this->attributeData;
        }

        return null !== $index
            ? ($this->attributeData[$attributeId][$index] ?? null)
            : $this->attributeData[$attributeId] ?? [];
    }

    /**
     * @inheritDoc
     */
    public function getAttributeDataByCode(string $attributeCode = null, ?string $index = null)
    {
        $result = current(
            array_filter($this->attributeData, function ($item) use ($attributeCode) {
                return isset($item['attribute_code']) && $attributeCode === $item['attribute_code'];
            })
        ) ?: [];

        return null !== $index
            ? ($result[$index] ?? null)
            : $result;
    }

    /**
     * @inheritDoc
     */
    public function getAttributeCodeToId(?string $attributeCode = null)
    {
        return null !== $attributeCode
            ? ($this->attributeCodeToId[$attributeCode] ?? null)
            : $this->attributeCodeToId;
    }

    /**
     * @inheritDoc
     */
    public function isAttributeStatic($attribute): bool
    {
        if (in_array($attribute, $this->getProductEntityMetadata())) {
            return true;
        }

        $attribute = $this->getAttributeDataByAttribute($attribute);
        return isset($attribute['is_static']) && $attribute['is_static'];
    }

    /**
     * @inheritDoc
     */
    public function getProductEntityMetadata(): array
    {
        if (null === $this->productEntityMetadata) {
            $columns = $this->connection->describeTable(
                $this->connection->getTableName('catalog_product_entity')
            );
            $this->productEntityMetadata = array_keys($columns);
        }

        return $this->productEntityMetadata;
    }

    /**
     * @param string $attributeCode
     * @return string
     */
    private function parseAttributeCode(string $attributeCode): string
    {
        return mb_strtolower(trim($attributeCode));
    }

    /**
     * @param int|string $attribute
     * @return array
     */
    private function getAttributeDataByAttribute($attribute): array
    {
        if (is_int($attribute)) {
            $attribute = $this->getAttributeData($attribute);
        } elseif (is_string($attribute)) {
            $attribute = $this->getAttributeDataByCode($attribute);
        }
        return $attribute;
    }

    /**
     * @return void
     */
    private function initialize(): void
    {
        $entityAttributes = $this->connection->fetchAll(
            $this->connection->select()
                ->from(
                    ['eea' => $this->connection->getTableName('eav_entity_attribute')],
                    ['eea.attribute_id']
                )
                ->joinLeft(
                    ['eas' => $this->connection->getTableName('eav_attribute_set')],
                    'eas.attribute_set_id = eea.attribute_set_id',
                    ['eas.attribute_set_id']
                )
                ->where('eea.entity_type_id = ?', $this->getEntityTypeId->execute())
        );

        foreach ($entityAttributes as $item) {
            if (isset($item['attribute_id'], $item['attribute_set_id'])) {
                $this->attributeSetIdToAttributeId[$item['attribute_set_id']][$item['attribute_id']] = $item['attribute_id'];
            }
        }

        foreach ($this->attributeSetIdToAttributeId as $attributeIds) {
            $this->initAttributes($attributeIds);
        }
    }

    /**
     * @param array $attributeIds
     * @return void
     */
    private function initAttributes(array $attributeIds): void
    {
        $collection = $this->productAttributeCollectionFactory->create();
        $collection = $collection
            ->addFieldToFilter('main_table.attribute_id', ['in' => $attributeIds])
            ->addFieldToFilter('main_table.backend_type', ['neq' => 'static'])
            ->addFieldToFilter('is_user_defined', ['eq' => 1]);

        /** @var Attribute $attribute */
        foreach ($collection as $attribute) {
            if (!$attribute->getIsVisible()) {
                continue;
            }

            $attributeCode = $attribute->getAttributeCode();
            $attributeId = (int) $attribute->getId();

            if (!isset($this->attributeCodeToId[$attributeCode])) {
                $this->attributeCodeToId[$attributeCode] = $attributeId;
            }

            if (!isset($this->attributeData[$attributeId])) {
                $this->attributeData[$attributeId] = [
                    'attribute_id' => $attributeId,
                    'attribute_code' => $attributeCode,
                    'is_static' => $attribute->isStatic(),
                    'backend_type' => $attribute->getBackendType(),
                    'backend_table' => $attribute->getBackendTable(),
                    'frontend_input' => $attribute->getFrontendInput(),
                    'source_model' => $attribute->getSourceModel() ?: null
                ];
            }
        }
    }
}
