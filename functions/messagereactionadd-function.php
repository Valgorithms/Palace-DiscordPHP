<?php 
function messageReactionAdd($reaction, $discord) {
	$message = $message ?? $reaction->message;
	$message_content = $message->content;															//$message->channel->sendMessage($message_content);

	//Load guild info
	$guild	= $reaction->guild;
	$author_guild_id = $reaction->guild_id; //if($GLOBALS['debug_echo']) echo "author_guild_id: $author_guild_id" . PHP_EOL;
	$author_guild = $discord->guilds->get('id', $author_guild_id);

	if (is_object($message->author) && get_class($message->author) == "Discord\Parts\User\Member") { //Load author info
		$author_user = $message->author->user;
		$author_member = $message->author;
	} else $author_user = $author;
	$author_channel = $message->channel;
	$author_channel_id	= $author_channel->id; 														//if($GLOBALS['debug_echo']) echo "author_channel_id: " . $author_channel_id . PHP_EOL;

	/*Disabling this so that the bot will automatically create the roles the first time they are added. They can be manually removed later.
	if ("{$discord->id}" == $reaction->user->id)
		return; //Don't process reactions made by this bot
	*/

	$is_dm = false;
	if (is_object($message->author) && get_class($message->author) == "Discord\Parts\User\User") { //True if direct message
		if($GLOBALS['debug_echo']) echo '[MESSAGE REACT DM]' . PHP_EOL;
		$is_dm = true;
		return; //Don't try and process direct messages
	}

	$author_username 			= $author_user->username; 											//if($GLOBALS['debug_echo']) echo "author_username: " . $author_username . PHP_EOL;
	$author_discriminator 		= $author_user->discriminator;										//if($GLOBALS['debug_echo']) echo "author_discriminator: " . $author_discriminator . PHP_EOL;
	$author_id 					= $author_user->id;													//if($GLOBALS['debug_echo']) echo "author_id: " . $author_id . PHP_EOL;
	$author_avatar 				= $author_user->avatar;												//if($GLOBALS['debug_echo']) echo "author_avatar: " . $author_avatar . PHP_EOL;
	$author_check 				= "$author_username#$author_discriminator"; 						//if($GLOBALS['debug_echo']) echo "author_check: " . $author_check . PHP_EOL;
	$author_folder				= $author_guild_id."\\".$author_id;

	//var_dump($reaction);
	$respondent_user = $reaction->user;
	//Load respondent info
	$respondent_username 		= $respondent_user->username; 										//if($GLOBALS['debug_echo']) echo "author_username: " . $author_username . PHP_EOL;
	$respondent_discriminator 	= $respondent_user->discriminator;									//if($GLOBALS['debug_echo']) echo "author_discriminator: " . $author_discriminator . PHP_EOL;
	$respondent_id 				= $respondent_user->id;												//if($GLOBALS['debug_echo']) echo "author_id: " . $author_id . PHP_EOL;
	$respondent_avatar 			= $respondent_user->avatar;											//if($GLOBALS['debug_echo']) echo "author_avatar: " . $author_avatar . PHP_EOL;
	$respondent_check 			= "$respondent_username#$respondent_discriminator"; 				//if($GLOBALS['debug_echo']) echo "respondent_check: " . $respondent_check . PHP_EOL;
	$respondent_member			= $reaction->member ?? $author_guild->members->offsetGet($respondent_id);

	/*
	//
	//
	//
	*/

	if($GLOBALS['debug_echo']) echo "[messageReactionAdd]" . PHP_EOL;
	$message_content_lower = strtolower($message_content);

	//Create a folder for the guild if it doesn't exist already
	$guild_folder = "\\guilds\\$author_guild_id";
	CheckDir($guild_folder);
	//Load config variables for the guild
	$guild_config_path = getcwd() . "$guild_folder\\guild_config.php"; //if($GLOBALS['debug_echo']) echo "guild_config_path: " . $guild_config_path . PHP_EOL;
	include "$guild_config_path";

	//Role picker stuff
	$message_id	= $message->id;														//if($GLOBALS['debug_echo']) echo "message_id: " . $message_id . PHP_EOL;
	global $species, $species2, $species3, $nsfwsubroles;
	//Attempt to load guild-specified declarations and override with a globally declared default if none exists
	$guild_game_roles_path = getcwd() . "$guild_folder\\game_roles.php";
	if (!include "$guild_game_roles_path")
		global $gameroles, $gameroles_message_text;
	$guild_gender_roles_path = getcwd() . "$guild_folder\\gender.php";
	if (!include "$guild_gender_roles_path")
		global $gender, $gender_message_text;
	$guild_pronouns_roles_path = getcwd() . "$guild_folder\\pronouns.php";
	if (!include "$guild_pronouns_roles_path")
		global $pronouns, $pronouns_message_text;
	$guild_sexualities_roles_path = getcwd() . "$guild_folder\\sexualities.php";
	if (!include "$guild_sexualities_roles_path")
		global $sexualities, $sexuality_message_text;
	$guild_nsfw_roles_path = getcwd() . "$guild_folder\\nsfw_roles.php";
	if (!include "$guild_nsfw_roles_path")
		global $nsfwroles, $nsfw_message_text;
	$guild_channel_roles_path = getcwd() . "$guild_folder\\channel_roles.php";
	if (!include "$guild_channel_roles_path")
		global $channelroles, $channelroles_message_text;
	$guild_custom_roles_path = getcwd() . "$guild_folder\\custom_roles.php";
	if (!include "$guild_custom_roles_path")
		global $customroles;
	
	//Color data
	$guild_species_color_path = getcwd() . "$guild_folder\\species_color.php";
	if (!include "$guild_species_color_path")
		$species_color = 15158332;
	$guild_species2_color_path = getcwd() . "$guild_folder\\species2_color.php";
	if (!include "$guild_species2_color_path")
		$species2_color = 15158332;
	$guild_species3_color_path = getcwd() . "$guild_folder\\species3_color.php";
	if (!include "$guild_species3_color_path")
		$species3_color = 15158332;
	$guild_game_color_path = getcwd() . "$guild_folder\\game_color.php";
	if (!include "$guild_game_color_path")
		$gameroles_color = 0x003ead;
	$guild_gender_color_path = getcwd() . "$guild_folder\\gender_color.php";
	if (!include "$guild_gender_color_path")
		$gender_color = 0x713678;
	$guild_pronouns_color_path = getcwd() . "$guild_folder\\pronouns_color.php";
	if (!include "$guild_pronouns_color_path")
		$pronouns_color = 0x9b59b6;
	$guild_sexualities_color_path = getcwd() . "$guild_folder\\sexualities_color.php";
	if (!include "$guild_sexualities_color_path")
		$sexualities_color = 0x992d22;
	$guild_nsfwroles_color_path = getcwd() . "$guild_folder\\nsfwroles_color.php";
	if (!include "$guild_nsfwroles_color_path")
		$nsfwroles_color = 0xff0000;
	$guild_nsfwsubroles_color_path = getcwd() . "$guild_folder\\nsfwsubroles_color.php";
	if (!include "$guild_nsfwsubroles_color_path")
		$nsfwsubroles_color = 0xff0000;
	$guild_channel_color_path = getcwd() . "$guild_folder\\channel_roles_color.php";
	if (!include "$guild_channel_color_path")
		$channel_roles_color = 0x1abc9c;
	$guild_custom_color_path = getcwd() . "$guild_folder\\custom_roles_color.php";
	if (!include "$guild_custom_color_path")
		$custom_roles_color = 0x1abc9c;

	//Load emoji info
	//guild, user
	//animated, managed, requireColons
	//createdTimestamp, createdAt
	$emoji = $reaction->emoji;
	$emoji_id = $emoji->id;																			//if($GLOBALS['debug_echo']) echo "emoji_id: " . $emoji_id . PHP_EOL; //Unicode if null

	$unicode = false;
	if (is_null($emoji_id)) $unicode = true;														//if($GLOBALS['debug_echo']) echo "unicode: " . $unicode . PHP_EOL;
	$emoji_name = $emoji->name;																		//if($GLOBALS['debug_echo']) echo "emoji_name: " . $emoji_name . PHP_EOL;
	$emoji_identifier = $emoji->id;																	//if($GLOBALS['debug_echo']) echo "emoji_identifier: " . $emoji_identifier . PHP_EOL;

	if ($unicode) $response = "$emoji_name";
	else $response = "<:$emoji_identifier>";
	//$message->reply("Response: $response");


	//if($GLOBALS['debug_echo']) echo "$author_check's message was reacted to by $respondent_check" . PHP_EOL;

	//Check rolepicker option
	global $rolepicker_option, $species_option, $sexuality_option, $gender_option, $gameroles_option, $custom_option;
	if ($rolepicker_id) {
		if (!CheckFile($guild_folder, "rolepicker_option.php")) $rp0 = $rolepicker_option; //Species role picker
		else $rp0 = VarLoad($guild_folder, "rolepicker_option.php");
	} else $rp0 = false; //if($GLOBALS['debug_echo']) echo "rp0: $rp0" . PHP_EOL;



	//if($GLOBALS['debug_echo']) echo $author_id.':'.$rolepicker_id.PHP_EOL;

	if ($rp0) {
		if ($author_id == $rolepicker_id) {
			//Check options
			if ($gameroles_message_id) {
				if (!CheckFile($guild_folder, "gameroles_option.php")) $gamerole= $gameroles_option; //Species role picker
				else $gamerole = VarLoad($guild_folder, "gameroles_option.php");
			} else $gamerole = false;
			if ($species_message_id) {
				if (!CheckFile($guild_folder, "species_option.php")) $rp1	= $species_option; //Species role picker
				else $rp1 = VarLoad($guild_folder, "species_option.php");
			} else $rp1 = false;
			if ($sexuality_message_id) {
				if (!CheckFile($guild_folder, "sexuality_option.php")) $rp2 = $sexuality_option; //Sexuality role picker
				else $rp2 = VarLoad($guild_folder, "sexuality_option.php");
			} else $rp2 = false; //if($GLOBALS['debug_echo']) echo "rp2: $rp2" . PHP_EOL;
			if ($gender_message_id) {
				if (!CheckFile($guild_folder, "gender_option.php")) $rp3 = $gender_option; //Gender role picker
				else $rp3 = VarLoad($guild_folder, "gender_option.php");
			} else $rp3 = false;
			if ($pronouns_message_id) {
				if (!CheckFile($guild_folder, "pronouns_option.php")) $rp5 = $pronouns_option; //Pronouns role picker
				else $rp5 = VarLoad($guild_folder, "pronouns_option.php");
			} else $rp5 = false;
			if ($channelroles_message_id) {
				if (!CheckFile($guild_folder, "channel_option.php")) $channeloption	= $channel_option; //Channel role picker
				else $channeloption = VarLoad($guild_folder, "channel_option.php");
			} else $channeloption = false;
			if ($customroles_message_id) {
				if (!CheckFile($guild_folder, "custom_option.php")) $rp4 = $custom_option; //Custom role picker
				else $rp4 = VarLoad($guild_folder, "custom_option.php");
			} else $rp4 = false; //if($GLOBALS['debug_echo']) echo "rp4: $rp4" . PHP_EOL;
			if ($nsfw_message_id) {
				if (!CheckFile($guild_folder, "nsfw_option.php")) $nsfw	= $nsfw_option; //Custom role picker
				else $nsfw = VarLoad($guild_folder, "nsfw_option.php");
			} else $nsfw = false; //if($GLOBALS['debug_echo']) echo "nsfw: $nsfw" . PHP_EOL;
			
			//Load guild roles info
			$guild_roles											= $guild->roles;
			$guild_roles_names 										= array();
			$guild_roles_ids 										= array();
			foreach ($guild_roles as $role) {
				$guild_roles_names[] 								= strtolower("{$role->name}"); 				//if($GLOBALS['debug_echo']) echo "role name: " . $role->name . PHP_EOL; //var_dump($role->name);
				$guild_roles_ids[]									= $role->id; 								//if($GLOBALS['debug_echo']) echo "role[$x] id: " . PHP_EOL; //var_dump($role->id);
				$guild_roles_role["{$role->id}"]					= $role;
			}
			//Load respondent roles info
			$respondent_member_role_collection 								= $respondent_member->roles;
			$respondent_member_roles_names 									= array();
			$respondent_member_roles_ids 									= array();
			foreach ($respondent_member_role_collection as $role) {
				$respondent_member_roles_names[] 							= strtolower("{$role->name}"); 		//if($GLOBALS['debug_echo']) echo "role[$x] name: " . PHP_EOL; //var_dump($role->name);
				$respondent_member_roles_ids[]  = $role->id; 													//if($GLOBALS['debug_echo']) echo "role[$x] id: " . PHP_EOL; //var_dump($role->id);
				$respondent_member_roles_role["{$role->id}"] = $role;
			}
			
			$enabled_options = [];
			$valid_message_ids = [];
			if ($rp1) {
				if ($species_message_id) $valid_message_ids["$species_message_id"] = ['species', $species_color];
				if ($species2_message_id) $valid_message_ids["$species2_message_id"] = ['species2', $species2_color];
				if ($species3_message_id) $valid_message_ids["$species3_message_id"] = ['species3', $species3_color];
			}
			if ($rp2 && $sexuality_message_id) 	$valid_message_ids["$sexuality_message_id"] = ['sexualities', $sexualities_color];
			if ($rp3 && $gender_message_id) $valid_message_ids["$gender_message_id"] = ['gender', $gender_color];
			if ($rp4 && $customroles_message_id) $valid_message_ids["$customroles_message_id"] = ['customroles', $custom_roles_color];
			if ($rp5 && $pronouns_message_id) $valid_message_ids["$pronouns_message_id"] = ['pronouns', $pronouns_color];
			if ($channeloption && $channelroles_message_id) $valid_message_ids["$channelroles_message_id"] = ['channelroles', $channel_roles_color];
			if ($gamerole && $gameroles_message_id) $valid_message_ids["$gameroles_message_id"] = ['gameroles', $gameroles_color];
			if ($nsfw) {
				if ($nsfw_message_id) $valid_message_ids["$nsfw_message_id"] = ['nsfwroles', $nsfwroles_color];
				if ($nsfwsubrole_message_id) $valid_message_ids["$nsfwsubrole_message_id"] = ['nsfwsubroles', $nsfwsubroles_color];
			}
			
			if ($valid_message_ids["$message_id"]) { //The message being reacted to is designated for the rolepicker
				$category = $valid_message_ids["$message_id"][0];
				$new_role = null;
				foreach ($$category as $var_name => $value) { //Access the variable with name matching the string associated with the message id
					if ($value == $emoji_name) {
						$select_name = $var_name;
						if (!in_array(strtolower($select_name), $guild_roles_names)) {//Check to make sure the role exists in the guild
							$new_role = $discord->factory(
							Discord\Parts\Guild\Role::class,
								[
									'name' => ucfirst($select_name),
									'permissions' => 0,
									'color' => $valid_message_ids["$message_id"][1],
									'hoist' => false,
									'mentionable' => false
								]
							);
						}
						if ($new_role) { // Create the role if it does not already exist in the guild
							$author_guild->createRole($new_role->getUpdatableAttributes())->done(function ($role) use ($select_name) : void {
								/*if($GLOBALS['debug_echo'])*/ echo "[ROLE $select_name CREATED]" . PHP_EOL;
							}, static function ($error) {
								/*if($GLOBALS['debug_echo'])*/ echo "[ROLE $select_name ERROR] " . $e->getMessage() . PHP_EOL;
							});
						}
					}
				}
				if (! $new_role && $select_name) //Add the role if a reaction role was found and at least one role was not just created, because we should wait before we try to add it
				if (! in_array(strtolower($select_name), $respondent_member_roles_names)) { //Check if the member has a role of the same name
					//Add the role
					//if($GLOBALS['debug_echo']) echo "Respondent does not already have the role" . PHP_EOL;
					if (in_array(strtolower($select_name), $guild_roles_names)) {//Check to make sure the role exists in the guild
						//Add the role
						$role_index = array_search(strtolower($select_name), $guild_roles_names);
						$target_role_id = $guild_roles_ids[$role_index];
						if ($respondent_member->id != $discord->id) {
							$respondent_member->addRole($guild_roles_role[$target_role_id]); // $target_role_id);
							//Post a message tagging the user, then delete it after a few seconds
							$embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
							$embed
								->setColor(0xa7c5fd)
								->setDescription("<@$respondent_id>\n**Added role**\n<@&$target_role_id>")
								->setTimestamp()
								->setFooter("Palace Bot by Valithor#5947")
								->setURL("");
							$author_channel->sendEmbed($embed)->done(
								function ($new_message) use ($discord) {
									$discord->getLoop()->addTimer(10, function () use ($new_message) {
										return $new_message->delete();
									});
								},
								function ($error) {
									//
								}
							);
						}
						if($GLOBALS['debug_echo']) echo "Role added: $select_name" . PHP_EOL;
					} else if($GLOBALS['debug_echo']) echo "Guild does not have this role" . PHP_EOL;
				}
			}
		}
	}
};