<?php

/**
 * Acquia/CommerceManager/Helper/Stock.php
 *
 * Acquia Commerce Stock Helper
 *
 * All rights reserved. No unauthorized distribution or reproduction.
 */

namespace Acquia\CommerceManager\Helper;

use Acquia\CommerceManager\Helper\Data as ClientHelper;
use Magento\CatalogInventory\Api\Data\StockItemInterfaceFactory;
use Magento\CatalogInventory\Api\StockItemCriteriaInterfaceFactory;
use Magento\CatalogInventory\Api\StockItemRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Webapi\ServiceOutputProcessor;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Framework\Module\ModuleListInterface;

/**
 * Stock
 *
 * Acquia Commerce Stock Helper
 */
class Stock extends AbstractHelper
{

    /**
     * Conductor Stock Update Endpoint
     *
     * @const ENDPOINT_PRODUCT_UPDATE
     */
    const ENDPOINT_STOCK_UPDATE = 'ingest/product-stock';

    /**
     * Consumer name for stock.
     */
    const STOCK_PUSH_CONSUMER = 'connector.stock.push';

    /**
     * Magento WebAPI Output Processor
     *
     * @var ServiceOutputProcessor $serviceOutputProcessor
     */
    protected $serviceOutputProcessor;

    /**
     * Stock Item factory object.
     *
     * @var \Magento\CatalogInventory\Api\Data\StockItemInterfaceFactory
     */
    protected $stockItemFactory;

    /**
     * Stock Item Repository object.
     *
     * @var \Magento\CatalogInventory\Api\StockItemRepositoryInterface
     */
    protected $stockItemRepository;

    /**
     * Stock Item Criteria builder object.
     *
     * @var \Magento\CatalogInventory\Api\StockItemCriteriaInterfaceFactory
     */
    protected $stockItemCriteriaFactory;

    /**
     * Acquia Commerce Manager Client Helper
     *
     * @var ClientHelper $clientHelper
     */
    private $clientHelper;

    /**
     * EE only
     *
     * @var \Magento\Framework\MessageQueue\PublisherInterface
     */
    private $publisher;

    /**
     * Magento WebAPI Service Class Name (for output formatting of stock)
     *
     * @var string $stockServiceClassName
     */
    protected $serviceClassName = \Magento\CatalogInventory\Api\StockItemRepositoryInterface::class;

    /**
     * Magento WebAPI Service Method Name (for output formatting)
     *
     * @var string $serviceMethodName
     */
    protected $serviceMethodName = 'get';

    /**
     * Magento Message Queue Module Name
     * @const MESSAGEQUEUE_MODULE
     */
    const MESSAGEQUEUE_MODULE = 'Magento_MessageQueue';

    /**
     * Magento Module List Service
     * @var ModuleListInterface $moduleList
     */
    private $moduleList;

    /**
     * Stock constructor.
     *
     * @param Context $context
     * @param ServiceOutputProcessor $outputProc
     * @param StockItemInterfaceFactory $stockItemFactory
     * @param StockItemCriteriaInterfaceFactory $stockItemCriteriaFactory
     * @param StockItemRepositoryInterface $stockItemRepository
     * @param Data $clientHelper
     * @param ModuleListInterface $moduleList
     */
    public function __construct(
        Context $context,
        ServiceOutputProcessor $outputProc,
        StockItemInterfaceFactory $stockItemFactory,
        StockItemCriteriaInterfaceFactory $stockItemCriteriaFactory,
        StockItemRepositoryInterface $stockItemRepository,
        ClientHelper $clientHelper,
        ModuleListInterface $moduleList
    ) {
        $this->serviceOutputProcessor = $outputProc;
        $this->stockItemFactory = $stockItemFactory;
        $this->stockItemCriteriaFactory = $stockItemCriteriaFactory;
        $this->stockItemRepository = $stockItemRepository;
        $this->clientHelper = $clientHelper;
        $this->moduleList = $moduleList;

        parent::__construct($context);
    }

