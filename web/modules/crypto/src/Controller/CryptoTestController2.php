<?php

namespace Drupal\crypto\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

class CryptoTestController2 extends ControllerBase {

  /**
   * WEEX futures public symbol price endpoint.
   */
  const WEEX_SYMBOL_PRICE_URL = 'https://api-contract.weex.com/capi/v3/market/symbolPrice';

  public function binanceConnect($symbol, $test_id, $trend) {
    // Exiting trades is handled by exitTrade()
    // (test2/{symbol}/{test_id}/{trend}/exit).

    // A trade with the same symbol and trend is already open: do nothing.
    if ($this->getOpenNodeByTrend($symbol, $trend) instanceof NodeInterface) {
      return [
        '#markup' => 'nvm',
      ];
    }

    $current_price = $this->getMarkPrice($symbol);
    if ($current_price === NULL) {
      // Don't open a trade with no entry price.
      return [
        '#markup' => 'Failed to get price for ' . $symbol . '; trade not opened.',
      ];
    }

    $binance_id = $test_id;

    $this->createNode($symbol, $binance_id, $trend, $current_price);

    return [
      '#markup' => 'Order ID: ' . $binance_id,
    ];
  }

  /**
   * Exits the open trade for a symbol and direction.
   *
   * Loads the published node with the same symbol and trend,
   * sets its exit price to the current Binance price and unpublishes it.
   * Never opens a new trade.
   *
   * Route: test2/{symbol}/{test_id}/{trend}/exit
   */
  public function exitTrade($symbol, $test_id, $trend) {
    $existing_node = $this->getOpenNodeByTrend($symbol, $trend);

    if (!$existing_node instanceof NodeInterface) {
      return [
        '#markup' => 'No open ' . $trend . ' trade for ' . $symbol . '; nothing to exit.',
      ];
    }

    $current_price = $this->getMarkPrice($symbol);
    if ($current_price === NULL) {
      // Leave the trade open so it can be exited on the next signal.
      return [
        '#markup' => 'Failed to get price for ' . $symbol . '; trade left open.',
      ];
    }

    $existing_node->field_exit_price->value = $current_price;

    $entry_price = (float) $existing_node->field_entry_price->value;
    $exit_price = (float) $current_price;
    $change = NULL;

    // Only calculate the change when there's a valid entry price.
    if ($entry_price > 0) {
      if ($existing_node->field_trend->value == 'long') {
        $change = (($exit_price - $entry_price) / $entry_price) * 100;
      }
      else {
        $change = (($entry_price - $exit_price) / $entry_price) * 100;
      }
      $change = number_format((float) $change, 8, '.', '');
      $existing_node->field_change->value = $change;
    }
    else {
      \Drupal::logger('crypto')->error('Node @nid has no valid entry price; exiting without change.', [
        '@nid' => $existing_node->id(),
      ]);
    }

    // set node to unpublished
    $existing_node->status = 0;
    $existing_node->save();

    return [
      '#markup' => 'Exited Order ID: ' . $existing_node->getTitle()
        . ' at ' . $current_price
        . ($change !== NULL ? ' (change: ' . $change . '%)' : ' (no valid entry price)'),
    ];
  }

  /**
   * Loads the oldest published crypto node for a symbol and trend.
   *
   * @param string $symbol
   *   Symbol, e.g. BTCUSDT.
   * @param string $trend
   *   Trend, e.g. long or short.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The node, or NULL if there is no open trade.
   */
  public function getOpenNodeByTrend($symbol, $trend) {
    $query = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE);
    $query->condition('type', 'crypto');
    $query->condition('status', 1);
    $query->condition('field_symbol', $symbol);
    $query->condition('field_trend', $trend);
    // If more than one open trade matches, exit the oldest first.
    $query->sort('created', 'ASC');
    $query->sort('nid', 'ASC');
    $query->range(0, 1);

    $nids = $query->execute();
    $nid = reset($nids);

    if ($nid) {
      return Node::load($nid);
    }

