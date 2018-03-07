<?php
/**
 * 2018 Payson AB
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 *  @author    Payson AB <integration@payson.se>
 *  @copyright 2018 Payson AB
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class PaysonCheckout2ConfirmationModuleFrontController extends ModuleFrontController
{
    
    public $display_column_left = false;
    public $display_column_right = false;
    
    public function init()
    {
        parent::init();

        if (_PCO_LOG_) {
            Logger::addLog('* ' . __FILE__ . ' -> ' . __METHOD__ . ' *', 1, null, null, null, true);
        }
        if (_PCO_LOG_) {
            Logger::addLog('Call Type: ' . Tools::getValue('call'), 1, null, null, null, true);
        }
        
        $cartId = (int) Tools::getValue('id_cart');
        if (!isset($cartId)|| $cartId < 1 || $cartId == null) {
            Tools::redirect('index.php');
        }

        $checkoutId = Tools::getValue('checkout');
        if (!isset($checkoutId) || $checkoutId == null) {
            if (isset($this->context->cookie->paysonCheckoutId) && $this->context->cookie->paysonCheckoutId != null) {
                // Get checkout ID from cookie
                $checkoutId = $this->context->cookie->paysonCheckoutId;
                if (_PCO_LOG_) {
                    Logger::addLog('No checkout ID in query, loaded: ' . $checkoutId . ' from cookie.', 1, null, null, null, true);
                }
            } else {
                if (_PCO_LOG_) {
                    Logger::addLog('No checkout ID in cookie, redirect.', 1, null, null, null, true);
                }
                Tools::redirect('index.php');
            }
        }

        $cart = new Cart($cartId);

        if (!$cart->checkQuantities()) {
            Tools::redirect('order.php?step=3');
        }

        require_once(_PS_MODULE_DIR_ . 'paysoncheckout2/paysoncheckout2.php');
        $payson = new PaysonCheckout2();
        $paysonApi = $payson->getPaysonApiInstance();
        
        $checkout = $paysonApi->GetCheckout($checkoutId);

        if (_PCO_LOG_) {
            Logger::addLog('Cart ID: ' . $cart->id, 1, null, null, null, true);
        }
        if (_PCO_LOG_) {
            Logger::addLog('Cart delivery cost: ' . $cart->getOrderTotal(true, Cart::ONLY_SHIPPING), 1, null, null, null, true);
        }
        if (_PCO_LOG_) {
            Logger::addLog('Cart total: ' . $cart->getOrderTotal(true, Cart::BOTH), 1, null, null, null, true);
        }
        if (_PCO_LOG_) {
            Logger::addLog('Checkout ID: ' . $checkout->id, 1, null, null, null, true);
        }
        if (_PCO_LOG_) {
            Logger::addLog('Checkout total: ' . $checkout->payData->totalPriceIncludingTax, 1, null, null, null, true);
        }
        if (_PCO_LOG_) {
            Logger::addLog('Checkout Status: ' . $checkout->status, 1, null, null, null, true);
        }

        $orderCreated = false;

        // For testing
        //$checkout->status = 'expired';

        switch ($checkout->status) {
            case 'created':
                Tools::redirect('order.php?step=3');
                break;
            case 'readyToShip':
                if ($cart->OrderExists() == false) {
                    // Create PS order
                    $orderCreated = $payson->createOrderPS($cart->id, $checkout);
                    if (_PCO_LOG_) {
                        Logger::addLog('New order ID: ' . $orderCreated, 1, null, null, null, true);
                    }
                } else {
                    $orderAlreadyCreated = true;
                }
                break;
            case 'readyToPay':
                Tools::redirect('order.php?step=3');
                break;
            case 'denied':
                $payson->updatePaysonOrderEvent($checkout, $cartId);
                $this->context->cookie->__set('validation_error', $this->l('The payment was denied. Please try using a different payment method.'));
                Tools::redirect('order.php?step=3');
                break;
            case 'canceled':
                $payson->updatePaysonOrderEvent($checkout, $cartId);
                $this->context->cookie->__set('validation_error', $this->l('This order has been canceled. Please try again.'));
                Tools::redirect('index.php');
                break;
            case 'expired':
                $this->context->cookie->__set('paysonCheckoutId', null);
                $payson->updatePaysonOrderEvent($checkout, $cartId);
                $this->context->cookie->__set('validation_error', $this->l('This order has expired. Please try again.'));
                Tools::redirect('index.php');
                break;
            case 'shipped':
                $payson->updatePaysonOrderEvent($checkout, $cartId);
                Tools::redirect('index.php');
                break;
            default:
                Logger::addLog('Unknown Checkout Status: ' . $checkout->status, 2, null, null, null, true);
                //$this->context->cookie->__set('validation_error', $this->l('Unknown order status.'));
                Tools::redirect('index.php');
        }

        $this->context->smarty->assign(array('snippet' => $checkout->snippet));
        
        if ($orderCreated !== false || $orderAlreadyCreated == true) {
            // Show CO2 order confirmation
            if (_PCO_LOG_) {
                Logger::addLog('Order completed successfully.', 1, null, null, null, true);
            }

            // Delete checkout id cookie
            $this->context->cookie->__set('paysonCheckoutId', null);

            $this->setTemplate('payment.tpl');
        } else {
            // Show order confirmation with errors
            if (_PCO_LOG_) {
                Logger::addLog('Order creation was unsuccessfull.', 1, null, null, null, true);
            }
            $this->context->cookie->__set('paysonCheckoutId', null);
            //$this->context->smarty->assign('payson_error', $this->l('Unable to create order.'));
            //$this->setTemplate('payment.tpl');
            Tools::redirect('index.php');
        }
    }
}