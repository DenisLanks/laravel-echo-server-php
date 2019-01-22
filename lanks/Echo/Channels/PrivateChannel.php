<?php

namespace Lanks\EchoServer\Channels;

use React\Promise\Promise;
use Lanks\EchoServer\Helper;
use React\EventLoop\Factory;
use React\HttpClient\Request;
use React\HttpClient\Client;

class PrivateChannel
{
     /**
     * Request client.
     *
     * @type {any}
     */
    private $request;

    /**
     * Create a new private channel instance.
     *
     * @param {any} options
     */
    public function constructor( $options) {
        $this->request = $request;
    }

    /**
     * Send authentication request to application server.
     *
     * @param  {any} socket
     * @param  {any} data
     * @return {Promise<any>}
     */
    public function authenticate($socket, $data): Promise {
        $form = json_decode('{ channel_name: data.channel }');
        $headers = (isset($data->auth) && isset($data->auth->headers)) ? $data->auth->headers : [];
        $options =[ 
            'url'=> $this->authHost($socket) . $this->options->authEndpoint,
            'form'=> $form,
            'headers'=> $headers,
            'rejectUnauthorized'=> false
        ];

        if ($this->options->devMode) {
            Helper::debug("- Sending auth request to: {$options->url}");
        }

        return $this->serverRequest($socket, $options);
    }

    /**
     * Get the auth host based on the Socket.
     *
     * @param {any} socket
     * @return {string}
     */
    protected function authHost($socket): string {
        $authHosts = isset($this->options->authHost) ?
            $this->options->authHost : $this->options->host;

        if (is_string($authHosts)) {
            $authHosts = [$authHosts];
        }
        if(empty($authHosts)){
            $authHosts[] ='http://localhost';
        }
        $authHostSelected = $authHosts[0];

        if ($socket->request->headers->referer) {
            $referer = Helper::toObject( parse_url($socket->request->headers->referer));

            foreach ($authHosts as $key=>$authHost) {
                $authHostSelected = $authHost;

                if ($this->hasMatchingHost($referer, $authHost)) {
                    $authHostSelected = "{$referer->protocol}//{$referer->host}";
                    break;
                }
            };
        }

        return $authHostSelected;
    }

    /**
     * Check if there is a matching auth host.
     *
     * @param  {any}  referer
     * @param  {any}  host
     * @return {boolean}
     */
    protected function hasMatchingHost($referer, $host): boolean {
        return substr( $referer->host,strpos('.',$referer->host)) === $host ||
            "{$referer->protocol}//{$referer->host}" === $host ||
            $referer->host === $host;
    }

    /**
     * Send a request to the server.
     *
     * @param  {any} socket
     * @param  {any} options
     * @return {Promise<any>}
     */
    protected function serverRequest($socket, $options): Promise {
        $that = $this;
        $resolver = function($resolve, $reject) use($that ){
            $options->headers = $this->prepareHeaders($socket, $options);
            $body;

            $loop = Factory::create();
            $client = new Client($loop);
            $request = $client->request("POST",$options->url,$options->headers );
            $request->on('response',function($response) use($that,$loop ){
                $body = '';
                $response->on('data', function ($chunk) use(&$body) {
                    $body.= $chunk;
                });

                $response->on('end', function () use(&$body, $response,$that, $loop) {
                    if ($response->getCode() !== 200) {
                        if ($that->options->devMode) {
                            Helper::error(" - {$socket->id} could not be authenticated to {$options->form->channel_name}");
                            Helper::error($body);
                        }
                        $loop->stop();
                        $reject(Helper::toObject([ 'reason'=> 'Client can not be authenticated, got HTTP status ' . $response->getCode(), 'status'=>  $response->getCode() ]));
                    }else{
                        if ($this->options->devMode) {
                            Helper::debug("- {$socket->id} authenticated for: {$options->form->channel_name}");
                        }
                        $loop->stop();
                        $resolve($body);
                    }
                });

            });

            $request->on('error', function (\Exception $e) use($socket, $options, $reject,$loop) {
                if ($this->options->devMode) {
                    Helper::error("- Error authenticating {$socket->id} for {$options->form->channel_name}");
                    Helper::error($error);
                }
                $loop->stop();
                $reject(Helper::toObject([ 'reason'=> 'Error sending authentication request.', 'status'=> 0 ]));
            });
            $request->end();
            $loop->run();
            
        };
        return new Promise($resolver);
    }

    /**
     * Prepare headers for request to app server.
     *
     * @param  {any} socket
     * @param  {any} options
     * @return {any}
     */
    protected function prepareHeaders($socket, $options) {
        if(is_object($socket->request->headers)){
            $options->headers['Cookie'] = $socket->request->headers->cookie;
        }
        $options->headers['X-Requested-With'] = 'XMLHttpRequest';

        return $options->headers;
    }
}