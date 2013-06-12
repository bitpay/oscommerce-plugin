<?php
require 'bitpay/bp_lib.php';
require 'includes/application_top.php';

$response = bpVerifyNotification(MODULE_PAYMENT_BITPAY_APIKEY);

if (is_string($response)) {
  bpLog( 'bitpay callback error: $response');
}
else {
  bpLog( 'bitpay callback: $response');
  $order_id = $response['posData'];
  switch($response['status'])
  {
    case 'confirmed':
    case 'complete':
	  tep_db_query("update " . TABLE_ORDERS . " set orders_status = " . MODULE_PAYMENT_BITPAY_PAID_STATUS_ID . " where orders_id = " . intval($order_id));
	  break;
	case 'expired':
	  tep_remove_order($order_id, $restock = true);
	  break;
	}
}
?>

