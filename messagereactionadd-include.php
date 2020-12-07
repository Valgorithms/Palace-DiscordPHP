<?php
if (($reaction->message->content == null) || ($reaction->message->content == "")) {
    //echo '[REACT TO EMPTY MESSAGE]' . __FILE__ . ':' . __LINE__ . PHP_EOL;
    //echo '[MessageID] ' . $reaction->message->id . PHP_EOL;
    //var_dump($reaction->message);
    $reaction->message->channel->messages->fetch("{$reaction->message->id}")->done(function ($message) use ($reaction, $discord) : void {
        include 'messagereactionadd2-include.php';
    }, static function ($error) {
        echo $e->getMessage() . PHP_EOL;
    });
    return true; //Don't process blank messages, bots, webhooks, or rich embeds
}
include 'messagereactionadd2-include.php';
return true;
