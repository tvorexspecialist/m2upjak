<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2018 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Info;

use Magento\Framework\View\Element\Template;

class PaymentLink extends AbstractInfo
{
    /**
     * @var \Magento\Framework\Registry
     */
    private $registry;

    /**
     * @var \Adyen\Payment\Gateway\Command\PayByMailCommand
     */
    private $payByMail;

    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory $adyenOrderPaymentCollectionFactory,
        Template\Context $context,
        array $data = [],
        \Magento\Framework\Registry $registry,
        \Adyen\Payment\Gateway\Command\PayByMailCommand $payByMailCommand
    ) {
        $this->registry = $registry;
        $this->payByMail = $payByMailCommand;

        parent::__construct($adyenHelper, $adyenOrderPaymentCollectionFactory, $context, $data);
    }

    /**
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder()
    {
        return $this->registry->registry('current_order');
    }

    /**
     * @return \Magento\Sales\Model\Order\Payment
     */
    public function getPayment()
    {
        $order = $this->getOrder();

        return $order->getPayment();
    }

    /**
     * @return string
     */
    public function getPaymentLinkUrl()
    {
        return $this->payByMail->generatePaymentUrl($this->getPayment(), $this->getOrder()->getTotalDue());
    }

    /**
     * Check if order was placed using Adyen payment method
     * and if total due is greater than zero while one or more payments have been made
     *
     * @return string
     */
    public function _toHtml()
    {
        return strpos($this->getPayment()->getMethod(), 'adyen_') === 0
            && $this->getOrder()->getTotalInvoiced() > 0
            && $this->getOrder()->getTotalDue() > 0
                ? parent::_toHtml()
                : '';
    }
}
