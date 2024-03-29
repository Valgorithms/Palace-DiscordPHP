<?php
function messageUpdate($message_new, $discord, $message_old) {
	//This event listener gets triggered willy-nilly so we need to do some checks here if we want to get anything useful out of it

	$message_content_new = $message_new->content; //Null if message is too old or if only an embed was sent
	$message_content_old = $message_old->content; //Null if message is too old or if only an embed was sent
	$message_id_new = $message_new->id; //This doesn't match any message id if the message is too old

	//Only process valid message changes
	if ($message_content_old && ($message_content_new === $message_content_old) ) return;

	if($GLOBALS['debug_echo']) echo "[messageUpdate]" . PHP_EOL;

	//Load global variables

	//Load author info
	$author_user = $message_new->author; //This will need to be updated in a future release of DiscordPHP
	if ($author_member = $message_new->member) $author_perms = $author_member->getPermissions($message->channel); //Populate permissions granted by roles

	$author_channel = $message_new->channel;
	$author_channel_id = $author_channel->id; 												//if($GLOBALS['debug_echo']) echo "author_channel_id: " . $author_channel_id . PHP_EOL;
	$is_dm = false;
	if ($message->channel->type == 1 || (is_null($message->guild_id) && is_null($author_member))) {
		$is_dm = true; //True if direct message
		//return; //Don't try to process direct messages
	}
	$guild = $message_new->channel->guild;
	$author_guild_id = $guild->id;

	//Load config variables for the guild
	$guild_folder = "\\guilds\\$author_guild_id"; //if($GLOBALS['debug_echo']) echo "guild_folder: $guild_folder" . PHP_EOL;
	$guild_owner_id = $author_channel->guild->owner_id;
	//Create a folder for the guild if it doesn't exist already
	if (!CheckDir($guild_folder)) {
		if (!CheckFile($guild_folder, "guild_owner_id.php")) {
			VarSave($guild_folder, "guild_owner_id.php", $guild_owner_id);
		}
	}

	//Load config variables for the guild
	$guild_config_path = getcwd() . "\\$guild_folder\\guild_config.php";														//if($GLOBALS['debug_echo']) echo "guild_config_path: " . $guild_config_path . PHP_EOL;
	if (!CheckFile($guild_folder, "guild_config.php")) {
		$file = 'guild_config_template.php';
		if (!copy(getcwd() . '/vendor/vzgcoders/palace/' . $file, $guild_config_path)) {
			$message_new->reply("Failed to create guild_config file! Please contact <@116927250145869826> for assistance.");
		} else {
			$author_channel->sendMessage("<@$guild_owner_id>, I'm here! Please ;setup the bot." . PHP_EOL . "While interacting with this bot, any conversations made through direct mention of the bot name are stored anonymously in a secure database. Avatars, IDs, Names, or any other unique user identifier is not stored with these messages. Through continuing to use this bot, you agree to allow it to track user information to support its functions and for debugging purposes. Your message data will never be used for anything more. If you wish to have any associated information removed, please contact Valithor#5937.");
			//$author_channel->sendMessage("(Maintenance is currently ongoing and many commands are currently not working. We are aware of the issue and working on a fix.)");
		}
	}
	include "$guild_config_path";


	if($modlog_channel_id) $modlog_channel	= $guild->channels->get('id', $modlog_channel_id);

	$author_username 			= $author_user->username; 											//if($GLOBALS['debug_echo']) echo "author_username: " . $author_username . PHP_EOL;
	$author_discriminator 		= $author_user->discriminator;										//if($GLOBALS['debug_echo']) echo "author_discriminator: " . $author_discriminator . PHP_EOL;
	$author_id 					= $author_user->id;													//if($GLOBALS['debug_echo']) echo "author_id: " . $author_id . PHP_EOL;
	$author_avatar 				= $author_user->avatar;												//if($GLOBALS['debug_echo']) echo "author_avatar: " . $author_avatar . PHP_EOL;
	$author_check 				= "$author_username#$author_discriminator"; 						//if($GLOBALS['debug_echo']) echo "author_check: " . $author_check . PHP_EOL;

	$changes = "";
	if ($message_content_new != $message_content_old) {
		//Build the string for the reply
		$changes .= "[Link](https://discord.com/channels/$author_guild_id/$author_channel_id/$message_id_new)\n";
		$changes .= "**Channel:** <#$author_channel_id>\n";
		$changes .= "**Message ID:** $message_id_new\n";
		
		$changes .= '**Before:** ```⠀' . str_replace('```', '\`\`\`', $message_content_old) . "\n```\n";
		$changes .= '**After:**```⠀' . str_replace('```', '\`\`\`', $message_content_new) . "\n```\n";
	}

	if ($modlog_channel) {
		if ($changes) {
			if($GLOBALS['debug_echo']) echo '[CHANGES]' . PHP_EOL;
			if (strlen($changes) <= 1024) {
				$embed = new \Discord\Parts\Embed\Embed($discord);
				$embed
					->setColor(0xa7c5fd)
					->addFieldValues("Message Update", "$changes")
					->setAuthor("$author_check ($author_id)", "$author_avatar");
					if ($author_guild_id != '115233111977099271') $embed->setFooter("Palace Bot by Valithor#5947");
					$embed->setTimestamp()
					->setURL("");
				return $modlog_channel->sendEmbed($embed);
			} elseif (strlen($changes) <= 2000) //Send changes as text
				return $modlog_channel->sendMessage($changes);
			else { //Send changes as a file if the changes are too many
				$changes = "[Link](https://discord.com/channels/$author_guild_id/$author_channel_id/$message_id_new)\n";
				$changes .= "**Channel:** <#$author_channel_id>\n";
				$changes .= "**Message ID:** $message_id_new\n";
			
				$changes_file = "**Before:** ```⠀$message_content_old\n```\n";
				$changes_file = $changes_file . "**After:**```⠀$message_content_new\n```\n";

				$embed = new \Discord\Parts\Embed\Embed($discord);
				$embed
				->setColor(0xa7c5fd)																	// Set a color (the thing on the left side)
				->addFieldValues("Message Update", "$changes")												// New line after this
				->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
				->setAuthor("$author_check ($author_id)", "$author_avatar");  							// Set an author with icon
				if ($author_guild_id != '115233111977099271') $embed->setFooter("Palace Bot by Valithor#5947");                             					// Set a footer without icon
				$embed->setURL("");                             												// Set the URL
			
				$builder = Discord\Builders\MessageBuilder::new();
                $builder
                    ->setContent('A message was updated but it was too long to log within an embed. Please see the attached file.')
                    ->addEmbed($embed)
                    ->addFileFromContent('changes.txt', $changes_file);
                return $modlog_channel->sendMessage($builder)->done(
					null,
					function ($error) {
						if($GLOBALS['debug_echo']) {
                            var_dump($error);
                            echo PHP_EOL;
                        }
					}
                );
                
                /*return $modlog_channel->sendMessage("A message was updated but it was too long to log within an embed. Please see the attached file.", false, array('embeds' => $embed, 'files' => [['name' => "changes.txt", 'data' => $changes_file]]))->done(
					null,
					function ($error) {
						if($GLOBALS['debug_echo']) {
                            var_dump($error);
                            echo PHP_EOL;
                        }
					}
				);*/
			}
		} else { //No info we want to check was changed
			return;
		}
	}
}