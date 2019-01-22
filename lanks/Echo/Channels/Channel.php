<?php

namespace Lanks\EchoServer\Channels;

class Channel
{
    /**
     * Channels and patters for private channels.
     *
     * @type {array}
     */
    protected $_privateChannels = ['private-*', 'presence-*'];

    /**
     * Allowed client events
     *
     * @type {array}
     */
    protected $_clientEvents = ['client-*'];


    /**
     * Private channel instance.
     *
     * @type {PrivateChannel}
     */
    protected $private;

    /**
     * Presence channel instance.
     *
     * @type {PresenceChannel}
     */
    protected $presence;

    public function __construct($io, array $options)
    {

        $this->private = new PrivateChannel($options);
        $this->presence = new PresenceChannel($io, $options);

        if ($this->options['devMode']) {
            echo ('Channels are ready.' . PHP_EOL);
        }
    }

    /**
     * Join a channel.
     *
     * @param  {object} socket
     * @param  {object} data
     * @return {void}
     */
    public function join($socket, $data)
    {
        if ($data->channel) {
            if ($this->isPrivate($data->channel)) {
                $this->joinPrivate($socket, $data);
            } else {
                $socket->join($data->channel);
                $this->onJoin($socket, $data->channel);
            }
        }
    }

    /**
     * Trigger a client message
     *
     * @param  {object} socket
     * @param  {object} data
     * @return {void}
     */
    public function clientEvent($socket, $data)
    {
        if ($data->event && $data->channel) {
            if ($this->isClientEvent($data->event) &&
                $this->isPrivate($data->channel) &&
                $this->isInChannel($socket, $data->channel)) {
                $this->io->sockets->connected[$socket->id]
                    ->broadcast->to($data->channel)
                    ->emit($data->event, $data->channel, $data->data);
            }
        }
    }

    /**
     * Leave a channel.
     *
     * @param  {object} socket
     * @param  {string} channel
     * @param  {string} reason
     * @return {void}
     */
    public function leave($socket, string $channel, string $reason)
    {
        if (!empty($channel)) {
            if ($this->isPresence($channel)) {
                $this->presence->leave($socket, $channel);
            }

            $socket->leave($channel);

            if ($this->options->devMode) {
                $date = date_format("Y-m-d h:i", date(DATE_ATOM));
                echo ("[$date] - {$socket->id} left channel: $channel ($reason)" . PHP_EOL);
            }
        }
    }

    /**
     * Check if the incoming socket connection is a private channel.
     *
     * @param  {string} channel
     * @return {boolean}
     */
    public function isPrivate(string $channel)
    {
        $isPrivate = false;
        
        foreach ($this->_privateChannels as $privateChannel => $value) {
            if(preg_match(str_replace('\*', '.*',$privateChannel),$event) ==1){
                $isPrivate =true;
            }
        }

        return $isPrivate;
    }

     /**
     * Join private channel, emit data to presence channels.
     *
     * @param  {object} socket
     * @param  {object} data
     * @return {void}
     */
    public function joinPrivate($socket, $data)
    {
        $self = $this;
        $this->private->authenticate($socket, $data)->then(function($res) use($self, $socket) {
            $socket->join($data->channel);

            if ($self->isPresence($data->channel)) {
                $member = json_encode($res->channel_data);

                $self->presence->join($socket, $data->channel, $member);
            }

            $self->onJoin($socket, $data->channel);
        }, function($error){
            if ($this->options->devMode) {
                echo ("{$error->reason}" . PHP_EOL);
            }

            $this->io->sockets->to($socket->id)
            ->emit('subscription_error', $data->channel, $error->status);
        });
    }

    /**
     * Check if a channel is a presence channel.
     *
     * @param  {string} channel
     * @return {boolean}
     */
    public function isPresence(string $channel): boolean {
        return strpos($channel,'presence-') === 0;
    }

    /**
     * On join a channel log success.
     *
     * @param {any} socket
     * @param {string} channel
     */
    public function onJoin($socket, $channel): void {
        if ($this->options->devMode) {
            $date = date_format("Y-m-d h:i", date(DATE_ATOM));
            echo ("[$date] - {$socket->id} joined channel: ${channel}" . PHP_EOL);
        }
    }

    /**
     * Check if client is a client event
     *
     * @param  {string} event
     * @return {boolean}
     */
    public function isClientEvent(string $event): boolean {
        $isClientEvent = false;

        foreach ($this->_clientEvents as $clientEvent => $value) {
            if(preg_match(str_replace('\*', '.*',$clientEvent),$event) ==1){
                $isClientEvent =true;
            }
        }

        return $isClientEvent;
    }

    /**
     * Check if a socket has joined a channel.
     *
     * @param socket
     * @param channel
     * @returns {boolean}
     */
    public function isInChannel($socket, string $channel): boolean {
        return isset($socket->rooms[$channel]);
    }
}