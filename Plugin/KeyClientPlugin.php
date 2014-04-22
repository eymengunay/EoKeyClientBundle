<?php

/*
 * This file is part of the EoKeyClient package.
 *
 * (c) 2014 Eymen Gunay <eymen@egunay.com>
 */

namespace Eo\KeyClientBundle\Plugin;

use Eo\KeyClient\Client;
use Eo\KeyClient\Payment\PaymentRequest;
use Eo\KeyClient\Notification\RedirectNotification;
use JMS\Payment\CoreBundle\Plugin\AbstractPlugin;
use JMS\Payment\CoreBundle\Model\ExtendedDataInterface;
use JMS\Payment\CoreBundle\Model\FinancialTransactionInterface;
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

        $client   = new Client($alias, $secret);
        $payment  = new PaymentRequest(intval($transaction->getRequestedAmount() * 100), 'EUR', $this->getTransactionCode($data), $this->getCancelUrl($data));
        $redirect = new RedirectNotification($this->getReturnUrl($data));
        $payment->addNotification($redirect);

        // Redirect new transactions to payment page
        if ($transaction->getState() === FinancialTransactionInterface::STATE_NEW) {
            $redirectUrl   = $client->createPaymentUrl($payment);
            $actionRequest = new ActionRequiredException('User must authorize the transaction.');
            $actionRequest->setFinancialTransaction($transaction);
            $actionRequest->setAction(new VisitUrl($redirectUrl));

            throw $actionRequest;  
        }
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