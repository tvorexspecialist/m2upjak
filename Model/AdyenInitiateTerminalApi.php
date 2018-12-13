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
 * Adyen Payment Module
 *
 * Copyright (c) 2018 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model;

use Adyen\Payment\Api\AdyenInitiateTerminalApiInterface;
use Adyen\Payment\Model\Ui\AdyenPosCloudConfigProvider;
use Adyen\Util\Util;

class AdyenInitiateTerminalApi implements AdyenInitiateTerminalApiInterface
{
    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $adyenHelper;

    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    private $adyenLogger;

    /**
     * @var \Adyen\Client
     */
    protected $client;

    /**
     * @var int
     */
    protected $storeId;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * AdyenInitiateTerminalApi constructor.
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     * @param \Magento\Checkout\Model\Session $_checkoutSession
     * @param array $data
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->adyenLogger = $adyenLogger;
        $this->checkoutSession = $checkoutSession;
        $this->storeId = $storeManager->getStore()->getId();

        // initialize client
        $apiKey = $this->adyenHelper->getPosApiKey($this->storeId);
        $client = $this->adyenHelper->initializeAdyenClient($this->storeId, $apiKey);

        //Set configurable option in M2
        $posTimeout = $this->adyenHelper->getAdyenPosCloudConfigData('pos_timeout', $this->storeId);
        if (!empty($posTimeout)) {
            $client->setTimeout($posTimeout);
        }

        $this->client = $client;
    }

    /**
     * Trigger sync call on terminal
     * @return mixed
     * @throws \Exception
     */
    public function initiate()
    {
        $quote = $this->checkoutSession->getQuote();
        $payment = $quote->getPayment();
        $payment->setMethod(AdyenPosCloudConfigProvider::CODE);
        $reference = $quote->reserveOrderId()->getReservedOrderId();

        $service = $this->adyenHelper->createAdyenPosPaymentService($this->client);
        $transactionType = \Adyen\TransactionType::NORMAL;
        $poiId = $this->adyenHelper->getPoiId($this->storeId);
        $serviceID = date("dHis");
        $initiateDate = date("U");
        $timeStamper = date("Y-m-d") . "T" . date("H:i:s+00:00");
        $customerId = $quote->getCustomerId();

        $request = [
            'SaleToPOIRequest' =>
                [
                    'MessageHeader' =>
                        [
                            'MessageType' => 'Request',
                            'MessageClass' => 'Service',
                            'MessageCategory' => 'Payment',
                            'SaleID' => 'Magento2Cloud',
                            'POIID' => $poiId,
                            'ProtocolVersion' => '3.0',
                            'ServiceID' => $serviceID,
                        ],
                    'PaymentRequest' =>
                        [
                            'SaleData' =>
                                [
                                    'TokenRequestedType' => 'Customer',
                                    'SaleTransactionID' =>
                                        [
                                            'TransactionID' => $reference,
                                            'TimeStamp' => $timeStamper,
                                        ],
                                ],
                            'PaymentTransaction' =>
                                [
                                    'AmountsReq' =>
                                        [
                                            'Currency' => $quote->getCurrency()->getQuoteCurrencyCode(),
                                            'RequestedAmount' => doubleval($quote->getGrandTotal()),
                                        ],
                                ],
                            'PaymentData' =>
                                [
                                    'PaymentType' => $transactionType,
                                ],
                        ],
                ],
        ];

        // If customer exists add it into the request to store request
        if (!empty($customerId)) {
            $shopperEmail = $quote->getCustomerEmail();
            $recurringContract = $this->adyenHelper->getAdyenPosCloudConfigData('recurring_type', $this->storeId);

            if (!empty($recurringContract) && !empty($shopperEmail)) {
                $recurringDetails = [
                    'shopperEmail' => $shopperEmail,
                    'shopperReference' => strval($customerId),
                    'recurringContract' => $recurringContract
                ];
                $request['SaleToPOIRequest']['PaymentRequest']['SaleData']['SaleToAcquirerData'] = http_build_query($recurringDetails);
            }
        }

        $quote->getPayment()->getMethodInstance()->getInfoInstance()->setAdditionalInformation(
            'serviceID',
            $serviceID
        );
        $quote->getPayment()->getMethodInstance()->getInfoInstance()->setAdditionalInformation(
            'initiateDate',
            $initiateDate
        );

        try {
            $response = $service->runTenderSync($request);
        } catch (\Adyen\AdyenException $e) {
            //Not able to perform a payment
            $this->adyenLogger->addAdyenDebug("adyenexception");
            $response['error'] = $e->getMessage();
        } catch (\Exception $e) {
            //Probably timeout
            $quote->getPayment()->getMethodInstance()->getInfoInstance()->setAdditionalInformation(
                'terminalResponse',
                null
            );
            $quote->save();
            $response['error'] = $e->getMessage();
            throw $e;
        }
        $quote->getPayment()->getMethodInstance()->getInfoInstance()->setAdditionalInformation(
            'terminalResponse',
            $response
        );

        $quote->save();
        return $response;
    }
}
