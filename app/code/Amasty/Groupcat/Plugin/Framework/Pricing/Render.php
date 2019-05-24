<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2017 Amasty (https://www.amasty.com)
 * @package Amasty_Groupcat
 */


namespace Amasty\Groupcat\Plugin\Framework\Pricing;

use Magento\Framework\Pricing\Render as PricingRender;
use Magento\Framework\Pricing\SaleableInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Amasty\Groupcat\Model\Rule\PriceActionOptionsProvider;
use Amasty\Groupcat\Model\Rule;

class Render
{
    /**
     * @var \Amasty\Groupcat\Model\ProductRuleProvider
     */
    private $ruleProvider;

    /**
     * @var \Amasty\Groupcat\Model\RuleRepository
     */
    private $ruleRepository;

    /**
     * @var \Magento\Cms\Model\BlockRepository
     */
    private $blockRepository;

    /**
     * @var \Magento\Customer\Model\Session
     */
    private $customerSession;

    /**
     * @var \Amasty\Groupcat\Helper\Data
     */
    private $helper;

    /**
     * @var \Magento\Cms\Model\Template\FilterProvider
     */
    private $filterProvider;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Magento\Framework\Registry
     */
    private $coreRegistry;

    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    private $eventManager;

    /**
     * Render constructor.
     *
     * @param \Amasty\Groupcat\Helper\Data               $helper
     * @param \Amasty\Groupcat\Model\ProductRuleProvider $ruleProvider
     * @param \Amasty\Groupcat\Model\RuleRepository      $ruleRepository
     * @param \Magento\Cms\Model\BlockRepository         $blockRepository
     * @param \Magento\Customer\Model\Session            $customerSession
     * @param \Magento\Cms\Model\Template\FilterProvider $filterProvider
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Registry                $coreRegistry
     */
    public function __construct(
        \Amasty\Groupcat\Helper\Data $helper,
        \Amasty\Groupcat\Model\ProductRuleProvider $ruleProvider,
        \Amasty\Groupcat\Model\RuleRepository $ruleRepository,
        \Magento\Cms\Model\BlockRepository $blockRepository,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Cms\Model\Template\FilterProvider $filterProvider,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Registry $coreRegistry,
        \Magento\Framework\Event\ManagerInterface $eventManager
    ) {
        $this->helper          = $helper;
        $this->ruleProvider    = $ruleProvider;
        $this->ruleRepository  = $ruleRepository;
        $this->blockRepository = $blockRepository;
        $this->customerSession = $customerSession;
        $this->filterProvider  = $filterProvider;
        $this->storeManager    = $storeManager;
        $this->coreRegistry    = $coreRegistry;
        $this->eventManager    = $eventManager;
    }

    /**
     * @since 1.2.7 while render default price (show price) - don't change isSaleable
     *
     * @param PricingRender     $subject
     * @param callable          $proceed
     * @param string            $priceCode
     * @param SaleableInterface $saleableItem
     * @param array             $arguments
     *
     * @return string
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundRender(
        PricingRender $subject,
        callable $proceed,
        $priceCode,
        SaleableInterface $saleableItem,
        array $arguments = []
    ) {
        if ($this->isNeedRenderPrice($saleableItem, $arguments)) {

            $deleteRegister = false;
            if (!$this->coreRegistry->registry('amasty_dont_change_isSalable')) {
                /** @see \Amasty\Groupcat\Plugin\Catalog\Model\Product\IsAvailable::afterIsSalable */
                $deleteRegister = true;
                $this->coreRegistry->register('amasty_dont_change_isSalable', true, true);
            }

            // Show Price Box
            $result = $proceed($priceCode, $saleableItem, $arguments);

            if ($deleteRegister) {
                $this->coreRegistry->unregister('amasty_dont_change_isSalable');
            }

            return $result;
        }

        return $this->getNewPriceHtmlBox($priceCode, $saleableItem, $arguments);
    }

    /**
     * @param $saleableItem
     * @param $arguments
     *
     * @return bool
     */
    private function isNeedRenderPrice($saleableItem, $arguments)
    {
        // if Item not a product - show price
        $isNotProduct = !($saleableItem instanceof ProductInterface);
        // is current price block zone is not list or view
        $isNoZone = (key_exists('zone', $arguments)
            && !in_array($arguments['zone'], [PricingRender::ZONE_ITEM_LIST, PricingRender::ZONE_ITEM_VIEW]));

        $isShowPrice = !$this->helper->isModuleEnabled()
            || $isNotProduct
            || $isNoZone
            || !$this->ruleProvider->getProductPriceAction($saleableItem);

        $this->eventManager->dispatch(
            'amasty_groupcat_is_show_price',
            ['item' => $saleableItem, 'is_show_price' => &$isShowPrice]
        );

        return $isShowPrice;
    }

    /**
     * Price block can be replaced by CMS or hided (return empty price)
     *
     * @param string           $priceCode
     * @param ProductInterface $product
     * @param array            $arguments
     *
     * @return string
     */
    private function getNewPriceHtmlBox($priceCode, $product, $arguments)
    {
        // Show CMS Block only for Final Price. Else just hide. For avoid display CMS for product many times
        if ($priceCode == \Magento\Catalog\Pricing\Price\FinalPrice::PRICE_CODE
            && $this->ruleProvider->getProductPriceAction($product) == PriceActionOptionsProvider::REPLACE
        ) {
            $ruleIndex = $this->ruleProvider->getRuleForProduct($product);
            $rule      = $this->ruleRepository->get($ruleIndex['rule_id']);
            $blockId   = null;
            switch ($arguments['zone']) {
                case PricingRender::ZONE_ITEM_VIEW:
                    $blockId = $rule->getBlockIdView();
                    break;
                case PricingRender::ZONE_ITEM_LIST:
                    $blockId = $rule->getBlockIdList();
                    break;
            }
            try {
                $block = $this->blockRepository->getById($blockId);
                if ($block->isActive()) {
                    return $this->filterProvider
                        ->getBlockFilter()
                        ->setStoreId($this->storeManager->getStore()->getId())
                        ->filter($block->getContent());
                }
            } catch (\Magento\Framework\Exception\NoSuchEntityException $exception) {
                // if failed to load CMS entity then hide price.
                return'';
            }
        }

        return '';
    }

    /**
     * Get Key for caching block content
     *
     * @since 1.2.0 cache contains active rule ids instead customer group
     * @since 1.1.5 added customer group id to the key.
     *              For correct work of hide/show switcher on product list of different group rules
     *
     * @param PricingRender $subject
     * @param string        $value
     *
     * @return string
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetCacheKey(PricingRender $subject, $value)
    {
        if ($this->helper->isModuleEnabled() && strpos($value, Rule::CACHE_TAG) === false) {
            $ruleCollection = $this->ruleProvider->getActiveRulesCollection();
            $activeRulesIds = $ruleCollection->getAllIds();
            $key = Rule::CACHE_TAG;
            if (count($activeRulesIds)) {
                $key .= implode('_', $activeRulesIds);
            }

            return $value . $key;
        }
        return $value;
    }
}
