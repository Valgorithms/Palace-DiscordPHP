<?php

include_once 'messagereactionadd_function.php'; //declared processReaction

if (is_null($reaction->message->content)) {
	//echo '[REACT TO EMPTY MESSAGE]' . __FILE__ . ':' . __LINE__ . PHP_EOL;
	//echo '[MessageID] ' . $reaction->message->id . PHP_EOL;
	$channel = $discord->getChannel($reaction->channel_id);
	$channel->messages->fetch("{$reaction->message_id}")->done(function ($message) use ($reaction, $discord) : void {
		processReaction($reaction, $discord);
	}, static function ($error) {
		echo $e->getMessage() . PHP_EOL;
	});
	return; //Don't process twice
}
processReaction($reaction, $discord);