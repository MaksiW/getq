<?php
/**
 * Created by PhpStorm.
 * User: home
 * Date: 17.06.2019
 * Time: 15:14
 */

require 'vendor/autoload.php';
require 'create_table.php';
spl_autoload_register(function ($class) {
    include 'exchanges/' . $class . '.php';
});

$exch = new Binance();
$ch = new \ClickHouse\Client("http://127.0.0.1", 8123);

$loop = React\EventLoop\Factory::create();
$loop->addPeriodicTimer(1, function(React\EventLoop\Timer\Timer $timer) {
    extract($GLOBALS, EXTR_REFS | EXTR_SKIP);
    $asksBidsAll = $exch->getAllAskBid();
    createTable($ch, $asksBidsAll, 'quotesBinanseTest');
    writeQuotes($ch, $asksBidsAll, 'quotesBinanseTest');
});

$loop->run();

