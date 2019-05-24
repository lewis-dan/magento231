<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2017 Amasty (https://www.amasty.com)
 * @package Amasty_Groupcat
 */


namespace Amasty\Groupcat\Observer\Customer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;

class Register implements ObserverInterface
{
    /**
     * @var \Amasty\Groupcat\Model\Indexer\Customer\IndexBuilder
     */
    private $indexBuilder;

    /**
     * Register constructor.
     * @param \Amasty\Groupcat\Model\Indexer\Customer\IndexBuilder $indexBuilder
     */
    public function __construct(
        \Amasty\Groupcat\Model\Indexer\Customer\IndexBuilder $indexBuilder
    ) {
        $this->indexBuilder = $indexBuilder;
    }

    /**
     * reindex after creation of new customer
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $customerId = $observer->getCustomer()->getId();
        $this->indexBuilder->reindexByCustomerId($customerId);
    }
}
