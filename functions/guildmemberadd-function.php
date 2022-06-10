<?php
function guildMemberAdd($guildmember, $discord) {
	$author_guild_id = $guildmember->guild->id;
	if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "guildMemberAdd ($author_guild_id)" . PHP_EOL;
	$user = $guildmember->user;
	if (isset($guildmember) && is_object($guildmember) && get_class($guildmember) == "Discord\Parts\User\Member") {
		$guildmember = $guildmember;
		$user = $guildmember->user;
	} else {
		$author_user = $author;
		$user = $guildmember;
	}

	$user_username 											= $user->username;
	$user_id 												= $user->id;
	$user_discriminator 									= $user->discriminator;
	$user_avatar 											= $user->avatar;
	$user_check 											= "$user_username#$user_discriminator";
	$user_tag												= $user_check;
	$user_createdTimestamp									= $user->createdTimestamp();
	$user_createdFormatted									= date("D M j H:i:s Y", $user_createdTimestamp);

	$guild_memberCount										= $guildmember->guild->member_count;
	$author_guild											= $guildmember->guild;
	$author_guild_id										= $guildmember->guild->id;
	$author_guild_name										= $guildmember->guild->name;


	if ($author_guild_id == "116927365652807686") { //Only in ValZarGaming
		$minimum_time = strtotime("-30 days");
		if ($user_createdTimestamp > $minimum_time) {
			if ($log_channel = $author_guild->channels->offsetGet('333484030492409856')) { //Alert staff
				$log_channel->sendMessage("<@$user_id> was banned because their discord account was newer than 30 days.");
			}
			$reason = "Your discord account is too new. Please contact <@116927250145869826> if you believe this ban is an error.";
			
			$guildmember->ban(1, $reason);
		}
	}


	//Load config variables for the guild
	$guild_folder = "\\guilds\\$author_guild_id";
	$guild_config_path = getcwd() . "$guild_folder\\guild_config.php"; //if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "guild_config_path: " . $guild_config_path . PHP_EOL;
	include "$guild_config_path";
	if ($welcome_log_channel_id) {
		$welcome_log_channel = $guildmember->guild->channels->get('id', $welcome_log_channel_id);
	}
	if ($welcome_public_channel_id) {
		$welcome_public_channel	= $guildmember->guild->channels->get('id', $welcome_public_channel_id);
	}
	//	Build the embed
	$embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
	$embed
		->setTitle("Member Joined")																// Set a title
		->setColor(0xa7c5fd)																	// Set a color (the thing on the left side)
		->setDescription("<@$user_id> just joined **$author_guild_name**" . "\n" .				// Set a description (below title, above fields)
			//"There are now **$guild_memberCount** members." . "\n" .
			"Account created on $user_createdFormatted")										
		//X days ago
		->addFieldValues("Member Count", "$guild_memberCount")
	//	->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             	// Set an image (below everything except footer)
		->setTimestamp();                                                                     	// Set a timestamp (gets shown next to footer)
		if ($author_guild_id->id != '115233111977099271')  $embed->setFooter("Palace Bot by Valithor#5947");                             					// Set a footer without icon
		$embed->setURL("");
	if ($user_avatar) $embed->setThumbnail("$user_avatar");										// Set a thumbnail (the image in the top right corner)

	if ($welcome_log_channel) { //Send a detailed embed with user info
	/*
		ob_flush();
		ob_start();
		var_dump($embed);
		file_put_contents("add_embed.txt", ob_get_flush());
		*/
		$welcome_log_channel->sendMessage("", false, $embed)->done(function ($r) {
			/*
			ob_flush();
			ob_start();
			var_dump($r);
			file_put_contents("add_result.txt", ob_get_flush());
			*/
		}, function ($error) {
			ob_flush();
			ob_start();
			var_dump($error);
			file_put_contents("add_error.txt", ob_get_flush());
		});
	} elseif ($modlog_channel) { //Send a detailed embed with user info
		$modlog_channel->sendMessage("", false, $embed)->done(function ($r) {
			/*
			ob_flush();
			ob_start();
			var_dump($r);
			file_put_contents("add_result.txt", ob_get_flush());
			*/
		}, function ($error) {
			ob_flush();
			ob_start();
			var_dump($error);
			file_put_contents("add_error.txt", ob_get_flush());
		});
	}
	if ($welcome_public_channel) { //Greet the new user to the server
		$welcome_public_channel->sendMessage("Welcome <@$user_id> to $author_guild_name!");
	}
	$user_folder = "\\users\\$user_id";
	CheckDir($user_folder);
	//Place user info in target's folder
	$array = VarLoad($user_folder, "tags.php");
	if ($user_tag && $array) {
		if (!in_array($user_tag, $array)) {
			$array[] = $user_tag;
		}
	}
	VarSave($user_folder, "tags.php", $array);
}