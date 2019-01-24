<?php

namespace Lanks\EchoServer;

use React\Promise\Promise;
use React\EventLoop\Factory;
use React\Socket\SecureServer;
use React\Http\Response;

class Server
{
     /**
     * The http server.
     *
     * @type {any}
     */
    public $loop;
    public $socketServer;
    public $secureServer;

    /**
     * Socket.io client.
     *
     * @type {object}
     */
    public $io;
    public $options;

    /**
     * Create a new server instance.
     */
    public function constructor($options) {
        $this->options = $options;
     }

    /**
     * Start the Socket.io server.
     *
     * @return {void}
     */
    public function init(): Promise {
        $that = $this;
        $initResolve = function ($resolve, $reject) use($that) {
            $serverProtocolResolver = function()use($resolve, $reject, $that) {
                $host = $that->options->host || 'localhost';
                Helper::debug("Running at ${host} on port {$that->getPort()}");
                $resolve($that->io);
            };
            $that->serverProtocol()
            ->then($serverProtocolResolver, function($error){ $reject($error); });
        };
        return new Promise($initResolve);
    }

    /**
     * Sanitize the port number from any extra characters
     *
     * @return {number}
     */
    public function getPort() {
        $portRegex = "/([0-9]{2,5})[\/]?$/";
        if(preg_match($portRegex, $this->options->port)){
            
            return intval( $this->options->port);
        }
    }

    /**
     * Select the http protocol to run on.
     *
     * @return {Promise}
     */
    public function serverProtocol(): Promise {
        return new Promise(function($resolve, $reject) {
            if ($this->options->protocol == 'https') {
                $this->secure()->then(function() {
                    $resolve($this->httpServer(true));
                }, function($error) { $reject($error); } );
            } else {
                resolve($this->httpServer(false));
            }
        });
    }

    /**
     * Load SSL 'key' & 'cert' files if https is enabled->
     *
     * @return {void}
     */
    public function secure(): Promise {
        return new Promise(function($resolve, $reject) {
            if (!$this->options->sslCertPath || !$this->options->sslKeyPath) {
                reject('SSL paths are missing in server config.');
            }

            assign($this->options, [
                'cert'=> $fs->readFileSync($this->options->sslCertPath),
                'key'=> $fs->readFileSync($this->options->sslKeyPath),
                'ca'=> ($this->options->sslCertChainPath) ? $fs->readFileSync($this->options->sslCertChainPath) : '',
                'passphrase'=> $this->options->sslPassphrase,
            ]);

            resolve($this->options);
        });
    }

    /**
     * Create a socket.io server.
     *
     * @return {any}
     */
    public function httpServer(bool $secure) {
        $loop = Factory::create();

        $socketServer = new \React\Socket\Server("{$this->options->host}:{$this->getPort()}",$loop);
        if ($secure) {
            $secureServer = new SecureServer($socketServer,$loop);
            $httpServer = $https->createServer($this->options, $this->express);
        } 

        $httpServer->listen($this->getPort(), $this->options->host);

        $this->authorizeRequests();

        /**Create new socketio instance */
        return $this->io =  io($httpServer, $this->options->socketio);
    }

    /**
     * Attach global protection to HTTP routes, to verify the API key.
     */
    public function authorizeRequests(): void {
        $this->express->param('appId', function($req, $res, $next){
            if (!$this->canAccess($req)) {
                return $this->unauthorizedResponse($req, $res);
            }

            $next();
        });
    }

    /**
     * Check is an incoming request can access the api.
     *
     * @param  {any} req
     * @return {boolean}
     */
    public function canAccess($req): boolean {
        $appId = $this->getAppId(req);
        $key = $this->getAuthKey(req);

        if ($key && $appId) {
            $client = $this->options->clients->find(function($client) {
                return $client->appId === $appId;
            });

            if ($client) {
                return $client->key === $key;
            }
        }

        return false;
    }

    /**
     * Get the appId from the URL
     *
     * @param  {any} req
     * @return {string|boolean}
     */
    public function getAppId($req){
        if ($req->params->appId) {
            return $req->params->appId;
        }

        return false;
    }

    /**
     * Get the api token from the request.
     *
     * @param  {any} req
     * @return {string|boolean}
     */
    public function getAuthKey($req) {
        if ($req->headers->authorization) {
            return $req->headers->authorization->replace('Bearer ', '');
        }

        // if ( url->parse(req->url, true)->query->auth_key) {
        //     return url->parse(req->url, true)->query->auth_key
        // }

        return false;

    }

    /**
     * Handle unauthorized requests.
     *
     * @param  {any} req
     * @return {boolean}
     */
    public function unauthorizedResponse($req): boolean {
        return new Response(403,[],json_encode("{ error: 'Unauthorized' }"));
    }
}