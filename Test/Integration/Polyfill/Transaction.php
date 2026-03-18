<?php

/**
 * See LICENSE.md for license details.
 */

/**
 * Backport of Magento 2.4.9 Transaction class (magento/magento2#39463).
 *
 * Removes _startTransaction() error handling that uses PHPUnit 9 APIs
 * (getTestResultObject, PHPUnit\Framework\Warning) removed in PHPUnit 10.
 *
 * Remove after upgrading to Magento 2.4.9+.
 */

namespace Magento\TestFramework\Event;

class Transaction
{
    /**
     * @var \Magento\TestFramework\EventManager
     */
    protected $_eventManager;

    /**
     * @var \Magento\TestFramework\Event\Param\Transaction
     */
    protected $_eventParam;

    /**
     * @var bool
     */
    protected $_isTransactionActive = false;

    /**
     * @param \Magento\TestFramework\EventManager $eventManager
     */
    public function __construct(\Magento\TestFramework\EventManager $eventManager)
    {
        $this->_eventManager = $eventManager;
    }

    /**
     * @param \PHPUnit\Framework\TestCase $test
     */
    public function startTest(\PHPUnit\Framework\TestCase $test)
    {
        $this->_processTransactionRequests('startTest', $test);
    }

    /**
     * @param \PHPUnit\Framework\TestCase $test
     */
    public function endTest(\PHPUnit\Framework\TestCase $test)
    {
        $this->_processTransactionRequests('endTest', $test);
    }

    public function endTestSuite()
    {
        $this->_rollbackTransaction();
    }

    /**
     * @param string $eventName
     * @param \PHPUnit\Framework\TestCase $test
     */
    protected function _processTransactionRequests($eventName, \PHPUnit\Framework\TestCase $test)
    {
        $param = $this->_getEventParam();
        $this->_eventManager->fireEvent($eventName . 'TransactionRequest', [$test, $param]);
        if ($param->isTransactionRollbackRequested()) {
            $this->_rollbackTransaction();
        }
        if ($param->isTransactionStartRequested()) {
            $this->_startTransaction($test);
        }
    }

    /**
     * Start transaction and fire 'startTransaction' event.
     *
     * Backport of Magento 2.4.9: removed set_error_handler and
     * getTestResultObject calls that used APIs removed in PHPUnit 10.
     *
     * @param \PHPUnit\Framework\TestCase $test
     */
    protected function _startTransaction(\PHPUnit\Framework\TestCase $test)
    {
        if (!$this->_isTransactionActive) {
            $this->_getConnection()->beginTransparentTransaction();
            $this->_isTransactionActive = true;
            $this->_eventManager->fireEvent('startTransaction', [$test]);
        }
    }

    protected function _rollbackTransaction()
    {
        if ($this->_isTransactionActive) {
            $this->_isTransactionActive = false;
            $connection = $this->_getConnection();

            // Unwind any leaked nested transactions from business code
            // (e.g. controller dispatch starting transactions that weren't committed)
            while ($connection->getTransactionLevel() > 1) {
                $connection->rollBack();
            }

            $connection->rollbackTransparentTransaction();
            $this->_eventManager->fireEvent('rollbackTransaction');
            $connection->closeConnection();
        }
    }

    /**
     * @param string $connectionName
     * @return \Magento\Framework\DB\Adapter\AdapterInterface|\Magento\TestFramework\Db\Adapter\TransactionInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _getConnection($connectionName = \Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION)
    {
        $resource = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
            ->get(\Magento\Framework\App\ResourceConnection::class);
        return $resource->getConnection($connectionName);
    }

    /**
     * @return \Magento\TestFramework\Event\Param\Transaction
     */
    protected function _getEventParam()
    {
        if (!$this->_eventParam) {
            $this->_eventParam = new \Magento\TestFramework\Event\Param\Transaction();
        } else {
            $this->_eventParam->__construct();
        }
        return $this->_eventParam;
    }
}
