<?php

namespace App\Monolog;

use Monolog\Handler\AbstractHandler;

/**
 * A special Monolog handler allowing an existing handler to be enabled or disabled based on a parameter
 */
class ToggleableHandler extends AbstractHandler
{
    /**
     * @var bool
     */
    private $enabled;
    /**
     * @var AbstractHandler
     */
    private $nestedHandler;

    public function __construct(AbstractHandler $nestedHandler, $enabled = true)
    {
        parent::__construct();
        $this->enabled = $enabled;
        $this->nestedHandler = $nestedHandler;
    }

    /**
     * Some handlers like SwiftMailer use specific methods, so redirect everything to the nested handler
     */
    public function __call($method, $args)
    {
        return call_user_func_array(array($this->nestedHandler, $method), $args);
    }

    public function handle(array $record)
    {
        if ($this->enabled) {
            $this->nestedHandler->handle($record);
        }
    }
}