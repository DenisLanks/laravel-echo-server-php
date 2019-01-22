<?php

namespace Lanks\EchoServer\Database;

use React\Promise\Promise;

interface DatabaseDriver
{
    /**
     * Get a value from the database.
     *
     * @return {Promise<any>}
     */
    public function get(string $key): Promise;

    /**
     * Set a value to the database.
     *
     * @return {Promise<any>}
     */
    public function set(string $key, $value): void;
}