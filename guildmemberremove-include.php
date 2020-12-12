<?php
$author_guild_id = $guildmember->guild->id;
echo "guildMemberRemove ($author_guild_id)" . PHP_EOL;
$user = $guildmember->user;
//TODO: Varload welcome setting
$welcome = true;

if ($welcome === true) {
    $user_username 								= $user->username; 													//echo "author_username: " . $author_username . PHP_EOL;
    $user_id 									= $user->id;														//echo "new_user_id: " . $new_user_id . PHP_EOL;
    $user_discriminator 						= $user->discriminator;												//echo "author_discriminator: " . $author_discriminator . PHP_EOL;
    $user_avatar 								= $user->avatar;											//echo "author_id: " . $author_id . PHP_EOL;
    $user_check 								= "$user_username#$user_discriminator"; 							//echo "author_check: " . $author_check . PHP_EOL;\
	$user_tag									= $user_check;
    $user_createdTimestamp						= $user->createdTimestamp();
    $user_createdTimestamp						= date("D M j Y H:i:s", $user_createdTimestamp);
    
    $target_guildmember_role_collection 		= $guildmember->roles;					//This is the Role object for the GuildMember
	
    $target_guildmember_roles_mentions			= array();
    $x=0;
    foreach ($target_guildmember_role_collection as $role) {
//		$target_guildmember_roles_names[] 				= $role->name; 													//echo "role[$x] name: " . PHP_EOL; //var_dump($role->name);
		$target_guildmember_roles_mentions[] = "<@&{$role->id}>"; 													//echo "role[$x] id: " . PHP_EOL; //var_dump($role->id);
    }
    $mention_role_id_queue = "";
    foreach ($target_guildmember_roles_mentions as $mention_role) {
        //$mention_role_name_queue 	= $mention_role_name_queue . $mention_role;
        $mention_role_id_queue 	= $mention_role_id_queue . "$mention_role";
    }
    if (($mention_role_id_queue === null) || ($mention_role_id_queue == "")) { //String cannot be empty or the embed will throw an exception
        $mention_role_id_queue = "?"; //Invisible unicode
    }
    
    $guild_memberCount										= $guildmember->guild->member_count;
    $author_guild_id = $guildmember->guild->id;
    //Load config variables for the guild
    $guild_folder = "\\guilds\\$author_guild_id";
    $guild_config_path = __DIR__  . "$guild_folder\\guild_config.php"; //echo "guild_config_path: " . $guild_config_path . PHP_EOL;
    include "$guild_config_path";
    
    try {
        if ($welcome_log_channel_id) {
            $welcome_log_channel	= $guildmember->guild->channels->get('id', $welcome_log_channel_id);
        }
    } catch (Exception $e) {
        //RuntimeException: Unknown property													//echo 'AUTHOR NOT IN GUILD' . PHP_EOL;
    }
    
    //			Build the embed
    $embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
    $embed
		->setTitle("Member Left")																// Set a title
        ->setColor(0xa7c5fd)																	// Set a color (the thing on the left side)
        ->setDescription("<@$user_id> has left the server!" . "\n" .
		//"There are now **$guild_memberCount** members." . "\n" .
		"Account created on $user_createdTimestamp")											// Set a description (below title, above fields)
		->addFieldValues("Member Count", "$guild_memberCount")
        ->addFieldValues("Roles", "$mention_role_id_queue")										// New line after this
        ->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
        ->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
        ->setURL("");                             												// Set the URL
		if ($user_avatar) $embed->setThumbnail("$user_avatar");									// Set a thumbnail (the image in the top right corner)
    
    if ($welcome_log_channel) {
		/*
		echo '[TEST]' . __FILE__ . ':' . __LINE__ . PHP_EOL;
		ob_flush();
		ob_start();
		var_dump($embed);
		file_put_contents("remove_embed.txt", ob_get_flush());
		*/
        //Send the message, announcing the member's departure
        $welcome_log_channel->sendEmbed($embed)->done(function ($r) {
			/*
			ob_flush();
			ob_start();
			var_dump($r);
			file_put_contents("remove_result.txt", ob_get_flush());
			*/
        }, function ($error) {
			ob_flush();
			ob_start();
			var_dump($error);
			file_put_contents("remove_error.txt", ob_get_flush());
        });
        return true;
    } elseif ($modlog_channel) { //Send a detailed embed with user info
		/*
		echo '[TEST]' . __FILE__ . ':' . __LINE__ . PHP_EOL;
		ob_flush();
		ob_start();
		var_dump($embed);
		file_put_contents("remove_embed.txt", ob_get_flush());
		*/
        //Send the message, announcing the member's departure
        $modlog_channel->sendEmbed($embed)->done(function ($r) {
			/*
			ob_flush();
			ob_start();
			var_dump($r);
			file_put_contents("result_dump.txt", ob_get_flush());
			*/
        }, function ($error) {
			ob_flush();
			ob_start();
			var_dump($error);
			file_put_contents("remove_error.txt", ob_get_flush());
        });
        return true;
	}
}
