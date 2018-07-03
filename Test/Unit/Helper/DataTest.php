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
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Tests\Helper;

class DataTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $dataHelper;

    private function getSimpleMock($originalClassName)
    {
        return $this->getMockBuilder($originalClassName)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function setUp()
    {
        $context = $this->getSimpleMock(\Magento\Framework\App\Helper\Context::class);
        $encryptor = $this->getSimpleMock(\Magento\Framework\Encryption\EncryptorInterface::class);
        $dataStorage = $this->getSimpleMock(\Magento\Framework\Config\DataInterface::class);
        $country = $this->getSimpleMock(\Magento\Directory\Model\Config\Source\Country::class);
        $moduleList = $this->getSimpleMock(\Magento\Framework\Module\ModuleListInterface::class);
        $billingAgreementCollectionFactory = $this->getSimpleMock(\Adyen\Payment\Model\ResourceModel\Billing\Agreement\CollectionFactory::class);
        $assetRepo = $this->getSimpleMock(\Magento\Framework\View\Asset\Repository::class);
        $assetSource = $this->getSimpleMock(\Magento\Framework\View\Asset\Source::class);
        $notificationFactory = $this->getSimpleMock(\Adyen\Payment\Model\ResourceModel\Notification\CollectionFactory::class);
        $taxConfig = $this->getSimpleMock(\Magento\Tax\Model\Config::class);
        $taxCalculation = $this->getSimpleMock(\Magento\Tax\Model\Calculation::class);

        $this->dataHelper = new \Adyen\Payment\Helper\Data(
            $context,
            $encryptor,
            $dataStorage,
            $country,
            $moduleList,
            $billingAgreementCollectionFactory,
            $assetRepo,
            $assetSource,
            $notificationFactory,
            $taxConfig,
            $taxCalculation
        );
    }

    public function testFormatAmount()
    {
        $this->assertEquals('1234', $this->dataHelper->formatAmount('12.34', 'EUR'));
        $this->assertEquals('1200', $this->dataHelper->formatAmount('12.00', 'USD'));
        $this->assertEquals('12', $this->dataHelper->formatAmount('12.00', 'JPY'));
    }
    
    public function testisPaymentMethodOpenInvoiceMethod()
    {
        $this->assertEquals(true, $this->dataHelper->isPaymentMethodOpenInvoiceMethod('klarna'));
        $this->assertEquals(true, $this->dataHelper->isPaymentMethodOpenInvoiceMethod('klarna_account'));
        $this->assertEquals(true, $this->dataHelper->isPaymentMethodOpenInvoiceMethod('afterpay'));
        $this->assertEquals(true, $this->dataHelper->isPaymentMethodOpenInvoiceMethod('afterpay_default'));
        $this->assertEquals(true, $this->dataHelper->isPaymentMethodOpenInvoiceMethod('ratepay'));
        $this->assertEquals(false, $this->dataHelper->isPaymentMethodOpenInvoiceMethod('ideal'));
        $this->assertEquals(true, $this->dataHelper->isPaymentMethodOpenInvoiceMethod('test_klarna'));
    }
}
