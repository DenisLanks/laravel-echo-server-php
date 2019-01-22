<?php

namespace Lanks\EchoServer\Subscribers;

use Closure;

class RedisSubscriber implements Subscriber
{
    /**
     * Redis pub/sub client.
     *
     * @type {object}
     */
    private $_redis;

    /**
     * Create a new instance of subscriber.
     *
     * @param {any} options
     */
    public function constructor($options) {
        $this->_redis = new Redis($options->databaseConfig->redis);
    }

    /**
     * Subscribe to events to broadcast.
     *
     * @return {Promise<any>}
     */
    public function subscribe(Closure $callback): Promise {

        // return new Promise((resolve, reject) => {
        //     this._redis.on('pmessage', (subscribed, channel, message) => {
        //         try {
        //             message = JSON.parse(message);

        //             if (this.options.devMode) {
        //                 Log.info("Channel: " + channel);
        //                 Log.info("Event: " + message.event);
        //             }

        //             callback(channel, message);
        //         } catch (e) {
        //             if (this.options.devMode) {
        //                 Log.info("No JSON message");
        //             }
        //         }
        //     });

        //     this._redis.psubscribe('*', (err, count) => {
        //         if (err) {
        //             reject('Redis could not subscribe.')
        //         }

        //         Log.success('Listening for redis events...');

        //         resolve();
        //     });
        // });
        return null;
    }
}