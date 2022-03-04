<?php

namespace QuickPayPayment\Components;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Shopware\Components\Plugin\ConfigReader;
class LogHandler extends RotatingFileHandler
{
    
    public function __construct($filename, ConfigReader $configReader, $pluginName)
    {
        $logLevel = Logger::INFO;
        try {
            $config = $configReader->getByPluginName($pluginName);
            $logLevel = $config['log_level'] ?? Logger::INFO;
        } catch (Exception $e) {
        }
        parent::__construct($filename, 14, $logLevel);
    }
    
}
