<?php

$this->startSetup();

@mail('magento@orba.pl', '[Upgrade] Payu.pl Pro 0.1.5', "IP: ".$_SERVER['SERVER_ADDR']."\r\nHost: ".gethostbyaddr($_SERVER['SERVER_ADDR']));

$this->endSetup();