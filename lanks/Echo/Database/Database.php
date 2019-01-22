<?php

namespace Lanks\EchoServer\Database;

use React\Promise\Promise;
use Lanks\EchoServer\Helper;
use Lanks\EchoServer\Database\Redis;
use Lanks\EchoServer\Database\Sqlite;

class Database implements DatabaseDriver 
{
    /**
     * Database driver.
     *
     * @type {DatabaseDriver}
     */
    private $driver;

    /**
     * Create a new database instance.
     *
     * @param  {any} options
     */
    public function constructor($options) {
        if ($options->database == 'redis') {
            $this->driver = new Redis($options);
        } else if ($options->database == 'sqlite') {
            $this->driver = new Sqlite($options);
        } else {
            Helper::error("Database driver not set.");
        }
    }

    /**
     * Get a value from the database.
     *
     * @return {Promise<any>}
     */
    public function get(string $key): Promise {
        return $this->driver->get($key);
    }

    /**
     * Set a value to the database.
     *
     * @return {Promise<any>}
     */
    public function set(string $key, $value): void {
        $this->driver->set($key, $value);
    }
}