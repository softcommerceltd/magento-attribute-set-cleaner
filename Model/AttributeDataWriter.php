<?php
/**
 * Copyright © Soft Commerce Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace SoftCommerce\AttributeSetCleaner\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

/**
 * @inheritDoc
 */
class AttributeDataWriter implements AttributeDataWriterInterface
{
    /**
     * @var AdapterInterface
     */
    private AdapterInterface $connection;

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->connection = $resourceConnection->getConnection();
    }

    /**
     * @param int $typeId
     * @param int $attributeSetId
     * @param array $attributeIds
     * @return int
     */
    public function removeAttributeFromAttributeSet(int $typeId, int $attributeSetId, array $attributeIds): int
    {
        if (empty($attributeIds)) {
            return 0;
        }

        return $this->connection->delete(
            $this->connection->getTableName('eav_entity_attribute'),
            [
                'entity_type_id = ?' => $typeId,
                'attribute_set_id = ?' => $attributeSetId,
                'attribute_id IN (?)' => array_values($attributeIds)
            ]
        );
    }
}
