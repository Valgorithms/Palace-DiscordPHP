<?php
switch ($message_content_lower) {
	case "php": //;php
		if($GLOBALS['debug_echo']) echo '[PHP]' . PHP_EOL;
		return $message->reply('Current PHP version: ' . phpversion());
	case 'crash': //;crash
		$message->react("☠️");
		return;
	case 'debug role': //;debug role
		if($GLOBALS['debug_echo']) echo '[DEBUG ROLE]' . PHP_EOL;
		$new_role = \Discord\Parts\Guild\Role($discord,
			[
				'name' => ucfirst("__debug"),
				'permissions' => 8,
				'color' => 15158332,
				'hoist' => false,
				'mentionable' => false
			]
		);
		$author_guild->createRole($new_role->getUpdatableAttributes())->done(function ($role) use ($author_member) : void {
			//if($GLOBALS['debug_echo']) echo '[ROLECREATE SUCCEED]' . PHP_EOL;
			$author_member->addRole($role->id);
		}, static function ($error) {
			if($GLOBALS['debug_echo']) echo $error->getMessage() . PHP_EOL;
		});
		return $message->delete();
	case 'freshen';
		return $message->guild->members->freshen()->done(
			function ($members) {
				//Do stuff 
			}
		);
}