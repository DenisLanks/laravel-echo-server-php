<?php

namespace Lanks\EchoServer\Subscribers;

use Closure;
use React\Promise\Promise;

interface Subscriber
{
     /**
     * Subscribe to incoming events.
     *
     * @param  {Function} callback
     * @return {void}
     */
    public function subscribe(Closure $callback): Promise;
}