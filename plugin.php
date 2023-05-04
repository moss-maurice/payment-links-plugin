<?php

use mmaurice\PaymentLinksPlugin\classes\EventHandler;

require_once realpath(dirname(__FILE__) . '/vendor/autoload.php');

new EventHandler($pageHandler);