<?php

namespace Lanks\EchoServer\Database;

class Redis implements DatabaseDriver
{
    /**
     * Redis client.
     *
     * @type {object}
     */
    private $_redis;

    /**
     * Create a new cache instance.
     */
    public function constructor($options) {
        $this->_redis = new Redis($options->databaseConfig->redis);
    }

    /**
     * Retrieve data from redis.
     *
     * @param  {string}  key
     * @return {Promise<any>}
     */
    public function get(string $key): Promise {
        // return new Promise<any>((resolve, reject) => {
        //     this._redis.get(key).then(value => resolve(JSON.parse(value)));
        // });
        return null;
    }

    /**
     * Store data to cache.
     *
     * @param  {string} key
     * @param  {any}  value
     * @return {void}
     */
    public function set(string $key, $value): void {
        // this._redis.set(key, JSON.stringify(value));
        // if(this.options.databaseConfig.publishPresence === true && /^presence-.*:members$/.test(key)) {
        //     this._redis.publish('PresenceChannelUpdated', JSON.stringify({
        //         "event": {
        //             "channel": key,
        //             "members": value
        //         }
        //     }));
        // }
    }
}