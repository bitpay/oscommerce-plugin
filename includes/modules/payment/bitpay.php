<?php

  class bitpay {
    var $code, $title, $description, $enabled;

    // Class Constructor
    function bitpay () {
      global $order;

      $this->code = 'bitpay';
      $this->title = MODULE_PAYMENT_BITPAY_TEXT_TITLE;
      $this->description = MODULE_PAYMENT_BITPAY_TEXT_DESCRIPTION;
      $this->sort_order = MODULE_PAYMENT_BITPAY_SORT_ORDER;
      $this->enabled = ((MODULE_PAYMENT_BITPAY_STATUS == 'True') ? true : false);

      if ((int)MODULE_PAYMENT_BITPAY_ORDER_STATUS_ID > 0) {
        $this->order_status = MODULE_PAYMENT_BITPAY_ORDER_STATUS_ID;
        $payment='bitpay';
      }
      else if ($payment=='bitpay') {
        $payment='';
      }
      if (is_object($order)) {
        $this->update_status();
      }

      $this->email_footer = MODULE_PAYMENT_BITPAY_TEXT_EMAIL_FOOTER;
    }

    // Class Methods
    function update_status () {
      global $order;

      if ( ($this->enabled == true) && ((int)MODULE_PAYMENT_BITPAY_ZONE > 0) ) {
        $check_flag = false;
        $check_query = tep_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . intval(MODULE_PAYMENT_BITPAY_ZONE) . "' and zone_country_id = '" . intval($order->billing['country']['id']) . "' order by zone_id");
        while ($check = tep_db_fetch_array($check_query)) {
          if ($check['zone_id'] < 1) {
            $check_flag = true;
            break;
          }
          elseif ($check['zone_id'] == $order->billing['zone_id']) {
            $check_flag = true;
            break;
          }
        }

        if ($check_flag == false) {
          $this->enabled = false;
        }
      }

      // check currency
      $currencies = array_map('trim',explode(",",MODULE_PAYMENT_BITPAY_CURRENCIES));

      if (array_search($order->info['currency'], $currencies) === false) {
        $this->enabled = false;
      }

      // check that api key is not blank
      if (!MODULE_PAYMENT_BITPAY_APIKEY OR !strlen(MODULE_PAYMENT_BITPAY_APIKEY)) {
        print 'no secret '.MODULE_PAYMENT_BITPAY_APIKEY;
        $this->enabled = false;
      }
    }

    function javascript_validation () {
      return false;
    }

    function selection () {
      return array('id' => $this->code, 'module' => $this->title);
    }

    function pre_confirmation_check () {
      return false;
    }

    function confirmation () {
      return false;
    }

    function process_button () {
      return false;
    }

    function before_process () {
      return false;
    }

    function after_process () {
      global $insert_id, $order;
      require_once 'bitpay/bp_lib.php';

      $lut = array(
        "High-0 Confirmations" => 'high',
        "Medium-0 Confirmations" => 'medium',
        "Low-0 Confirmations" => 'low'
      );

      // change order status to value selected by merchant
      tep_db_query("update ". TABLE_ORDERS. " set orders_status = " . intval(MODULE_PAYMENT_BITPAY_UNPAID_STATUS_ID) . " where orders_id = ". intval($insert_id));
      $options = array(
        'physical' => $order->content_type == 'physical' ? 'true' : 'false',
        'currency' => $order->info['currency'],
        'buyerName' => $order->customer['firstname'].' '.$order->customer['lastname'],
        'fullNotifications' => 'true',
        'notificationURL' => tep_href_link('bitpay_callback.php', $parameters='', $connection='NONSSL', $add_session_id=true, $search_engine_safe=true, $static=true ),
        'redirectURL' => tep_href_link('account'),
        'transactionSpeed' => $lut[MODULE_PAYMENT_BITPAY_TRANSACTION_SPEED],
        'apiKey' => MODULE_PAYMENT_BITPAY_APIKEY,
      );

      bpLog($options);
      $invoice = bpCreateInvoice($insert_id, $order->info['total'], $insert_id, $options);
      bpLog($invoice);

      if (!is_array($invoice) or array_key_exists('error', $invoice)) {
        tep_remove_order($insert_id, $restock = true);
      }
      else {
        $_SESSION['cart']->reset(true);
        tep_redirect($invoice['url']);

      }
      return false;
    }

    function get_error () {
      return false;
    }

    function check () {
      if (!isset($this->_check)) {
        $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_BITPAY_STATUS'");
        $this->_check = tep_db_num_rows($check_query);
      }
      return $this->_check;
    }

    function install () {
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) "
        ."values ('Enable BitPay Module', 'MODULE_PAYMENT_BITPAY_STATUS', 'False', 'Do you want to accept bitcoin payments via bitpay.com?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now());");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) "
        ."values ('API Key', 'MODULE_PAYMENT_BITPAY_APIKEY', '', 'Enter you API Key which you generated at bitpay.com', '6', '0', now());");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) "
        ."values ('Transaction Speed', 'MODULE_PAYMENT_BITPAY_TRANSACTION_SPEED', 'Low-6 Confirmations', 'At what speed do you want the transactions to be considered confirmed?', '6', '0', 'tep_cfg_select_option(array(\'High-0 Confirmations\', \'Medium-1 Confirmations\', \'Low-6 Confirmations\'),', now());");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) "
        ."values ('Unpaid Order Status', 'MODULE_PAYMENT_BITPAY_UNPAID_STATUS_ID', '" . intval(DEFAULT_ORDERS_STATUS_ID) .  "', 'Automatically set the status of unpaid orders to this value.', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) "
        ."values ('Paid Order Status', 'MODULE_PAYMENT_BITPAY_PAID_STATUS_ID', '2', 'Automatically set the status of paid orders to this value.', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) "
        ."values ('Currencies', 'MODULE_PAYMENT_BITPAY_CURRENCIES', 'BTC, USD, EUR, GBP, AUD, BGN, BRL, CAD, CHF, CNY, CZK, DKK, HKD, HRK, HUF, IDR, ILS, INR, JPY, KRW, LTL, LVL, MXN, MYR, NOK, NZD, PHP, PLN, RON, RUB, SEK, SGD, THB, TRY, ZAR', 'Only enable BitPay payments if one of these currencies is selected (note: currency must be supported by bitpay.com).', '6', '0', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) "
        ."values ('Payment Zone', 'MODULE_PAYMENT_BITPAY_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '2', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) "
        ."values ('Sort Order of Display.', 'MODULE_PAYMENT_BITPAY_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '2', now())");

    }

    function remove () {
      tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys() {
      return array(
        'MODULE_PAYMENT_BITPAY_STATUS',
        'MODULE_PAYMENT_BITPAY_APIKEY',
        'MODULE_PAYMENT_BITPAY_TRANSACTION_SPEED',
        'MODULE_PAYMENT_BITPAY_UNPAID_STATUS_ID',
        'MODULE_PAYMENT_BITPAY_PAID_STATUS_ID',
        'MODULE_PAYMENT_BITPAY_SORT_ORDER',
        'MODULE_PAYMENT_BITPAY_ZONE',
        'MODULE_PAYMENT_BITPAY_CURRENCIES');
    }
  }
?>