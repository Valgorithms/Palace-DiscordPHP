<?php
function messageReactionRemove($reaction, $discord) {
	if ($reaction->user_id == $discord->user->id) { //Don't process reactions this bot makes
		if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "[MESSAGE REACTION REMOVED - SELF]" . PHP_EOL;
		return true;
	}
	//if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "messageReactionRemove" . PHP_EOL;
	global $bot_id;
	$respondent_user = $reaction->user;

	//		Load message info
	$message					= $reaction->message;
	$message_content			= $message->content;
	$message_id                 = $message->id;
	if (($message_content == null) || ($message_content == "")) {
		return true;
	} //Don't process blank messages, bots, webhooks, or rich embeds
	$message_content_lower = strtolower($message_content);

	//		Load author info
	$author_user				= $message->author->user; //User object
	$author_channel 			= $message->channel;
	$author_channel_id			= $author_channel->id; 												//if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "author_channel_id: " . $author_channel_id . PHP_EOL;
	$is_dm = false;
	if (is_null($message->channel->guild_id) && ! ($author_member = $message->member)) {
		$is_dm = true; //True if direct message
		return;
	}

	$author_username 			= $author_user->username; 											//if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "author_username: " . $author_username . PHP_EOL;
	$author_discriminator 		= $author_user->discriminator;										//if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "author_discriminator: " . $author_discriminator . PHP_EOL;
	$author_id 					= $author_user->id;													//if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "author_id: " . $author_id . PHP_EOL;
	$author_avatar 				= $author_user->avatar;										//if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "author_avatar: " . $author_avatar . PHP_EOL;
	$author_check 				= "$author_username#$author_discriminator"; 						//if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "author_check: " . $author_check . PHP_EOL;

	//Load respondent info
	$respondent_username 		= $respondent_user->username; 										//if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "author_username: " . $author_username . PHP_EOL;
	$respondent_discriminator 	= $respondent_user->discriminator;									//if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "author_discriminator: " . $author_discriminator . PHP_EOL;
	$respondent_id 				= $respondent_user->id;												//if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "author_id: " . $author_id . PHP_EOL;
	$respondent_avatar 			= $respondent_user->avatar;									//if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "author_avatar: " . $author_avatar . PHP_EOL;
	$respondent_check 			= "$respondent_username#$respondent_discriminator"; 				//if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "author_check: " . $author_check . PHP_EOL;
			
	//Load emoji info
	//guild, user
	//animated, managed, requireColons
	//createdTimestamp, createdAt
	$emoji						= $reaction->emoji;
	$emoji_id					= $emoji->id;			//if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "emoji_id: " . $emoji_id . PHP_EOL; //Unicode if null

	$unicode					= false;
	if ($emoji_id === null) {
		$unicode 	= true;
	}					//if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "unicode: " . $unicode . PHP_EOL;
	$emoji_name					= $emoji->name;			//if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "emoji_name: " . $emoji_name . PHP_EOL;
	$emoji_identifier			= $emoji->identifier;	//if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "emoji_identifier: " . $emoji_identifier . PHP_EOL;

	if ($unicode) {
		$response = "$emoji_name";
	} else {
		$response = "<:$emoji_identifier>";
	}

	//Do things here
	if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "$respondent_check removed their reaction from $author_check's message" . PHP_EOL;
	if ($author_id == $bot_id) { //Message reacted to belongs to this bot
		/*
		*********************
		*********************
		Remove reaction trigger
		*********************
		*********************
		*/
		switch ($message_id) {
			case 0:
				if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "" . PHP_EOL;
				break;
			case 1:
				if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "" . PHP_EOL;
				break;
			case 2:
				if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "" . PHP_EOL;
				break;
		}
	} else {
		//Do things here
	}
}