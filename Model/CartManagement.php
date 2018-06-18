<?php

/**
 * Acquia/CommerceManager/Model/CartManagement.php
 *
 * Acquia Commerce Manager Cart Api Extended Operations Model
 *
 * All rights reserved. No unauthorized distribution or reproduction.
 */

namespace Acquia\CommerceManager\Model;

use Magento\Directory\Model\RegionFactory;
use Acquia\CommerceManager\Api\Data\CartWithTotalsInterface;
use Acquia\CommerceManager\Api\CartManagementInterface as ApiInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\Message\MessageInterface;
use Magento\Quote\Api\BillingAddressManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartItemRepositoryInterface;
use Magento\Quote\Api\CartTotalRepositoryInterface;
use Magento\Quote\Api\CouponManagementInterface;
use Magento\Quote\Api\PaymentMethodManagementInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Framework\Reflection\DataObjectProcessor;
use Magento\Framework\App\State;

/**
 * CartManagement
 *
 * Acquia Commerce Manager Cart Api Extended Operations Model
 */
class CartManagement implements ApiInterface
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var RegionFactory $regionFactory
     */
    protected $regionFactory;

    /**
     * Coupon Managment Model
     * @var CouponManagementInterface $couponManager
     */
    protected $couponManager;

    /**
     * Magento EDA Dispatcher
     * @var ManagerInterface $eventManager
     */
    protected $eventManager;

    /**
     * Catalog Product Repository
     * @var ProductRepositoryInterface $productRepository
     */
    protected $productRepository;

    /**
     * Quote / Cart Managment Model
     * @var CartManagementInterface $quoteManager
     */
    protected $quoteManager;

    /**
     * Quote / Cart Repository
     * @var CartRepositoryInterface $quoteRepository
     */
    protected $quoteRepository;

    /**
     * Cart Item repository.
     * @var \Magento\Quote\Api\CartItemRepositoryInterface
     */
    protected $cartItemRepository;

    /**
     * Cart Totals Repository
     * @var \Magento\Quote\Api\CartTotalRepositoryInterface
     */
    protected $cartTotalsRepository;

    /**
     * @var MessageManagerInterface
     */
    protected $messageManager;

    /**
     * @var BillingAddressManagementInterface
     */
    protected $billingManager;

    /**
     * @var ShippingInformationManagementInterface
     */
    protected $shippingManager;

    /**
     * @var PaymentMethodManagementInterface
     */
    protected $paymentManager;

    /**
     * @var \Magento\Shipping\Model\Shipping
     */
    private $_shipping;

    /**
     * @var \Magento\Quote\Api\Data\CartExtensionFactory
     */
    private $cartExtensionFactory;

    /**
     * @var \Magento\Quote\Model\Quote\ShippingAssignment\ShippingAssignmentProcessor
     */
    private $shippingAssignmentProcessor;

    protected $logger;

    /**
     * Data processor.
     * @var DataObjectProcessor
     */
    protected $dataProcessor;

    /**
     * Application mode
     * @var State $appMode
     */
    protected $appMode;

  /**
     * @param RegionFactory $regionFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Quote\Api\Data\CartExtensionFactory $cartExtensionFactory
     * @param \Magento\Quote\Model\Quote\ShippingAssignment\ShippingAssignmentProcessor $shippingAssignmentProcessor
     * @param \Magento\Shipping\Model\Shipping $shipping
     * @param CartRepositoryInterface $quoteRepository
     * @param ProductRepositoryInterface $productRepository
     * @param CartManagementInterface $quoteManager
     * @param CouponManagementInterface $couponManager
     * @param CartItemRepositoryInterface $cartItemRepository
     * @param CartTotalRepositoryInterface $cartTotalsRepository
     * @param BillingAddressManagementInterface $billingManager
     * @param ShippingInformationManagementInterface $shippingManager
     * @param PaymentMethodManagementInterface $paymentManager
     * @param MessageManagerInterface $messageManager
     * @param ManagerInterface $eventManager
     * @param DataObjectProcessor $dataProcessor
     * @param State $appMode
     */
    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        RegionFactory $regionFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Quote\Api\Data\CartExtensionFactory $cartExtensionFactory,
        \Magento\Quote\Model\Quote\ShippingAssignment\ShippingAssignmentProcessor $shippingAssignmentProcessor,
        \Magento\Shipping\Model\Shipping $shipping,
        CartRepositoryInterface $quoteRepository,
        ProductRepositoryInterface $productRepository,
        CartManagementInterface $quoteManager,
        CouponManagementInterface $couponManager,
        CartItemRepositoryInterface $cartItemRepository,
        CartTotalRepositoryInterface $cartTotalsRepository,
        BillingAddressManagementInterface $billingManager,
        ShippingInformationManagementInterface $shippingManager,
        PaymentMethodManagementInterface $paymentManager,
        MessageManagerInterface $messageManager,
        ManagerInterface $eventManager,
        DataObjectProcessor $dataProcessor,
        State $appMode
    ) {
        $this->regionFactory = $regionFactory;
        $this->logger = $logger;
        $this->cartExtensionFactory = $cartExtensionFactory;
        $this->shippingAssignmentProcessor = $shippingAssignmentProcessor;
        $this->_shipping = $shipping;
        $this->couponManager = $couponManager;
        $this->productRepository = $productRepository;
        $this->quoteManager = $quoteManager;
        $this->quoteRepository = $quoteRepository;
        $this->cartItemRepository = $cartItemRepository;
        $this->cartTotalsRepository = $cartTotalsRepository;
        $this->billingManager = $billingManager;
        $this->shippingManager = $shippingManager;
        $this->paymentManager = $paymentManager;
        $this->messageManager = $messageManager;
        $this->eventManager = $eventManager;
        $this->dataProcessor = $dataProcessor;
        $this->appMode = $appMode;
    }

    /**
     * {@inheritdoc}
     *
     * @param int $customerId Customer ID
     *
     * @return bool $success
     */
    public function abandon($customerId)
    {
        try {
            $quote = $this->quoteRepository->getForCustomer($customerId);

            $this->eventManager->dispatch(
                'acqcomm_cart_abandon',
                ['quote' => $quote]
            );

            $this->quoteRepository->delete($quote);
        } catch (NoSuchEntityException $e) {
            // Intentionally left blank.
            // Except empty catches are a code sniff fail
            // Why is it left blank?
            // Surely we return false.
            // Except 'NoSuchEntityException' just means the customerId doesn't exist
            return true;
        }

        return true;
    }

    /**
     * {@inheritDoc}
     *
     * @param int $customerId Customer ID
     * @param int $cartId Cart ID
     * @param int $storeId Store ID
     * @param string $couponCode Cart coupon code
     *
     * @return bool $couponApplied
     */
    public function associateCart(
        $customerId,
        $cartId,
        $storeId = null,
        $couponCode = null
    ) {
        // Abandon existing cart
        $assigned = false;
        try {
            $quote = $this->quoteRepository->getForCustomer($customerId);

            if ($quote->getId() != $cartId) {
                $this->quoteRepository->delete($quote);
            } else {
                $assigned = true;
            }
        } catch (NoSuchEntityException $e) {
            $assigned = false;
        }

        if ($storeId === null) {
            $storeId = $this->storeManager->getStore()->getId();
        }
        // Associate new cart ID
        if (!$assigned) {
            // Unset existing customer if assigned, this is required when
            // user is trying to change email address for Guest Carts.
            $this->quoteRepository->getActive($cartId)->setCustomerId(null);

            // Always true actually (throws if assignation is unsuccessful)
            $assigned = $this->quoteManager->assignCustomer($cartId, $customerId, $storeId);
        }

        $quote = $this->quoteRepository->getActive($cartId);

        // Assign cart coupon
        $couponApplied = true;
        if ($couponCode) {
            try {
                $couponApplied = $this->couponManager->set(
                    $cartId,
                    $couponCode
                );
            } catch (LocalizedException $e) {
                $couponApplied = false;
            }
        }

        $this->eventManager->dispatch(
            'acqcomm_cart_associate',
            [
                'quote' => $quote,
                'coupon' => $couponCode,
                'couponApplied' => $couponApplied,
            ]
        );

        // $assigned is always true here
        return ($assigned && $couponApplied);
    }

    /**
     * {@inheritDoc}
     *
     * @param int $cartId
     * @param \Magento\Quote\Api\Data\CartItemInterface[] $items
     * @param \Magento\Quote\Api\Data\AddressInterface $billing
     * @param \Magento\Checkout\Api\Data\ShippingInformationInterface $shipping
     * @param \Magento\Quote\Api\Data\PaymentInterface $method
     * @param string $coupon
     * @param mixed $extension
     *
     * @return \Acquia\CommerceManager\Api\Data\CartWithTotalsInterface
     */
    public function updateCart(
        $cartId,
        $items = [],
        AddressInterface $billing = null,
        ShippingInformationInterface $shipping = null,
        PaymentInterface $payment = null,
        $coupon = null,
        $extension = []
    ) {
        $response_message = [];
        $updateTotals = false;

        // We always remove the coupon first and apply again.
        if ($currentCoupon = $this->couponManager->get($cartId)) {
            $this->couponManager->remove($cartId);
        }

        $quote = $this->quoteRepository->getActive($cartId);

        $this->eventManager->dispatch(
            'acqcomm_cart_update_before',
            [
                'quote' => $quote,
                'items' => $items,
                'billing' => $billing,
                'shipping' => $shipping,
                'payment' => $payment,
                'coupon' => $coupon,
                'extension' => $extension,
            ]
        );

        // Reload the quote, we might have saved changes in observers.
        $quote = $this->quoteRepository->getActive($cartId);

        $cart_items_updated = FALSE;

        $new_items_index = [];

        if (is_array($items)) {
            // Loop through all items to build index array.
            foreach ($items as $item) {
                $new_items_index[$item->getSku()] = $item->getQty();
            }
        }

        $new_items = [];

        if (is_array($quote->getItems())) {
            // Loop through all existing items to remove or update.
            foreach ($quote->getItems() as &$item) {
                // Update item.
                if (isset($new_items_index[$item->getSku()])) {
                    if ($item->getQty() != $new_items_index[$item->getSku()]) {
                        $cart_items_updated = TRUE;
                    }

                    $item->setQty($new_items_index[$item->getSku()]);

                    if ($item->getHasError()) {
                        throw new LocalizedException(__($item->getMessage()));
                    }

                    unset($new_items_index[$item->getSku()]);
                    $new_items[] = $item;
                } else {
                    // Just remove.
                    unset($new_items_index[$item->getSku()]);
                    $quote->removeItem($item->getId());
                    $cart_items_updated = TRUE;
                }
            }
        }

        if (is_array($items)) {
            // Loop through all items again to add new ones.
            foreach ($items as $item) {
                if (isset($new_items_index[$item->getSku()])) {
                    $new_items[] = $item;
                    $cart_items_updated = TRUE;
                }
            }
        }

        if ($new_items) {
            $quote->setItems($new_items);
        }

        // Save the quote after adding items.
        $this->quoteRepository->save($quote);

        // Try to apply the coupon if we have items and coupon.
        if (!empty($items) && trim($coupon) != '') {
            try {
                $this->couponManager->set($cartId, $coupon);

                // Success message.
                $response_message['message'] = $this->getMessage('coupon', MessageInterface::TYPE_SUCCESS);
                $response_message['type'] = MessageInterface::TYPE_SUCCESS;
            } catch (\Exception $e) {
                // Error message..
                $error_message = $this->getMessage('coupon', MessageInterface::TYPE_ERROR);
                // If no error message.
                if (!$error_message) {
                    $error_message = $e->getMessage();
                }

                $response_message['message'] = $error_message;
                $response_message['type'] = 'error_coupon';

                // Just returning from here as at this point we hit by an
                // error and don't want to update the cart.
                $cart = $this->quoteRepository->getActive($cartId);
                $totals = $this->cartTotalsRepository->get($cartId);
                return (new CartWithTotals($cart, $totals, $response_message));
            }
        }

        // Clear shipping method when cart item is updated or no cart items
        // left in quote.
        if ($cart_items_updated || empty($items)) {
            if ($shippingAddress = $quote->getShippingAddress()) {
                $shippingAddress->setShippingMethod(null);
                $shippingAddress->save();
                $updateTotals = true;
            }
        } else {
            if ($billing) {
                // Region in Magento is sensitive to something, so check it.
                $billing = $this->checkRegion($billing);
                $this->billingManager->assign($cartId, $billing);
            }

            if ($shipping) {
                // The hybris cart needs the shipping address in the cart
                // before getting shipping estimates.
                // But the Magento API's Shipping Manager expects
                // the ShippingInformation model to have a carrier and method
                // already present. We decide to say Acquia Commerce Manager
                // ShippingInformation's address can be populated *without* the carrier and method.
                // Therefore test if it is missing and if it is missing here just do nothing.
                $carrierCode = $shipping->getShippingCarrierCode();
                $methodCode = $shipping->getShippingMethodCode();

                if ($carrierCode && $methodCode) {
                    // Region in Magento is sensitive to something, so check it.
                    $shippingAddress = $shipping->getShippingAddress();
                    $shippingAddress = $this->checkRegion($shippingAddress);
                    $shipping->setShippingAddress($shippingAddress);
                    $this->shippingManager->saveAddressInformation($cartId, $shipping);
                }
                // Unset shipping info if already there in cart.
                elseif ($shippingAddress = $quote->getShippingAddress()) {
                    $shippingAddress->setShippingMethod(null);
                    $shippingAddress->save();
                    $updateTotals = true;
                }
            }

            if ($payment) {
                $this->paymentManager->set($cartId, $payment);
            }
        }

        if ($updateTotals) {
            $this->updateTotals($cartId);
        }

        $cart = $this->quoteRepository->getActive($cartId);
        $totals = $this->cartTotalsRepository->get($cartId);

        $this->eventManager->dispatch(
            'acqcomm_cart_update_after',
            [
                'quote' => $cart,
                'items' => $items,
                'billing' => $billing,
                'shipping' => $shipping,
                'payment' => $payment,
                'coupon' => $coupon,
                'extension' => $extension,
            ]
        );

        $this->eventManager->dispatch(
            'acqcomm_cart_get',
            ['quote' => $cart, 'totals' => $totals]
        );

        $cart_with_totals = new CartWithTotals($cart, $totals, $response_message);
        // Log the object.
        $this->logCartObject($cart_with_totals);
        return ($cart_with_totals);
    }

    /**
     * Function checkRegion()
     *
     * The Drupal module uses Commerce_Guys address/region plugin
     * It doesn't match Magento. Magento has many more region details in it.
     * TODO change the Drupal module to ask the ecommerce app for all the address meta data.
     * In the meantime, we try to find a matching region in the Magento DB
     *
     * @param \Magento\Quote\Api\Data\AddressInterface $address
     * @return AddressInterface
     */
    public function checkRegion(\Magento\Quote\Api\Data\AddressInterface $address) {
        $region = $address->getRegion();
        $region_id = $address->getRegionId();
        $countryIsoTwoLetter = $address->getCountryId();

        $useRegion = "";
        if($region)
        {
            $useRegion = $region;
        }
        elseif($region_id)
        {
            $useRegion = $region_id;
        }
        if($useRegion && $countryIsoTwoLetter)
        {
            //look up a region code aka region id
            /** @var \Magento\Directory\Model\Region $regionModel */
            $regionModel = $this->regionFactory->create();
            $regionModel = $regionModel->loadByCode($useRegion,$countryIsoTwoLetter);

            if(empty($regionModel->getData()))
            {
                $regionModel = $regionModel->loadByName($useRegion,$countryIsoTwoLetter);
            }
            if(!empty($regionModel->getData()))
            {
                // I am not 100% sure why doing this solves the address-saving problem
                $address->setRegion($regionModel->getName());
                $address->setRegionCode($regionModel->getCode());
                $address->setRegionId($regionModel->getRegionId());
            }
        }
        return $address;
    }


    /**
     * {@inheritDoc}
     *
     * @param int $cartId
     *
     * @return \Acquia\CommerceManager\Api\Data\CartWithTotalsInterface | false
     */
    public function getCart($cartId)
    {
        try {
            $cart = $this->quoteRepository->getActive($cartId);
            $totals = $this->cartTotalsRepository->get($cartId);

            $this->eventManager->dispatch(
                'acqcomm_cart_get',
                ['quote' => $cart, 'totals' => $totals]
            );

            $cart_with_totals = new CartWithTotals($cart, $totals);
            // Log the object.
            $this->logCartObject($cart_with_totals);
            return($cart_with_totals);
        } catch (NoSuchEntityException $e) {
            return (false);
        }
    }

    /**
     * Get message of the given type in a given group.
     *
     * @param string $message_group
     * @param string $message_type
     *
     * @return null|string
     */
    protected function getMessage($message_group, $message_type)
    {
        /** @var \Magento\Framework\Message\Collection $message_collection */
        if ($message_collection = $this->messageManager->getMessages(true, $message_group)) {
            /** @var \Magento\Framework\Message\MessageInterface[] $messages */
            if (!empty($messages = $message_collection->getItemsByType($message_type))) {
                return end($messages)->getText();
            }
        }

        return null;
    }

    /**
     * Wrapper function to update cart totals.
     *
     * @param int $cartId
     */
    protected function updateTotals($cartId) {
        $quote = $this->quoteRepository->getActive($cartId);
        $quote->setTotalsCollectedFlag(false);
        $quote->getShippingAddress()->unsetData('cached_items_all');
        $quote->getShippingAddress()->unsetData('cached_items_nominal');
        $quote->getShippingAddress()->unsetData('cached_items_nonnominal');
        $quote->collectTotals();
        $quote->save();
    }

    /**
     * Log the cart object.
     *
     * @param CartWithTotalsInterface $cart
     */
    protected function logCartObject(CartWithTotalsInterface $cart)
    {
        // If development mode on.
        if ($this->appMode->getMode() == 'developer') {
            $data = $this->dataProcessor->buildOutputDataArray($cart, 'Acquia\CommerceManager\Api\Data\CartWithTotalsInterface');
            if (is_array($data)) {
                $this->logger->debug(json_encode($data));
            }
        }
    }

}
