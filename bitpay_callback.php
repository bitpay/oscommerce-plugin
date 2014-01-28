<?php

/**
 * ©2011,2012,2013,2014 BIT-PAY LLC.
 * 
 * Permission is hereby granted to any person obtaining a copy of this software
 * and associated documentation for use and/or modification in association with
 * the bitpay.com service.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 * 
 * Bitcoin oscommerce payment plugin using the bitpay.com service.
 * 
 */

require 'bitpay/bp_lib.php';
require 'includes/application_top.php';

$response = bpVerifyNotification(MODULE_PAYMENT_BITPAY_APIKEY);

if (is_string($response)) {
  bpLog('bitpay callback error: $response');
} else {
  bpLog('bitpay callback: $response');
  $order_id = $response['posData'];
  switch($response['status']) {
    case 'paid':
    case 'confirmed':
    case 'complete':
    	if(function_exists('tep_db_query'))
          tep_db_query("update " . TABLE_ORDERS . " set orders_status = " . MODULE_PAYMENT_BITPAY_PAID_STATUS_ID . " where orders_id = " . intval($order_id));
    	break;
    case 'invalid':
    case 'expired':
    	if(function_exists('tep_remove_order'))
          tep_remove_order($order_id, $restock = true);
    	break;
    case 'new':
    	break;
  }
}
?>
