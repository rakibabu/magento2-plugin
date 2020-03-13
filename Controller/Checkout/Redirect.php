<?php
/**
 * Copyright © 2015 Pay.nl All rights reserved.
 */

namespace Paynl\Payment\Controller\Checkout;

use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Model\OrderRepository;
use Paynl\Error\Error;
use Paynl\Payment\Controller\PayAction;

/**
 * Description of Redirect
 *
 * @author Andy Pieters <andy@pay.nl>
 */
class Redirect extends PayAction
{
    /**
     * @var \Paynl\Payment\Model\Config
     */
	private $config;

    /**
     * @var \Magento\Checkout\Model\Session
     */
	private $checkoutSession;

    /**
     * @var \Psr\Log\LoggerInterface
     */
	private $_logger;

    /**
     * @var PaymentHelper
     */
	private $paymentHelper;

	/**
	 * @var QuoteRepository
	 */
	private $quoteRepository;


	/**
	 * @var OrderRepository
	 */
	private $orderRepository;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Paynl\Payment\Model\Config $config
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Paynl\Payment\Model\Config $config,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Psr\Log\LoggerInterface $logger,
        PaymentHelper $paymentHelper,
		QuoteRepository $quoteRepository,
		OrderRepository $orderRepository
    )
    {
        $this->config          = $config; // Pay.nl config helper
        $this->checkoutSession = $checkoutSession;
        $this->_logger         = $logger;
        $this->paymentHelper   = $paymentHelper;
		$this->quoteRepository = $quoteRepository;
		$this->orderRepository = $orderRepository;

        parent::__construct($context);
    }

    public function execute()
    {
        try {
            $order = $this->checkoutSession->getLastRealOrder();

            if(empty($order)){
                throw new Error('No order found in session, please try again');
            }

          # Restore the quote
          $quote = $this->quoteRepository->get($order->getQuoteId());
          $quote->setIsActive(true)->setReservedOrderId(null);
          $this->checkoutSession->replaceQuote($quote);
          $this->quoteRepository->save($quote);

          $payment = $order->getPayment();

          if (empty($payment)) {
            $this->_redirect('checkout/cart');
            return;
          }

            $methodInstance = $this->paymentHelper->getMethodInstance($payment->getMethod());
            if ($methodInstance instanceof \Paynl\Payment\Model\Paymentmethod\Paymentmethod) {
                $this->_logger->notice('PAY.: Start new payment for order ' . $order->getId());
                $redirectUrl = $methodInstance->startTransaction($order);
                $this->getResponse()->setNoCacheHeaders();
                $this->getResponse()->setRedirect($redirectUrl);
            } else {
              throw new Error('PAY.: Method is not a paynl payment method');
            }

        } catch (\Exception $e) {
            $this->_getCheckoutSession()->restoreQuote();
            $this->messageManager->addExceptionMessage($e, __('Something went wrong, please try again later'));
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
            $this->_logger->critical($e);

            $this->_redirect('checkout/cart');
        }
    }

    /**
     * Return checkout session object
     *
     * @return \Magento\Checkout\Model\Session
     */
    protected function _getCheckoutSession()
    {
        return $this->checkoutSession;
    }
}
