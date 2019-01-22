<?php

namespace Lanks\EchoServer\Api;

use Lanks\EchoServer\Channels\Channel;

class HttpApi
{
    protected $io;
    protected $channel;
    protected $server;
    protected $options;

    public function __construct($io, Channel $channel, $server, $options) {
        $this->io = $io;
        $this->channel = $channel;
        $this->server = $server;
        $this->options = $options;
    }

     /**
     * Initialize the API.
     */
    public function init()
    {
       $this->corsMiddleware();
    }

    public function corsMiddleware()
    {
        if ($this->options->allowCors) {
            // this.express.use((req, res, next) => {
            //     res.header('Access-Control-Allow-Origin', this.options.allowOrigin);
            //     res.header('Access-Control-Allow-Methods', this.options.allowMethods);
            //     res.header('Access-Control-Allow-Headers', this.options.allowHeaders);
            //     next();
            // });
        }
    }
    /**
     * Outputs a simple message to show that the server is running.
     *
     * @param {any} req
     * @param {any} res
     */
    public function getRoot($req, $resp)
    {
       $resp->send('OK');
    }

    /**
     * Get the status of the server.
     *
     * @param {any} req
     * @param {any} res
     */
    public function getStatus($req, $resp)
    {
        $subscriptionCount = $this->io->engine->clientsCount;
        $executionTime = microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"];
        $memoryUsage = memory_get_usage ();
        $resp->write(json_encode([
            'subscription_count'=> $subscriptionCount,
            'uptime'=> $executionTime,
            'memory_usage'=> $memoryUsage,
        ]));
    }

     /**
     * Get a list of the open channels on the server.
     *
     * @param {any} req
     * @param {any} res
     */
    public function getChannels($req, $resp)
    {
        $prefix = url.parse(req.url, true).query.filter_by_prefix;
        $rooms = $this->io->sockets->adapter->rooms;
        $channels = [];

        foreach ($rooms as $room => $value) {
            if ( isset($value->sockets[$room])) {
                return;
            }
            if (!empty($prefix) && !\substr($room, 0, strlen($prefix)) ) {
                return;
            }
            $channels[$room] = [
                    'subscription_count'=> count($rooms[$room]),
                    'occupied'=> true
                ];
        }

        $res->write(json_encode([ 'channels'=> $channels ]));
    }

     /**
     * Get a information about a channel.
     *
     * @param  {any} req
     * @param  {any} res
     */
    public function getChannel($req, $resp)
    {
        /**
         * @todo get correct param channel name
         */
        $channelName = $req->params->channelName;

        $room = $this->io->sockets->adapter->rooms[$channelName];
        $subscriptionCount = empty($room) ? count($room) : 0;

        $result = [
            'subscription_count'=> $subscriptionCount,
            'occupied'=> ($subscriptionCount > 0)
        ];

        if ($this->channel->isPresence($channelName)) {
            $this->channel->presence->getMembers($channelName).then(function($members) use($resp,$result ){
                /**
                 * @todo get distinct users on channel
                 */
                $result['user_count'] = _.uniqBy(members, 'user_id')->length;

                $resp->write(json_encode($result));
            });
        } else {
                $resp->write(json_encode($result));
        }
    }

    /**
     * Get the users of a channel.
     *
     * @param  {any} req
     * @param  {any} res
     * @return {boolean}
     */
    public function getChannelUsers($req, $resp)
    {
        /**
         * @todo get correct param channel name
         */
        $channelName = $req->params->channelName;

        if (!$this->channel->isPresence($channelName)) {
            return $this->badResponse(
                $req,
                $res,
                'User list is only possible for Presence Channels'
            );
        }

        $this->channel->presence->getMembers($channelName)->then(
            function($members){
            $users = [];

            // _.uniqBy(members, 'user_id').forEach((member: any) => {
            //     users.push({ id: member.user_id });
            // });
            $resp->write(json_encode(['users'=> $users]));
            
        }, function($error) {
            Log.error(error);
        } );
    }
    
    /**
     * Handle bad requests.
     *
     * @param  {any} req
     * @param  {any} res
     * @param  {string} message
     * @return {boolean}
     */
    public function badResponse($req, $resp, string $message)
    {
        /**
         * @todo set status code to 400
         */
        $resp->write(json_encode(['error'=> $users]));
        return false;
    }

}