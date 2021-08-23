<?php
function guildBanAdd($ban, $discord) {
	include_once "custom_functions.php";

	$guild_id = $ban->guild_id;
	$guild = $ban->guild ?? $discord->guilds->offsetGet($guild_id);
	$user_id = $ban->user_id;
	$user = $ban->user ?? $discord->users->offsetGet($user_id);
	$reason = $ban->reason;

	if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "[guildBanAdd] ($guild_id)" . PHP_EOL;
	$author_guild_name = $guild->name;
	//$author_guild_avatar = $guild->icon;
	$author_username = $user->username;
	$author_discriminator = $user->discriminator;
	$author_avatar = $user->avatar;
	$author_check = "$author_username#$author_discriminator";

	$user_folder = "\\users\\$user_id";
	CheckDir($user_folder);
	$guild_folder = "\\guilds\\$guild_id";
	if (!CheckDir($guild_folder)) {
		//
	}

	//Load config variables for the guild
	$guild_config_path = getcwd() . "$guild_folder\\guild_config.php"; //if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "guild_config_path: " . $guild_config_path . PHP_EOL;
	if (!include "$guild_config_path") {
		if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "CONFIG CATCH!" . PHP_EOL;
		$counter = $GLOBALS[$guild_id."_config_counter"] ?? 0;
		if ($counter <= 10) {
			$GLOBALS[$guild_id."_config_counter"]++;
		} else {
			$discord->guilds->leave($guild);
			rmdir(getcwd() . $guild_folder);
			if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "[GUILD DIR REMOVED - BAN]" . PHP_EOL;
		}
	}

	if ($modlog_channel_id && $guild) {
		$modlog_channel	= $guild->channels->get('id', $modlog_channel_id);
		if ($modlog_channel) {
			//Build the embed message
			$embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
			$embed
			//	->setTitle("Commands")																	// Set a title
				->setColor(0xe1452d)																	// Set a color (the thing on the left side)
				->setDescription("$author_guild_name")																// Set a description (below title, above fields)
				->addFieldValues("Banned", "<@$user_id>")																// New line after this
				
			//	->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
			//	->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             	// Set an image (below everything except footer)
				->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
				->setAuthor("$author_check ($user_id)", "$author_avatar")  							// Set an author with icon
				->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
				->setURL("");                             												// Set the URL
			if ($reason) $embed->addFieldValues("Reason", $reason);
			$modlog_channel->sendEmbed($embed);
		}
	}
}