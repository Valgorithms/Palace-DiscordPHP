<?php
function messageDeleteRaw($channel, $message_id, $discord) {
	if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "[messageDeleteRaw] " . $channel->guild_id . '/' . $channel->id . '/' . $message_id . PHP_EOL;		
	$channel_id = $channel->id;
	$log_message = "Message with id $message_id was deleted from <#$channel_id>\n" . PHP_EOL;
	$author_guild_id = $channel->guild_id;
	
	$embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
	$embed
		->setColor(0xa7c5fd)																	// Set a color (the thing on the left side)
		->setDescription("$log_message")														// Set a description (below title, above fields)
		->setTimestamp();                                                                     	// Set a timestamp (gets shown next to footer)
		if ($author_guild_id != '115233111977099271') $embed->setFooter("Palace Bot by Valithor#5947");                             					// Set a footer without icon
		$embed->setURL("");
	$guild = $channel->guild ?? $discord->guilds->get('id', $author_guild_id);
	//Load config variables for the guild
	$guild_folder = "\\guilds\\$author_guild_id";
	$guild_config_path = getcwd() . "$guild_folder\\guild_config.php"; //if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "guild_config_path: " . $guild_config_path . PHP_EOL;
	include "$guild_config_path"; //$modlog_channel_id
	$modlog_channel	= $guild->channels->get('id', $modlog_channel_id);
	if($modlog_channel) $modlog_channel->sendEmbed($embed);
}
?>