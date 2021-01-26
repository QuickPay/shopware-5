<?php

namespace QuickPayPayment\Components;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Shopware\Components\Plugin\ConfigReader;


class LogHandler extends RotatingFileHandler
{
    
    public function __construct($filename, ConfigReader $configReader, $pluginName)
    {
        $config = $configReader->getByPluginName($pluginName);
        
        $logLevel = $config['log_level'] ?? Logger::INFO;
        
        parent::__construct($filename, 14, $logLevel);
    }
    
}
