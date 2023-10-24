<?php

namespace Drupal\crypto\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

class CryptoController extends ControllerBase {

  function binanceConnect($symbol, $dollar_amount, $buy_or_sell) {
    $time = time();
    $recvWindow = 99999999999999999;
    $domain = 'https://fapi.binance.com';
    $path = '/fapi/v1/order?';
    $price_path = '/fapi/v1/premiumIndex?';

    $existing_node = $this->getExistingNode($symbol);

    $query_array = [
      'symbol' => $symbol,
      'side' => $buy_or_sell,
      'type' => 'MARKET',
      'recvWindow' => $recvWindow,
      'timestamp' => $time,
      'newOrderRespType' => 'RESULT',
    ];

    if ($existing_node instanceof NodeInterface) {
      $trend = $existing_node->field_trend->value;
      if ($buy_or_sell == $trend) {
        return [
          '#markup' => 'nvm',
        ];
      }
      $orderId = $this->getOrderId($symbol, $existing_node);
      $existing_query_array = [
        'symbol' => $symbol,
        'orderId' => $orderId,
        'recvWindow' => $recvWindow,
        'timestamp' => $time,
      ];

//      // Get position info from Binance
//      $existing_response = $this->connectToBinance($existing_query_array, $domain, $path, 'GET');
//      $existing_response_array = json_decode($existing_response,true);
//      // Exit current position
//      $existing_trend = $existing_response_array['side'];
      $existing_trend = $existing_node->field_trend->value;
      if ($existing_trend == 'BUY') {
        $opposite = 'SELL';
      }
      if ($existing_trend == 'SELL') {
        $opposite = 'BUY';
      }
      $exit_position_query_array = $query_array;
//      $executedQty = $existing_response_array['executedQty'];
      $existing_quantity = $existing_node->field_quantity->value;
      $exit_position_query_array['side'] = $opposite;
      $exit_position_query_array['quantity'] = $existing_quantity;
      $close_response_array = $this->connectToBinance($exit_position_query_array, $domain, $path, 'POST');
      $close_response_array = json_decode($close_response_array,true);
      $close_price = $close_response_array['avgPrice'];
      // set node to unpublished
      $existing_node->status = 0;
      $existing_node->field_exit_price->value = $close_price;

      $entry_price = $existing_node->field_entry_price->value;
      $exit_price = $existing_node->field_exit_price->value;

      if ($existing_node->field_trend->value == 'BUY') {
        $change = (($exit_price - $entry_price) / $entry_price) * 100;
      }
      else {
        $change = (($entry_price - $exit_price) / $entry_price) * 100;
      }
      $change = number_format((float)$change, 2, '.', '');
      $existing_node->field_change->value = $change;
      $existing_node->field_exit_price->value = $close_price;

      $existing_node->save();
    }

    $query_price_array = [
      'symbol' => $symbol,
      'recvWindow' => $recvWindow,
      'timestamp' => $time
    ];

    $price_response = $this->connectToBinance($query_price_array, $domain, $price_path, 'GET');
    $price_response_array = json_decode($price_response,true);
    if (empty($price_response_array['markPrice'])) {
      \Drupal::logger('crypto')->error($price_response);
      \Drupal::logger('crypto')->error('Failed to get the current price');
      return [
        '#markup' => 'Failed to get the current price',
      ];
    }
    $current_price = $price_response_array['markPrice'];
    $current_price = number_format((float)$current_price, 3, '.', '');

    $quantity_to_order = $dollar_amount / $current_price * 10;
    $quantity_to_order = round($quantity_to_order);

    $query_array['quantity'] = $quantity_to_order;

    $response = $this-> connectToBinance($query_array, $domain, $path, 'POST');
    $response_array = json_decode($response,true);

    if (empty($response_array['orderId'])) {
      \Drupal::logger('crypto')->error($response);
      \Drupal::logger('crypto')->error('Failed to get Binance Order ID');
      return [
        '#markup' => 'Failed to get Binance Order ID',
      ];
    }
    $binance_id = $response_array['orderId'];

    $quantity = $response_array['executedQty'];

    $current_price = $response_array['avgPrice'];

    $this->createNode($symbol, $binance_id, $buy_or_sell, $current_price, $quantity);

    return [
      '#markup' => 'Order ID: ' . $binance_id
    ];
  }

  public function connectToBinance($query_array, $domain, $path, $request_type) {
    $query = http_build_query($query_array);
    $hash_key = hash_hmac('sha256', $query, 'fJKVhpjJykMh2cHSh7usqJXDrtVnQfEtrhD1hkRoW6GgT9ep70u08C7eSqZPtB5y');
    $query = $query . '&signature=' . $hash_key;
    $curl_url = $domain . $path . $query;

    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => $curl_url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => $request_type,
      CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json',
        'X-MBX-APIKEY: SW4wbok336Q3v9jDxrqo4cafq7C0z9aMZ9G23o1sMtlIKCQ4uUqU3IImCmdpHpNX'
      ),
    ));

    $response = curl_exec($curl);

    \Drupal::logger('crypto')->notice($response);

    curl_close($curl);

    return $response;
  }

  public function createNode($symbol, $binance_id, $buy_or_sell, $current_price, $quantity) {
    $node = \Drupal::entityTypeManager()->getStorage('node')->create(array(
      'type' => 'crypto_live',
      'title' => $binance_id,
      'field_symbol' => $symbol,
      'field_trend' => $buy_or_sell,
      'field_entry_price' => $current_price,
      'field_quantity' => $quantity,
      'status' => 1,
    ));
    $node->save();
  }

  public function getOrderId($symbol, $node) {
    if ($node instanceof NodeInterface) {
      return $node->title->value;
    }
  }

  public function getExistingNode($symbol) {
//    $nodes = \Drupal::entityTypeManager()
//      ->getStorage('node')
//      ->loadByProperties([
//        'type' => 'crypto_live',
//        'field_symbol' => $symbol,
//        'status' => 1
//      ]);
//    $node = reset($nodes);
//
//    return $node;

    $query = \Drupal::entityTypeManager()
      ->getListBuilder('node')
      ->getStorage()
      ->getQuery();
    $query->condition('type', 'crypto_live');
    $query->condition('status', 1);
    $query->condition('field_symbol', $symbol);
    $query->sort('created', 'DESC');
    $query->range(0, 1);

    $nids = $query->execute();
    $nid = reset($nids);

    $node = NODE::load($nid);

    return $node;
  }

}

//    $path = '/fapi/v1/premiumIndex?';

//  $query_array = [
//    'symbol' => 'LUNAUSDT',
//    'side' => 'SELL',
//    'type' => 'MARKET',
//    'type' => 'TAKE_PROFIT_MARKET',
//    'closePosition' => 'true',
//    'stopPrice' => 99,
//    'recvWindow' => 99999999999999999,
//    'timestamp' => $time,
//  ];
