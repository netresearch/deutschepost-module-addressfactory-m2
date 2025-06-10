<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Model\ResourceModel;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\VersionControl\AbstractDb;

class AnalysisResult extends AbstractDb
{
    /**
     * Entities with no auto-increment ID must toggle "$_useIsObjectNew" property
     * to distinguish between create and update operations.
     *
     * @see \Magento\Framework\Model\ResourceModel\Db\AbstractDb::isObjectNotNew
     *
     * @var bool
     */
    protected $_useIsObjectNew = true;

    /**
     * Entities with no auto-increment ID must toggle "$_isPkAutoIncrement" property
     * to preserve the ID field.
     *
     * @see \Magento\Framework\Model\ResourceModel\Db\AbstractDb::saveNewObject
     *
     * @var bool
     */
    protected $_isPkAutoIncrement = false;

    /**
     * Init main table and primary key.
     *
     */
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('postdirekt_addressfactory_analysis', 'order_address_id');
    }

    /**
     * Determine persistence state.
     *
     * Primary key presence/absence is not an indicator for entities with no auto-increment ID.
     *
     * @param AbstractModel $object
     *
     * @return AbstractDb
     */
    #[\Override]
    protected function _beforeSave(AbstractModel $object): AbstractDb
    {
        $select = $this->_getLoadSelect($object->getIdFieldName(), $object->getId(), $object);
        $select->limit(1);
        $entityId = $this->getConnection()->fetchOne($select);
        $object->isObjectNew(!$entityId);

        return parent::_beforeSave($object);
    }
}
