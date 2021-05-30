<?php
echo '[SLASH INIT]' . PHP_EOL;
$slash_client = new \Discord\Slash\RegisterClient("$token"); //Register commands
$slash = new \Discord\Slash\Client([ //Listen for events
	'public_key' => "$public_key",
    'loop' => $discord->getLoop(), // reactphp event loop, default creates a new loop
]);
$slash->linkDiscord($discord);

/// GETTING COMMANDS
// gets a list of all GLOBAL comamnds (not guild-specific)
//$commands = $client->getCommands();
// gets a list of all guild-specific commands to the given guild
//$guildCommands = $client->getCommands('guild_id_here');
// gets a specific command with command id - if you are getting a guild-specific command you must provide a guild id
//$command = $client->getCommand('command_id', 'optionally_guild_id');

/// CREATING COMMANDS
$command = $slash_client->createGlobalCommand('ping', 'Pong!', [ //Global command
    // optional array of options
]);

$command = $slash_client->createGuildSpecificCommand('115233111977099271', 'palace-test', 'command_description', [ //Guild command
    // optional array of options
]);

/// UPDATING COMMANDS
// change the command name etc.....
//$command->name = 'newcommandname';
//$client->updateCommand($command);

/// DELETING COMMANDS
//$client->deleteCommand($command);


// register global command `/ping`
$slash->registerCommand('ping', function (\Discord\Slash\Parts\Interaction $interaction, \Discord\Slash\Parts\Choices $choices) {
	$interaction->replyWithSource('Pong!');
});

// register guild command `/palace-test`
$slash->registerCommand('palace-test', function (\Discord\Slash\Parts\Interaction $interaction, \Discord\Slash\Parts\Choices $choices) {
	/*
	echo 'Interactions: ' . PHP_EOL;
	var_dump($interaction);
	echo PHP_EOL;
	
	echo 'Choices: ' . PHP_EOL;
	var_dump($choices);
	echo PHP_EOL;	
	$guild = $interaction->guild;
    $channel = $interaction->channel;
    $member = $interaction->member;
	*/
    // do some cool stuff here
    // good idea to var_dump interaction and choices to see what they contain

    // once finished, you MUST either acknowledge or reply to a message
    //$interaction->acknowledge(); // acknowledges the message, doesn't show source message
    //$interaction->acknowledge(true); // acknowledges the message and shows the source message

    // to reply to the message
    //$interaction->reply('Hello, world!'); // replies to the message, doesn't show source message
    $interaction->replyWithSource('Hello, world!'); // replies to the message and shows the source message
});