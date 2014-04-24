<?php

/*
 * This file is part of the EoKeyClient package.
 *
 * (c) 2014 Eymen Gunay <eymen@egunay.com>
 */

namespace Eo\KeyClientBundle\Plugin;

use Eo\KeyClient\Client;
use Eo\KeyClient\Payment\PaymentRequest;
use JMS\Payment\CoreBundle\Model\ExtendedDataInterface;
use JMS\Payment\CoreBundle\Model\FinancialTransactionInterface;
use JMS\Payment\CoreBundle\Plugin\AbstractPlugin;
use JMS\Payment\CoreBundle\Plugin\PluginInterface;
use JMS\Payment\CoreBundle\Plugin\Exception\FinancialException;
use JMS\Payment\CoreBundle\Plugin\Exception\Action\VisitUrl;
use JMS\Payment\CoreBundle\Plugin\Exception\ActionRequiredException;
use Symfony\Component\DependencyInjection\ContainerInterface;

class KeyClientPlugin extends AbstractPlugin
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var string
     */
    protected $returnUrl;

    /**
     * @var string
     */
    protected $cancelUrl;

    /**
     * Class constructor
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function processes($name)
    {
        return 'keyclient' === $name;
    }

    /**
     * {@inheritdoc}
     */
    public function approveAndDeposit(FinancialTransactionInterface $transaction, $retry)
    {
        $data   = $transaction->getExtendedData();
        $alias  = $this->container->getParameter('eo_keyclient.alias');
        $secret = $this->container->getParameter('eo_keyclient.secret');

        $client = new Client($alias, $secret);
        $paymentRequest = new PaymentRequest(
            intval($transaction->getRequestedAmount() * 100),  // Amount
            'EUR',                                             // Currency
            $this->getTransactionCode($data),                  // Transaction code
            $this->getReturnUrl($data),                        // Complete url
            $this->getCancelUrl($data)                         // Cancel url
        );

        // Redirect new transactions to payment page
        if ($transaction->getState() === FinancialTransactionInterface::STATE_NEW) {
            $redirectUrl   = $client->createPaymentUrl($paymentRequest);
            $actionRequest = new ActionRequiredException('User must authorize the transaction.');
            $actionRequest->setFinancialTransaction($transaction);
            $actionRequest->setAction(new VisitUrl($redirectUrl));

            throw $actionRequest;  
        }

        $paymentResponse = $client->parsePaymentResponse();

        if ($paymentResponse->getResult() == 'KO') {
            $ex = new FinancialException('Key Client error: '.$paymentResponse->getMessage());
            $ex->setFinancialTransaction($transaction);
            $transaction->setResponseCode('Failed');
            $transaction->setReasonCode($paymentResponse->getResult());

            throw $ex;
        }

        $transaction->setReferenceNumber($paymentResponse->getSignature());
        $transaction->setProcessedAmount($paymentResponse->getAmount());
        $transaction->setResponseCode(PluginInterface::RESPONSE_CODE_SUCCESS);
        $transaction->setReasonCode(PluginInterface::REASON_CODE_SUCCESS);
    }

    /**
     * Get returnUrl
     *
     * @param  ExtendedDataInterface $data
     * @return string
     */
    protected function getReturnUrl(ExtendedDataInterface $data)
    {
        if ($data->has('return_url')) {
            return $data->get('return_url');
        }
        else if (0 !== strlen($this->returnUrl)) {
            return $this->returnUrl;
        }

        throw new \RuntimeException('You must configure a return url.');
    }

    /**
     * Get cancelUrl
     *
     * @param  ExtendedDataInterface $data
     * @return string
     */
    protected function getCancelUrl(ExtendedDataInterface $data)
    {
        if ($data->has('cancel_url')) {
            return $data->get('cancel_url');
        }
        else if (0 !== strlen($this->cancelUrl)) {
            return $this->cancelUrl;
        }

        throw new \RuntimeException('You must configure a cancel url.');
    }

    /**
     * Get transactionCode
     * 
     * @param  ExtendedDataInterface $data
     * @return string
     */
    protected function getTransactionCode(ExtendedDataInterface $data)
    {
        if ($data->has('transaction_code')) {
            return $data->get('transaction_code');
        }

        throw new \RuntimeException('You must configure a transaction code.');
    }
}