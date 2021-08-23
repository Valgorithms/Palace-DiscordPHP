<?php
if($GLOBALS['debug_echo']) echo "----- BOT DISCONNECTED FROM DISCORD WITH CODE $code FOR REASON: $erMsg -----" . PHP_EOL;

switch ($code) {
    case '4004':
        if($GLOBALS['debug_echo']) echo "[CRITICAL] TOKEN INVALIDATED BY DISCORD!" . PHP_EOL;
        //Use Restcord to send a message
        $guild = $restcord->guild->getGuild(['guild.id' => 116927365652807686]);
        try {
            $restcord->channel->createMessage([
                'channel.id' => 315259546308444160,
                'content'    => '<@116927250145869826> token invalidated!',
            ]);
        } catch (GuzzleHttp\Command\Exception\CommandClientException $e) {
            var_dump($e->getResponse()->getBody()->getContents());
        }
        //Kill the bot
        $loop->stop();
        $discord->destroy();
        return true;
}

$discord->destroy();
if ($vm == true) {
    switch ($code) {
    case '1000':
    case '4003':
    //case '4004':
        $loop->stop();
        $loop = \React\EventLoop\Factory::create(); //Recreate loop if the cause of the disconnect was possibly related to a VM being paused
}
}
$discord = new \CharlotteDunois\Yasmin\Client(array(), $loop); //Create a new client using the same React loop

if($GLOBALS['debug_echo']) echo "[RESTART LOOP]" . PHP_EOL;
$dt = new DateTime("now");  // convert UNIX timestamp to PHP DateTime
if($GLOBALS['debug_echo']) echo "[TIME] " . $dt->format('d-m-Y H:i:s') . PHP_EOL; // output = 2017-01-01 00:00:00

$discord->login($token)->done(null, function ($error) {
    if($GLOBALS['debug_echo']) echo "[LOGIN ERROR] " . $e->getMessage() . " in file " . $e->getFile() . " on line " . $e->getLine(). PHP_EOL; //if($GLOBALS['debug_echo']) echo any errors
});

if (($vm == true) && (($code == "1000") || ($code == "4000") || ($code == "4003"))) {
    $loop->run();
}