    return NULL;
  }

  /**
   * Gets the current futures mark price for a symbol from WEEX.
   *
   * Uses the public WEEX futures endpoint (no API key required):
   * GET https://api-contract.weex.com/capi/v3/market/symbolPrice
   *   ?symbol=BTCUSDT&priceType=MARK
   * Response: {"symbol":"BTCUSDT","price":"69348.5","time":1764505776890}
   *
   * @see https://www.weex.com/api-doc/catalog/core-trading-futures/api/rest-api/market-api#get-symbol-price
   *
   * @param string $symbol
   *   Symbol, e.g. BTCUSDT.
   *
   * @return string|null
   *   Price formatted to 8 decimals, or NULL if no valid price was returned.
   */
  public function getMarkPrice($symbol) {
    $data = $this->fetchPublicJson(self::WEEX_SYMBOL_PRICE_URL, [
      'symbol' => $symbol,
      'priceType' => 'MARK',
    ]);

    // WEEX returns {"code":-1142,"msg":"Parameter 'symbol' is invalid."}
    // when the symbol isn't listed.
    if (isset($data['code'])) {
      \Drupal::logger('crypto')->error('WEEX error for @symbol: @code @msg', [
        '@symbol' => $symbol,
        '@code' => $data['code'],
        '@msg' => $data['msg'] ?? '',
      ]);
      return NULL;
    }

    $price = isset($data['price']) ? (float) $data['price'] : 0.0;
    if ($price <= 0) {
      \Drupal::logger('crypto')->error('No valid price for @symbol from WEEX. Response: @response', [
        '@symbol' => $symbol,
        '@response' => json_encode($data),
      ]);
      return NULL;
    }

    return number_format($price, 8, '.', '');
  }

  /**
   * Makes an unsigned GET request to a public market data endpoint.
   *
   * @param string $url
   *   Endpoint URL without query string.
   * @param array $query_array
   *   Query parameters.
   *
   * @return array|null
   *   Decoded JSON (including error bodies), or NULL on transport/JSON failure.
   */
  public function fetchPublicJson($url, array $query_array) {
    $curl_url = $url . '?' . http_build_query($query_array);

    $curl = curl_init();
    curl_setopt_array($curl, [
      CURLOPT_URL => $curl_url,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_ENCODING => '',
      CURLOPT_TIMEOUT => 10,
      CURLOPT_CONNECTTIMEOUT => 5,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    ]);

    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);

    if ($response === FALSE || $http_code !== 200) {
      \Drupal::logger('crypto')->error('Price request failed. URL: @url | HTTP: @code | cURL error: @error | Body: @body', [
        '@url' => $curl_url,
        '@code' => $http_code,
        '@error' => $curl_error ?: 'none',
        '@body' => is_string($response) ? mb_substr($response, 0, 500) : '',
      ]);
    }

    if (!is_string($response)) {
      return NULL;
    }

    $data = json_decode($response, TRUE);
    return is_array($data) ? $data : NULL;
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
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);

    if ($response === FALSE || $http_code !== 200) {
      \Drupal::logger('crypto')->error('Binance request failed. URL: @url | HTTP: @code | cURL error: @error | Body: @body', [
        '@url' => $domain . $path,
        '@code' => $http_code,
        '@error' => $curl_error ?: 'none',
        '@body' => is_string($response) ? mb_substr($response, 0, 500) : '',
      ]);
    }

    return $response;
  }

  public function createNode($symbol, $binance_id, $trend, $current_price) {
    $node = \Drupal::entityTypeManager()->getStorage('node')->create(array(
      'type' => 'crypto',
      'title' => $binance_id,
      'field_symbol' => $symbol,
      'field_trend' => $trend,
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
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE);
    $query->condition('type', 'crypto');
    $query->condition('title', $test_id);
    $query->condition('status', 1);
    $query->condition('field_symbol', $symbol);
    $query->sort('created', 'DESC');
    $query->range(0, 1);

    $nids = $query->execute();
    $nid = reset($nids);

    if ($nid) {
      return Node::load($nid);
    }

    return NULL;
  }

}

