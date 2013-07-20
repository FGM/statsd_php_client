<?php
/**
 * @file
 * A Drupal-decoupled adaptation of the StatsD agent provided with StatsD.
 *
 * @łink
 * https://github.com/etsy/statsd/blob/master/examples/php-example.php
 */

/**
 * Sends statistics to the stats daemon over UDP.
 *
 * Properties have public visibility rather than protected to reduce clutter in
 * statsd.admin.inc where they are used for configuration.
 */
class StatsD {
  public $enabled = FALSE;
  public $sampleRate = 1;
  public $host = '127.0.0.1';
  public $port = 8125;
  public $prefix = '';
  public $suffix = '';

  /**
   * Constructor. Copy valid arguments to public properties, ignore others.
   *
   * @param array $arguments
   *   A name-value hash.
   */
  public function __construct($arguments) {
    $properties = $this->getDefaultProperties();
    foreach ($arguments as $name => $value) {
      if (isset($properties[$name])) {
        $this->$name = $value;
      }
    }
  }

  /**
   * Return the default class properties.
   *
   * @return array
   *   A hash of name-default_value property descriptions.
   */
  public static function getDefaultProperties() {
    $rc = new ReflectionClass(__CLASS__);
    $return = $rc->getDefaultProperties();
    return $return;
  }

  /**
   * Logs timing information.
   *
   * @param string $stat
   *   The metric to log timing info for.
   * @param int $time
   *   The elapsed time (ms) to log.
   * @param float $sample_rate
   *   A value between 0 and 1 representing the sampling rate.
   *
   * @return void
   *   Nothing to return.
   */
  public function timing($stat, $time, $sample_rate = NULL) {
    $this->send(array($stat => "$time|ms"), $sample_rate);
  }

  /**
   * Sends a gauge, an arbitrary value to statsd.
   *
   * @param string $stat
   *   The metric to send.
   * @param int $value
   *   The value to send for this metric.
   * @param float $sample_rate
   *   A value between 0 and 1 representing the sampling rate.
   *
   * @return void
   *   Nothing to return.
   */
  public function gauge($stat, $value, $sample_rate = NULL) {
    $this->send(array($stat => "$value|g"), $sample_rate);
  }

  /**
   * Increments one or more stats counters.
   *
   * @param string|array $stats
   *   A string or an array of strings representing the metric(s) to increment.
   * @param float $sample_rate
   *   A value between 0 and 1 representing the sampling rate.
   *
   * @return void
   *   Nothing to return.
   */
  public function increment($stats, $sample_rate = NULL) {
    $this->updateStats($stats, 1, $sample_rate);
  }

  /**
   * Decrements one or more stats counters.
   *
   * @param string|array $stats
   *   A string or an array of strings representing the metric(s) to decrement.
   * @param float $sample_rate
   *   A float between 0 and 1 representing the sampling rate.
   *
   * @return void
   *   Nothing to return.
   */
  public function decrement($stats, $sample_rate = NULL) {
    $this->updateStats($stats, -1, $sample_rate);
  }

  /**
   * Updates one or more stats counters by arbitrary amounts.
   *
   * @param string|array $stats
   *   A string or an array of strings representing the metric(s) to increment
   *   or decrement.
   * @param int $delta
   *   The amount to increment/decrement each metric by.
   * @param float $sample_rate
   *   A value between 0 and 1 representing the sampling rate.
   *
   * @return void
   *   Nothing to return.
   */
  public function updateStats($stats, $delta = 1, $sample_rate = NULL) {
    if (!is_array($stats)) {
      $stats = array($stats);
    }

    $data = array();
    foreach ($stats as $stat) {
      $data[$stat] = "$delta|c";
    }

    $this->send($data, $sample_rate);
  }

  /**
   * Squirts the metrics over UDP.
   *
   * @param array $data
   *   A hash of name-value metrics.
   * @param float $sample_rate
   *   A value between 0 and 1 representing the sampling rate.
   *
   * @return void
   *   Nothing to return.
   */
  public function send($data, $sample_rate = NULL) {
    if (!$this->enabled) {
      return;
    }

    $sample_rate  = $sample_rate ? $sample_rate : $this->sampleRate;
    $sampled_data = array();
    $data = $this->prefixData($data);

    if ($sample_rate < 1) {
      foreach ($data as $stat => $value) {
        if ((mt_rand() / mt_getrandmax()) <= $sample_rate) {
          $sampled_data[$stat] = "$value|@$sample_rate";
        }
      }
    }
    else {
      $sampled_data = $data;
    }

    if (empty($sampled_data)) {
      return;
    }

    $fp = stream_socket_client("udp://$this->host:$this->port", $errno, $errstr);

    if ($fp) {
      stream_set_blocking($fp, 0);
      foreach ($sampled_data as $stat => $value) {
        fwrite($fp, "$stat:$value");
      }
      fclose($fp);
    }
  }

  /**
   * Creates the data strings that will be passed into statsd.
   *
   * @param array $data
   *   A hash of key-value pairs to prefix.
   *
   * @return array
   *   A hash of prefix.key.suffix-value pairs.
   */
  protected function prefixData($data) {
    $prefix = $this->prefix ? $this->prefix . '.' : '';
    $suffix = $this->suffix ? '.' . $this->suffix : '';
    $return = array();
    foreach ($data as $key => $value) {
      $name = sprintf('%s' . $key . '%s', $prefix, $suffix);
      $return[$name] = $value;
    }

    return $return;
  }
}
