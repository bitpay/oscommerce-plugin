<?php

/**
 * The MIT License (MIT)
 * 
 * Copyright (c) 2011-2015 BitPay
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

require 'bitpay/bp_lib.php';
require 'includes/application_top.php';
require 'bitpay/remove_order.php';

$response = bpVerifyNotification(MODULE_PAYMENT_BITPAY_APIKEY);

if (is_string($response))
{
    bpLog('bitpay callback error: ' . $response);
}
else
{
    $order_id = $response['posData'];
    switch($response['status'])
    {
        case 'paid':
        case 'confirmed':
        case 'complete':
            if(function_exists('tep_db_query'))
            {
                tep_db_query("update " . TABLE_ORDERS . " set orders_status = " . MODULE_PAYMENT_BITPAY_PAID_STATUS_ID . " where orders_id = " . intval($order_id));
            }
            else
            {
                bpLog('FATAL: tep_db_query function is missing. Cannot update order_id = ' . $order_id . ' as ' . $response['status']);
            }
            break;
        case 'invalid':
        case 'expired':
            if(function_exists('tep_remove_order'))
            {
                tep_remove_order($order_id, $restock = true);
            }
            else
            {
                bpLog('FATAL: tep_remove_order function is missing. Cannot update order_id = ' . $order_id . ' as ' . $response['status']);
            }
            break;
        case 'new':
            break;
        default:
            bpLog('INFO: Receieved unknown IPN status of ' . $response['status'] . ' for order_id = ' . $order_id);
            break;
    }
}
