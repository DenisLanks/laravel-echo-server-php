<?php

namespace Lanks\EchoServer\Subscribers;

use Closure;
use React\Promise\Promise;
use Lanks\EchoServer\Helper;

class HttpSubscriber implements Subscriber
{
    /**
     * Create new instance of http subscriber.
     *
     * @param  {any} express
     */
    public function constructor($express, $options) { }

    /**
     * Subscribe to events to broadcast.
     *
     * @return {void}
     */
    public function subscribe(Closure $callback): Promise {
        // return new Promise((resolve, reject) => {
        //     // Broadcast a message to a channel
        //     this.express.post('/apps/:appId/events', (req, res) => {
        //         let body: any = [];
        //         res.on('error', (error) => {
        //             if (this.options.devMode) {
        //                 Log.error(error);
        //             }
        //         });

        //         req.on('data', (chunk) => body.push(chunk))
        //             .on('end', () => this.handleData(req, res, body, callback));
        //     });

        //     Log.success('Listening for http events...');

        //     resolve();
        // });
        return null;
    }

    /**
     * Handle incoming event data.
     *
     * @param  {any} req
     * @param  {any} res
     * @param  {any} body
     * @param  {Function} broadcast
     * @return {boolean}
     */
    public function handleData($req, $res, $body, $broadcast): boolean {
        $body = \json_decode($body);

        if (($body->channels || $body->channel) && $body->name && $body->data) {

            $data = $body->data;
            $data = \json_decode($data);
            $message = Helper::toObject([
                'event'=> $body->name,
                'data'=> $data,
                'socket'=> $body->socket_id
            ]);
            $channels = $body->channels || [$body->channel];

            if ($this->options->devMode) {
                Helper::debug("Channel: ".implode(', ',$channels));
                Helper::debug("Event: ". $message->event);
            }

            foreach ($channels as $key => $value) {
                //broadcast($channel, $message);
            }
        } else {
            return $this->badResponse(
                $req,
                $res,
                'Event must include channel, event name and data'
            );
        }

        $res->write(json_encode("{ message: 'ok' }"));
    }

    /**
     * Handle bad requests.
     *
     * @param  {any} req
     * @param  {any} res
     * @param  {string} message
     * @return {boolean}
     */
    public function badResponse($req, $res, $message): boolean {
        $res->statusCode = 400;
        //$res->json({ error: message });

        return false;
    }
}