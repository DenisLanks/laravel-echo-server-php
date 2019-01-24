<?php

namespace Lanks\EchoServer;

use React\Promise\Promise;
use React\EventLoop\Factory;

class EchoServer
{
    protected $loop;
      /**
     * Default server options.
     *
     * @type {object}
     */
    public $defaultOptions = [
        'authHost'=> 'http://localhost',
        'authEndpoint'=> '/broadcasting/auth',
        'clients'=> [],
        'database'=> 'redis',
        'databaseConfig'=> [
            'redis'=> [],
            'sqlite'=> [
                'databasePath'=> '/database/laravel-echo-server.sqlite'
            ]
        ],
        'devMode'=> false,
        'host'=> null,
        'port'=> 6001,
        'protocol'=> "http",
        'socketio'=> [],
        'sslCertPath'=> '',
        'sslKeyPath'=> '',
        'sslCertChainPath'=> '',
        'sslPassphrase'=> '',
        'subscribers'=> [
            'http'=> true,
            'redis'=> true
        ],
        'apiOriginAllow'=> [
            'allowCors'=> false,
            'allowOrigin'=> '',
            'allowMethods'=> '',
            'allowHeaders'=> ''
        ]
    ];

    /**
     * Configurable server options.
     *
     * @type {object}
     */
    public $options;

    /**
     * Socket.io server instance.
     *
     * @type {Server}
     */
    private $server;

    /**
     * Channel instance.
     *
     * @type {Channel}
     */
    private $channel;

    /**
     * Subscribers
     *
     * @type {Subscriber[]}
     */
    private $subscribers=[];

    /**
     * Http api instance.
     *
     * @type {HttpApi}
     */
    private $httpApi;

    /**
     * Create a new instance.
     */
    public function constructor() { }

    /**
     * Start the Echo Server.
     *
     * @param  {Object} config
     * @return {Promise}
     */
    public function run($options=[]): Promise {
        $this->loop = Factory::create();
        $that = $this;
        $resolver = function($resolve, $reject) use($that, $options ){

            if(empty($options)){
                $json_config = file_get_contents('laravel-echo-server.json');
                if($json_config!= false)
                    $options = json_decode($json_config);

            }

            $that->options = array_merge($that->defaultOptions, $options);
            $that->startup();
            $that->server = new Server($that->options);

            $serverInitResolver = function($io) use($that,$resolve ){
                $ioInitResolver = function() use($that,$resolve){
                    Helper::debug('\nServer ready!\n');
                    $resolve($that);
                };
                $that->init($io)->then($ioInitResolver,function($error){ Helper::error($error); } );
            };
            $that->server->init()->then($serverInitResolver,function($error){ Helper::error($error); } );
        };
        $this->loop->run();
        return new Promise($resolver);
    }

    /**
     * Initialize the class
     *
     * @param {any} io
     */
    public function init($io): Promise {
        $that=$this;
        $initResolver = function($resolve, $reject){
            $this->channel = new Channel(io, $this->options);

            $this->subscribers = [];
            if ($this->options->subscribers->http)
                $this->subscribers->push(new HttpSubscriber($this->server->express, $this->options));
            if ($this->options->subscribers->redis)
                $this->subscribers->push(new RedisSubscriber($this->options));

            $this->httpApi = new HttpApi(io, $this->channel, $this->server->express, $this->options->apiOriginAllow);
            $this->httpApi->init();

            $this->onConnect();
            $this->listen()->then(function()use($resolve){ $resolve(); },function($error){ Helper::error($error); });
        };
        return new Promise($initResolver);
    }

    /**
     * Text shown at startup.
     *
     * @return {void}
     */
    public function startup(): void {
        Helper::debug('\nP H P  L A R A V E L  E C H O  S E R V E R\n');
        //Log.info(`version ${packageFile.version}\n`);

        if ($this->options->devMode) {
            Helper::debug('Starting server in DEV mode...\n');
        } else {
            Helper::debug('Starting server...\n');
        }
    }

    /**
     * Listen for incoming event from subscibers.
     *
     * @return {void}
     */
    public function listen(): Promise {
        $resolver = function($resolve, $reject) {
            $subscribePromises = array_map(function ($subscriber)
            {
                $resolver = function($channel, $message){
                    return $this->broadcast($channel, $message);
                };
                return $subscriber->subscribe($resolver);
            },$this->subscribers);

            //Promise->all($subscribePromises)->then(() => resolve());
        };
        return new Promise($resolver);
    }

    /**
     * Return a channel by its socket id.
     *
     * @param  {string} socket_id
     * @return {any}
     */
    public function find(string $socket_id) {
        return $this->server->io->sockets->connected[$socket_id];
    }

    /**
     * Broadcast events to channels from subscribers.
     *
     * @param  {string} channel
     * @param  {any} message
     * @return {void}
     */
    public function broadcast(string $channel, $message): boolean {
        if ($message->socket && $this->find($message->socket)) {
            return $this->toOthers($this->find($message->socket), $channel, $message);
        } else {
            return $this->toAll($channel, $message);
        }
    }

    /**
     * Broadcast to others on channel.
     *
     * @param  {any} socket
     * @param  {string} channel
     * @param  {any} message
     * @return {boolean}
     */
    public function toOthers($socket, string $channel, $message): boolean {
        $socket->broadcast->to($channel)
            ->emit($message->event, $channel, $message->data);

        return true;
    }

    /**
     * Broadcast to all members on channel.
     *
     * @param  {any} socket
     * @param  {string} channel
     * @param  {any} message
     * @return {boolean}
     */
    public function toAll(string $channel, $message): boolean {
        $this->server->io->to($channel)
            ->emit($message->event, $channel, $message->data);

        return true;
    }

    /**
     * On server connection.
     *
     * @return {void}
     */
    public function onConnect(): void {
        $that = $this;
        $this->server->io->on('connection', function($socket)use ($that){
            $that->onSubscribe($socket);
            $that->onUnsubscribe($socket);
            $that->onDisconnecting($socket);
            $that->onClientEvent($socket);
        });
    }

    /**
     * On subscribe to a channel.
     *
     * @param  {object} socket
     * @return {void}
     */
    public function onSubscribe($socket): void {
        $that = $this;
        $socket->on('subscribe', function($data) use ($that,$socket ){
            $that->channel->join($socket, $data);
        });
    }

    /**
     * On unsubscribe from a channel.
     *
     * @param  {object} socket
     * @return {void}
     */
    public function onUnsubscribe($socket): void {
        $that = $this;
        $socket->on('unsubscribe', function($data) use ($that,$socket ){
            $that->channel->leave($socket, $data->channel, 'unsubscribed');
        });
    }

    /**
     * On socket disconnecting.
     *
     * @return {void}
     */
    public function onDisconnecting($socket): void {
        $that = $this;

        $socket->on('disconnecting', function($reason)use($that) {
            foreach ($socket->rooms as $room => $value) {
                if ($room !== $socket->id) {
                    $that->channel->leave($socket, $room, $reason);
                }
            }
        });
    }

    /**
     * On client events.
     *
     * @param  {object} socket
     * @return {void}
     */
    public function onClientEvent($socket): void {
        $that = $this;
        $socket->on('client event', function($data)use($that){
            $that->channel->clientEvent($socket, $data);
        });
    }
}