<?php
/**
 * @file
 * An example demonstrating the StatsdPhpClient API.
 */

namespace OSInet\StatsdPhpClient;

// Bring in Composer autoloader.
require_once 'vendor/autoload.php';

$config = Config::getInstance();
$client = new Client($config);

// Default send: configuration determines when the data actually goes to the wire.
$client->send($data, $sample_rate);

// Immediate send: data is sent immediately, queued data are not modified.
$client->sendImmediate($data, $sample_rate);

// Deferred send: data will be sent on next flush, which can be on page shutdown.
$client->sendDeferred($data, $sample_rate);

// Flush send: data is sent immediately, after existing queued.
$client->sendFlush($data, $sample_rate);

// Empty flush send: just send already queued data.
$client->sendFlush();

echo "Our configuration is like this:\n";
var_dump($client->getConfig());