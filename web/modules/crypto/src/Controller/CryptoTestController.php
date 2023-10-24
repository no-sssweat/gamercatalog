<?php

namespace Drupal\crypto\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

class CryptoTestController extends ControllerBase {

  function binanceConnect($symbol, $test_id, $buy_or_sell) {
    $time = time();
    $recvWindow = 99999999999999999;
    $domain = 'https://fapi.binance.com';
    $path = '/fapi/v1/premiumIndex?';

    $existing_node = $this->getExistingNode($symbol, $test_id);

    $query_array = [
      'symbol' => $symbol,
      'recvWindow' => $recvWindow,
      'timestamp' => $time
    ];

    if ($existing_node instanceof NodeInterface) {
      $trend = $existing_node->field_trend->value;
      if ($buy_or_sell == $trend) {
        return [
          '#markup' => 'nvm',
        ];
      }

      // Get mark price info from Binance
      $existing_response = $this->connectToBinance($query_array, $domain, $path, 'GET');
      $existing_response_array = json_decode($existing_response,true);
      $current_price = $existing_response_array['markPrice'];
      $current_price = number_format((float)$current_price, 5, '.', '');

      if (empty($current_price)) {
        \Drupal::logger('crypto')->error('Failed to get price from Binance');
      }

      // set node to unpublished
      $existing_node->status = 0;
      $existing_node->field_exit_price->value = $current_price;

      $entry_price = $existing_node->field_entry_price->value;
      $exit_price = $existing_node->field_exit_price->value;

      if ($existing_node->field_trend->value == 'BUY') {
        $change = (($exit_price - $entry_price) / $entry_price) * 100;
      }
      else {
        $change = (($entry_price - $exit_price) / $entry_price) * 100;
      }
      $change = number_format((float)$change, 5, '.', '');
      $existing_node->field_change->value = $change;
      $existing_node->save();
    }

    $response = $this->connectToBinance($query_array, $domain, $path, 'GET');
    $response_array = json_decode($response,true);
    $current_price = $response_array['markPrice'];
    $current_price = number_format((float)$current_price, 5, '.', '');

    $binance_id = $test_id;

    $this->createNode($symbol, $binance_id, $buy_or_sell, $current_price);

    return [
      '#markup' => 'Order ID: ' . $binance_id,
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
    curl_close($curl);

    return $response;
  }

  public function createNode($symbol, $binance_id, $buy_or_sell, $current_price) {
    $node = \Drupal::entityTypeManager()->getStorage('node')->create(array(
      'type' => 'crypto',
      'title' => $binance_id,
      'field_symbol' => $symbol,
      'field_trend' => $buy_or_sell,
      'field_entry_price' => $current_price,
      'status' => 1,
    ));
    $node->save();
  }

  public function getExistingNode($symbol, $test_id) {
//    $nodes = \Drupal::entityTypeManager()
//      ->getStorage('node')
//      ->loadByProperties([
//        'field_symbol' => $symbol,
//        'title' => $test_id,
//        'status' => 1,
//      ]);
//    $node = reset($nodes);

    $query = \Drupal::entityTypeManager()
      ->getListBuilder('node')
      ->getStorage()
      ->getQuery();
    $query->condition('type', 'crypto');
    $query->condition('title', $test_id);
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

