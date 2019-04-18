<?php


namespace EmbedSocial\Reviews\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Psr\Log\LoggerInterface as Logger;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{

    protected $_logger;
    protected $_storemanager;
    protected $_curl;
    protected $_appEmulation;
    protected $_blockFactory;
    protected $_productRepository;

    const STORE_API_URL = "https://embedsocial.com/admin/save_web_shop_data";
    const ORDER_API_URL = "https://embedsocial.com/admin/save_web_shop_order";

    /**
     * @param \Magento\Framework\App\Helper\Context $context
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storemanager,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Framework\View\Element\BlockFactory $blockFactory,
        \Magento\Store\Model\App\Emulation $appEmulation,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        Logger $logger
    ) {
        $this->_logger = $logger;
        $this->_storemanager = $storemanager;
        $this->_curl = $curl;
        $this->_blockFactory = $blockFactory;
        $this->_appEmulation = $appEmulation;
        $this->_productRepository = $productRepository;
        parent::__construct($context);
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return true;
    }

    public function setUpStore(\Magento\Framework\Event\Observer $observer)
    {
        try {
            $data = [];
            $storeId = $observer->getData('store');
            $store = $this->_storemanager->getStore($storeId);

            $changedPaths = $observer->getData('changed_paths');
            $apiKey = $this->getConfigValue('embedsocial/options/apikey', $storeId);
            $orderStatuses = $this->getConfigValue('embedsocial/options/order_status', $storeId);

            $data['storeId'] = $storeId;
            $data['storeWebsiteId'] = $store->getWebsiteId();
            $data['storeName'] = $store->getName();
            $data['storeCode'] = $store->getCode();
            $data['storeUrl'] = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
            $data['orderStatuses'] = json_encode($orderStatuses);

            $params = ['token' => $apiKey, 'data' => json_encode($data)];
            $this->_curl->post(self::STORE_API_URL, $params);
            $response = $this->_curl->getBody();

        } catch (\Exception $e) {
            $this->_logger->error(json_encode($e));
        }
    }

    public function sendOrder(\Magento\Framework\Event\Observer $observer)
    {
        try {
            $order = $observer->getEvent()->getOrder();
            $storeId = $observer->getData('store');

            $apiKey = $this->getConfigValue('embedsocial/options/apikey', $storeId);

            $orderStatuses = $this->getConfigValue('embedsocial/options/order_status', $storeId); 
            $orderStatuses = explode(",", $orderStatuses);

            if (!in_array($order->getState(), $orderStatuses)) {
                return;
            }

            $data['customerEmail'] = $order->getCustomerEmail();
            $name = $order->getCustomerFirstName() . ' ' . $order->getCustomerLastName();

            //guest
            if ($order->getCustomerId() === NULL || !trim($name)) {
                $billing = $order->getBillingAddress();
                $name = $billing->getFirstname() . ' ' . $billing->getLastname();
            } 

            $data['customerName'] = $name;
            $data['orderId'] = $order->getId();
            $data['currency'] = $order->getOrderCurrency()->getCode();
            $data['orderDate'] = $order->getCreatedAt();
            $data['orderState'] = $order->getState();

            $data['products'] = [];
            $products = $order->getAllItems();

            foreach ($products as $product) {
                $product = $this->_productRepository->getById($product->getProduct()->getId());
                if (!$product) {
                    continue;
                }
                if ($product->getTypeId() === 'configurable') {
                    continue;
                }

                $productData = [];
                $productData['name'] = $product->getName();
                $productData['url'] = $product->getProductUrl();
                $productData['price'] = $product->getPrice();
                $productData['image'] = $this->getImageUrl($product, 'product_page_image_medium');
                if ($product->getSku()) {
                    $productData['sku'] = $product->getSku();
                }
                if ($product->getBrand()) {
                    $productData['brand'] = $product->getBrand();
                }
                if ($product->getUrlKey()) {
                    $productData['tag'] = $product->getUrlKey();
                }

                $productData['description'] = $product->getDescription();
                $data['products'][$product->getId()] = $productData;
            }

            //skip if no products
            if ($data['products']) {
                $params = ['token' => $apiKey, 'data' => json_encode($data)];
                $this->_curl->post(self::ORDER_API_URL, $params);
                $response = $this->_curl->getBody();
            }

        } catch (\Exception $e) {
            $this->_logger->error(json_encode($e));
        }
    }

    protected function getImageUrl($product, string $imageType = '')
    {
        $storeId = $this->_storemanager->getStore()->getId();
        $this->_appEmulation->startEnvironmentEmulation($storeId, \Magento\Framework\App\Area::AREA_FRONTEND, true);
        $imageBlock =  $this->_blockFactory->createBlock('Magento\Catalog\Block\Product\ListProduct');
        $productImage = $imageBlock->getImage($product, $imageType);
        $imageUrl = $productImage->getImageUrl();
        $this->_appEmulation->stopEnvironmentEmulation();
        return $imageUrl;
    }

    private function getConfigValue($field, $storeId = null)
    {
        return $this->scopeConfig->getValue(
            $field, ScopeInterface::SCOPE_STORE, $storeId
        );
    }
}
