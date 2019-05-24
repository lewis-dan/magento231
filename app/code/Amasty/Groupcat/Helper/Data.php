<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2017 Amasty (https://www.amasty.com)
 * @package Amasty_Groupcat
 */

namespace Amasty\Groupcat\Helper;

use Amasty\Groupcat\Model\Rule as RuleModel;
use Magento\Framework\App\Action\Action;
use Amasty\Groupcat\Model\Rule\ForbiddenActionOptionsProvider;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var \Magento\Cms\Helper\Page
     */
    private $pageHelper;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Cms\Helper\Page $pageHelper
    ) {
        parent::__construct($context);
        $this->pageHelper = $pageHelper;
    }

    public function getModuleConfig($path)
    {
        return $this->scopeConfig->getValue('amasty_groupcat/' . $path);
    }

    public function isModuleEnabled()
    {
        return $this->getModuleConfig('general/enabled') && $this->isModuleOutputEnabled();
    }

    /**
     * @param Action    $controller
     * @param RuleModel $rule
     */
    public function setRedirect(Action $controller, RuleModel $rule)
    {
        if ($rule->getAllowDirectLinks()) {
            return;
        }

        /** @var Action $controller */
        $controller->getActionFlag()->set('', Action::FLAG_NO_DISPATCH, true);
        $controller->getActionFlag()->set('', Action::FLAG_NO_POST_DISPATCH, true);
        $controller->getResponse()->setStatusCode(\Zend\Http\Response::STATUS_CODE_401);
        $controller->getResponse()->setRedirect('404');

        if ($rule->getForbiddenAction() == ForbiddenActionOptionsProvider::REDIRECT_TO_PAGE) {
            $url = $this->pageHelper->getPageUrl($rule->getForbiddenPageId());

            if ($url) {
                $controller->getResponse()->setRedirect($url);
            }
        }
    }
}
