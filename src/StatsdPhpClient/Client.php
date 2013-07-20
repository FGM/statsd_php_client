<?php
/**
 * @file
 * An UDP client class for StatsD.
 *
 * Adapted from the examples/php-example.php file in the StatsD package.
 */

namespace OSInet\StatsdPhpClient;

class Client {
  /**
   * @var \OSInet\StatsdPhpClient\Config
   */
  protected $config;

  /**
   * @var boolean
   *   Is the script shutdown handler enabled ?
   */
  protected $isShutdownEnabled;

  /**
   * @var array
   *   A metric-indexed hash of [value, sample_rate].
   */
  protected $queue;

  /**
   * Constructor. 
   */
  public function __construct(Config $config) {
    $this->config = $config;
    $this->isShutdownEnabled = false;
  }

  /**
   * @return \OSInet\StatsdPhpClient\Config
   */
  public function getConfig() {
    return $this->config;
  }

  /**
   * Enable a shutdown handler ensuring data flush on page shutdown.
   */
  protected function registerShutdown() {
    $that = $this;
    register_shutdown_function(function () use (Client $that) {
      $that->doSend($that->queue);
    });
    $this->isShutdownEnabled = true;
  }

  /**
   *  Deferred send.
   *
   * - passed data will be sent on next flush, which can be on page
   *   shutdown.
   *
   * @param array $data
   * @param float $sample_rate
   *
   * @return void
   */
  public function sendDeferred($data, $sample_rate = 1.0) {
    // Deduplicate metrics with currently queued ones.
    foreach ($data as $metric => $value) {
      $this->queue[$metric] = array($value, $sample_rate);
    }
  }

  /**
   * Flush send: send data immediately, along with existing queued data.
   * 
   * On empty flush send: just send existing queued data.
   *
   * @var array $data
   * @var float $sample_rate
   * 
   * @return void
   */
  public function sendFlush($data = array(), $sample_rate = 1.0) {
    if (!empty($data)) {
      $this->sendDeferred($data, $sample_rate);
    }
    $this->doSend($this->queue);
  }

  /**
   * Immediate send.
   *
   * - passed data are sent immediately
   * - queued data are not modified.
   *
   * @param array $data
   * @param float $sample_rate
   *
   * @return void
   */
  public function sendImmediate($data, $sample_rate) {
    $queue = array();
    foreach ($data as $metric -> $value) {
      $queue[$metric] = array($value, $sample_rate);
    }
    $this->doSend($queue);
  }

  function __wip____________________________________________________()(}
  
    /**
     * Sets one or more timing values
     *
     * @param string|array $stats The metric(s) to set.
     * @param float $time The elapsed time (ms) to log
     **/
    public static function timing($stats, $time) {
        StatsD::updateStats($stats, $time, 1, 'ms');
    }

    /**
     * Sets one or more gauges to a value
     *
     * @param string|array $stats The metric(s) to set.
     * @param float $value The value for the stats.
     **/
    public static function gauge($stats, $value) {
        StatsD::updateStats($stats, $value, 1, 'g');
    }

    /**
     * A "Set" is a count of unique events.
     * This data type acts like a counter, but supports counting
     * of unique occurences of values between flushes. The backend
     * receives the number of unique events that happened since
     * the last flush.
     *
     * The reference use case involved tracking the number of active
     * and logged in users by sending the current userId of a user
     * with each request with a key of "uniques" (or similar).
     *
     * @param string|array $stats The metric(s) to set.
     * @param float $value The value for the stats.
     **/
    public static function set($stats, $value) {
        StatsD::updateStats($stats, $value, 1, 's');
    }

    /**
     * Increments one or more stats counters
     *
     * @param string|array $stats The metric(s) to increment.
     * @param float|1 $sampleRate the rate (0-1) for sampling.
     * @return boolean
     **/
    public static function increment($stats, $sampleRate=1) {
        StatsD::updateStats($stats, 1, $sampleRate, 'c');
    }

    /**
     * Decrements one or more stats counters.
     *
     * @param string|array $stats The metric(s) to decrement.
     * @param float|1 $sampleRate the rate (0-1) for sampling.
     * @return boolean
     **/
    public static function decrement($stats, $sampleRate=1) {
        StatsD::updateStats($stats, -1, $sampleRate, 'c');
    }

    /**
     * Updates one or more stats.
     *
     * @param string|array $stats The metric(s) to update. Should be either a string or array of metrics.
     * @param int|1 $delta The amount to increment/decrement each metric by.
     * @param float|1 $sampleRate the rate (0-1) for sampling.
     * @param string|c $metric The metric type ("c" for count, "ms" for timing, "g" for gauge, "s" for set)
     * @return boolean
     **/
    public static function updateStats($stats, $delta=1, $sampleRate=1, $metric='c') {
        if (!is_array($stats)) { $stats = array($stats); }
        $data = array();
        foreach($stats as $stat) {
            $data[$stat] = "$delta|$metric";
        }

        StatsD::send($data, $sampleRate);
    }

  /**
   * Sample a data set to only send a sampling of the original data.
   */
  protected function sampleData($data, $sample_rate) {
    $sampledData = array();
    if ($sample_rate < 1) {
      foreach ($data as $stat => $value) {
        if ((mt_rand() / mt_getrandmax()) <= $sample_rate) {
          $sampledData[$stat] = "$value|@$sample_rate";
        }
      }
    } 
    else {
      $sampledData = $data;
    }
    
    return $sampledData;
  }

  /*
   * Squirt the metrics over UDP
   * 
   * @var array $queue
   *   A metric-indexed hash of [data, sample_rate] arrays. 
   */
  protected static function doSend($queue) {
    $config = $this->config;
    if (!$config->isEnabled("statsd")) { 
      return; 
    }

    // FIXME adapt code for the send queue instead of the origina format.
    
    // Sampling
    $sampledData = $this->sampleData($data, $sample_rate);
    if (empty($sampledData)) {
      return; 
    }

    // Any exception in any of this should be silently ignored.
    try {
      $host = $config->getConfig("statsd.host");
      $port = $config->getConfig("statsd.port");
      $fp = fsockopen("udp://$host", $port, $errno, $errstr);
      if (!$fp) { 
        return; 
      }
      foreach ($sampledData as $stat => $value) {
        fwrite($fp, "$stat:$value");
      }
      fclose($fp);
    } 
    catch (Exception $e) {
    }
  }
}
