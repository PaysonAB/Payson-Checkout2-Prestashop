<?php
/**
 * 2019 Payson AB
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 *  @author    Payson AB <integration@payson.se>
 *  @copyright 2019 Payson AB
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class PaysonCheckout2NotificationsModuleFrontController extends ModuleFrontController
{

    public function init()
    {

        // Give order confirmation a chance to finish
        sleep(2);
        
        PaysonCheckout2::paysonAddLog('* ' . __FILE__ . ' -> ' . __METHOD__ . ' *');
        PaysonCheckout2::paysonAddLog('Notification Query: ' . print_r($_REQUEST, true));

        $call = Tools::getValue('call');

        if ($call == 'notification') {
            $cartId = (int) Tools::getValue('id_cart');
            if (!isset($cartId) || $cartId == null) {
                PaysonCheckout2::paysonAddLog('Notification No cart ID.', 2);
                var_dump(http_response_code(500));
                exit();
            }
            
            require_once(_PS_MODULE_DIR_ . 'paysoncheckout2/paysoncheckout2.php');
            $payson = new PaysonCheckout2();
            $paysonApi = $payson->getPaysonApiInstance();

            if (Tools::getIsset('checkout') && Tools::getValue('checkout') != null) {
                // Get checkout ID from query
                $checkoutId = Tools::getValue('checkout');
                PaysonCheckout2::paysonAddLog('Notification Got checkout ID: ' . $checkoutId . ' from query.');
            } else {
                // Get checkout ID from DB
                $checkoutId = $payson->getPaysonOrderEventId($cartId);
                if (isset($checkoutId) && $checkoutId != null) {
                    PaysonCheckout2::paysonAddLog('Notification Got checkout ID: ' . $checkoutId . ' from DB.');
                } else {
                    // Unable to get checkout ID
                    PaysonCheckout2::paysonAddLog('Notification No checkout ID.', 2);
                    var_dump(http_response_code(500));
                    exit();
                }
            }

            $checkoutClient = new \Payson\Payments\CheckoutClient($paysonApi);
            $checkout = $checkoutClient->get(array('id' => $checkoutId));

            PaysonCheckout2::paysonAddLog('Notification Checkout Status: ' . $checkout['status']);

            switch ($checkout['status']) {
                case 'created':
                    var_dump(http_response_code(200));
                    exit();
                case 'readyToShip':
                    $cart = new Cart($cartId);
                    PaysonCheckout2::paysonAddLog('Notification Cart ID: ' . $cart->id);
                    $newOrderId = false;
                    if ($cart->OrderExists() == false) {
                        // Create PS order
                        $newOrderId = $payson->createOrderPS($cart->id, $checkout);
                        
                        if ($newOrderId == false) {
                            Logger::addLog('Notification Unable to create order.', 3);
                            
                            var_dump(http_response_code(500));
                            exit();
                        } else {
                            PaysonCheckout2::paysonAddLog('Notification New order ID: ' . $newOrderId);
                            
                            // Set order id
                            $checkout['merchant']['reference'] = $newOrderId;
                            $checkoutClient->update($checkout);
                        }
                    } else {
                        PaysonCheckout2::paysonAddLog('Notification Order already created.');
                    }
                    var_dump(http_response_code(200));
                    exit();
                case 'readyToPay':
                    var_dump(http_response_code(200));
                    exit();
                case 'denied':
                    $payson->updatePaysonOrderEvent($checkout, $cartId);
                    var_dump(http_response_code(200));
                    exit();
                case 'canceled':
                    $payson->updatePaysonOrderEvent($checkout, $cartId);
                    var_dump(http_response_code(200));
                    exit();
                case 'paidToAccount':
                    $payson->updatePaysonOrderEvent($checkout, $cartId);
                    var_dump(http_response_code(200));
                    exit();
                case 'expired':
                    $this->context->cookie->__set('paysonCheckoutId', null);
                    $payson->updatePaysonOrderEvent($checkout, $cartId);
                    var_dump(http_response_code(200));
                    exit();
                case 'shipped':
                    $payson->updatePaysonOrderEvent($checkout, $cartId);
                    var_dump(http_response_code(200));
                    exit();
                default:
                    PaysonCheckout2::paysonAddLog('Notification Unknown Checkout Status: ' . $checkout['status'], 2);
                    var_dump(http_response_code(200));
                    exit();
            }
        }
    }
}
