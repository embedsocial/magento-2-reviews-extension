<?php


namespace EmbedSocial\Reviews\Observer;

use Psr\Log\LoggerInterface as Logger;
use EmbedSocial\Reviews\Helper\Data as DataHelper;

class ConfigObserver implements \Magento\Framework\Event\ObserverInterface
{

    protected $logger;
    protected $dataHelper;

    public function __construct(
        DataHelper $dataHelper,
        Logger $logger
    ) {
        $this->logger = $logger;
        $this->dataHelper = $dataHelper;
    }

    /**
     * Execute observer
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(
        \Magento\Framework\Event\Observer $observer
    ) {
        $this->dataHelper->setUpStore($observer);
    }
}
