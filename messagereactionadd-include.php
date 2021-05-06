<?php
if (($reaction->message->content == null) || ($reaction->message->content == "")) {
    //echo '[REACT TO EMPTY MESSAGE]' . __FILE__ . ':' . __LINE__ . PHP_EOL;
    //echo '[MessageID] ' . $reaction->message->id . PHP_EOL;
	//var_dump($reaction->message);
	ob_start();
	
	//echo print_r(get_defined_vars(), true); //REALLY REALLY BAD IDEA
	var_dump($reaction);
	
	$debug_output = ob_get_contents();
	ob_end_clean(); //here, output is cleaned. You may want to flush it with ob_end_flush()
	file_put_contents('messagereactionadd_debug.txt', $debug_output);
	ob_end_flush();
	
    $reaction->message->channel->messages->fetch($reaction->message->id)->done(function ($message) use ($reaction, $discord) {
        include 'messagereactionadd2-include.php';
    }, static function ($error) {
        echo $e->getMessage() . PHP_EOL;
    });
    return true; //Don't process blank messages, bots, webhooks, or rich embeds
}
include 'messagereactionadd2-include.php';
return true;
