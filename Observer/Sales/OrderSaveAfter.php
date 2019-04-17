<?php


namespace EmbedSocial\Reviews\Observer\Sales;
use EmbedSocial\Reviews\Helper\Data as DataHelper;

class OrderSaveAfter implements \Magento\Framework\Event\ObserverInterface
{
    protected $dataHelper;

    public function __construct(
        DataHelper $dataHelper
    ) {
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
        $this->dataHelper->sendOrder($observer);
    }
}
