<?php
function messageDelete($message, $discord, $browser) {
    $message_id = $message->id;
    $author_user = $message->author;
    $guild_id = $message->guild_id
    if (! $author_guild = $message->guild ) $author_guild = $discord->guilds->offsetGet($guild_id);
    if (! $author_member = $message->member) $author_member = $author_guild->members->offsetGet($author_user->id);
    $author_channel_id = $channel_id;
    
    //Browser function used to retrieve attachments from deleted messages
    $browser_get = function ($browser, string $url, array $headers = [], $curl = false)
    {
        if ( ! $curl && $browser instanceof \React\Http\Browser) {
            return $browser->get($url, $headers);
        } else {
            $ch = curl_init(); //create curl resource
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //return the transfer as a string
            $result = curl_exec($ch);
            return $data; //string
        }
    };
    //Recursive processing of deleted embeds and attachments
    $func = function ($channel, $data_string = '', $message_embeds = [], $message_attachments = [], $x = 1) use (&$func, $browser_get, $browser, $message_id, $author_user, $author_channel_id) {
        if (count($message_embeds) != 0) {
            $deleted_embed = array_shift($message_embeds);
            $deleted_embed->setTimestamp();
            $builder2 = Discord\Builders\MessageBuilder::new();
            $builder2->setContent("Deleted embed $x");
            $builder2->addEmbed($deleted_embed);
            $channel->sendMessage($builder2);
            return $func($channel, $data_string, $message_embeds, $message_attachments, ++$x);
        }
        if (count($message_attachments) != 0) {
            $builder2 = Discord\Builders\MessageBuilder::new();
            $deleted_attachment = array_shift($message_attachments);
            $response = $browser_get($browser, $deleted_attachment->url, [], true); //using cURL=true for testing
            if ($response) {
                echo '[RESPONSE]' . PHP_EOL;
                var_dump($response);
                $builder2->addFileFromContent($deleted_attachment->filename, $response);
                $builder2->setContent("Deleted attachment $x");
                $builder2->addFileFromContent($deleted_attachment->filename, file_get_contents($deleted_attachment->url));
                $channel->sendMessage($builder2);
            } else {
                echo '[NO RESPONSE]' . PHP_EOL;
                //Resolve promise interface and continue inside of ->done
                /*
                $builder2->addFileFromContent($deleted_attachment->filename, $response);
                $builder2->setContent("Deleted attachment $x");
                $builder2->addFileFromContent($deleted_attachment->filename, file_get_contents($deleted_attachment->url));
                $channel->sendMessage($builder2);
                $func($channel, $data_string, $message_embeds, $message_attachments, ++$x);
                */
            }
            return $func($channel, $data_string, $message_embeds, $message_attachments, ++$x);
        }
    };
    
	//id, author, channel, guild, member
	//createdAt, editedAt, createdTimestamp, editedTimestamp, content, cleanContent, attachments, embeds, mentions, pinned, type, reactions, webhookID
	$message_content = $message->content;
	$channel_id = $message->channel_id;
	$message_embeds	= $message->embeds; //collection of embeds, needs a foreach method
    $message_attachments = $message->attachments;
	if (is_null($message_content) && is_null($message_embeds)) {
		if($GLOBALS['debug_echo']) echo '[messageDelete No-Cache(?)] ' . $guild_id . '/' . $channel_id . PHP_EOL;
		$content = "Message with ID $message_id sent by $author_user deleted from <#$channel_id>";
		
		$guild = $discord->guilds->get('id', $guild_id);
		$guild_folder = "\\guilds\\$guild_id";
		$guild_config_path = getcwd() . "$guild_folder\\guild_config.php"; //if($GLOBALS['debug_echo']) echo "guild_config_path: " . $guild_config_path . PHP_EOL;
		include "$guild_config_path";
		
		if ($modlog_channel_id && ($modlog_channel = $guild->channels->offsetGet($modlog_channel_id))) $modlog_channel->sendMessage($content);
		return;
	} //Don't process blank messages, bots, or webhooks
	if($GLOBALS['debug_echo']) echo '[messageDelete] ' . $message->guild_id . '/' . $channel_id . PHP_EOL;
    $message_content = str_replace('```', '\`\`\`', $message_content);
    $message_content_lower = strtolower($message_content);

	$is_dm = false;
	
	if (is_null($message->guild_id) && !($author_member = $message->member)) { //True if direct message
		$is_dm = true;
		if($GLOBALS['debug_echo']) echo "[DM MESSAGE DELETED]" . PHP_EOL;
		return; //Don't process DMs
	}
	if ("{$discord->id}" == "{$author_user->id}") {
		if($GLOBALS['debug_echo']) echo "[SELF MESSAGE DELETED]" . PHP_EOL;
		return; //Don't log messages made by this bot
	}
	$author_id = $author_user->id;
	$author_avatar = $author_user->avatar;
    $author_username = $author_user->username;
    $author_discriminator = $author_user->discriminator;
	$author_check = "$author_username#$author_discriminator";

	//Load guild info
	if (!$guild = $discord->guilds->offsetGet($guild_id)) return; //Probably a DM, we don't care for it

	//Load config variables for the guild
	$guild_folder = "\\guilds\\$guild_id";
	$guild_config_path = getcwd() . "$guild_folder\\guild_config.php";
	include "$guild_config_path";

	if ($author_channel_id == $modlog_channel_id) return; //Don't log deletion of messages in the log channel
	$modlog_channel = $guild->channels->get('id', $modlog_channel_id);

	//Build the embed stuff
	$log_message = "Message with ID $message_id sent by $author_user deleted from <#$channel_id>\n**Content:** $message_content" . PHP_EOL;
	if (strlen($log_message) > 2048) {
		$log_message = "Message with ID $message_id sent by $author_user deleted from <#$channel_id>";
		$data_string = $message_content;
	}
	//		Build the embed
	$embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
	$embed
	//	->setTitle("$user_check")																// Set a title
		->setColor(0xa7c5fd)																	// Set a color (the thing on the left side)
	//	->setDescription("$author_guild_name")													// Set a description (below title, above fields)
		->setDescription("$log_message")														// Set a description (below title, above fields)
		//X days ago
		->setAuthor("$author_check ($author_id)", "$author_avatar")  							// Set an author with icon
	//	->addFieldValues("Roles", "$author_role_name_queue_full")								// New line after this
		
		->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
	//	->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             	// Set an image (below everything except footer)
		->setTimestamp();                                                                   	// Set a timestamp (gets shown next to footer)
		
		if ($guild->id != '115233111977099271') $embed->setFooter("Palace Bot by Valithor#5947");                             					// Set a footer without icon
		$embed->setURL("");
	//	Send the message
	//	We do not need another promise here, so we call done, because we want to consume the promise
	if ($modlog_channel) {
		/*
		//old method (Yasmin)
		if ($data_string) { //Embed the changes as a text file
			$modlog_channel->sendMessage('', array('embeds' => $embed, 'files' => [['name' => "message.txt", 'data' => $data_string]]))->done(null, function ($error) {
				if($GLOBALS['debug_echo']) echo $error.PHP_EOL; //if($GLOBALS['debug_echo']) echo any errors
			});
		}else{
			$modlog_channel->sendEmbed($embed);
		}
		*/
		$message_array['embeds'] = $embed;
		//$content = $message->content ?? '';
		$content = '';
		if (!$message_embeds && !$data_string) { //if($GLOBALS['debug_echo']) echo "!message_embeds && !data_string" . PHP_EOL;
			return $modlog_channel->sendMessage($builder)->done(function () use ($func, $modlog_channel, $message_embeds, $message_attachments) {
                $func($modlog_channel, '', $message_embeds, $message_attachments);
            });
		} elseif (!$message_embeds && $data_string) { //if($GLOBALS['debug_echo']) echo "!message_embeds && data_string" . PHP_EOL;
			//Message overflow
            $builder = Discord\Builders\MessageBuilder::new();
            $builder->addEmbed($embed);
            $builder->addFileFromContent('message.txt', $data_string);
            return $modlog_channel->sendMessage($builder)->done(function () use ($func, $modlog_channel, $message_embeds, $message_attachments) {
                $func($modlog_channel, '', $message_embeds, $message_attachments);
            });
		} elseif ($message_embeds && !$data_string) { //if($GLOBALS['debug_echo']) echo "message_embeds && !data_string" . PHP_EOL;
			//No message overflow, process message_embeds onto the first message
			$builder = Discord\Builders\MessageBuilder::new();
            $builder->addEmbed($embed);
            return $modlog_channel->sendMessage($builder)->done(function () use ($func, $modlog_channel, $message_embeds, $message_attachments) {
                $func($modlog_channel, '', $message_embeds, $message_attachments);
            });
                /*$x=1;
				foreach ($message_embeds as $deleted_embed) {
                    $deleted_embed->setTimestamp();
                    $builder2 = Discord\Builders\MessageBuilder::new();
                    $builder2->addEmbed($deleted_embed);
					$modlog_channel->sendMessage($builder2);
				}
                */
            
            //
		} elseif ($message_embeds && $data_string) { //if($GLOBALS['debug_echo']) echo "message_embeds && data_string" . PHP_EOL;
			//Message overflow as an attachment, do not process message_mebeds until after the first message
            $builder = Discord\Builders\MessageBuilder::new();
            $builder->setContent("Long message with ID $message_id sent by $author_user was deleted from <#$author_channel_id>");
            if($embed) $builder->addEmbed($embed);
            $builder->addFileFromContent('message.txt', $data_string);
            return $modlog_channel->sendMessage($builder)->done(function () use ($func, $modlog_channel, $data_string, $message_embeds, $message_attachments) {
                $func($modlog_channel, $data_string, $message_embeds, $message_attachments);
            });
		}
	}
}