    /**
     * getMessageQueueEnabled
     *
     * Check if the Magento EE Magento\Framework\MessageQueue\PublisherInterface class is installed / enabled.
     *
     * @return bool $enabled
     */
    public function getMessageQueueEnabled()
    {
        return ($this->moduleList->has(self::MESSAGEQUEUE_MODULE));
    }

    /**
     * pushStock.
     *
     * Helper function to push stock data through API.
     *
     * @param array $stockData Stock data.
     * @param string $storeId Magento store ID or null to use default store ID.
     */
    public function pushStock($stockData, $storeId = NULL) {
        // Send Connector request.
        $doReq = function ($client, $opt) use ($stockData) {
            $opt['json'] = $stockData;
            return $client->post(self::ENDPOINT_STOCK_UPDATE, $opt);
        };

        $this->clientHelper->tryRequest($doReq, 'pushStock', $storeId);
    }

    /**
     * getStockInfo.
     *
     * Get stock info for a product.
     *
     * @param $productId
     * @param $scopeId
     * @param $returnObject
     *
     * @return array
     */
    public function getStockInfo($productId, $scopeId = NULL, $returnObject = FALSE)
    {
        // When consumers are running, StockRegistry uses static cache.
        // With this cache applied, stock for a particular product if
        // changed multiple times within lifespan of consumer, it pushes
        // only the first change every-time.
        // To avoid the issue, we use the code used to cache stock info
        // directly. Code taken from below class::method:
        // Magento\CatalogInventory\Model\StockRegistryProvider::getStockItem().
        $criteria = $this->stockItemCriteriaFactory->create();
        $criteria->setProductsFilter($productId);

        if ($scopeId) {
            $criteria->setScopeFilter($scopeId);
        }

        $collection = $this->stockItemRepository->getList($criteria);
        $stockItem = current($collection->getItems());

        if (!($stockItem && $stockItem->getItemId())) {
            $stockItem = $this->stockItemFactory->create();
        }

        if ($returnObject) {
            return $stockItem;
        }

        $stock = $this->serviceOutputProcessor->process(
            $stockItem,
            $this->serviceClassName,
            $this->serviceMethodName
        );

        return $stock;
    }

    /**
     * getMessageQueue
     *
     * Build a MessageQueue\Publisher model from the ObjectManager to prevent
     * coupling to EE.
     *
     * @return null|\Magento\Framework\MessageQueue\Publisher
     */
    private function getMessageQueue()
    {
        if(!$this->publisher) {
            if ($this->getMessageQueueEnabled()) {
                // Object Manager's get() is type-preference aware,
                // so we can request a class using its interface
                $this->publisher = ObjectManager::getInstance()->get(
                    PublisherInterface::class
                )->create();
            } else {
                $this->publisher = null;
                // Or perhaps use a class that mimics the queue using Magento CRON
            }
        }

        return ($this->publisher);
    }

    /**
     * Add stock message (consisting of product id and sku) to queue.
     *
     * @param mixed $message
     */
    public function addStockMessageToQueue($message)
    {
        // MessageQueue is EE only. So do nothing if there is no queue.
        $messageQueue = $this->getMessageQueue();
        if($messageQueue) {
            $messageQueue->publish(self::STOCK_PUSH_CONSUMER, $message);
        } else {
            // At 20180123, do nothing.
            // Later (Malachy or Anuj): Use Magento CRON instead
        }
    }

    /**
     * Get stock mode (pull / push).
     *
     * @return bool
     *   TRUE if delta changes are to be pushed.
     */
    public function pushDeltaChanges()
    {
        $path = 'webapi/acquia_commerce_settings/push_stock';

        return (bool) $this->scopeConfig->getValue(
            $path,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );
    }

    /**
     * Get from config if non-detla changes are to be pushed.
     *
     * @return bool
     *   TRUE if non-delta (nonzero/full) changes are to be pushed.
     */
    public function pushNonDeltaChanges()
    {
        $path = 'webapi/acquia_commerce_settings/push_stock_non_delta';

        return (bool) $this->scopeConfig->getValue(
            $path,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );
    }

}
