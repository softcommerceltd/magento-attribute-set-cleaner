<?php
/**
 * Copyright © Soft Commerce Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace SoftCommerce\AttributeSetCleaner\Console\Command;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Console\Cli;
use Magento\Framework\DB\Adapter\AdapterInterface;
use SoftCommerce\AttributeSetCleaner\Model\AttributeDataReaderInterface;
use SoftCommerce\AttributeSetCleaner\Model\AttributeDataWriterInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CleanProductAttributeSets used to clean
 * product entity type attribute sets by removing
 * unused attributes from the SETs [eav_entity_attribute]
 */
class CleanProductAttributeSets extends Command
{
    private const COMMAND_NAME = 'attributeset:clean';
    private const ID_OPTION = 'id';

    /**
     * @var AttributeDataReaderInterface
     */
    private AttributeDataReaderInterface $attributeDataReader;

    /**
     * @var AttributeDataWriterInterface
     */
    private AttributeDataWriterInterface $attributeDataWriter;

    /**
     * @var AdapterInterface
     */
    private AdapterInterface $connection;

    public function __construct(
        AttributeDataReaderInterface $attributeDataReader,
        AttributeDataWriterInterface $attributeDataWriter,
        ResourceConnection $resourceConnection,
        ?string $name = null
    ) {
        $this->attributeDataReader = $attributeDataReader;
        $this->attributeDataWriter = $attributeDataWriter;
        $this->connection = $resourceConnection->getConnection();
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription('Attribute Set Cleaner')
            ->setDefinition([
                new InputOption(
                    self::ID_OPTION,
                    '-i',
                    InputOption::VALUE_REQUIRED,
                    'Attribute Set ID. Comma-separated values accepted.'
                )
            ]);

        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $attributeSetId = [];
        if ($idOption = $input->getOption(self::ID_OPTION)) {
            $attributeSetId = explode(',', $idOption);
            $attributeSetId = array_map('trim', $attributeSetId);
        }

        try {
            $this->process($output, $attributeSetId);
        } catch (\Exception $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Cli::RETURN_FAILURE;
        }

        return Cli::RETURN_SUCCESS;
    }

    /**
     * @param OutputInterface $output
     * @param array $requestCriteria
     * @return void
     */
    private function process(OutputInterface $output, array $requestCriteria = []): void
    {
        $attributeTypeId = $this->attributeDataReader->getEntityTypeId();
        foreach ($this->attributeDataReader->getAttributeSetIdToAttributeId() as $attributeSetId => $attributeIds) {
            if ($requestCriteria && !in_array($attributeSetId, $requestCriteria)) {
                continue;
            }

            $removeRequest = [];
            foreach ($this->attributeDataReader->getAttributeDataByAttributeSetId($attributeSetId) as $attribute) {
                $attributeId = $attribute['attribute_id'] ?? null;
                if (!$attributeId || !$tableName = $attribute['backend_table'] ?? null) {
                    continue;
                }

                $isAttributeUsed = $this->isAttributeUsed($attributeSetId, $attributeId, $tableName);
                if (false === $isAttributeUsed) {
                    $removeRequest[$attributeId] = $attributeId;
                }
            }

            $result = 0;
            if ($removeRequest) {
                $result = $this->attributeDataWriter->removeAttributeFromAttributeSet(
                    $attributeTypeId,
                    $attributeSetId,
                    $removeRequest
                );
            }

            $output->writeln(
                sprintf(
                    '<info>Processed attribute set ID:</info> <comment>%s.</comment>',
                    $attributeSetId,
                )
            );

            $responseIds = $result ? implode(',', $removeRequest) : 'None';
            $output->writeln(
                sprintf(
                    '<info>Removed attribute IDs:</info> <comment>%s</comment>.',
                    $responseIds
                )
            );
        }
    }

    /**
     * @param int $attributeSetId
     * @param int $attributeId
     * @param string $tableName
     * @return bool
     */
    private function isAttributeUsed(int $attributeSetId, int $attributeId, string $tableName): bool
    {
        $select = $this->connection->select()
            ->from(
                ['cpe' => $this->connection->getTableName('catalog_product_entity')],
                ['cpe.entity_id']
            )
            ->joinLeft(
                ['cpet' => $this->connection->getTableName($tableName)],
                'cpe.entity_id = cpet.entity_id',
                null
            )
            ->joinLeft(
                ['ea' => $this->connection->getTableName('eav_attribute')],
                'cpet.attribute_id = ea.attribute_id',
                null
            )
            ->where('cpe.attribute_set_id = ?', $attributeSetId)
            ->where('ea.attribute_id = ?', $attributeId)
            ->where('cpet.value is not null')
            ->limit(1);

        return !!$this->connection->fetchOne($select);
    }
}
