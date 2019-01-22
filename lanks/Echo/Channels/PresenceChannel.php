<?php

namespace Lanks\EchoServer\Channels;

use React\Promise\Promise;
use Lanks\EchoServer\Database\Database;

function debug($msg)
{
    echo($msg.PHP_EOL);
}

class PresenceChannel
{
     /**
     * Database instance.
     *
     * @type {Database}
     */
    public $db;

    /**
     * Create a new Presence channel instance.
     *
     * @param  {} options
     * @param  {string} channel
     * @param  {string}$member
     */
    public function constructor($io, $options) {
        $this->db = new Database($options);
    }

    /**
     * Get the$members of a presence channel.
     *
     * @param  {string}  channel
     * @return {Promise}
     */
    public function getMembers(string $channel) {
        return $this->db->get($channel . ':members');
    }

    /**
     * Check if a user is on a presence channel.
     *
     * @param  {string}  channel
     * @param  {}$member
     * @return {boolean}
     */
    public function isMember(string $channel, $member){
        $that = $this;
        $resolver = function ($resolve, $reject) use($that, $channel, $member)
        {
            $canceller = function($error){
                echo($error.PHP_EOL);
            };

            $resolver = function($members) use($that, $channel, $member, $resolve) {
                $that->removeInactive($channel,$members,$member)
                ->then(function($members) use( $member, $resolve ) {
                    $search = array_filter($members,function($m) use( $member ){
                        return $m->user_id ==$member->user_id;
                    });

                    if ($search && count($search) > 0) {
                        $resolve(true);
                    }

                    $resolve(false);
                });
            };

            $that->getMembers($channel)->then($resolver, $canceller);
        };

        return new Promise($resolver);
    }

    /**
     * Remove inactive channel$members from the presence channel.
     *
     * @param  {string} channel
     * @param  {[]}$members
     * @param  {[]}$member
     * @return {Promise<>}
     */
    public function removeInactive(string $channel,$members, $member):Promise {
        $that = $this;
        $resolver = function($resolve, $reject) use($that, $channel,$members, $member){
            $resolver = function($error, $clients) use($that, $channel,$members, $member) {
                $members = empty($members)? [] :$members;
                $members = array_filter($members,function($m) use($that, $channel,$members, $member){
                    return \in_array($member->socketId);
                });
 
                 $this->db->set($channel . ':members',$members);
 
                 $resolve($members);
             };
            $that->io->of('/')
            ->in($channel)
            ->clients($resolver );
        };
        return new Promise($resolver);
    }

    /**
     * Join a presence channel and emit that they have joined only if it is the
     * first instance of their presence.
     *
     * @param  {} socket
     * @param  {string} channel
     * @param  {object}  member
     */
    public function join($socket, string $channel, $member) {
        if (!empty($member)) {
            if ($this->options->devMode) {
                echo('Unable to join channel. Member data for presence channel missing'.PHP_EOL);
            }

            return;
        }

        $that = $this;
        $isMemberResolver = function($is_member) use($socket, $channel, $member, $that ){
            $getMemberResolver = function($members )  use($socket, $channel, $member, $that ){
                $members = empty($members)? []: $members;
                $member->socketId = $socket->id;
                $members[] = $member;
 
                $that->db->set($channel + ':members',$members);

                /**
                 * @todo get unique members
                 */
                //$members = _->uniqBy($members->reverse(), 'user_id');
 
                $that->onSubscribed($socket, $channel,$members);
 
                 if (!$is_member) {
                    $that->onJoin($socket, $channel,$member);
                 }
             };

            $that->getMembers($channel)
            ->then($getMemberResolver , function($error) { echo($error.PHP_EOL); } );
        };
        $this->isMember($channel,$member)
        ->then($isMemberResolver, function() {
            echo('Error retrieving pressence channel members.'.PHP_EOL);
        });
    }

    /**
     * Remove a member from a presenece channel and broadcast they have left
     * only if not other presence channel instances exist.
     *
     * @param  {} socket
     * @param  {string} channel
     * @return {void}
     */
    public function leave($socket, string $channel): void {
        $that = $this;
        $getMembersResolver = function($members) use($socket, $channel, $that){
            $members =$members || [];
            $member = array_filter($members, function($member)use($socket){ $member->socketId == $socket->id; } );
            $member = $member[array_key_first($member)];
            $members = array_filter($members, function($m)use($member){ $m->socketId != $member->socketId; } );
 
            $that->db->set($channel + ':members',$members);
 
            $that->isMember($channel,$member)
            ->then(function($is_member) use($member, $channel, $that){
                if (!$is_member) {
                    unset($member->socketId);
                    $that->onLeave($channel,$member);
                }
            });
         };

        $this->getMembers($channel)
        ->then($getMembersResolver, "debug");
    }

    /**
     * On join event handler.
     *
     * @param  {} socket
     * @param  {string} channel
     * @param  {} member
     * @return {void}
     */
    public function onJoin($socket, string $channel, $member): void {
        $this->io
            ->sockets
            ->connected[$socket->id]
            ->broadcast
            ->to($channel)
            ->emit('presence:joining', $channel,$member);
    }

    /**
     * On leave emitter.
     *
     * @param  {string} channel
     * @param  {member} member
     * @return {void}
     */
    public function onLeave(string $channel, $member): void {
        $this->io
            ->to($channel)
            ->emit('presence:leaving', $channel,$member);
    }

    /**
     * On subscribed event emitter.
     *
     * @param  {} socket
     * @param  {string} channel
     * @param  {[]}$members
     * @return {void}
     */
    public function onSubscribed($socket, string $channel,array $members) {
        $this->io
            ->to($socket->id)
            ->emit('presence:subscribed', $channel ,$members);
    }
}