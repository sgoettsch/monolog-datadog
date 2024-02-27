<?php

namespace sgoettsch\MonologDatadogTest;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Client;

/**
 * Class ClientFake
 */
class ClientFake extends Client
{
    /** @var MockHandler */
    protected $mockHandler;

    /**
     * ClientFake constructor.
     */
    public function __construct()
    {
        $this->mockHandler = new MockHandler();
        $handler = HandlerStack::create($this->mockHandler);
        parent::__construct(['handler' => $handler]);
    }

    /**
     * @param $responses
     */
    public function appendResponse($responses): void
    {
        $this->mockHandler->append(...$responses);
    }
}
