<?php
$slash = new \Discord\Slash\Client([
    // required options
    //'public_key' => "$public_key",

    // optional options, defaults are shown
    //'uri' => '0.0.0.0:80', // if you want the client to listen on a different URI
    'logger' => $logger, // different logger, default will write to stdout
    'loop' => $loop, // reactphp event loop, default creates a new loop
    'socket_options' => [
		'dns' => '8.8.8.8', // can change dns
	], // options to pass to the react/socket instance, default empty array
]);

$slash->linkDiscord($discord);

// register a command `/hello`
$slash->registerCommand('hello', function (\Discord\Slash\Parts\Interaction $interaction, \Discord\Slash\Parts\Choices $choices) {
    // do some cool stuff here
    // good idea to var_dump interaction and choices to see what they contain

    // once finished, you MUST either acknowledge or reply to a message
    //$interaction->acknowledge(); // acknowledges the message, doesn't show source message
    //$interaction->acknowledge(true); // acknowledges the message and shows the source message

    // to reply to the message
    //$interaction->reply('Hello, world!'); // replies to the message, doesn't show source message
    $interaction->replyWithSource('Hello, world!'); // replies to the message and shows the source message

    // the `reply` methods take 4 parameters: content, tts, embed and allowed_mentions
    // all but content are optional.
    // read the discord developer documentation to see what to pass to these options:
    // https://discord.com/developers/docs/resources/channel#create-message
});