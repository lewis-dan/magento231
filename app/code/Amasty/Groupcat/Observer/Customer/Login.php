<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2017 Amasty (https://www.amasty.com)
 * @package Amasty_Groupcat
 */


namespace Amasty\Groupcat\Observer\Customer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Catalog\Api\CategoryRepositoryInterface;

class Login implements ObserverInterface
{
     protected $_responseFactory;
    protected $_url;
     private $ruleProvider;
      protected $categoryRepository;

    public function __construct(
        \Amasty\Groupcat\Model\ProductRuleProvider $ruleProvider,
        \Magento\Framework\App\ResponseFactory $responseFactory,
        \Magento\Framework\UrlInterface $url,
        CategoryRepositoryInterface $categoryRepository
    ) {
        $this->_responseFactory = $responseFactory;
        $this->_url = $url;
        $this->ruleProvider = $ruleProvider;
        $this->categoryRepository = $categoryRepository;
    }
    public function execute(Observer $observer)
    {
        $customer = $observer->getEvent()->getCustomer();

        if($categoryId = $this->getAllowedCategoryId($customer->getGroupId())){
            $category = $this->categoryRepository->get($categoryId);
            $this->_responseFactory->create()->setRedirect($category->getUrl())->sendResponse();
            die();
       }else
         return;
       
    }
    public function getAllowedCategoryId($groupId)
    {
       $objectManager =   \Magento\Framework\App\ObjectManager::getInstance();
        $connection = $objectManager->get('Magento\Framework\App\ResourceConnection')->getConnection('\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION'); 
        $rules = $connection->fetchRow("SELECT distinct(rcg.rule_id),rc.category_id FROM amasty_groupcat_rule_customer_group rcg left join amasty_groupcat_rule_category rc on (rc.rule_id = rcg.rule_id) where rcg.rule_id not in (select rule_id from amasty_groupcat_rule_customer_group where customer_group_id=".$groupId.")  order by rcg.rule_id asc");


       if(sizeof($rules) > 0 && isset($rules['category_id']))
          return $rules['category_id'];
       else
        return 0;
        
    }
}
