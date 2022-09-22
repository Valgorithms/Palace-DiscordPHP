<?php
function message($message, $discord, $loop, $token, $stats, $twitch, $browser) {
	if (is_null($message) || empty($message)) return; //An invalid message object was passed
	if (is_null($message->content)) return; //Don't process messages without content
	if ($message->webhook_id || $message->author->webhook) return; //Don't process webhooks
	if ($message->author->bot) return; //Don't process messages sent by bots

	$message_content = $message->content;
	if (!$message_content) return;
	$message_id = $message->id;
	$message_content_lower = mb_strtolower(trim($message_content));

	/*
	*********************
	*********************
	Required includes
	*********************
	*********************
	*/

	include_once "custom_functions.php";
	include "constants.php"; //Redeclare $now every time

	/*
	*********************
	*********************
	// Load author data from message
	*********************
	*********************
	*/
	$author_user = $message->author; //This will need to be updated in a future release of DiscordPHP
	if ($author_member = $message->member) $author_perms = $author_member->getPermissions($message->channel); //Populate permissions granted by roles

	$author_channel 												= $message->channel;
	$author_channel_id												= $author_channel->id; 											//if($GLOBALS['debug_echo']) echo "author_channel_id: " . $author_channel_id . PHP_EOL;
	$is_dm															= false; //if($GLOBALS['debug_echo']) echo "author_channel_class: " . $author_channel_class . PHP_EOL;

	//if($GLOBALS['debug_echo']) echo "[CLASS] " . get_class($message->author) . PHP_EOL;
	if ($message->channel->type == 1 || (is_null($message->guild_id) && is_null($author_member))) $is_dm = true; //True if direct message
	$author_username 												= $author_user->username; 										//if($GLOBALS['debug_echo']) echo "author_username: " . $author_username . PHP_EOL;
	$author_discriminator 											= $author_user->discriminator;									//if($GLOBALS['debug_echo']) echo "author_discriminator: " . $author_discriminator . PHP_EOL;
	$author_id 														= $author_user->id;												//if($GLOBALS['debug_echo']) echo "author_id: " . $author_id . PHP_EOL;
	$author_avatar 													= $author_user->avatar;									//if($GLOBALS['debug_echo']) echo "author_avatar: " . $author_avatar . PHP_EOL;
	$author_check 													= "$author_username#$author_discriminator"; 					//if($GLOBALS['debug_echo']) echo "author_check: " . $author_check . PHP_EOL;

	if ($message_content_lower == ';invite') {
		if($GLOBALS['debug_echo']) echo '[INVITE]' . PHP_EOL;
		//$author_channel->sendMessage($discord->application->getInviteURLAttribute('[permission string]'));
		//$author_channel->sendMessage($discord->application->getInviteURLAttribute('8&redirect_uri=https%3A%2F%2Fdiscord.com%2Foauth2%2Fauthorize%3Fclient_id%3D586694030553776242%26permissions%3D8%26scope%3Dbot&response_type=code&scope=identify%20email%20connections%20guilds.join%20gdm.join%20guilds%20applications.builds.upload%20messages.read%20bot%20webhook.incoming%20rpc.notifications.read%20rpc%20applications.builds.read%20applications.store.update%20applications.entitlements%20activities.read%20activities.write%20relationships.read'));
		$author_channel->sendMessage($discord->application->getInviteURLAttribute('8'));
		/*
		$author_user->getPrivateChannel()->done(function($author_dmchannel) use ($discord) {
			$discord->generateOAuthInvite(8)->done(function($BOTINVITELINK) use ($author_dmchannel) {
				$author_dmchannel->sendMessage($BOTINVITELINK);
			});
		});
		*/
		return;
	}
	/*
	*********************
	*********************
	Get the guild and guildmember collections for the author
	*********************
	*********************
	*/

	if (!$is_dm) { //Guild message
		$author_guild 												= $author_channel->guild;
		$author_guild_id 											= $author_guild->id; 											//if($GLOBALS['debug_echo']) echo "discord_guild_id: " . $author_guild_id . PHP_EOL;
		$author_guild_name											= $author_guild->name;
		$guild_owner_id												= $author_guild->owner_id;
		if(is_null($author_member)) $author_member = $author_guild->members->get('id', $author_id);
		//Leave the guild if the owner is blacklisted
		global $blacklisted_owners;
		if ($blacklisted_owners) {
			if (in_array($guild_owner_id, $blacklisted_owners)) {
				//$author_guild->leave($author_guild_id)->done(null, function ($error) {
				$discord->guilds->leave($author_guild);
			}
		}
		if (in_array($author_id, $blacklisted_owners)) return; //Ignore all commands from blacklisted guild owners
		//Leave the guild if blacklisted
		global $blacklisted_guilds;
		if ($blacklisted_guilds) {
			if (in_array($author_guild_id, $blacklisted_guilds)) {
				//$author_guild->leave($author_guild_id)->done(null, function ($error) {
				$discord->guilds->leave($author_guild)->done(null, function ($error) {
					if($GLOBALS['debug_echo']){
						echo "[ERROR] [BLACKLISTED GUILD] $author_guild_id:" . PHP_EOL;
						var_dump($error);
					}
				});
			}
		}
		//Leave the guild if not whitelisted
		global $whitelisted_guilds;
		if ($whitelisted_guilds) {
			if (!in_array($author_guild_id, $whitelisted_guilds)) {
				//$author_guild->leave()->done(null, function ($error) {
				$discord->guilds->leave($author_guild)->done(null, function ($error) {
					var_dump($error->getMessage());
				});
			}
		}
		
		$guild_folder = "\\guilds\\$author_guild_id"; //if($GLOBALS['debug_echo']) echo "guild_folder: $guild_folder" . PHP_EOL;
		//Create a folder for the guild if it doesn't exist already
		if (!CheckDir($guild_folder)) {
			if (!CheckFile($guild_folder, "guild_owner_id.php"))
				VarSave($guild_folder, "guild_owner_id.php", $guild_owner_id);
			elseif ( ($old_guild_owner_id = VarLoad($guild_folder, "guild_owner_id.php")) && ($old_guild_owner_id != $guild_owner_id) )
				VarSave($guild_folder, "guild_owner_id.php", $guild_owner_id);
		}
		if ($guild_owner_id == $author_id) $owner = true; //Enable usage of restricted commands
		else $owner = false;
		
		if ($role->name == "Server Booster") $booster = true; //Author boosted the server
		else $booster = false;
		
		//Load config variables for the guild
		$guild_config_path = getcwd() . "\\$guild_folder\\guild_config.php";														//if($GLOBALS['debug_echo']) echo "guild_config_path: " . $guild_config_path . PHP_EOL;
		if (!CheckFile($guild_folder, "guild_config.php")) {
			$file = 'guild_config_template.php';
			if (!copy(getcwd() . '/vendor/vzgcoders/palace/' . $file, $guild_config_path)) {
				$message->reply("Failed to create guild_config file! Please contact <@116927250145869826> for assistance.");
			} else {
				$author_channel->sendMessage("<@$guild_owner_id>, I'm here! Please ;setup the bot." . PHP_EOL . "While interacting with this bot, any conversations made through direct mention of the bot name are stored anonymously in a secure database. Avatars, IDs, Names, or any other unique user identifier is not stored with these messages. Through continuing to use this bot, you agree to allow it to track user information to support its functions and for debugging purposes. Your message data will never be used for anything more. If you wish to have any associated information removed, please contact Valithor#5937.");
				//$author_channel->sendMessage("(Maintenance is currently ongoing and many commands are currently not working. We are aware of the issue and working on a fix.)");
			}
		}
		
		include "$guild_config_path"; //Configurable channel IDs, role IDs, and message IDs used in the guild for special functions
		
		$author_guild_avatar = $author_guild->icon;
		$author_guild_roles = $author_guild->roles;
		if ($getverified_channel_id) $getverified_channel  = $author_guild->channels->get('id', $getverified_channel_id);
		if ($verifylog_channel_id) $verifylog_channel = $author_guild->channels->get('id', $verifylog_channel_id); //Modlog is used if this is not declared
		if ($watch_channel_id) $watch_channel  = $author_guild->channels->get('id', $watch_channel_id);
		if ($modlog_channel_id) $modlog_channel  = $author_guild->channels->get('id', $modlog_channel_id);
		if ($general_channel_id) $general_channel = $author_guild->channels->get('id', $general_channel_id);
		if ($rolepicker_channel_id) $rolepicker_channel = $author_guild->channels->get('id', $rolepicker_channel_id);
		if ($nsfw_rolepicker_channel_id) $nsfw_rolepicker_channel = $author_guild->channels->get('id', $nsfw_rolepicker_channel_id);
		if ($games_rolepicker_channel_id) $games_rolepicker_channel = $author_guild->channels->get('id', $games_rolepicker_channel_id);
		if ($games_channel_id) $games_channel = $author_guild->channels->get('id', $games_channel_id);
		if ($gameroles_message_id) $gameroles_channel = $author_guild->channels->get('id', $gameroles_message_id);
		if ($suggestion_pending_channel_id) $suggestion_pending_channel	= $author_guild->channels->get('id', strval($suggestion_pending_channel_id));
		if ($suggestion_approved_channel_id) $suggestion_approved_channel = $author_guild->channels->get('id', strval($suggestion_approved_channel_id));
		if ($tip_pending_channel_id) $tip_pending_channel = $author_guild->channels->get('id', strval($tip_pending_channel_id));
		if ($tip_approved_channel_id) $tip_approved_channel = $author_guild->channels->get('id', strval($tip_approved_channel_id));
	} else { //Direct message
		if ($author_id != $discord->id) { //Don't trigger on messages sent by this bot
			global $server_invite;
			//if($GLOBALS['debug_echo']) echo "[DM-EARLY BREAK]" . PHP_EOL;
			if($GLOBALS['debug_echo']) echo "[DM] $author_check: $message_content" . PHP_EOL;
			$dm_text = "Please use commands for this bot within a server unless otherwise prompted.";
			//$message->reply("$dm_text \n$server_invite");
			//$message->reply("$dm_text");
		}
		return;
	}

	/*
	*********************
	*********************
	Options
	*********************
	*********************
	*/
	if (!CheckFile($guild_folder, "command_symbol.php")) {
		//Author must prefix text with this to use commands
	} else $command_symbol = VarLoad($guild_folder, "command_symbol.php"); //Load saved option file (Not used yet, but might be later)

	//Chat options
	global $react_option, $vanity_option, $nsfw_option, $channel_option, $games_option, $gameroles_option;
	if (!CheckFile($guild_folder, "react_option.php")) $react	= $react_option; //Bot will not react to messages if false
	else $react  = VarLoad($guild_folder, "react_option.php"); //Load saved option file
	if (!CheckFile($guild_folder, "vanity_option.php")) $vanity	= $vanity_option; //Allow SFW vanity like hug, nuzzle, kiss
	else $vanity = VarLoad($guild_folder, "vanity_option.php"); //Load saved option file
	if (!CheckFile($guild_folder, "nsfw_option.php")) $nsfw	= $nsfw_option; //Allow NSFW commands
	else $nsfw  = VarLoad($guild_folder, "nsfw_option.php"); //Load saved option file
	if (!CheckFile($guild_folder, "channel_option.php")) $channeloption	= $channel_option; //Allow channelrole reactions
	else $channeloption  = VarLoad($guild_folder, "channel_option.php"); //Load saved option file
	if (!CheckFile($guild_folder, "games_option.php")) $games	= $games_option; //Allow games like Yahtzee
	else $games  = VarLoad($guild_folder, "games_option.php"); //Load saved option file
	if (!CheckFile($guild_folder, "gameroles_option.php")) $gamerole	= $gameroles_option; //Allow gameroles
	else $gamerole  = VarLoad($guild_folder, "gameroles_option.php"); //Load saved option file

	//Role picker options
	if (!$rolepicker_id) //Message rolepicker menus
		$rolepicker_id = $discord->id; //Default to Palace Bot
	global $rolepicker_option, $species_option, $gender_option, $pronouns_option, $sexuality_option, $channel_option, $gameroles_option, $custom_option;
	if (!CheckFile($guild_folder, "rolepicker_option.php")) $rp0 = $rolepicker_option; //Allow Rolepicker
	else $rp0 = VarLoad($guild_folder, "rolepicker_option.php");
	if ($species_message_id) {
		if (!CheckFile($guild_folder, "species_option.php")) $rp1 = $species_option; //Species role picker
		else $rp1 = VarLoad($guild_folder, "species_option.php");
	}
	if ($gender_message_id) {
		if (!CheckFile($guild_folder, "gender_option.php")) $rp2 = $gender_option; //Gender role picker
		else $rp2 = VarLoad($guild_folder, "gender_option.php");
	}
	if ($pronouns_message_id) {
		if (!CheckFile($guild_folder, "pronouns_option.php")) $rp5 = $pronouns_option; //Custom role picker
		else $rp5 = VarLoad($guild_folder, "pronouns_option.php");
	}
	if ($species_message_id) {
		if (!CheckFile($guild_folder, "sexuality_option.php")) $rp3	= $sexuality_option; //Sexuality role picker
		else $rp3 = VarLoad($guild_folder, "sexuality_option.php");
	}
	if ($customroles_message_id) {
		if (!CheckFile($guild_folder, "custom_option.php")) $rp4 = $custom_option;//Custom role picker
		else $rp4 = VarLoad($guild_folder, "custom_option.php");
	}
	if ($nsfw_message_id) {
		if (!CheckFile($guild_folder, "nsfw_option.php")) $nsfw	= $nsfw_option; //NSFW/Adult role picker
		else $nsfw = VarLoad($guild_folder, "nsfw_option.php");
	}
	if ($channelroles_message_id) {
		if (!CheckFile($guild_folder, "channel_option.php")) $channeloption	= $channel_option;
		else $channeloption = VarLoad($guild_folder, "channel_option.php");
	}
	if ($gameroles_message_id) {
		if (!CheckFile($guild_folder, "gameroles_option.php")) $gamerole = $gameroles_option;
		else $gamerole = VarLoad($guild_folder, "gameroles_option.php");
	}

	//if($GLOBALS['debug_echo']) echo "$author_check <@$author_id> ($author_guild_id): {$message_content}", PHP_EOL;
	$author_webhook = $author_user->webhook;


	/*
	*********************
	*********************
	Twitch Chat Integration
	*********************
	*********************
	*/

	if ($twitch) { //Passed down into the event from run.php
		if($twitch_discord_output = $twitch->getDiscordOutput()) {		
			if ( //These values can be null, but we only want to do this if they are valid strings
				($twitch_guild_id = $twitch->getGuildId())
				&&
				($twitch_channel_id = $twitch->getChannelId())
			) {
				if ($message->id != $discord->id) //Don't output messages sent by this bot (or any other bot, really)
				{
					if ( //Only process if the message was sent in the designated channel
						($twitch_guild_id == $author_guild_id)
						&&
						($twitch_channel_id == $author_channel_id)
					) {
						$content = $message->content;
						//search the message for anything containing a discord snowflake in the format of either <@id> or <@!id> and replace it with @username
						preg_match_all('/<@([0-9]*)>/', $message->content, $matches1);
						preg_match_all('/<@!([0-9]*)>/', $message->content, $matches2);
						$matches = array_merge($matches1, $matches2);
						if($matches) {
							foreach($matches as $array) {
								foreach ($array as $match) {
									if(is_numeric($match)) {
										if ($user = $discord->users->get('id', $match))
											$username = $user->username;
										if (($member = $author_guild->members->get('id', $match)) && $member->nick)
											$nickname = $member->nick;
										if ($nickname || $username)
											$content = str_replace($match, '@'.($nickname ?? $username), $content);
									}
								}
							}
							$filter = "<@!";
							$content = str_replace($filter, "", $content);
							$filter = "<@";
							$content = str_replace($filter, "", $content);
							$filter = ">";
							//$content = str_replace($filter, "", $content); //I kinda like this as a reply symbol, also prevents smiley faces like :> from being filtered
						}
						$msg = '[DISCORD] ' . $author_user->username . ': ' . $content;
						if(str_starts_with($message_content_lower, '#')) { //Send message only to designated channel
							$channels = $twitch->getChannels();
							$arr = explode(' ', $content);
							foreach ($channels as $temp) {
								if($GLOBALS['debug_echo']) echo "temp: `$temp`" . PHP_EOL;
								if (substr($arr[0], 1) == $temp) $target_channel = $temp;
							}
							if($GLOBALS['debug_echo']) echo "msg: `$msg`" . PHP_EOL;
							if($GLOBALS['debug_echo']) echo "content: `$content`" . PHP_EOL;
							if($GLOBALS['debug_echo']) echo "target_channel: `$target_channel`" . PHP_EOL;
							if ($target_channel) {
								$msg = str_replace('#'.$target_channel, '', $msg);
								$twitch->sendMessage($msg, $target_channel);
							}else $twitch->sendMessage($msg);
						}else $twitch->sendMessage($msg);
					}
				}
			}
		}
	}

	/*
	*********************
	*********************
	Load persistent variables for author
	*********************
	*********************
	*/

	$author_folder = $guild_folder."\\".$author_id;
	CheckDir($author_folder); //Check if folder exists and create if it doesn't
	if (CheckFile($author_folder, "watchers.php")) {
		if($GLOBALS['debug_echo']) echo "[WATCH] $author_id" . PHP_EOL;
		$watchers = VarLoad($author_folder, "watchers.php");
		//	if($GLOBALS['debug_echo']) echo "WATCHERS: " . var_dump($watchers); //array of user IDs
		$null_array = true; //Assume the object is empty
		foreach ($watchers as $watcher) {
			if ($watcher != null) {																									//if($GLOBALS['debug_echo']) echo "watcher: " . $watcher . PHP_EOL;
				$null_array = false; //Mark the array as valid
				if ($watcher_member = $author_guild->members->get('id', $watcher)) {
					$discord->users->fetch("$watcher")->done(
						function ($watcher_user) use ($message, $watch_channel) {
							if (isset($watch_channel)) $watch_channel->sendMessage("<@{$message->author->id}> sent a message in <#{$message->channel->id}>: \n{$message->content}");
							else $watcher_user->sendMessage("<@{$message->author->id}> sent a message in <#{$message->channel->id}>: \n{$message->content}");
						}
					);
				}
			}
		}
		if ($null_array) { //Delete the null file
			VarDelete($author_folder, "watchers.php");
			if($GLOBALS['debug_echo']) echo "[REMOVE WATCH] $author_id" . PHP_EOL;
		}
	}

	/*
	*********************
	*********************
	Guild-specific variables
	*********************
	*********************
	*/


	if(!(include getcwd() . '/CHANGEME.PHP')) include getcwd() . '/vendor/vzgcoders/palace/CHANGEME.php';
	if ($author_id == $creator_id) $creator = true;

	$author_guild_roles_names 				= array(); 												//Names of all guild roles
	$author_guild_roles_ids 				= array(); 												//IDs of all guild roles
	foreach ($author_guild_roles as $role) {
		$author_guild_roles_names[] 		= $role->name; 																		//if($GLOBALS['debug_echo']) echo "role[$x] name: " . PHP_EOL; //var_dump($role->name);
		$author_guild_roles_ids[] 			= $role->id; 																		//if($GLOBALS['debug_echo']) echo "role[$x] id: " . PHP_EOL; //var_dump($role->id);
		if ($role->name == "Palace Bot") //Author is this bot
			$role_vzgbot_id = $role->id;
	}																															//if($GLOBALS['debug_echo']) echo "discord_guild_roles_names" . PHP_EOL; var_dump($author_guild_roles_names);
																																//if($GLOBALS['debug_echo']) echo "discord_guild_roles_ids" . PHP_EOL; var_dump($author_guild_roles_ids);
	/*
	*********************
	*********************
	Get the guild-related collections for the author
	*********************
	*********************
	*/
	//Populate arrays of the info we need
	$author_member_roles											= $author_member->roles;
	$author_member_roles_names 										= array();
	$author_member_roles_ids 										= array();
	foreach ($author_member_roles as $role) {
		$author_member_roles_names[] 							= $role->name; 												//if($GLOBALS['debug_echo']) echo "role[$x] name: " . PHP_EOL; //var_dump($role->name);
		$author_member_roles_ids[]								= $role->id; 												//if($GLOBALS['debug_echo']) echo "role[$x] id: " . PHP_EOL; //var_dump($role->id);
		if ($role->id == $role_18_id)
			$adult = true; //Author has the 18+ role
		if ($role->id == $role_dev_id)
			$dev = true; //Author has the dev role
		if ($role->id == $role_owner_id)
			$owner = true; //Author has the owner role
		if ($role->id == $role_admin_id)
			$admin = true; //Author has the admin role
		if ($role->id == $role_mod_id)
			$mod = true; //Author has the mod role
		if ($role->id == $role_assistant_id)
			$assistant = true; //Author has the assistant role
		if ($role->id == $role_tech_id)
			$tech = true; //Author has the tech role
		if ($role->id == $role_verified_id)
			$verified = true; //Author has the verified role
		if ($role->id == $role_bot_id)
			$bot = true; //Author has the bot role
		if ($role->id == $role_vzgbot_id)
			$vzgbot = true; //Author is this bot
		if ($role->id == $role_muted_id) {
			$muted = true; //Author is muted
		}
	}
	if ($creator || $owner || $dev) $bypass = true; //Ignore spam restrictions

	if ($muted) return; //Ignore commands by muted users

	if ($creator) if($GLOBALS['debug_echo']) echo "[CREATOR $author_guild_id/$author_id] " . PHP_EOL;
	if ($owner) if($GLOBALS['debug_echo']) echo "[OWNER $author_guild_id/$author_id] " . PHP_EOL;
	if ($dev) if($GLOBALS['debug_echo']) echo "[DEV $author_guild_id/$author_id] " . PHP_EOL;
	if ($admin) if($GLOBALS['debug_echo']) echo "[ADMIN $author_guild_id/$author_id] " . PHP_EOL;
	if ($mod) if($GLOBALS['debug_echo']) echo "[MOD $author_guild_id/$author_id] " . PHP_EOL;
	//if($GLOBALS['debug_echo']) echo PHP_EOL;
	
	global $species, $species2, $species3, $species_message_text, $species2_message_text, $species3_message_text, $nsfwsubroles;
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
		global $customroles, $customroles_message_text;
		
	/*
	*********************
	*********************
	Early Break
	*********************
	*********************
	*/

	$called = false;
	$message_content_original = $message_content;
	$message_content_lower_original = $message_content_lower;
	if (str_starts_with($message_content_lower,  "<@!".$discord->id."> ")) { //Allow calling commands by <@user_id>
		$message_content_lower = trim(substr($message_content_lower, (4+strlen($discord->id))));
		$message_content = trim(substr($message_content, (4+strlen($discord->id))));
		$called = true;
	} elseif (str_starts_with($message_content_lower,  "<@".$discord->id."> ")) { //Allow calling commands by <@user_id>
		$message_content_lower = trim(substr($message_content_lower, (3+strlen($discord->id))));
		$message_content = trim(substr($message_content, (3+strlen($discord->id))));
		$called = true;
	} elseif (str_starts_with($message_content_lower, $command_symbol)) { //Allow calling comamnds by command symbol
		$message_content_lower = trim(substr($message_content_lower, strlen($command_symbol)));
		$message_content = trim(substr($message_content, strlen($command_symbol)));
		$called = true;
	} elseif (str_starts_with($message_content_lower, '!s ')) {
		$message_content_lower = trim(substr($message_content_lower, 2));
		$message_content = trim(substr($message_content, 2));
		$called = true;
	}
	if (!$called) return;
	/*
	*********************
	*********************
	Owner setup command (NOTE: Changes made here will not affect servers using a manual config file)
	*********************
	*********************
	*/
	if ($creator || $owner) { //BCP
		if (str_starts_with($message_content_lower, 'bcp')) {
			$whitelist_array = array();
			if(!CheckFile($guild_folder, "ownerwhitelist.php")) {
				$whitelist_array = array($guild_owner_id);
				VarSave($guild_folder, "ownerwhitelist.php", $whitelist_array); //The original guildowner should be added to the whitelist in case they ever transfer ownership but still need access
			}else{
				$whitelist_array = VarLoad($guild_folder, "ownerwhitelist.php");
			}
			$subcommand = trim(substr($message_content_lower, 3));
			if($GLOBALS['debug_echo']) echo "[SUBCOMMAND $subcommand]" . PHP_EOL;
			
			$switch = null;
			if (str_starts_with($subcommand, 'add')) $switch = 'add';
			if (str_starts_with($subcommand, 'rem')) $switch = 'rem';
			if (str_starts_with($subcommand, 'remove')) $switch = 'remove';
			if (str_starts_with($subcommand, 'list')) $switch = 'list';
			if ($switch) {
				$value = trim(str_replace($switch, "", $subcommand));
				$filter = "<@!";
				$value = str_replace($filter, "", $value);
				$filter = "<@";	
				$value = str_replace($filter, "", $value);
				$filter = ">";
				$value = str_replace($filter, "", $value);
				if(is_numeric($value))
					if (!preg_match('/^[0-9]{16,20}$/', $value))
						return $message->react('❌');
				if ($switch == 'add')
					if ($target_user = $discord->users->get('id', $value)) //Add to whitelist
						if(!in_array($value, $whitelist_array)) {
							$whitelist_array[] = $value;
							VarSave($guild_folder, "ownerwhitelist.php", $whitelist_array);
							return $message->react("👍");
						}
				if ( ($switch == 'rem') || ($switch == 'remove'))
					if(in_array($value, $whitelist_array)) { //Remove from whitelist
						$pruned_whitelist_array = array();
						foreach ($whitelist_array as $id)
							if ($id != $value) $pruned_whitelist_array[] = $id;
						VarSave($guild_folder, "ownerwhitelist.php", $pruned_whitelist_array);
						return $message->react("👍");
					}
				if ($switch == 'list') {
					$string = "Whitelisted users: ";
					foreach ($whitelist_array as $id)
						$string .= "<@$id> ";
					return $message->channel->sendMessage($string);
				}
				return $message->react("👎");
			}
			return; //check for empty subcommand and subcommands
		}
	}
	if ($creator || $owner || $dev) {
		if ($author_guild_id == '807759102624792576') {
			if (str_starts_with($message_content_lower, 'host world')) 
				if($handle = popen("start ". 'cmd /c "'. 'D:\GitHub' . '\World - Pull-Compile-Kill-Copy-Host.bat"', "r"))
					return $message->react("👍");
		}
		switch ($message_content_lower) {
			case 'setup': //;setup
				$documentation = $documentation . "`currentsetup` send DM with current settings\n";
				$documentation = $documentation . "`updateconfig` updates the configuration file (needed for updates)\n";
				$documentation = $documentation . "`clearconfig` deletes all configuration information for the server\n";
				$documentation = $documentation . "`help` displays all other commands\n";
				//Roles
				$documentation = $documentation . "\n**Roles:**\n";
				$documentation = $documentation . "`setup dev @role`\n";
				$documentation = $documentation . "`setup admin @role`\n";
				$documentation = $documentation . "`setup mod @role`\n";
				$documentation = $documentation . "`setup bot @role`\n";
				$documentation = $documentation . "`setup vzgbot @role` (Role with the name Palace Bot, not the actual bot)\n";
				$documentation = $documentation . "`setup muted @role`\n";
				$documentation = $documentation . "`setup verified @role`\n";
				$documentation = $documentation . "`setup adult @role`\n";
				//User
				/* Deprecated
				$documentation = $documentation . "**Users:**\n";
				$documentation = $documentation . "`setup rolepicker @user` The user who posted the rolepicker messages\n";
				*/
				//Channels
				$documentation = $documentation . "**Channels:**\n";
				$documentation = $documentation . "`setup general <#channel_id>` The primary chat channel, also welcomes new users to everyone\n";
				$documentation = $documentation . "`setup welcome <#channel_id>` Simple welcome message tagging new user\n";
				$documentation = $documentation . "`setup welcomelog <#channel_id>` Detailed message about the user\n";
				$documentation = $documentation . "`setup log <#channel_id>` Detailed log channel\n"; //Modlog
				$documentation = $documentation . "`setup verify channel <#channel_id>` Where users get verified\n";
				$documentation = $documentation . "`setup watch <#channel_id>` ;watch messages are duplicated here instead of in a DM\n";
				/* Deprecated
				$documentation = $documentation . "`setup rolepicker channel <#channel_id>` Where users pick a role\n";
				*/
				$documentation = $documentation . "`setup games channel <#channel_id>` Where users can play games\n";
				$documentation = $documentation . "`setup suggestion pending <#channel_id>` \n";
				$documentation = $documentation . "`setup suggestion approved <#channel_id>` \n";
				$documentation = $documentation . "`setup tip pending <#channel_id>` \n";
				$documentation = $documentation . "`setup tip approved <#channel_id>` \n";
				//Messages
				$documentation = $documentation . "**Messages:**\n";
				/* Deprecated
				$documentation = $documentation . "`setup species messageid`\n";
				$documentation = $documentation . "`setup species2 messageid`\n";
				$documentation = $documentation . "`setup species3 messageid`\n";
				$documentation = $documentation . "`setup sexuality messageid`\n";
				$documentation = $documentation . "`setup gender messageid`\n";
				$documentation = $documentation . "`setup channelroles messageid`\n";
				$documentation = $documentation . "`setup customroles messageid`\n";
				*/
				$documentation = $documentation . "`message species`\n";
				$documentation = $documentation . "`message species2`\n";
				$documentation = $documentation . "`message species3`\n";
				$documentation = $documentation . "`message gender`\n";
				$documentation = $documentation . "`message pronouns`\n";
				$documentation = $documentation . "`message sexuality`\n";
				$documentation = $documentation . "`message channels`\n";
				$documentation = $documentation . "`message adult`\n";
				$documentation = $documentation . "`message customroles`\n";
				//TODO REVIEW AND ADD MISSING
				
				$documentation_sanitized = str_replace("\n", "", $documentation);
				$doc_length = strlen($documentation_sanitized);
				if ($doc_length < 1024) {
					$embed = new \Discord\Parts\Embed\Embed($discord);
					$embed
						->setTitle("Setup commands for $author_guild_name")														// Set a title
						->setColor(0xe1452d)																	// Set a color (the thing on the left side)
						->setDescription("$documentation")														// Set a description (below title, above fields)
						//->addFieldValues("⠀", "$documentation")														// New line after this
						//->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
						//->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')			 	// Set an image (below everything except footer)
						//->setTimestamp()																	 	// Set a timestamp (gets shown next to footer)
						//->setAuthor("$author_check", "$author_guild_avatar")  									// Set an author with icon
						->setFooter("Palace Bot by Valithor#5947")							 					// Set a footer without icon
						->setURL("");							 												// Set the URL
					//Open a DM channel then send the rich embed message
					$author_user->sendMessage('', false, $embed);
					return;
				} else {
					$author_user->getPrivateChannel()->done(function ($author_dmchannel) use ($documentation) {	//Promise
						if($GLOBALS['debug_echo']) echo "[;SETUP MESSAGE]" . PHP_EOL;
						$author_dmchannel->sendMessage($documentation);
					});
					return;
				}
				break;
			case '__currentsetup': //;currentsetup
				//Send DM with current settings
				//Roles
				$documentation = "⠀\n**Roles:**\n";
				$documentation = $documentation . "`dev @role` $role_dev_id\n";
				$documentation = $documentation . "`admin @role` $role_admin_id\n";
				$documentation = $documentation . "`mod @role` $role_mod_id\n";
				$documentation = $documentation . "`bot @role` $role_bot_id\n";
				$documentation = $documentation . "`vzg @role` $role_vzgbot_id\n";
				$documentation = $documentation . "`muted @role` $role_muted_id\n";
				$documentation = $documentation . "`verified @role` $role_verified_id\n";
				$documentation = $documentation . "`adult @role` $role_18_id\n";
				//User
				$documentation = $documentation . "**Users:**\n";
				$documentation = $documentation . "`rolepicker @user` $rolepicker_id\n";
				//Channels
				$documentation = $documentation . "**Channels:**\n";
				$documentation = $documentation . "`general <#channel_id>` <#{$general_channel->id}>\n";
				if ($welcome_public_channel_id) $welcome_public_channel = $author_guild->channels->get('id', $welcome_public_channel_id);
				if ($welcome_log_channel_id) $welcome_log_channel = $author_guild->channels->get('id', $welcome_log_channel_id);
				if ($welcome_public_channel_id) $documentation = $documentation . "`welcome <#channel_id>` <#{$welcome_public_channel->id}>\n";
				$documentation = $documentation . "`welcomelog <#channel_id>` <#{$welcome_log_channel->id}>\n";
				$documentation = $documentation . "`log <#channel_id>` <#{$modlog_channel->id}>\n";
				$documentation = $documentation . "`verify channel <#channel_id>` <#{$getverified_channel->id}>\n";
				if ($verifylog_channel_id) {
					$documentation = $documentation . "`verifylog <#channel_id>` <#{$verifylog_channel->id}>\n";
				} else {
					$documentation = $documentation . "`verifylog <#channel_id>` (defaulted to log channel)\n";
				}
				if ($watch_channel_id) {
					$documentation = $documentation . "`watch <#channel_id>` <#{$watch_channel->id}>\n";
				} else {
					$documentation = $documentation . "`watch <#channel_id>` (defaulted to direct message only)\n";
				}
				$documentation = $documentation . "`rolepicker channel <#channel_id>`  <#{$rolepicker_channel->id}>\n";
				$documentation = $documentation . "`nsfw rolepicker channel <#channel_id>`  <#{$nsfw_rolepicker_channel->id}>\n";
				$documentation = $documentation . "`games rolepicker channel <#channel_id>`  <#{$games_rolepicker_channel->id}>\n";
				$documentation = $documentation . "`games <#channel_id>` <#{$games_channel->id}>\n";
				$documentation = $documentation . "`suggestion pending <#channel_id>` <#{$suggestion_pending_channel->id}>\n";
				$documentation = $documentation . "`suggestion approved <#channel_id>` <#{$suggestion_approved_channel->id}>\n";
				$documentation = $documentation . "`tip pending <#channel_id>` <#{$tip_pending_channel->id}>\n";
				$documentation = $documentation . "`tip approved <#channel_id>` <#{$tip_approved_channel->id}>\n";
				//Messages
				$documentation = $documentation . "**Messages:**\n";
				if ($gameroles_message_id) {
					$documentation = $documentation . "`gameroles messageid` $gameroles_message_id\n";
				} else {
					$documentation = $documentation . "`gameroles messageid` Message not yet sent!\n";
				}
				if ($species_message_id) {
					$documentation = $documentation . "`species messageid` $species_message_id\n";
				} else {
					$documentation = $documentation . "`species messageid` Message not yet sent!\n";
				}
				if ($species2_message_id) {
					$documentation = $documentation . "`species2 messageid` $species2_message_id\n";
				} else {
					$documentation = $documentation . "`species2 messageid` Message not yet sent!\n";
				}
				if ($species3_message_id) {
					$documentation = $documentation . "`species3 messageid` $species3_message_id\n";
				} else {
					$documentation = $documentation . "`species3 messageid` Message not yet sent!\n";
				}
				if ($gender_message_id) {
					$documentation = $documentation . "`gender messageid` $gender_message_id\n";
				} else {
					$documentation = $documentation . "`gender messageid` Message not yet sent!\n";
				}
				if ($pronouns_message_id) {
					$documentation = $documentation . "`prnouns messageid` $pronouns_message_id\n";
				} else {
					$documentation = $documentation . "`pronouns messageid` Message not yet sent!\n";
				}				
				if ($sexuality_message_id) {
					$documentation = $documentation . "`sexuality messageid` $sexuality_message_id\n";
				} else {
					$documentation = $documentation . "`sexuality messageid` Message not yet sent!\n";
				}
				if ($nsfw_message_id) {
					$documentation = $documentation . "`nsfw messageid` $nsfw_message_id\n";
				} else {
					$documentation = $documentation . "`nsfw messageid` Message not yet sent!\n";
				}
				if ($channelroles_message_id) {
					$documentation = $documentation . "`channelroles messageid` $channelroles_message_id\n";
				} else {
					$documentation = $documentation . "`channelroles messageid` Message not yet sent!\n";
				}
				if ($customroles_message_id) {
					$documentation = $documentation . "`customroles messageid` $customroles_message_id\n";
				} else {
					$documentation = $documentation . "`customroles messageid` Message not yet sent!\n";
				}
				
				$documentation_sanitized = str_replace("\n", "", $documentation);
				$doc_length = strlen($documentation_sanitized); if($GLOBALS['debug_echo']) echo "doc_length: " . $doc_length . PHP_EOL;
				if ($doc_length < 1024) {
					$embed = new \Discord\Parts\Embed\Embed($discord);
					$embed
						->setTitle("Current setup for $author_guild_name")														// Set a title
						->setColor(0xe1452d)																	// Set a color (the thing on the left side)
						->setDescription("$documentation")														// Set a description (below title, above fields)
			//					->addFieldValues("⠀", "$documentation")														// New line after this
			//					->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
			//					->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')			 	// Set an image (below everything except footer)
			//					->setTimestamp()																	 	// Set a timestamp (gets shown next to footer)
			//					->setAuthor("$author_check", "$author_guild_avatar")  									// Set an author with icon
						->setFooter("Palace Bot by Valithor#5947")							 					// Set a footer without icon
						->setURL("");							 												// Set the URL
			//				Open a DM channel then send the rich embed message
					if($GLOBALS['debug_echo']) echo "embed class: " . get_class($embed) . PHP_EOL;
					$author_user->getPrivateChannel()->done(function ($author_dmchannel) use ($embed) {	//Promise
						if($GLOBALS['debug_echo']) echo "[;CURRENTSETUP EMBED]" . PHP_EOL;
						$author_dmchannel->sendEmbed($embed);
						return;
					});
				} else {
					$author_user->getPrivateChannel()->done(function ($author_dmchannel) use ($documentation) {	//Promise
						if($GLOBALS['debug_echo']) echo "[;CURRENTSETUP MESSAGE]" . PHP_EOL;
						$author_dmchannel->sendMessage($documentation);
					});
				}
			case '__settings':
				$documentation = "Command symbol: $command_symbol\n";
				$documentation = $documentation . "\nBot options:\n";
				//react
				$documentation = $documentation . "`react:` ";
				if ($react) $documentation = $documentation . "**Enabled**\n";
				else $documentation = $documentation . "**Disabled**\n";
				//vanity
				$documentation = $documentation . "`vanity:` ";
				if ($vanity) $documentation = $documentation . "**Enabled**\n";
				else $documentation = $documentation . "**Disabled**\n";
				//nsfw
				$documentation = $documentation . "`nsfw:` ";
				if ($nsfw) $documentation = $documentation . "**Enabled**\n";
				else $documentation = $documentation . "**Disabled**\n";
				//games
				$documentation = $documentation . "`games:` ";
				if ($games) $documentation = $documentation . "**Enabled**\n";
				else $documentation = $documentation . "**Disabled**\n";
				
				//rolepicker
				$documentation = $documentation . "`\nrolepicker:` ";
				if ($rp0) $documentation = $documentation . "**Enabled**\n";
				else $documentation = $documentation . "**Disabled**\n";
				
				if (!$rp0) $documentation = $documentation . "~~"; //Strikeout invalid options 
				//gameroles
				$documentation = $documentation . "`game roles:` ";
				if ($gamerole) $documentation = $documentation . "**Enabled**\n";
				else $documentation = $documentation . "**Disabled**\n";
				//species
				$documentation = $documentation . "`species:` ";
				if ($rp1) $documentation = $documentation . "**Enabled**\n";
				else $documentation = $documentation . "**Disabled**\n";
				//gender
				$documentation = $documentation . "`gender:` ";
				if ($rp2) $documentation = $documentation . "**Enabled**\n";
				else $documentation = $documentation . "**Disabled**\n";
				//prnouns
				$documentation = $documentation . "`pronouns:` ";
				if ($rp5) $documentation = $documentation . "**Enabled**\n";
				else $documentation = $documentation . "**Disabled**\n";
				//sexuality
				$documentation = $documentation . "`sexuality:` ";
				if ($rp3) $documentation = $documentation . "**Enabled**\n";
				else $documentation = $documentation . "**Disabled**\n";
				//channel roles
				$documentation = $documentation . "`channel roles:` ";
				if ($channeloption) $documentation = $documentation . "**Enabled**\n";
				else $documentation = $documentation . "**Disabled**\n";
				//customrole
				$documentation = $documentation . "`customrole:` ";
				if ($rp4) $documentation = $documentation . "**Enabled**\n";
				else $documentation = $documentation . "**Disabled**\n";
				if (!$rp0) $documentation = $documentation . "~~"; //Strikeout invalid options
			
				$doc_length = strlen($documentation);
				if ($doc_length < 1024) {
					$embed = new \Discord\Parts\Embed\Embed($discord);
					$embed
					->setTitle("Settings for $author_guild_name")											// Set a title
					->setColor(0xe1452d)																	// Set a color (the thing on the left side)
					->setDescription("$documentation")														// Set a description (below title, above fields)
		//					->addFieldValues("⠀", "$documentation")														// New line after this
					
		//					->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
		//					->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')			 	// Set an image (below everything except footer)
		//					->setTimestamp()																	 	// Set a timestamp (gets shown next to footer)
		//					->setAuthor("$author_check", "$author_guild_avatar")  									// Set an author with icon
					->setFooter("Palace Bot by Valithor#5947")							 					// Set a footer without icon
					->setURL("");							 												// Set the URL
		//				Open a DM channel then send the rich embed message
                    $author_user->getPrivateChannel()->done(function ($author_dmchannel) use ($embed) {	//Promise
                        if($GLOBALS['debug_echo']) echo "[;SETTINGS EMBED]" . PHP_EOL;
                        return $author_dmchannel->sendEmbed($embed);
                    });
				} else {
					$author_user->getPrivateChannel()->done(function ($author_dmchannel) use ($documentation) {	//Promise
						if($GLOBALS['debug_echo']) echo "[;SETTINGS MESSAGE]" . PHP_EOL;
						return $author_dmchannel->sendMessage($documentation);
					});
				}
				return $message->delete();
				break;
			case 'updateconfig': //;updateconfig
				$file = __DIR__ . 'guild_config_template.php';
				if (sha1_file($guild_config_path) == sha1_file(__DIR__ . '\guild_config_template.php')) return $message->reply("Guild configuration is already up to date!");
				else {
					if (!copy(getcwd() . '/vendor/vzgcoders/palace/' . $file, $guild_config_path)) return $message->reply("Failed to create guild_config file! Please contact <@116927250145869826> for assistance.");
					else return $author_channel->sendMessage("The server's configuration file was recently updated by <@$author_id>. Please check the ;currentsetup");
				}
				break;
			case 'clearconfig': //;clearconfig
				$files = glob(getcwd() . "$guild_folder" . '/*');
				// Deleting all the files in the list
				foreach ($files as $file) {
					if (is_file($file)) {
						unlink($file);
					} //Delete the file
				}
				return $author_channel->sendMessage("The server's configuration files were recently delete by <@$author_id>. Please run the ;setup commands again.");
				break;
			//Role Messages Setup
			case 'message games': //;message games
			case 'message gamerole': //;message gamerole
						case 'message gameroles': //;message gameroles
				VarSave($guild_folder, "games_rolepicker_channel_id.php", strval($author_channel_id)); //Make this channel the rolepicker channel
				$author_channel->sendMessage($gameroles_message_text)->done(function ($new_message) use ($guild_folder, $gameroles, $message) {
					VarSave($guild_folder, "gameroles_message_id.php", strval($new_message->id));
					/* //Does not preserve order
					foreach ($gameroles as $var_name => $value) {
						$new_message->react($value);
					}
					*/
					
					/* //Preserves order but forces compiling in realtime
					$promise = null;
					$string = '';
					$string1 = '$promise = $new_message->react(array_shift($gameroles))->done(function () use ($gameroles, $i, $new_message) {';
					$string2 = '});';
					for ($i = 0; $i < count($gameroles); $i++) {
					  $string .= $string1;
					}
					for ($i = 0; $i < count($gameroles); $i++) {
					  $string .= $string2;
					}
					eval($string); //I really hate this language sometimes
					return $message->delete();
					*/
					
					//Preserves order and executes recursively until the job is done
					$add = function ($gameroles, $new_message) use (&$add) {
						if (count($gameroles) != 0) {
							$new_message->react(array_shift($gameroles))->done(function () use ($add, $gameroles, $new_message) {
								$add($gameroles, $new_message);
							});
						}
					};
					$add($gameroles, $new_message);
					return $message->delete();
				});
				return;
				break;
			case 'message species': //;message species
				VarSave($guild_folder, "rolepicker_channel_id.php", strval($author_channel_id)); //Make this channel the rolepicker channel
				$author_channel->sendMessage($species_message_text)->done(function ($new_message) use ($guild_folder, $species, $message) {
					VarSave($guild_folder, "species_message_id.php", strval($new_message->id));
					$add = function ($species, $new_message) use (&$add) {
						if (count($species) != 0) {
							$new_message->react(array_shift($species))->done(function () use ($add, $species, $new_message) {
								$add($species, $new_message);
							});
						}
					};
					$add($species, $new_message);
					return $message->delete();
				});
				return;
				break;
			case 'message species2': //;message species2
				VarSave($guild_folder, "rolepicker_channel_id.php", strval($author_channel_id)); //Make this channel the rolepicker channel
				$author_channel->sendMessage($species2_message_text)->done(function ($new_message) use ($guild_folder, $species2) {
					VarSave($guild_folder, "species2_message_id.php", strval($new_message->id));
					$add = function ($species2, $new_message) use (&$add) {
						if (count($species2) != 0) {
							$new_message->react(array_shift($species2))->done(function () use ($add, $species2, $new_message) {
								$add($species2, $new_message);
							});
						}
					};
					$add($species2, $new_message);
					return $message->delete();
				});
				return;
				break;
			case 'message species3': //;message species3
				VarSave($guild_folder, "rolepicker_channel_id.php", strval($author_channel_id)); //Make this channel the rolepicker channel
				$author_channel->sendMessage($species3_message_text)->done(function ($new_message) use ($guild_folder, $species3, $message) {
					VarSave($guild_folder, "species3_message_id.php", strval($new_message->id));
					$add = function ($species3, $new_message) use (&$add) {
						if (count($species3) != 0) {
							$new_message->react(array_shift($species3))->done(function () use ($add, $species3, $new_message) {
								$add($species3, $new_message);
							});
						}
					};
					$add($species3, $new_message);
					return $message->delete();
				});
				return;
				break;
			case 'message gender': //;message gender
				if($GLOBALS['debug_echo']) echo '[GENDER MESSAGE GEN]' . PHP_EOL;
				VarSave($guild_folder, "rolepicker_channel_id.php", strval($author_channel_id)); //Make this channel the rolepicker channel
				$author_channel->sendMessage($gender_message_text)->done(function ($new_message) use ($guild_folder, $gender, $message) {
					VarSave($guild_folder, "gender_message_id.php", strval($new_message->id));
					$add = function ($gender, $new_message) use (&$add) {
						if (count($gender) != 0) {
							$new_message->react(array_shift($gender))->done(function () use ($add, $gender, $new_message) {
								$add($gender, $new_message);
							});
						}
					};
					$add($gender, $new_message);
					return $message->delete();					
				});
				return;
				break;
			case 'message pronoun': //;message pronoun
			case 'message pronouns': //;message pronouns
				if($GLOBALS['debug_echo']) echo '[GENDER MESSAGE GEN]' . PHP_EOL;
				VarSave($guild_folder, "rolepicker_channel_id.php", strval($author_channel_id)); //Make this channel the rolepicker channel
				$author_channel->sendMessage($pronouns_message_text)->done(function ($new_message) use ($guild_folder, $pronouns, $message) {
					VarSave($guild_folder, "pronouns_message_id.php", strval($new_message->id));
					$add = function ($pronouns, $new_message) use (&$add) {
						if (count($pronouns) != 0) {
							$new_message->react(array_shift($pronouns))->done(function () use ($add, $pronouns, $new_message) {
								$add($pronouns, $new_message);
							});
						}
					};
					$add($pronouns, $new_message);
					return $message->delete();	
				});
				return;
				break;
			case 'message sexuality':
			case 'message sexualities':
				VarSave($guild_folder, "rolepicker_channel_id.php", strval($author_channel_id)); //Make this channel the rolepicker channel
				$author_channel->sendMessage($sexuality_message_text)->done(function ($new_message) use ($guild_folder, $sexualities, $message) {
					VarSave($guild_folder, "sexuality_message_id.php", strval($new_message->id));
					$add = function ($sexualities, $new_message) use (&$add) {
						if (count($sexualities) != 0) {
							$new_message->react(array_shift($sexualities))->done(function () use ($add, $sexualities, $new_message) {
								$add($sexualities, $new_message);
							});
						}
					};
					$add($sexualities, $new_message);
					return $message->delete();	
				});
				return;
				break;
			case 'message nsfw': //;message nsfw
			case 'message adult': //;message adult
				VarSave($guild_folder, "rolepicker_channel_id.php", strval($author_channel_id)); //Make this channel the rolepicker channel
				$author_channel->sendMessage($nsfw_message_text)->done(function ($new_message) use ($guild_folder, $nsfwroles, $message) {
					VarSave($guild_folder, "nsfw_message_id.php", strval($new_message->id));
					$add = function ($nsfwroles, $new_message, $message) use (&$add) {
						if (count($nsfwroles) != 0) {
							$new_message->react(array_shift($nsfwroles))->done(function () use ($add, $nsfwroles, $new_message, $message) {
								$add($nsfwroles, $new_message, $message);
							});
						}
					};
					$add($nsfwroles, $new_message, $message);
					return $message->delete();	
				});
				return;
				break;
			case 'message channel':
			case 'message channels':
			case 'message channelroles': //;message channelroles
				VarSave($guild_folder, "rolepicker_channel_id.php", strval($author_channel_id)); //Make this channel the rolepicker channel
				$author_channel->sendMessage($channelroles_message_text)->done(function ($new_message) use ($guild_folder, $channelroles, $message) {
					VarSave($guild_folder, "channelroles_message_id.php", strval($new_message->id));
					$add = function ($channelroles, $new_message) use (&$add) {
						if (count($channelroles) != 0) {
							$new_message->react(array_shift($channelroles))->done(function () use ($add, $channelroles, $new_message) {
								$add($channelroles, $new_message);
							});
						}
					};
					$add($channelroles, $new_message);
					return $message->delete();	
				});
				return;
				break;
			case 'message customroles': //;message customroles
				if($GLOBALS['debug_echo']) echo '[MESSAGE CUSTOMROLES]' . PHP_EOL;
				VarSave($guild_folder, "rolepicker_channel_id.php", strval($author_channel_id)); //Make this channel the rolepicker channel
				$author_channel->sendMessage($customroles_message_text)->done(function ($new_message) use ($guild_folder, $customroles, $message) { //React in order
					VarSave($guild_folder, "customroles_message_id.php", strval($new_message->id));
					$add = function ($customroles, $new_message) use (&$add) {
						if (count($customroles) != 0) {
							$new_message->react(array_shift($customroles))->done(function () use ($add, $customroles, $new_message) {
								$add($customroles, $new_message);
							});
						}
					};
					$add($customroles, $new_message);
					return $message->delete();	
				});
				return;
				break;
		//Toggles
			case 'react':
				if (!CheckFile($guild_folder, "react_option.php")) {
					VarSave($guild_folder, "react_option.php", $react_option);
					if($GLOBALS['debug_echo']) echo "[NEW REACT OPTION FILE]";
				}
				$react_var = VarLoad($guild_folder, "react_option.php");
				$react_flip = !$react_var;
				VarSave($guild_folder, "react_option.php", $react_flip);
				if ($react) $message->react("👍");
				if ($react_flip) return $message->reply("Reaction functions enabled!");
				else return $message->reply("Reaction functions disabled!");
				break;
			case 'vanity': //toggle vanity functions ;vanity
				if (!CheckFile($guild_folder, "vanity_option.php")) {
					VarSave($guild_folder, "vanity_option.php", $vanity_option);
					if($GLOBALS['debug_echo']) echo "[NEW VANITY OPTION FILE]" . PHP_EOL;
				}
				$vanity_var = VarLoad($guild_folder, "vanity_option.php");
				$vanity_flip = !$vanity_var;
				VarSave($guild_folder, "vanity_option.php", $vanity_flip);
				if ($react) $message->react("👍");
				if ($vanity_flip) return $message->reply("Vanity functions enabled!");
				else return $message->reply("Vanity functions disabled!");
				break;
			case 'nsfw':
				if (!CheckFile($guild_folder, "nsfw_option.php")) {
					VarSave($guild_folder, "nsfw_option.php", $nsfw_option);
					if($GLOBALS['debug_echo']) echo "[NEW NSFW OPTION FILE]" . PHP_EOL;
				}
				$nsfw_var = VarLoad($guild_folder, "nsfw_option.php");
				$nsfw_flip = !$nsfw_var;
				VarSave($guild_folder, "nsfw_option.php", $nsfw_flip);
				if ($react) $message->react("👍");
				if ($nsfw_flip ) return $message->reply("NSFW functions enabled!");
				else return $message->reply("NSFW functions disabled!");
				break;
			case 'games':
				if (!CheckFile($guild_folder, "games_option.php")) {
					VarSave($guild_folder, "games_option.php", $games_option);
					if($GLOBALS['debug_echo']) echo "[NEW GAMES OPTION FILE]" . PHP_EOL;
				}
				$games_var = VarLoad($guild_folder, "games_option.php");
				$games_flip = !$games_var;
				VarSave($guild_folder, "games_option.php", $games_flip);
				if ($react) $message->react("👍");
				if ($games_flip) return $message->reply("Games functions enabled!");
				else return $message->reply("Games functions disabled!");
				break;
			case 'rolepicker':
				if (!CheckFile($guild_folder, "rolepicker_option.php")) {
					VarSave($guild_folder, "rolepicker_option.php", $rolepicker_option);
					if($GLOBALS['debug_echo']) echo "[NEW ROLEPICKER FILE]" . PHP_EOL;
				}
				$rolepicker_var = VarLoad($guild_folder, "rolepicker_option.php");
				$rolepicker_flip = !$rolepicker_var;
				VarSave($guild_folder, "rolepicker_option.php", $rolepicker_flip);
				if ($react) $message->react("👍");
				if ($rolepicker_flip) return $message->reply("Rolepicker enabled!");
				else return $message->reply("Rolepicker disabled!");
				break;
			case 'gamerole':
			case 'gameroles':
				if (!CheckFile($guild_folder, "gameroles_option.php")) {
					VarSave($guild_folder, "gameroles_option.php", $gameroles_option);
					if($GLOBALS['debug_echo']) echo "[NEW GAME ROLES OPTION FILE]" . PHP_EOL;
				}
				$gameroles_var = VarLoad($guild_folder, "gameroles_option.php");
				$gameroles_flip = !$gameroles_var;
				VarSave($guild_folder, "gameroles_option.php", $gameroles_flip);
				if ($react) $message->react("👍");
				if ($gameroles_flip) return $message->reply("Game role functions enabled!");
				else return $message->reply("Game role functions disabled!");
				break;
			case 'species':
				if (!CheckFile($guild_folder, "species_option.php")) {
					VarSave($guild_folder, "species_option.php", $species_option);
					if($GLOBALS['debug_echo']) echo "[NEW SPECIES FILE]" . PHP_EOL;
				}
				$species_var = VarLoad($guild_folder, "species_option.php");
				$species_flip = !$species_var;
				VarSave($guild_folder, "species_option.php", $species_flip);
				if ($react) $message->react("👍");
				if ($species_flip) return $message->reply("Species roles enabled!");
				else return $message->reply("Species roles	disabled!");
				break;
			case 'gender':
				if (!CheckFile($guild_folder, "gender_option.php")) {
					VarSave($guild_folder, "gender_option.php", $gender_option);
					if($GLOBALS['debug_echo']) echo "[NEW GENDER FILE]" . PHP_EOL;
				}
				$gender_var = VarLoad($guild_folder, "gender_option.php");
				$gender_flip = !$gender_var;
				VarSave($guild_folder, "gender_option.php", $gender_flip);
				if ($react) $message->react("👍");
				if ($gender_flip) return $message->reply("Gender roles enabled!");
				else return $message->reply("Gender roles disabled!");
				break;
			case 'pronoun':
			case 'pronouns':
				if (!CheckFile($guild_folder, "pronouns_option.php")) {
					VarSave($guild_folder, "pronouns_option.php", $pronouns_option);
					if($GLOBALS['debug_echo']) echo "[NEW pronouns FILE]" . PHP_EOL;
				}
				$pronouns_var = VarLoad($guild_folder, "pronouns_option.php");
				$pronouns_flip = !$pronouns_var;
				VarSave($guild_folder, "pronouns_option.php", $pronouns_flip);
				if ($react) $message->react("👍");
				if ($pronouns_flip) {
					$message->reply("Pronoun roles enabled!");
				} else {
					$message->reply("Pronoun roles disabled!");
				}
				return;
				break;
			case 'sexuality':
				if (!CheckFile($guild_folder, "sexuality_option.php")) {
					VarSave($guild_folder, "sexuality_option.php", $sexuality_option);
					if($GLOBALS['debug_echo']) echo "[NEW SEXUALITY FILE]" . PHP_EOL;
				}
				$sexuality_var = VarLoad($guild_folder, "sexuality_option.php");
				$sexuality_flip = !$sexuality_var;
				VarSave($guild_folder, "sexuality_option.php", $sexuality_flip);
				if ($react) $message->react("👍");
				if ($sexuality_flip) return $message->reply("Sexuality roles enabled!");
				else return $message->reply("Sexuality roles disabled!");
				break;
			case 'channelrole':
			case 'channelroles':
				if (!CheckFile($guild_folder, "channel_option.php")) {
					VarSave($guild_folder, "channel_option.php", $channel_option);
					if($GLOBALS['debug_echo']) echo "[NEW CHANNELROLE FILE]" . PHP_EOL;
				}
				$channel_var = VarLoad($guild_folder, "channel_option.php");
				$channel_flip = !$channel_var;
				VarSave($guild_folder, "channel_option.php", $channel_flip);
				if ($react) $message->react("👍");
				if ($channel_flip) return $message->reply("Channel roles enabled!");
				else return $message->reply("Channel roles disabled!");
				break;
			case 'customroles':
				if (!CheckFile($guild_folder, "custom_option.php")) {
					VarSave($guild_folder, "custom_option.php", $custom_option);
					if($GLOBALS['debug_echo']) echo "[NEW CUSTOM ROLE OPTION FILE]" . PHP_EOL;
				}
				$custom_var = VarLoad($guild_folder, "custom_option.php");
				$custom_flip = !$custom_var;
				VarSave($guild_folder, "custom_option.php", $custom_flip);
				if ($react) $message->react("👍");
				if ($custom_flip) return $message->reply("Custom roles enabled!");
				else return $message->reply("Custom roles disabled!");
				break;
		}
		//End switch
		//Roles
		if (str_starts_with($message_content_lower, 'setup dev ')) {
			$filter = "setup dev ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = str_replace("<@&", "", $value);
			$value = str_replace(">", "", $value);
			$value = trim($value);
			if (is_numeric($value)) {
				VarSave($guild_folder, "role_dev_id.php", $value);
				return $message->reply("Developer role ID saved!");
			} else return $message->reply("Invalid input! Please enter an ID or @mention the role");
		}
		if (str_starts_with($message_content_lower, 'setup admin ')) {
			$filter = "setup admin ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = str_replace("<@&", "", $value);
			$value = str_replace(">", "", $value);
			$value = trim($value);
			if (is_numeric($value)) {
				VarSave($guild_folder, "role_admin_id.php", $value);
				return $message->reply("Admin role ID saved!");
			} else return $message->reply("Invalid input! Please enter an ID or @mention the role");
		}
		if (str_starts_with($message_content_lower, 'setup mod ')) {
			$filter = "setup mod ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = str_replace("<@&", "", $value);
			$value = str_replace(">", "", $value);
			$value = trim($value);
			if (is_numeric($value)) {
				VarSave($guild_folder, "role_mod_id.php", $value);
				return $message->reply("Moderator role ID saved!");
			} else return $message->reply("Invalid input! Please enter an ID or @mention the role");
		}
		if (str_starts_with($message_content_lower, 'setup bot ')) {
			$filter = "setup bot ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = str_replace("<@&", "", $value);
			$value = str_replace(">", "", $value);
			$value = trim($value);
			if (is_numeric($value)) {
				VarSave($guild_folder, "role_bot_id.php", $value);
				return $message->reply("Bot role ID saved!");
			} else return $message->reply("Invalid input! Please enter an ID or @mention the role");
		}
		if (str_starts_with($message_content_lower, 'setup vzgbot ')) {
			$filter = "setup vzgbot ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = str_replace("<@&", "", $value);
			$value = str_replace(">", "", $value);
			$value = trim($value);
			if (is_numeric($value)) {
				VarSave($guild_folder, "role_vzgbot_id.php", $value);
				return $message->reply("Palace Bot role ID saved!");
			} else return $message->reply("Invalid input! Please enter an ID or @mention the role");
		}
		if (str_starts_with($message_content_lower, 'setup muted ')) {
			$filter = "setup muted ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = str_replace("<@&", "", $value);
			$value = str_replace(">", "", $value);
			$value = trim($value);//if($GLOBALS['debug_echo']) echo "value: '$value';" . PHP_EOL;
			if (is_numeric($value)) {
				VarSave($guild_folder, "role_muted_id.php", $value);
				return $message->reply("Muted role ID saved!");
			} else return $message->reply("Invalid input! Please enter an ID or @mention the role");
		}
		if (str_starts_with($message_content_lower, 'setup verified ')) {
			$filter = "setup verified ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = str_replace("<@&", "", $value);
			$value = str_replace(">", "", $value);
			$value = trim($value);
			if (is_numeric($value)) {
				VarSave($guild_folder, "role_verified_id.php", $value);
				return$message->reply("Verified role ID saved!");
			} else return $message->reply("Invalid input! Please enter an ID or @mention the role");
		}
		if (str_starts_with($message_content_lower, 'setup adult ')) {
			$filter = "setup adult ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = str_replace("<@&", "", $value);
			$value = str_replace(">", "", $value);
			$value = trim($value);
			if (is_numeric($value)) {
				VarSave($guild_folder, "role_18_id.php", $value);
				return $message->reply("Adult role ID saved!");
			} else return $message->reply("Invalid input! Please enter an ID or @mention the role");
		}
		//Channels
		if (str_starts_with($message_content_lower, 'setup general ')) {
			$filter = "setup general ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = str_replace("<#", "", $value);
			$value = str_replace(">", "", $value);
			$value = trim($value);
			if (is_numeric($value)) {
				VarSave($guild_folder, "general_channel_id.php", $value);
				return $message->reply("General channel ID saved!");
			} else return $message->reply("Invalid input! Please enter a channel ID or <#mention> a channel");
		}
		if (str_starts_with($message_content_lower, 'setup welcome ')) {
			$filter = "setup welcome ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = str_replace("<#", "", $value);
			$value = str_replace(">", "", $value);
			$value = trim($value);
			if (is_numeric($value)) {
				VarSave($guild_folder, "welcome_public_channel_id.php", $value);
				return $message->reply("Welcome channel ID saved!");
			} else return $message->reply("Invalid input! Please enter a channel ID or <#mention> a channel");
		}
		if (str_starts_with($message_content_lower, 'setup welcomelog ')) {
			$filter = "setup welcomelog ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = str_replace("<#", "", $value);
			$value = str_replace(">", "", $value);
			$value = trim($value);
			if (is_numeric($value)) {
				VarSave($guild_folder, "welcome_log_channel_id.php", $value);
				return $message->reply("Welcome log channel ID saved!");
			} else return $message->reply("Invalid input! Please enter a channel ID or <#mention> a channel");
		}
		if (str_starts_with($message_content_lower, 'setup log ')) {
			$filter = "setup log ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = str_replace("<#", "", $value);
			$value = str_replace(">", "", $value);
			$value = trim($value);
			if (is_numeric($value)) {
				VarSave($guild_folder, "modlog_channel_id.php", $value);
				return $message->reply("Log channel ID saved!");
			} else return $message->reply("Invalid input! Please enter a channel ID or <#mention> a channel");
		}
		if (str_starts_with($message_content_lower, 'setup verify channel ')) {
			$filter = "setup verify channel ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = str_replace("<#", "", $value);
			$value = str_replace(">", "", $value);
			$value = trim($value);
			if (is_numeric($value)) {
				VarSave($guild_folder, "getverified_channel_id.php", $value);
				return $message->reply("Verify channel ID saved!");
			} else return $message->reply("Invalid input! Please enter a channel ID or <#mention> a channel");
		}
		if (str_starts_with($message_content_lower, 'setup verifylog ')) {
			$filter = "setup verifylog ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = str_replace("<#", "", $value);
			$value = str_replace(">", "", $value);
			$value = trim($value);
			if (is_numeric($value)) {
				VarSave($guild_folder, "verifylog_channel_id.php", $value);
				return $message->reply("Verifylog channel ID saved!");
			} else return $message->reply("Invalid input! Please enter a channel ID or <#mention> a channel");
		}
		if (str_starts_with($message_content_lower, 'setup watch ')) {
			$filter = "setup watch ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = str_replace("<#", "", $value);
			$value = str_replace(">", "", $value);
			$value = trim($value);
			if (is_numeric($value)) {
				VarSave($guild_folder, "watch_channel_id.php", $value);
				return $message->reply("Watch channel ID saved!");
			} else return $message->reply("Invalid input! Please enter a channel ID or <#mention> a channel");
		}
		if (str_starts_with($message_content_lower, 'setup rolepicker channel ')) {
			$filter = "setup rolepicker channel ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = str_replace("<#", "", $value);
			$value = str_replace(">", "", $value);
			$value = trim($value); //if($GLOBALS['debug_echo']) echo "value: " . $value . PHP_EOL;
			if (is_numeric($value)) {
				VarSave($guild_folder, "rolepicker_channel_id.php", $value);
				return $message->reply("Rolepicker channel ID saved!");
			} else return $message->reply("Invalid input! Please enter a channel ID or <#mention> a channel");
		}
		if (str_starts_with($message_content_lower, 'setup nsfw rolepicker channel ')) {
			$filter = "setup nsfw rolepicker channel ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = str_replace("<#", "", $value);
			$value = str_replace(">", "", $value);
			$value = trim($value); //if($GLOBALS['debug_echo']) echo "value: " . $value . PHP_EOL;
			if (is_numeric($value)) {
				VarSave($guild_folder, "nsfw_rolepicker_channel_id.php", $value);
				return $message->reply("NSFW Rolepicker channel ID saved!");
			} else return $message->reply("Invalid input! Please enter a channel ID or <#mention> a channel");
		}
		if (str_starts_with($message_content_lower, 'setup games rolepicker channel ')) {
			$filter = "setup games rolepicker channel ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = str_replace("<#", "", $value);
			$value = str_replace(">", "", $value);
			$value = trim($value); //if($GLOBALS['debug_echo']) echo "value: " . $value . PHP_EOL;
			if (is_numeric($value)) {
				VarSave($guild_folder, "games_rolepicker_channel_id.php", $value);
				return $message->reply("Games Rolepicker channel ID saved!");
			} else return $message->reply("Invalid input! Please enter a channel ID or <#mention> a channel");
		}
		if (str_starts_with($message_content_lower, 'setup games channel ')) {
			$filter = "setup games channel ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = str_replace("<#", "", $value);
			$value = str_replace(">", "", $value);
			$value = trim($value); //if($GLOBALS['debug_echo']) echo "value: " . $value . PHP_EOL;
			if (is_numeric($value)) {
				VarSave($guild_folder, "games_channel_id.php", $value);
				return $message->reply("Games channel ID saved!");
			} else return $message->reply("Invalid input! Please enter a channel ID or <#mention> a channel");
		}
		if (str_starts_with($message_content_lower, 'setup gameroles ')) {
			$filter = "setup gameroles ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = str_replace("<#", "", $value);
			$value = str_replace(">", "", $value);
			$value = trim($value); //if($GLOBALS['debug_echo']) echo "value: " . $value . PHP_EOL;
			if (is_numeric($value)) {
				VarSave($guild_folder, "gameroles_message_id.php", $value);
				return$message->reply("Game roles channel ID saved!");
			} else return $message->reply("Invalid input! Please enter a channel ID or <#mention> a channel");
		}
		if (str_starts_with($message_content_lower, 'setup suggestion pending ')) {
			$filter = "setup suggestion pending ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = str_replace("<#", "", $value);
			$value = str_replace(">", "", $value);
			$value = trim($value); //if($GLOBALS['debug_echo']) echo "value: " . $value . PHP_EOL;
			if (is_numeric($value)) {
				VarSave($guild_folder, "suggestion_pending_channel_id.php", $value);
				return $message->reply("Suggestion pending channel ID saved!");
			} else return $message->reply("Invalid input! Please enter a channel ID or <#mention> a channel");
		}
		if (str_starts_with($message_content_lower, 'setup suggestion approved ')) {
			$filter = "setup suggestion approved ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = str_replace("<#", "", $value);
			$value = str_replace(">", "", $value);
			$value = trim($value); //if($GLOBALS['debug_echo']) echo "value: " . $value . PHP_EOL;
			if (is_numeric($value)) {
				VarSave($guild_folder, "suggestion_approved_channel_id.php", $value);
				return $message->reply("Suggestion approved channel ID saved!");
			} else return $message->reply("Invalid input! Please enter a channel ID or <#mention> a channel");
		}
		if (str_starts_with($message_content_lower, 'setup tip pending ')) {
			$filter = "setup tip pending ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = str_replace("<#", "", $value);
			$value = str_replace(">", "", $value);
			$value = trim($value); //if($GLOBALS['debug_echo']) echo "value: " . $value . PHP_EOL;
			if (is_numeric($value)) {
				VarSave($guild_folder, "tip_pending_channel_id.php", $value);
				return $message->reply("Tip pending channel ID saved!");
			} else return $message->reply("Invalid input! Please enter a channel ID or <#mention> a channel");
		}
		if (str_starts_with($message_content_lower, 'setup tip approved ')) {
			$filter = "setup tip approved ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = str_replace("<#", "", $value);
			$value = str_replace(">", "", $value);
			$value = trim($value); //if($GLOBALS['debug_echo']) echo "value: " . $value . PHP_EOL;
			if (is_numeric($value)) {
				VarSave($guild_folder, "tip_approved_channel_id.php", $value);
				return $message->reply("Tip approved channel ID saved!");
			} else return $message->reply("Invalid input! Please enter a channel ID or <#mention> a channel");
		}
		
		//Users
		if (str_starts_with($message_content_lower, 'setup rolepicker ')) {
			$filter = "setup rolepicker ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = str_replace("<@!", "", $value);
			$value = str_replace("<@", "", $value);
			$value = str_replace("<@", "", $value);
			$value = str_replace(">", "", $value);
			$value = trim($value); //if($GLOBALS['debug_echo']) echo "value: " . $value . PHP_EOL;
			if (is_numeric($value)) {
				VarSave($guild_folder, "rolepicker_id.php", $value);
				return $message->reply("Rolepicker user ID saved!");
			} else return $message->reply("Invalid input! Please enter an ID or @mention the user");
		}
		//Messages
		if (str_starts_with($message_content_lower, 'setup species ')) {
			$filter = "setup species ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = trim($value);
			if (is_numeric($value)) {
				VarSave($guild_folder, "species_message_id.php", $value);
				return $message->reply("Species message ID saved!");
			} else return $message->reply("Invalid input! Please enter a message ID");
		}
		if (str_starts_with($message_content_lower, 'setup species2 ')) {
			$filter = "setup species2 ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = trim($value);
			if (is_numeric($value)) {
				VarSave($guild_folder, "species2_message_id.php", $value);
				return $message->reply("Species2 message ID saved!");
			} else return $message->reply("Invalid input! Please enter a message ID");
		}
		if (str_starts_with($message_content_lower, 'setup species3 ')) {
			$filter = "setup species3 ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = trim($value);
			if (is_numeric($value)) {
				VarSave($guild_folder, "species3_message_id.php", $value);
				return $message->reply("Species3 message ID saved!");
			} else return $message->reply("Invalid input! Please enter a message ID");
		}
		if (str_starts_with($message_content_lower, 'setup gender ')) {
			$filter = "setup gender ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = trim($value);
			if (is_numeric($value)) {
				VarSave($guild_folder, "gender_message_id.php", $value);
				return $message->reply("Gender message ID saved!");
			} else return $message->reply("Invalid input! Please enter a message ID");
		}
		if (str_starts_with($message_content_lower, 'setup sexuality ')) {
			$filter = "setup sexuality ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = trim($value);
			if (is_numeric($value)) {
				VarSave($guild_folder, "sexuality_message_id.php", $value);
				return $message->reply("Sexuality message ID saved!");
			} else return $message->reply("Invalid input! Please enter a message ID");
		}
		if (str_starts_with($message_content_lower, 'setup channelroles ')) {
			$filter = "setup channelroles ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = trim($value);
			if (is_numeric($value)) {
				VarSave($guild_folder, "channelroles_message_id.php", $value);
				return $message->reply("Channel roles message ID saved!");
			} else return $message->reply("Invalid input! Please enter a message ID");
		}
		if (str_starts_with($message_content_lower, 'setup customroles ')) {
			$filter = "setup customroles ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = trim($value);
			if (is_numeric($value)) {
				VarSave($guild_folder, "customroles_message_id.php", $value);
				return $message->reply("Custom roles message ID saved!");
			} else return $message->reply("Invalid input! Please enter a message ID");
		}
		
		if (str_starts_with($message_content_lower, 'toggles')) {
			$builder = Discord\Builders\MessageBuilder::new();
			
			$select = Discord\Builders\Components\SelectMenu::new()
				->addOption(Discord\Builders\Components\Option::new('Reactions'))
				->addOption(Discord\Builders\Components\Option::new('Vanity'))
				->addOption(Discord\Builders\Components\Option::new('NSFW'))
				->addOption(Discord\Builders\Components\Option::new('Games'))
				->addOption(Discord\Builders\Components\Option::new('Rolepicker'))
				->addOption(Discord\Builders\Components\Option::new('Species Roles'))
				->addOption(Discord\Builders\Components\Option::new('Gender Roles'))
				->addOption(Discord\Builders\Components\Option::new('Pronoun Roles'))
				->addOption(Discord\Builders\Components\Option::new('Sexuality Roles'))
				->addOption(Discord\Builders\Components\Option::new('Channel Roles'))
				->addOption(Discord\Builders\Components\Option::new('Game Roles'))
				->addOption(Discord\Builders\Components\Option::new('Custom Roles'))
				;
			
			$select->setListener(function (Discord\Parts\Interactions\Interaction $interaction, Discord\Helpers\Collection $options) use ($author_id, $guild_folder, $react_option, $vanity_option, $nsfw_option, $channel_option, $games_option, $gameroles_option) {
				if ($interaction->user->id != $author_id) return;
				foreach ($options as $option) $choice = $option->getLabel();
				
				$bit = '';
				switch($choice) { //File name parser
					case 'Reactions':
						$bit = 'react';
						break;
					case 'Gender Roles':
						$bit = 'gender';
						break;
					case 'Pronoun Roles':
						$bit = 'pronouns';
						break;
					case 'Sexuality Roles':
						$bit = 'sexuality';
						break;
					case 'Channel Roles':
						$bit = 'channel';
						break;
					case 'Game Roles':
						$bit = 'gameroles';
						break;
					case 'Custom Roles':
						$bit = 'custom';
						break;
					default:
						$bit = $choice;
				}
				$bit = trim(strtolower($bit));
				$bit_option = trim(strtolower($bit)) . '_option';
				
				if (!CheckFile($guild_folder, "$bit_option.php")) {
					VarSave($guild_folder, "$bit_option.php", $$bit_option);
					if($GLOBALS['debug_echo']) echo "[NEW $choice FILE]" . PHP_EOL;
				}
				$bit_var = VarLoad($guild_folder, "$bit_option.php");
				$bit_flip = !$bit_var;
				VarSave($guild_folder, "$bit_option.php", $bit_flip);
				if ($bit_flip) return $interaction->respondWithMessage(Discord\Builders\MessageBuilder::new()->setContent("$choice enabled!"), true);
				return $interaction->respondWithMessage(Discord\Builders\MessageBuilder::new()->setContent("$choice disabled!"), true);
			}, $discord);
			$builder->addComponent($select);
			
			$row = Discord\Builders\Components\ActionRow::new();
			$button = Discord\Builders\Components\Button::new(Discord\Builders\Components\Button::STYLE_SUCCESS);
			$button->setLabel('Done');
			$button->setListener(function (Discord\Parts\Interactions\Interaction $interaction) use ($author_id) {
				if ($interaction->user->id != $author_id) return;
				$interaction->message->delete();
			}, $discord, true);
			$row->addComponent($button);
			$builder->addComponent($row);
			
			$builder->setContent('Server Toggles');
			$builder->setReplyTo($message);
			$message->channel->sendMessage($builder);
		}
		if (str_starts_with($message_content_lower, 'settings')) {
			$builder = Discord\Builders\MessageBuilder::new();
			
			$row = Discord\Builders\Components\ActionRow::new();
			$button = Discord\Builders\Components\Button::new(Discord\Builders\Components\Button::STYLE_SUCCESS);
			$button->setLabel('Current Setup');
			$button->setListener(function (Discord\Parts\Interactions\Interaction $interaction) use ($discord, $author_id, $author_guild, $author_guild_name, $guild_folder,
			$role_dev_id, $role_admin_id, $role_mod_id, $role_bot_id, $role_vzgbot_id, $role_muted_id, $role_verified_id, $role_18_id, $rolepicker_id,
			$welcome_public_channel_id, $welcome_log_channel_id, $verifylog_channel_id, $watch_channel_id,
			$welcome_public_channel, $welcome_log_channel, $modlog_channel, $getverified_channel, $verifylog_channel, $watch_channel,
			$general_channel, $rolepicker_channel, $nsfw_rolepicker_channel, $games_rolepicker_channel, $games_channel,
			$suggestion_pending_channel, $suggestion_approved_channel, $tip_pending_channel, $tip_approved_channel,
			$gameroles_message_id, $species_message_id, $species2_message_id, $species3_message_id, $gender_message_id, $pronouns_message_id, $sexuality_message_id, $nsfw_message_id,
			$channelroles_message_id, $customroles_message_id, 
			$command_symbol, $react, $vanity, $nsfw, $games, $rp0, $rp1, $rp2, $rp3, $rp4, $rp5, $channeloption, $gamerole
			) {
				if ($interaction->user->id != $author_id) return;
				//Roles
				$documentation = "⠀\n**Roles:**\n";
				$documentation = $documentation . "`dev @role` <@&$role_dev_id>\n";
				$documentation = $documentation . "`admin @role` <@&$role_admin_id>\n";
				$documentation = $documentation . "`mod @role` <@&$role_mod_id>\n";
				$documentation = $documentation . "`bot @role` <@&$role_bot_id>\n";
				$documentation = $documentation . "`vzg @role` <@&$role_vzgbot_id>\n";
				$documentation = $documentation . "`muted @role` <@&$role_muted_id>\n";
				$documentation = $documentation . "`verified @role` <@&$role_verified_id>\n";
				$documentation = $documentation . "`adult @role` <@&$role_18_id>\n";
				//User
				$documentation = $documentation . "**Users:**\n";
				$documentation = $documentation . "`rolepicker @user` <@$rolepicker_id>\n";
				//Channels
				$documentation = $documentation . "**Channels:**\n";
				$documentation = $documentation . "`general <#channel_id>` <#{$general_channel->id}>\n";
				if ($welcome_public_channel_id) {
					$welcome_public_channel = $author_guild->channels->get('id', $welcome_public_channel_id);
				}
				if ($welcome_log_channel_id) {
					$welcome_log_channel = $author_guild->channels->get('id', $welcome_log_channel_id);
				}
				if ($welcome_public_channel_id) {
					$documentation = $documentation . "`welcome <#channel_id>` <#{$welcome_public_channel->id}>\n";
				}
				$documentation = $documentation . "`welcomelog <#channel_id>` <#{$welcome_log_channel->id}>\n";
				$documentation = $documentation . "`log <#channel_id>` <#{$modlog_channel->id}>\n";
				$documentation = $documentation . "`verify channel <#channel_id>` <#{$getverified_channel->id}>\n";
				if ($verifylog_channel_id) {
					$documentation = $documentation . "`verifylog <#channel_id>` <#{$verifylog_channel->id}>\n";
				} else {
					$documentation = $documentation . "`verifylog <#channel_id>` (defaulted to log channel)\n";
				}
				if ($watch_channel_id) {
					$documentation = $documentation . "`watch <#channel_id>` <#{$watch_channel->id}>\n";
				} else {
					$documentation = $documentation . "`watch <#channel_id>` (defaulted to direct message only)\n";
				}
				$documentation = $documentation . "`rolepicker channel <#channel_id>`  <#{$rolepicker_channel->id}>\n";
				$documentation = $documentation . "`nsfw rolepicker channel <#channel_id>`  <#{$nsfw_rolepicker_channel->id}>\n";
				$documentation = $documentation . "`games rolepicker channel <#channel_id>`  <#{$games_rolepicker_channel->id}>\n";
				$documentation = $documentation . "`games channel <#channel_id>`  <#{$games_channel->id}>\n";
				$documentation = $documentation . "`suggestion pending <#channel_id>` <#{$suggestion_pending_channel->id}>\n";
				$documentation = $documentation . "`suggestion approved <#channel_id>` <#{$suggestion_approved_channel->id}>\n";
				$documentation = $documentation . "`tip pending <#channel_id>` <#{$tip_pending_channel->id}>\n";
				$documentation = $documentation . "`tip approved <#channel_id>` <#{$tip_approved_channel->id}>\n";
				//Messages
				$documentation = $documentation . "**Messages:**\n";
				if ($gameroles_message_id) {
					$documentation = $documentation . "`gameroles messageid` $gameroles_message_id\n";
				} else {
					$documentation = $documentation . "`gameroles messageid` Message not yet sent!\n";
				}
				if ($species_message_id) {
					$documentation = $documentation . "`species messageid` $species_message_id\n";
				} else {
					$documentation = $documentation . "`species messageid` Message not yet sent!\n";
				}
				if ($species2_message_id) {
					$documentation = $documentation . "`species2 messageid` $species2_message_id\n";
				} else {
					$documentation = $documentation . "`species2 messageid` Message not yet sent!\n";
				}
				if ($species3_message_id) {
					$documentation = $documentation . "`species3 messageid` $species3_message_id\n";
				} else {
					$documentation = $documentation . "`species3 messageid` Message not yet sent!\n";
				}
				if ($gender_message_id) {
					$documentation = $documentation . "`gender messageid` $gender_message_id\n";
				} else {
					$documentation = $documentation . "`gender messageid` Message not yet sent!\n";
				}
				if ($pronouns_message_id) {
					$documentation = $documentation . "`prnouns messageid` $pronouns_message_id\n";
				} else {
					$documentation = $documentation . "`pronouns messageid` Message not yet sent!\n";
				}				
				if ($sexuality_message_id) {
					$documentation = $documentation . "`sexuality messageid` $sexuality_message_id\n";
				} else {
					$documentation = $documentation . "`sexuality messageid` Message not yet sent!\n";
				}
				if ($nsfw_message_id) {
					$documentation = $documentation . "`nsfw messageid` $nsfw_message_id\n";
				} else {
					$documentation = $documentation . "`nsfw messageid` Message not yet sent!\n";
				}
				if ($channelroles_message_id) {
					$documentation = $documentation . "`channelroles messageid` $channelroles_message_id\n";
				} else {
					$documentation = $documentation . "`channelroles messageid` Message not yet sent!\n";
				}
				if ($customroles_message_id) {
					$documentation = $documentation . "`customroles messageid` $customroles_message_id\n";
				} else {
					$documentation = $documentation . "`customroles messageid` Message not yet sent!\n";
				}
				
				$documentation_sanitized = str_replace("\n", "", $documentation);
				$doc_length = strlen($documentation_sanitized); if($GLOBALS['debug_echo']) echo "doc_length: " . $doc_length . PHP_EOL;
				if ($doc_length < 1024) {
					$embed = new \Discord\Parts\Embed\Embed($discord);
					$embed
						->setTitle("Current Settings for `$author_guild_name`")														// Set a title
						->setColor(0xe1452d)																	// Set a color (the thing on the left side)
						->setDescription("$documentation")														// Set a description (below title, above fields)
						->setFooter("Palace Bot by Valithor#5947")							 					// Set a footer without icon
						->setURL("");							 												// Set the URL
					if($GLOBALS['debug_echo']) echo "embed class: " . get_class($embed) . PHP_EOL;
					return $interaction->respondWithMessage(Discord\Builders\MessageBuilder::new()->addEmbed($embed), true);
				} else return $interaction->respondWithMessage(Discord\Builders\MessageBuilder::new()->setContent($documentation), true);
			}, $discord, true);
			$row->addComponent($button);
			$button = Discord\Builders\Components\Button::new(Discord\Builders\Components\Button::STYLE_SUCCESS);
			$button->setLabel('Settings');
			$button->setListener(function (Discord\Parts\Interactions\Interaction $interaction) use ($discord, $author_id, $author_guild, $author_guild_name, $guild_folder,
			$role_dev_id, $role_admin_id, $role_mod_id, $role_bot_id, $role_vzgbot_id, $role_muted_id, $role_verified_id, $role_18_id, $rolepicker_id,
			$welcome_public_channel_id, $welcome_log_channel_id, $verifylog_channel_id, $watch_channel_id,
			$welcome_public_channel, $welcome_log_channel, $modlog_channel, $getverified_channel, $verifylog_channel, $watch_channel,
			$general_channel, $rolepicker_channel, $nsfw_rolepicker_channel, $games_rolepicker_channel, $games_channel,
			$suggestion_pending_channel, $suggestion_approved_channel, $tip_pending_channel, $tip_approved_channel,
			$gameroles_message_id, $species_message_id, $species2_message_id, $species3_message_id, $gender_message_id, $pronouns_message_id, $sexuality_message_id, $nsfw_message_id,
			$channelroles_message_id, $customroles_message_id, 
			$command_symbol, $react, $vanity, $nsfw, $games, $rp0, $rp1, $rp2, $rp3, $rp4, $rp5, $channeloption, $gamerole
			) {
				if ($interaction->user->id != $author_id) return;
				$documentation = "Command symbol: $command_symbol\n";
				$documentation = $documentation . "\nBot options:\n";
				//react
				$documentation = $documentation . "`react:` ";
				if ($react) $documentation = $documentation . "**Enabled**\n";
				else $documentation = $documentation . "**Disabled**\n";
				//vanity
				$documentation = $documentation . "`vanity:` ";
				if ($vanity) $documentation = $documentation . "**Enabled**\n";
				else $documentation = $documentation . "**Disabled**\n";
				//nsfw
				$documentation = $documentation . "`nsfw:` ";
				if ($nsfw) $documentation = $documentation . "**Enabled**\n";
				else $documentation = $documentation . "**Disabled**\n";
				//games
				$documentation = $documentation . "`games:` ";
				if ($games) $documentation = $documentation . "**Enabled**\n";
				else $documentation = $documentation . "**Disabled**\n";
				
				//rolepicker
				$documentation = $documentation . "`\nrolepicker:` ";
				if ($rp0) $documentation = $documentation . "**Enabled**\n";
				else $documentation = $documentation . "**Disabled**\n";
				
				if (!$rp0) $documentation = $documentation . "~~"; //Strikeout invalid options 
				//gameroles
				$documentation = $documentation . "`game roles:` ";
				if ($gamerole) $documentation = $documentation . "**Enabled**\n";
				else $documentation = $documentation . "**Disabled**\n";
				//species
				$documentation = $documentation . "`species:` ";
				if ($rp1) $documentation = $documentation . "**Enabled**\n";
				else $documentation = $documentation . "**Disabled**\n";
				//gender
				$documentation = $documentation . "`gender:` ";
				if ($rp2) $documentation = $documentation . "**Enabled**\n";
				else $documentation = $documentation . "**Disabled**\n";
				//prnouns
				$documentation = $documentation . "`pronouns:` ";
				if ($rp5) $documentation = $documentation . "**Enabled**\n";
				else $documentation = $documentation . "**Disabled**\n";
				//sexuality
				$documentation = $documentation . "`sexuality:` ";
				if ($rp3) $documentation = $documentation . "**Enabled**\n";
				else $documentation = $documentation . "**Disabled**\n";
				//channel roles
				$documentation = $documentation . "`channel roles:` ";
				if ($channeloption) $documentation = $documentation . "**Enabled**\n";
				else $documentation = $documentation . "**Disabled**\n";
				//customrole
				$documentation = $documentation . "`customrole:` ";
				if ($rp4) $documentation = $documentation . "**Enabled**\n";
				else $documentation = $documentation . "**Disabled**\n";
				if (!$rp0) $documentation = $documentation . "~~"; //Strikeout invalid options
			
				$doc_length = strlen($documentation);
				if ($doc_length < 1024) {
					$embed = new \Discord\Parts\Embed\Embed($discord);
					$embed
						->setTitle("Current Settings For `$author_guild_name`")														// Set a title
						->setColor(0xe1452d)																	// Set a color (the thing on the left side)
						->setDescription("$documentation")														// Set a description (below title, above fields)
						->setFooter("Palace Bot by Valithor#5947")							 					// Set a footer without icon
						->setURL("");							 												// Set the URL
					if($GLOBALS['debug_echo']) echo "embed class: " . get_class($embed) . PHP_EOL;
					return $interaction->respondWithMessage(Discord\Builders\MessageBuilder::new()->addEmbed($embed), true);
				} else return $interaction->respondWithMessage(Discord\Builders\MessageBuilder::new()->setContent($documentation), true);
			}, $discord, true);
			$row->addComponent($button);
			$builder->addComponent($row);
			
			
			$row = Discord\Builders\Components\ActionRow::new();
			$button = Discord\Builders\Components\Button::new(Discord\Builders\Components\Button::STYLE_SUCCESS);
			$button->setLabel('Done');
			$button->setListener(function (Discord\Parts\Interactions\Interaction $interaction) use ($author_id) {
				if ($interaction->user->id != $author_id) return;
				$interaction->message->delete();
			}, $discord, true);
			$row->addComponent($button);
			$builder->addComponent($row);
			
			$builder->setContent('Server Settings');
			$builder->setReplyTo($message);
			$message->channel->sendMessage($builder);
			$message->delete();
		}
	}

	/*
	*********************
	*********************
	Server Setup Functions
	*********************
	*********************
	*/

	if ($message_content_lower == 'invite') { //;invite
		$author_channel->sendMessage($discord->application->getInviteURLAttribute('8'));
	} 
	if ($message_content_lower == 'help') { //;help
		$documentation ="\n`;invite` sends a DM with an OAuth2 link to invite Palace Bot to your server\n";
		$documentation = $documentation . "**\nCommand symbol: $command_symbol**\n";
		if ($creator || $owner) {
			$documentation = $documentation . "\n__**Owner:**__\n";
			//Website BCP
			$documentation = $documentation . "`bcp (add/rem/list)` allows another users access to the bot control panel on valzargaming.com\n";
		}
		if ($creator || $owner || $dev) { //toggle options
			$documentation = $documentation . "\n__**Owner / Dev:**__\n";
			$documentation = $documentation . "`toggles` Change current toggle settings\n";
			/*
			//react
			$documentation = $documentation . "`react`\n";
			//vanity
			$documentation = $documentation . "`vanity`\n";
			//nsfw
			$documentation = $documentation . "`nsfw`\n";
			//games
			$documentation = $documentation . "`games`\n";
			//rolepicker
			$documentation = $documentation . "`rolepicker`\n";
			//game roles
			$documentation = $documentation . "`gameroles`\n";
			//species
			$documentation = $documentation . "`species`\n";
			//gender
			$documentation = $documentation . "`gender`\n";
			//sexuality
			$documentation = $documentation . "`sexuality`\n";
			//customrole
			$documentation = $documentation . "`customrole`\n";
			*/
			//TODO:
			//tempmute/tm
		}
		if ($creator || $owner || $dev || $admin) {
			$documentation = $documentation . "\n__**High Staff:**__\n";
			//current settings
			$documentation = $documentation . "`settings` View current settings\n";
			
			//v
			if (!$role_verified_id) $documentation = $documentation . "~~";
			$documentation = $documentation . "`v` or `verify` gives the verified role\n";
			if (!$role_verified_id) $documentation = $documentation . "~~";
			//cv
			if (!$getverified_channel) $documentation = $documentation . "~~";
			$documentation = $documentation . "`cv` or `clearv` clears the verification channel and posts a short notice\n";
			if (!$getverified_channel) $documentation = $documentation . "~~";
			//clearall
			$documentation = $documentation . "`clearall` clears the current channel of up to 100 messages\n";
			//clear #
			$documentation = $documentation . "`clear #` clears the current channel of # messages\n";
			//watch
			$documentation = $documentation . "`watch` sends a direct message to the author whenever the mentioned sends a message\n";
			//unwatch
			$documentation = $documentation . "`unwatch` removes the effects of the watch command\n";
			//vwatch
			if (!$role_verified_id) $documentation = $documentation . "~~";
			$documentation = $documentation . "`vw` or `vwatch` gives the verified role to the mentioned and watches them\n";
			if (!$role_verified_id) $documentation = $documentation . "~~";
			//warn
			$documentation = $documentation . "`warn <@user_id> reason` logs an infraction\n";
			//infractions
			$documentation = $documentation . "`infractions <@user_id>` replies with a list of infractions\n";
			//removeinfraction
			$documentation = $documentation . "`removeinfraction <@user_id> #`\n";
			//kick
			$documentation = $documentation . "`kick <@user_id> reason`\n";
			//ban
			$documentation = $documentation . "`ban <@user_id> reason`\n";
			//unban
			$documentation = $documentation . "`unban <@user_id>`\n";
			//Strikeout invalid options
			if (!$suggestion_pending_channel) $documentation = $documentation . "~~";
			//suggest approve
			$documentation = $documentation . "`suggest approve #`\n";
			//suggest deny
			$documentation = $documentation . "`suggest deny #`\n";
			//Strikeout invalid options
			if (!$suggestion_pending_channel) $documentation = $documentation . "~~";
			if (!$tip_pending_channel) $documentation = $documentation . "~~";
			//tip approve
			$documentation = $documentation . "`tip approve #`\n";
			//tip deny
			$documentation = $documentation . "`tip deny #`\n";
			if (!$tip_pending_channel) $documentation = $documentation . "~~";
			
		}
		if ($creator || $owner || $dev || $admin || $mod) {
			$documentation = $documentation . "\n__**Moderators:**__\n";
			//Strikeout invalid options
			if (!$role_muted_id) $documentation = $documentation . "~~"; //Strikeout invalid options
			//mute/m
			$documentation = $documentation . "`mute <@user_id> reason`\n";
			//unmute
			$documentation = $documentation . "`unmute <@user_id> reason`\n";
			//Strikeout invalid options
			if (!$role_muted_id) $documentation = $documentation . "~~"; //Strikeout invalid options
			//whois
			$documentation = $documentation . "`whois` displays known info about a user\n";
			//lookup
			$documentation = $documentation . "`lookup` retrieves a username#discriminator using either a discord id or mention\n";
		}
		if ($vanity) {
			$documentation = $documentation . "\n__**Vanity:**__\n";
			//cooldown
			$documentation = $documentation . "`cooldown` or `cd` tells you how much time must pass before using another command \n";
			//hug/snuggle
			$documentation = $documentation . "`hug` or `snuggle`\n";
			//kiss/smooch
			$documentation = $documentation . "`kiss` or `smooch`\n";
			//nuzzle
			$documentation = $documentation . "`nuzzle`\n";
			//boop
			$documentation = $documentation . "`boop`\n";
			//bap
			$documentation = $documentation . "`bap`\n";
			//bap
			$documentation = $documentation . "`pet`\n";
		}
		if ($nsfw && $adult) {
			//TODO
		}
		if ($games) {
			$documentation = $documentation . "\n__**Games:**__\n";
			//yahtzee
			$documentation = $documentation . "`yahtzee start` starts a new game\n";
			$documentation = $documentation . "`yahtzee end` ends the game and deletes all progress\n";
			$documentation = $documentation . "`yahtzee pause`\n";
			$documentation = $documentation . "`yahtzee resume`\n";
			//roll
			$documentation = $documentation . "`roll #d#(+/-#)`\n";
		}
		//All other functions
		$documentation = $documentation . "\n__**General:**__\n";
		//ping
		$documentation = $documentation . "`ping` replies with 'Pong!'\n";
		//roles / roles @
		$documentation = $documentation . "`roles` displays the roles for the author or user being mentioned\n";
		//avatar
		$documentation = $documentation . "`avatar` displays the avatar of the author or user being mentioned\n";
		//poll
		$documentation = $documentation . "`poll # message` creates a message for people to vote on\n";
		//remindme
		$documentation = $documentation . "`remindme #` send a DM after # of seconds have passed\n";
		//suggest
		if (!$suggestion_pending_channel) $documentation = $documentation . "~~";
		$documentation = $documentation . "`suggest` posts a suggestion for staff to vote on\n";
		if (!$suggestion_pending_channel) $documentation = $documentation . "~~";
		//tip
		if (!$tip_pending_channel) $documentation = $documentation . "~~";
		$documentation = $documentation . "`tip` posts a tip for staff to vote on\n";
		if (!$tip_pending_channel) $documentation = $documentation . "~~";

		$documentation_sanitized = str_replace("\n", "", $documentation);
		$doc_length = strlen($documentation);
		if ($doc_length <= 2048) {
			$embed = new \Discord\Parts\Embed\Embed($discord);
			$embed
				->setTitle("Commands for $author_guild_name")											// Set a title
				->setColor(0xe1452d)																	// Set a color (the thing on the left side)
				->setDescription("$documentation")														// Set a description (below title, above fields)
	//					->addFieldValues("⠀", "$documentation")														// New line after this
	//					->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
	//					->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')			 	// Set an image (below everything except footer)
	//					->setTimestamp()																	 	// Set a timestamp (gets shown next to footer)
	//					->setAuthor("$author_check", "$author_guild_avatar")  									// Set an author with icon
				->setFooter("Palace Bot by Valithor#5947")							 					// Set a footer without icon
				->setURL("");							 												// Set the URL
	//				Open a DM channel then send the rich embed message
			return $message->channel->sendMessage('', false, $embed)->then(
				null,
				function ($error) use ($author_user, $documentation, $message) {
					$author_user->getPrivateChannel()->done(
						function($author_dmchannel) use ($documentation, $message) {
							$handle = fopen('help.txt', 'w+');
							fwrite($handle, $documentation);
							fclose($handle);
							$message->channel->sendFile('help.txt')->done(
								function ($result) {
									unlink('help.txt');
								},
								function ($error) use ($message) {
									unlink('help.txt');
									$message->reply("Unable to send you a DM! Please check your privacy settings and try again.");
								}
							);
						},
						function ($error) use ($message) {
							$message->reply("Unable to send you a DM! Please check your privacy settings and try again.");
						}
					);
				}
			);
		} else {
			return $author_user->getPrivateChannel()->done(
				function($author_dmchannel) use ($documentation, $message) {
					$handle = fopen('help.txt', 'w+');
					fwrite($handle, $documentation);
					fclose($handle);
					$message->channel->sendFile('help.txt')->done(
						function ($result) {
							unlink('help.txt');
						},
						function ($error) use ($message) {
							unlink('help.txt');
							$message->reply("Unable to send you a DM! Please check your privacy settings and try again.");
						}
					);
				},
				function ($error) use ($message) {
					$message->reply("Unable to send you a DM! Please check your privacy settings and try again.");
				}
			);
		}
	}
	
	/*
	*********************
	*********************
	Creator/Owner option functions
	*********************
	*********************
	*/

	/*
	*********************
	*********************
	Twitch Commands
	*********************
	*********************
	*/
	if (str_starts_with($message_content_lower, 'join #')) return $twitch->joinChannel(explode(' ', str_replace('join #', "", $message_content_lower))[0]);
	if (str_starts_with($message_content_lower, 'leave #')) return $twitch->leaveChannel(explode(' ', str_replace('leave #', "", $message_content_lower))[0]);

	/*
	*********************
	*********************
	Gerneral command functions
	*********************
	*********************
	*/

	if ($nsfw) { //This currently doesn't serve a purpose
		if ($message_content_lower == '18+') {
			if ($adult) {
				if ($react) $message->react("👍");
				return $message->reply("You have the 18+ role!");
			} else {
				if ($react) $message->react("👎");
				return $message->reply("You do NOT have the 18+ role!");
			}
		}
	}
	if ($games) {
		if ( is_null($games_channel_id) || ($author_channel_id == $games_channel_id) ) { //Commands that can only be used in the dedicated games channel
			//yahtzee
			include '..\yahtzee.php';
			//machi koro
			//include_once (getcwd() . "/machikoro/classes.php");
			//include (getcwd() . "/machikoro/game.php");
		}
		
		/*Commands that can be used anywhere*/
		
		//ymdhis cooldown time
		$spam_limit['year'] = 0;
		$spam_limit['month'] = 0;
		$spam_limit['day'] = 0;
		$spam_limit['hour'] = 0;
		$spam_limit['min'] = 0;
		$spam_limit['sec'] = 30;
		$spam_limit_seconds = TimeArrayToSeconds($spam_limit);
		if (str_starts_with($message_content_lower, 'roll ')) { //;roll #d#
			if($GLOBALS['debug_echo']) echo '[ROLL]' . PHP_EOL;
			$cooldown = CheckCooldownMem($author_id, "spam", $spam_limit);
			if (($cooldown[0]) || ($bypass)) {
				$filter = "roll ";
				$message_content_lower = str_replace($filter, "", $message_content_lower);
				$arr = explode('d', $message_content_lower);
				if($GLOBALS['debug_echo']) echo 'arr[0]: ' . $arr[0] . PHP_EOL;
				if($GLOBALS['debug_echo']) echo 'arr[1]: ' . $arr[1] . PHP_EOL;
				if(str_contains($arr[1], '+')) {
					$arr2 = explode('+', $arr[1]);
					$arr[1] = $arr2[0];
					$arr[2] = $arr2[1];
				}
				elseif(str_contains($arr[1], '-')) {
					$arr2 = explode('-', $arr[1]);
					$arr[1] = $arr2[0];
					$arr[2] = '-'.$arr2[1];
				}
				if($GLOBALS['debug_echo']) echo 'arr[2]: ' . $arr[2] . PHP_EOL;
				if( is_numeric($arr[0]) && is_numeric($arr[1]) ) {
					if ( ((int)$arr[0] < 1) || ((int)$arr[1] < 1) )
						return $message->reply('Die count and side count must be positive!');
					if (isset($arr[2]) && is_nan($arr[2]))
						return $message->reply('Modifier is not a valid number!');
					$count = (int)$arr[0];
					$side = (int)$arr[1];
					$mod = (int)$arr[2] ?? 0;
					$result = array();
					for ($x = 1; $x <= $count; $x++)
						$result[] = rand(1,(int)$side);
					if($GLOBALS['debug_echo']) echo 'result: '; var_dump($result); if($GLOBALS['debug_echo']) echo PHP_EOL;
					$sum = array_sum($result) + $mod;
					$output = "You rolled $sum!";
					foreach ($result as $roll) {
						$rolls .= "$roll, ";
					}
					if (isset($rolls)) {
						$rolls = substr(trim($rolls), 0, -1);
						$output .= "\n`$rolls`";
					}
					SetCooldownMem($author_id, "spam");
					return $message->reply($output);
				}else return $message->reply('Command must in #d#(+/-#) format!');
				
			}else{ //Reply with remaining time
				$waittime = ($spam_limit_seconds - $cooldown[1]);
				$formattime = FormatTime($waittime);
				if ($react) $message->react("👎");
				return $message->reply("You must wait $formattime before using the roll command again.");
			}
		}
	}
	if ($message_content_lower == 'ping') { //;ping
		if($GLOBALS['debug_echo']) echo '[PING]' . PHP_EOL;
		//$pingdiff = $message->timestamp->floatDiffInRealSeconds();
		//$message->reply("your message took $pingdiff to arrive.");
		return $message->reply("Pong!");
	}
	if ( ($message_content_lower == '_players') || ($message_content_lower == '_serverstatus') ) { //;players
		//Sends a message containing data for each server we host as collected from serverinfo.json
		//This method does not have to be called locally, so it can be moved to VZG Verifier
		if($GLOBALS['debug_echo']) echo "[SERVER STATE] $author_check" . PHP_EOL;
		$browser->get('http://192.168.1.175:8080/servers/serverinfo_get.php')->done( //Hosted on the website, NOT the bot's server
			function ($response) use ($author_channel, $discord, $message) {
				if($GLOBALS['debug_echo']) echo '[RESPONSE]' . PHP_EOL;
				include "../servers/serverinfo.php"; //$servers[1]["key"] = address / alias / port / servername
				if($GLOBALS['debug_echo']) echo '[RESPONSE SERVERINFO INCLUDED]' . PHP_EOL;
				$string = var_export((string)$response->getBody(), true);
				
				$data_json = json_decode($response->getBody());
				$desc_string_array = array();
				$desc_string = "";
				$server_state = array();
				foreach ($data_json as $varname => $varvalue) { //individual servers
					$varvalue = json_encode($varvalue);
					//if($GLOBALS['debug_echo']) echo "varname: " . $varname . PHP_EOL; //Index
					//if($GLOBALS['debug_echo']) echo "varvalue: " . $varvalue . PHP_EOL; //Json
					$server_state["$varname"] = $varvalue;
					
					$desc_string = $desc_string . $varname . ": " . urldecode($varvalue) . "\n";
					//if($GLOBALS['debug_echo']) echo "desc_string length: " . strlen($desc_string) . PHP_EOL;
					//if($GLOBALS['debug_echo']) echo "desc_string: " . $desc_string . PHP_EOL;
					$desc_string_array[] = $desc_string ?? "null";
					$desc_string = "";
				}
				
				$server_index[0] = "TDM" . PHP_EOL;
				$server_url[0] = "byond://51.254.161.128:1714";
				$server_index[1] = "Nomads" . PHP_EOL;
				$server_url[1] = "byond://51.254.161.128:1715";
				$server_index[2] = "Persistence" . PHP_EOL;
				$server_url[2] = "byond://69.140.47.22:1717";
				$server_index[3] = "Blue Colony" . PHP_EOL;
				$server_url[3] = "byond://69.140.47.22:7777";
				$server_state_dump = array(); // new assoc array for use with the embed
				
				$embed = new \Discord\Parts\Embed\Embed($discord);
				foreach ($server_index as $index => $servername) {
					$assocArray = json_decode($server_state[$index], true);
					foreach ($assocArray as $key => $value) {
						$value = urldecode($value);
						//if($GLOBALS['debug_echo']) echo "$key:$value" . PHP_EOL;
						$playerlist = "";
						if($key/* && $value && ($value != "unknown")*/)
							switch($key) {
								case "version": //First key if online
									//$server_state_dump[$index]["Status"] = "Online";
									$server_state_dump[$index]["Server"] = "<" . $server_url[$index] . "> " . PHP_EOL . $server_index[$index]/* . " **(Online)**"*/;
									break;
								case "ERROR": //First key if offline
									//$server_state_dump[$index]["Status"] = "Offline";
									$server_state_dump[$index]["Server"] = "" . $server_url[$index] . " " . PHP_EOL . $server_index[$index] . " (Offline)"; //Don't show offline
									break;
								case "host":
									if( ($value == NULL) || ($value == "") ) {
										$server_state_dump[$index]["Host"] = "Taislin";
									}elseif(strpos($value, 'Guest')!==false){
										$server_state_dump[$index]["Host"] = "ValZarGaming";
									}else $server_state_dump[$index]["Host"] = $value;
									break;
								/*case "players":
									$server_state_dump[$index]["Player Count"] = $value;
									break;*/
								case "age":
									//"Epoch", urldecode($serverinfo[0]["Epoch"])
									$server_state_dump[$index]["Epoch"] = $value;
									break;
								case "season":
									//"Season", urldecode($serverinfo[0]["Season"])
									$server_state_dump[$index]["Season"] = $value;
									break;
								case "map":
									//"Map", urldecode($serverinfo[0]["Map"]);
									$server_state_dump[$index]["Map"] = $value;
									break;
								case "roundduration":
									$rd = explode (":", $value);
									$remainder = ($rd[0] % 24);
									$rd[0] = floor($rd[0] / 24);
									if( ($rd[0] != 0) || ($remainder != 0) || ($rd[1] != 0) ) { //Round is starting
										$rt = $rd[0] . "d " . $remainder . "h " . $rd[1] . "m";
									}else{
										$rt = null; //"STARTING";
									}
									$server_state_dump[$index]["Round Time"] = $rt;
									//
									break;
								case "stationtime":
									$rd = explode (":", $value);
									$remainder = ($rd[0] % 24);
									$rd[0] = floor($rd[0] / 24);
									if( ($rd[0] != 0) || ($remainder != 0) || ($rd[1] != 0) ) { //Round is starting
										$rt = $rd[0] . "d " . $remainder . "h " . $rd[1] . "m";
									}else{
										$rt = null; //"STARTING";
									}
									//$server_state_dump[$index]["Station Time"] = $rt;
									break;
								case "cachetime":
									$server_state_dump[$index]["Cache Time"] = gmdate("F j, Y, g:i a", $value) . " GMT";
								default:
									if ((substr($key, 0, 6) == "player") && ($key != "players") ) {
										$server_state_dump[$index]["Players"][] = $value;
										//$playerlist = $playerlist . "$varvalue, ";
										//"Players", urldecode($serverinfo[0]["players"])
									}
									break;
							}
					}
				}
				//Build the embed message
				//if($GLOBALS['debug_echo']) echo "server_state_dump count:" . count($server_state_dump) . PHP_EOL;
				for($x=0; $x < count($server_state_dump)+1; $x++) { //+1 because we commented Persistence
					//if($GLOBALS['debug_echo']) echo "x: " . $x . PHP_EOL;
					if(is_array($server_state_dump[$x]))
					foreach ($server_state_dump[$x] as $key => $value) { //Status / Byond / Host / Player Count / Epoch / Season / Map / Round Time / Station Time / Players
						if($key && $value)
						if(is_array($value)) {
							$output_string = implode(', ', $value);
							$embed->addFieldValues($key . " (" . count($value) . ")", $output_string, true);
						}elseif($key == "Host") {
							if(strpos($value, "(Offline") == false)
							$embed->addFieldValues($key, $value, true);
						}elseif($key == "Cache Time") {
							//$embed->addFieldValues($key, $value, true);
						}elseif($key == "Server") {
							$embed->addFieldValues($key, $value, false);
						}else{
							$embed->addFieldValues($key, $value, true);
						}
					}
				}
				if($GLOBALS['debug_echo']) echo '[RESPONSE FOR LOOP DONE]' . PHP_EOL;
				//Finalize the embed
				$embed
					->setColor(0xe1452d)
					->setTimestamp()
					->setFooter("Palace Bot by Valithor#5947")
					->setURL("");
				$author_channel->sendEmbed($embed)->done(
					null,
					function ($error) use ($message) {
						var_dump($error->getMessage());
						$message->react("👎");
					}
				);
				if($GLOBALS['debug_echo']) echo '[RESPONSE SEND EMBED DONE]' . PHP_EOL;
				if($GLOBALS['debug_echo']) echo '[RESPONSE RETURNING]' . PHP_EOL;
				return;
			}, function ($error) use ($discord, $message) {
				if($GLOBALS['debug_echo']) echo '[ERROR]' . PHP_EOL;
				var_dump($error->getMessage());
				$message->react("❌");
				$discord->getChannel('315259546308444160')->sendMessage("<@116927250145869826>, Webserver is down! " . $message->link); //Alert Valithor
			}
		);
		return;
	}
	if (str_starts_with($message_content_lower, 'remindme ')) { //;remindme
		if($GLOBALS['debug_echo']) echo '[REMINDER]'. PHP_EOL;
		$arr = explode(' ', $message_content_lower);
		//$filter = "remindme ";
		//$value = str_replace($filter, "", $message_content_lower);
		if (! is_numeric($arr[1])) return $message->reply("Invalid input! Please use the format `;remindme #` where # is seconds.");
		
		var_dump($arr);
		$switch = ['second', 'seconds', 'minute', 'minutes', 'hour', 'hours', 'day', 'days'];
		if (count($arr) > 2 && is_numeric($arr[1]) && in_array($arr[2], $switch)) {
			$total_time = 0;
			switch ($arr[2]) {
				case 'second':
				case 'seconds':
					$total_time = $arr[1];
					break;
				case 'minute':
				case 'minutes':
					$total_time = $arr[1] * 60;
					break;
				case 'hour':
				case 'hours':
					$total_time = $arr[1] * 3600;
					break;
				case 'day':
				case 'days':
					$total_time = $arr[1] * 86400;
					break;
				default:
					return $message->reply('NYI');
			}
			if ($total_time > 0) $message->reply("I'll remind you in $total_time seconds.");
			else return $message->reply('Total time must be a positive integer!');
			$discord->getLoop()->addTimer($total_time, function() use ($message, $author_id, $string) {
				return $message->channel->sendMessage("<@$author_id>, This is your requested reminder!" . PHP_EOL . "`{$message->content}`", false, null, false, $message);
			});
			return;
		} else {
			$string = trim(substr($message_content, strpos($message_content,' ')+1+strlen($arr[1])));
			$discord->getLoop()->addTimer($arr[1], function() use ($message, $author_id, $string) {
				return $message->channel->sendMessage("<@$author_id>, This is your requested reminder!" . PHP_EOL . "`{$message->content}`", false, null, false, $message);
			});
			
			if ($react) $message->react("👍");
			return $message->reply("I'll remind you in " . FormatTime($arr[1]) . '.');
		}
	}	
	if ($message_content_lower == 'roles') { //;roles
		if($GLOBALS['debug_echo']) echo "[GET AUTHOR ROLES]" . PHP_EOL;
		//	Build the string for the reply
		$author_role_name_queue 									= "";
		//	$author_role_name_queue_full 								= "Here's a list of roles for you:" . PHP_EOL;
		foreach ($author_member_roles_ids as $author_role) {
			$author_role_name_queue 								= "$author_role_name_queue<@&$author_role> ";
		}
		$author_role_name_queue 									= substr($author_role_name_queue, 0, -1);
		$author_role_name_queue_full 								= PHP_EOL . $author_role_name_queue;
		//	Send the message
		if ($react) $message->react("👍");
		//	$message->reply($author_role_name_queue_full . PHP_EOL);
		//	Build the embed
		$embed = new \Discord\Parts\Embed\Embed($discord);
		$embed
	//		->setTitle("Roles")																		// Set a title
			->setColor(0xe1452d)																	// Set a color (the thing on the left side)
			->setDescription("$author_guild_name")												// Set a description (below title, above fields)
			->addFieldValues("Roles", "$author_role_name_queue_full")								// New line after this if ,true
			
			->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
	//		->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')			 	// Set an image (below everything except footer)
			->setTimestamp()																	 	// Set a timestamp (gets shown next to footer)
			->setAuthor("$author_check", "$author_guild_avatar")  									// Set an author with icon
			->setFooter("Palace Bot by Valithor#5947")							 					// Set a footer without icon
			->setURL("");							 												// Set the URL
	//	Send the message
	//	We do not need another promise here, so we call done, because we want to consume the promise
		return $author_channel->sendEmbed($embed);
	}
	if (str_starts_with($message_content_lower, 'roles ')) {//;roles @
		if($GLOBALS['debug_echo']) echo "[GET MENTIONED ROLES]" . PHP_EOL;
		//	Get an array of people mentioned
		$mentions_arr 						= $message->mentions; 									//if($GLOBALS['debug_echo']) echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
		if (!strpos($message_content_lower, "<")) { //String doesn't contain a mention
			$filter = "roles ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = str_replace("<@!", "", $value);
			$value = str_replace("<@", "", $value);
			$value = str_replace(">", "", $value); //if($GLOBALS['debug_echo']) echo "value: " . $value . PHP_EOL;
			if (is_numeric($value)) {
				if (!preg_match('/^[0-9]{16,20}$/', $value)) return $message->react('❌');
				$mention_member				= $author_guild->members->get('id', $value);
				$mention_user				= $mention_member->user;
				$mentions_arr				= array($mention_user);
			} else return $message->reply("Invalid input! Please enter a valid ID or @mention the user");
			if (is_null($mention_member)) return $message->reply("Invalid input! Please enter an ID or @mention the user");
		}
		//$mention_role_name_queue_full								= "Here's a list of roles for the requested users:" . PHP_EOL;
		$mention_role_name_queue_default							= "";
		//	$mentions_arr_check = (array)$mentions_arr;																					//if($GLOBALS['debug_echo']) echo "mentions_arr_check: " . PHP_EOL; var_dump ($mentions_arr_check); //Shows the collection object
	//	$mentions_arr_check2 = empty((array) $mentions_arr_check);																	//if($GLOBALS['debug_echo']) echo "mentions_arr_check2: " . PHP_EOL; var_dump ($mentions_arr_check2); //Shows the collection object
		foreach ($mentions_arr as $mention_param) {																				//if($GLOBALS['debug_echo']) echo "mention_param: " . PHP_EOL; var_dump ($mention_param);
	//		id, username, discriminator, bot, avatar, email, mfaEnabled, verified, webhook, createdTimestamp
			$mention_param_encode 									= json_encode($mention_param); 									//if($GLOBALS['debug_echo']) echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
			$mention_json 											= json_decode($mention_param_encode, true); 					//if($GLOBALS['debug_echo']) echo "mention_json: " . PHP_EOL; var_dump($mention_json);
			$mention_id 											= $mention_json['id']; 											//if($GLOBALS['debug_echo']) echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
			$mention_username 										= $mention_json['username']; 									//if($GLOBALS['debug_echo']) echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
			
			$mention_discriminator 									= $mention_json['discriminator']; 								//if($GLOBALS['debug_echo']) echo "mention_discriminator: " . $mention_discriminator . PHP_EOL; //Just the discord ID
			$mention_check 											= $mention_username ."#".$mention_discriminator; 				//if($GLOBALS['debug_echo']) echo "mention_check: " . $mention_check . PHP_EOL; //Just the discord ID
			
			if ($mention_id != $discord->id) {
				//Get the roles of the mentioned user
				$target_guildmember 									= $message->guild->members->get('id', $mention_id); 	//This is a GuildMember object
				$target_guildmember_role_collection 					= $target_guildmember->roles;					//This is the Role object for the GuildMember
				
				//Get the avatar URL of the mentioned user
				$target_guildmember_user								= $target_guildmember->user;									//if($GLOBALS['debug_echo']) echo "member_class: " . get_class($target_guildmember_user) . PHP_EOL;
				$mention_avatar 										= "{$target_guildmember_user->avatar}";					//if($GLOBALS['debug_echo']) echo "mention_avatar: " . $mention_avatar . PHP_EOL;				//if($GLOBALS['debug_echo']) echo "target_guildmember_role_collection: " . (count($target_guildmember_role_collection)-1);
				
				//Populate arrays of the info we need
				//$target_guildmember_roles_names 						= array();
				$target_guildmember_roles_ids 							= array(); //Not being used here, but might as well grab it
				
				
				foreach ($target_guildmember_role_collection as $role) {
						//$target_guildmember_roles_names[] 				= $role->name; 													//if($GLOBALS['debug_echo']) echo "role[$x] name: " . PHP_EOL; //var_dump($role->name);
						$target_guildmember_roles_ids[] 				= $role->id; 													//if($GLOBALS['debug_echo']) echo "role[$x] id: " . PHP_EOL; //var_dump($role->id);
				}
				
				//Build the string for the reply
				//$mention_role_name_queue 								= "**$mention_id:** ";
				//$mention_role_id_queue 								= "**<@$mention_id>:**\n";
				foreach ($target_guildmember_roles_ids as $mention_role) {
					//$mention_role_name_queue 							= "$mention_role_name_queue$mention_role, ";
					$mention_role_id_queue 								= "$mention_role_id_queue<@&$mention_role> ";
				}
				//$mention_role_name_queue 								= substr($mention_role_name_queue, 0, -2); 		//Get rid of the extra ", " at the end
				$mention_role_id_queue 									= substr($mention_role_id_queue, 0, -1); 		//Get rid of the extra ", " at the end
				//$mention_role_name_queue_full 							= $mention_role_name_queue_full . PHP_EOL . $mention_role_name_queue;
				$mention_role_id_queue_full 							= PHP_EOL . $mention_role_id_queue;
			
				//Check if anyone had their roles changed
				//if ($mention_role_name_queue_default != $mention_role_name_queue) {
				if ($mention_role_name_queue_default != $mention_role_id_queue) {
					//Send the message
					if ($react) $message->react("👍");
					//$message->reply($mention_role_name_queue_full . PHP_EOL);
					//					Build the embed
					$embed = new \Discord\Parts\Embed\Embed($discord);
					$embed
		//						->setTitle("Roles")																		// Set a title
						->setColor(0xe1452d)																	// Set a color (the thing on the left side)
						->setDescription("$author_guild_name")												// Set a description (below title, above fields)
		//						->addFieldValues("Roles", 	"$mention_role_name_queue_full")								// New line after this
						->addFieldValues("Roles", "$mention_role_id_queue_full", true)							// New line after this
						
						->setThumbnail("$mention_avatar")														// Set a thumbnail (the image in the top right corner)
		//						->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')			 	// Set an image (below everything except footer)
						->setTimestamp()																	 	// Set a timestamp (gets shown next to footer)
						->setAuthor("$mention_check", "$author_guild_avatar")  									// Set an author with icon
						->setFooter("Palace Bot by Valithor#5947")							 					// Set a footer without icon
						->setURL("");							 												// Set the URL
		//					Send the message
		//					We do not need another promise here, so we call done, because we want to consume the promise
					return $author_channel->sendEmbed($embed);
				} else {
					if ($react) $message->react("👎");
					return $message->reply("Nobody in the guild was mentioned!");
				}
			}
		}
		//Foreach method didn't return, so nobody was mentioned
		return $message->reply("You need to mention someone!");
	}

	//ymdhis cooldown time
	$avatar_limit['year']	= 0;
	$avatar_limit['month']	= 0;
	$avatar_limit['day']	= 0;
	$avatar_limit['hour']	= 0;
	$avatar_limit['min']	= 10;
	$avatar_limit['sec']	= 0;
	$avatar_limit_seconds = TimeArrayToSeconds($avatar_limit);																		//if($GLOBALS['debug_echo']) echo "TimeArrayToSeconds: " . $avatar_limit_seconds . PHP_EOL;
	if ($message_content_lower == 'avatar') { //;avatar
		if($GLOBALS['debug_echo']) echo "[GET AUTHOR AVATAR]" . PHP_EOL;
		//$cooldown = CheckCooldown($author_folder, "avatar_time.php", $avatar_limit); //	Check Cooldown Timer
		$cooldown = CheckCooldownMem($author_id, 'avatar', $avatar_limit);
		if (($cooldown[0]) || ($bypass)) {
			$embed = new \Discord\Parts\Embed\Embed($discord);
			$embed
				->setColor(0xe1452d)																	// Set a color (the thing on the left side)
				->setImage("$author_avatar")			 													// Set an image (below everything except footer)
				->setTimestamp()																	 	// Set a timestamp (gets shown next to footer)
				->setAuthor("$author_check", "$author_guild_avatar")  									// Set an author with icon
				->setFooter("Palace Bot by Valithor#5947")							 					// Set a footer without icon
				->setURL("");							 												// Set the URL

			//SetCooldown($author_folder, "avatar_time.php");
			SetCooldownMem($author_id, "avatar");
			return $author_channel->sendEmbed($embed);
		} else {
			//		Reply with remaining time
			$waittime = $avatar_limit_seconds - $cooldown[1];
			$formattime = FormatTime($waittime);
			return $message->reply("You must wait $formattime before using this command again.");
		}
	}
	if (str_starts_with($message_content_lower, 'avatar ')) {//;avatar @
		if($GLOBALS['debug_echo']) echo "GETTING AVATAR FOR MENTIONED" . PHP_EOL;
		//$cooldown = CheckCooldown($author_folder, "avatar_time.php", $avatar_limit); //Check Cooldown Timer
		$cooldown = CheckCooldownMem($author_id, "avatar", $avatar_limit);
		if (($cooldown[0]) || ($bypass)) {
			$mentions_arr = $message->mentions; 									//if($GLOBALS['debug_echo']) echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
			if (!strpos($message_content_lower, "<")) { //String doesn't contain a mention
			$filter = "avatar ";
				$value = str_replace($filter, "", $message_content_lower);
				$value = str_replace("<@!", "", $value);
				$value = str_replace("<@", "", $value);
				$value = str_replace(">", "", $value);//if($GLOBALS['debug_echo']) echo "value: " . $value . PHP_EOL;
				if (is_numeric($value)) {
					if (!preg_match('/^[0-9]{16,20}$/', $value)) return $message->react('❌');
					$mention_member				= $author_guild->members->get('id', $value);
					$mention_user				= $mention_member->user;
					$mentions_arr				= array($mention_user);
				} else return $message->reply("Invalid input! Please enter a valid ID or @mention the user");
				if (is_null($mention_member)) return $message->reply("Invalid input! Please enter an ID or @mention the user");
			}
			foreach ($mentions_arr as $mention_param) {																				//if($GLOBALS['debug_echo']) echo "mention_param: " . PHP_EOL; var_dump ($mention_param);
	//			id, username, discriminator, bot, avatar, email, mfaEnabled, verified, webhook, createdTimestamp
				$mention_param_encode 								= json_encode($mention_param); 									//if($GLOBALS['debug_echo']) echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
				$mention_json 										= json_decode($mention_param_encode, true); 					//if($GLOBALS['debug_echo']) echo "mention_json: " . PHP_EOL; var_dump($mention_json);
				$mention_id 										= $mention_json['id']; 											//if($GLOBALS['debug_echo']) echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
				$mention_username 									= $mention_json['username']; 									//if($GLOBALS['debug_echo']) echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
				
				$mention_discriminator 								= $mention_json['discriminator']; 								//if($GLOBALS['debug_echo']) echo "mention_discriminator: " . $mention_discriminator . PHP_EOL; //Just the discord ID
				$mention_check 										= $mention_username ."#".$mention_discriminator; 				//if($GLOBALS['debug_echo']) echo "mention_check: " . $mention_check . PHP_EOL; //Just the discord ID

				if ($mention_id != $discord->id) {
		//			Get the avatar URL of the mentioned user
					$target_guildmember 								= $message->guild->members->get('id', $mention_id); 	//This is a GuildMember object
					$target_guildmember_user							= $target_guildmember->user;									//if($GLOBALS['debug_echo']) echo "member_class: " . get_class($target_guildmember_user) . PHP_EOL;
					$mention_avatar 									= "{$target_guildmember_user->avatar}";
					
					//			Build the embed
					$embed = new \Discord\Parts\Embed\Embed($discord);
					$embed
		//			->setTitle("Avatar")																	// Set a title
					->setColor(0xe1452d)																	// Set a color (the thing on the left side)
		//			->setDescription("$author_guild_name")													// Set a description (below title, above fields)
		//			->addFieldValues("Total Given", 		"$vanity_give_count")									// New line after this
						
		//			->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
					->setImage("$mention_avatar")			 												// Set an image (below everything except footer)
					->setTimestamp()																	 	// Set a timestamp (gets shown next to footer)
					->setAuthor("$mention_check", "$author_guild_avatar")  									// Set an author with icon
					->setFooter("Palace Bot by Valithor#5947")							 					// Set a footer without icon
					->setURL("");							 												// Set the URL
					
		//			Send the message
					//			Set Cooldown
					//SetCooldown($author_folder, "avatar_time.php");
					SetCooldownMem($author_id, "avatar");
					return $author_channel->sendEmbed($embed);
				}
			}
			//Foreach method didn't return, so nobody was mentioned
			return $message->reply("You need to mention someone!");
		} else {
			//		Reply with remaining time
			$waittime = $avatar_limit_seconds - $cooldown[1];
			$formattime = FormatTime($waittime);
			return $message->reply("You must wait $formattime before using this command again.");
		}
	}

	if ($suggestion_approved_channel != null) {
		if ($creator || $owner || $dev || $admin || $mod || $author_perms['kick_members']) {
			if ( (str_starts_with($message_content_lower, 'suggestion approve ')) || (str_starts_with($message_content_lower, 'suggest approve ')) ) { //;suggestion
				$filter = "suggestion approve ";
				$value = str_replace($filter, "", $message_content_lower);
				$filter = "suggest approve ";
				$value = str_replace($filter, "", $value);
				$pieces = explode(" ", $value);
				$valid = false;
				$nums = array();
				foreach ($pieces as $piece) {
					if (is_numeric($piece)) {
						if($GLOBALS['debug_echo']) echo "approve: $piece" . PHP_EOL;
						$nums[] = $piece;
						$valid = true;
					}
				}
				if (!$valid) return $message->reply("Invalid input! Please enter an integer number");
				foreach ($nums as $num) {
					//Get the message stored at the index
					if (!$array = VarLoad($guild_folder, "guild_suggestions.php")) return;
					if (isset($array[$num]) && ($array[$num] != "Approved") && ($array[$num] != "Denied")) {
						$embed = new \Discord\Parts\Embed\Embed($discord, $array[$num]);
						$suggestion_approved_channel->sendMessage("{$embed->title}", false, $embed, false)->done(function ($new_message) use ($guild_folder, $embed) {
							//Repost the suggestion
							$new_message->react("👍")->done(function($result) use ($new_message) {
								$new_message->react("👎");
							});
						});
						//Clear the value stored in the array
						$array[$num] = "Approved";
						if ($react) $message->react("👍");
						//Send a DM to the person who made the suggestion to let them know that it has been approved.
					} else {
						return $message->reply("Suggestion not found or already processed!");
					}
				}
				return; //catch
			}
			if ( (str_starts_with($message_content_lower, 'suggestion deny ')) || (str_starts_with($message_content_lower, 'suggest deny ')) ) { //;suggestion
				$filter = "suggestion deny ";
				$value = str_replace($filter, "", $message_content_lower);
				$filter = "suggest deny ";
				$value = str_replace($filter, "", $value);
				$pieces = explode(" ", $value);
				$valid = false;
				$nums = array();
				foreach ($pieces as $piece) {
					if (is_numeric($piece)) {
						if($GLOBALS['debug_echo']) echo "deny: $piece" . PHP_EOL;
						$nums[] = $piece;
						$valid = true;
					}
				}
				if (!$valid) return $message->reply("Invalid input! Please enter an integer number");
				foreach ($nums as $num) {
					//Get the message stored at the index
					if (!$array = VarLoad($guild_folder, "guild_suggestions.php")) return;
					if (($array[$num]) && ($array[$num] != "Approved") && ($array[$num] != "Denied")) {
						$embed = new \Discord\Parts\Embed\Embed($discord, $array[$num]);
						//Clear the value stored in the array
						$array[$num] = "Denied";
						if ($react) $message->react("👍");
					} else return $message->reply("Suggestion not found or already processed!");
				}
				return;
			}
		}
	}
	if (isset($suggestion_pending_channel)) {
		 if ( (str_starts_with($message_content_lower, 'suggestion ')) || (str_starts_with($message_content_lower, 'suggest ')) ) { //;suggestion
			//return;
			$filter = "suggestion ";
			$value = str_replace($filter, "", $message_content_lower);
			$filter = "suggest ";
			$value = str_replace($filter, "", $value);
			if (!$value) return $message->reply("Invalid input! Please enter text for your suggestion");
			//Build the embed message
			$message_sanitized = str_replace("*", "", $value);
			$message_sanitized = str_replace("_", "", $message_sanitized);
			$message_sanitized = str_replace("`", "", $message_sanitized);
			$message_sanitized = str_replace("\n", "", $message_sanitized);
			$doc_length = strlen($message_sanitized);
			if ($doc_length <= 2048) {
				//Find the size of $suggestions and get what will be the next number
				if (CheckFile($guild_folder, "guild_suggestions.php")) {
					$array = VarLoad($guild_folder, "guild_suggestions.php");
				}
				if ($array) {
					$array_count = sizeof($array);
				} else {
					$array_count = 0;
				}
				//Build the embed
				$embed = new \Discord\Parts\Embed\Embed($discord);
				$embed
				->setTitle("#$array_count")																// Set a title
				->setColor(0xe1452d)																	// Set a color (the thing on the left side)
				->setDescription("$message_sanitized")													// Set a description (below title, above fields)
				->setTimestamp()																	 	// Set a timestamp (gets shown next to footer)
				->setAuthor("$author_check ($author_id)", "$author_avatar")  							// Set an author with icon
				->setFooter("Palace Bot by Valithor#5947")							 					// Set a footer without icon
				->setURL("");							 												// Set the URL
			$suggestion_pending_channel->sendMessage("{$embed->title}", false, $embed, false)->done(function ($new_message) use ($guild_folder, $embed) {
				$new_message->react("👍")->done(
					function($result) use ($new_message) {
						$new_message->react("👎");
					},
					function ($error) use ($new_message) {
						var_dump($error->getMessage());
					}
				);
				//Save the suggestion somewhere
				$array = VarLoad($guild_folder, "guild_suggestions.php");
				$array[] = $embed->getRawAttributes();
				VarSave($guild_folder, "guild_suggestions.php", $array);
			});
			} else {
				$message->reply("Please shorten your suggestion!");
			}
			$message->reply("Your suggestion has been logged and is pending approval!")->done(function ($new_message) use ($discord, $message) {
				$message->delete(); //Delete the original ;suggestion message
				$discord->getLoop()->addTimer(10, function () use ($new_message) {
					return $new_message->delete(); //Delete message confirming the suggestion was logged
				});
				return;
			});
			return;
		}
	}
	if (isset($tip_approved_channel)) {
		if ($creator || $owner || $dev || $admin || $mod) {
			if (str_starts_with($message_content_lower, 'tip approve ')) { //;tip approve
				$filter = "tip approve ";
				$value = str_replace($filter, "", $message_content_lower);
				$pieces = explode(" ", $value); if($GLOBALS['debug_echo']) echo "pieces: "; var_dump($pieces); if($GLOBALS['debug_echo']) echo PHP_EOL;
				$valid = false;
				$nums = array();
				foreach ($pieces as $piece) {
					if (is_numeric($piece)) {
						if($GLOBALS['debug_echo']) echo "approve: " . (int)$piece . PHP_EOL;
						$nums[] = (int)$piece;
						$valid = true;
					}
				}
				if (!$valid) {
					return $message->reply("Invalid input! Please enter an integer number");
				}
				foreach ($nums as $num) {
					//Get the message stored at the index
					$array = VarLoad($guild_folder, "guild_tips.php");
					if (!$array) {
						return false;
					}
					if (($array[$num]) && ($array[$num] != "Approved") && ($array[$num] != "Denied")) {
						$embed = new \Discord\Parts\Embed\Embed($discord, $array[$num]);
						$tip_approved_channel->sendMessage("{$embed->title}", false, $embed)->done(function ($new_message) use ($guild_folder, $embed) {
							//Repost the tip
							$new_message->react("👍")->done(function($result) use ($new_message) {
								$new_message->react("👎");
							});
						});
						//Clear the value stored in the array
						$array[$num] = "Approved";
						if ($react) $message->react("👍");
						//Send a DM to the person who made the tip to let them know that it has been approved.
					} else {
						return $message->reply("Tip not found or already processed!");
					}
				}
				return; //catch
			}
			if (str_starts_with($message_content_lower, 'tip deny ')) { //;tip deny
				//return;
				$filter = "tip deny ";
				$value = str_replace($filter, "", $message_content_lower);
				$pieces = explode(" ", $value);
				$valid = false;
				$nums = array();
				foreach ($pieces as $piece) {
					if (is_numeric($piece)) {
						if($GLOBALS['debug_echo']) echo "deny: " . (int)$piece . PHP_EOL;
						$nums[] = (int)$piece;
						$valid = true;
					}
				}
				if (!$valid) {
					return $message->reply("Invalid input! Please enter an integer number");
				}
				foreach ($nums as $num) {
					//Get the message stored at the index
					$array = VarLoad($guild_folder, "guild_tips.php");
					if (!$array) {
						return false;
					}
					if (($array[$num]) && ($array[$num] != "Approved") && ($array[$num] != "Denied")) {
						$embed = new \Discord\Parts\Embed\Embed($discord, $array[$num]);
						//Clear the value stored in the array
						$array[$num] = "Denied";
						if ($react) $message->react("👍");
					} else return $message->reply("Tip not found or already processed!");
				}
				return;
			}
		}
	}
	if (isset($tip_pending_channel)) {
		if (str_starts_with($message_content_lower, 'tip ')) { //;tip
			//return;
			$filter = "tip ";
			$value = str_replace($filter, "", $message_content_lower);
			if (!$value) return $message->reply("Invalid input! Please enter text for your tip");
			//Build the embed message
			$message_sanitized = str_replace("*", "", $value);
			$message_sanitized = str_replace("_", "", $message_sanitized);
			$message_sanitized = str_replace("`", "", $message_sanitized);
			$message_sanitized = str_replace("\n", "", $message_sanitized);
			$doc_length = strlen($message_sanitized);
			if ($doc_length <= 2048) {
				//Find the size of $tips and get what will be the next number
				if (CheckFile($guild_folder, "guild_tips.php")) {
					$array = VarLoad($guild_folder, "guild_tips.php");
				}
				if ($array) {
					$array_count = sizeof($array);
				} else {
					$array_count = 0;
				}
				//Build the embed
				$embed = new \Discord\Parts\Embed\Embed($discord);
				$embed
				->setTitle("#$array_count")																// Set a title
				->setColor(0xe1452d)																	// Set a color (the thing on the left side)
				->setDescription("$message_sanitized")													// Set a description (below title, above fields)
				->setTimestamp()																	 	// Set a timestamp (gets shown next to footer)
				->setAuthor("$author_check ($author_id)", "$author_avatar")  							// Set an author with icon
				->setFooter("Palace Bot by Valithor#5947")							 					// Set a footer without icon
				->setURL("");							 												// Set the URL
			$tip_pending_channel->sendMessage("{$embed->title}", false, $embed, false)->done(function ($new_message) use ($guild_folder, $embed) {
				$new_message->react("👍")->done(function ($result) use ($new_message) {
					$new_message->react("👎");
				});
				//Save the tip somewhere
				$array = VarLoad($guild_folder, "guild_tips.php");
				$array[] = $embed->getRawAttributes();
				VarSave($guild_folder, "guild_tips.php", $array);
			});
			} else {
				$message->reply("Please shorten your tip!");
			}
			$message->reply("Your tip has been logged and is pending approval!")->done(function ($new_message) use ($discord, $message) {
				$message->delete(); //Delete the original ;tip message
				$discord->getLoop()->addTimer(10, function () use ($new_message) {
					$new_message->delete(); //Delete message confirming the tip was logged
					return;
				});
				return;
			});
			return;
		}
	}

	/*
	*********************
	*********************
	Mod/Admin command functions
	*********************
	*********************
	*/


	/*
	*********************
	*********************
	Vanity command functions
	*********************
	*********************
	*/
	if ($vanity) {
		//ymdhis cooldown time
		$vanity_limit['year'] = 0;
		$vanity_limit['month'] = 0;
		$vanity_limit['day'] = 0;
		$vanity_limit['hour'] = 0;
		$vanity_limit['min'] = 10;
		$vanity_limit['sec'] = 0;
		$vanity_limit_seconds = TimeArrayToSeconds($vanity_limit);
		//	Load author give statistics
		if (!CheckFile($author_folder, "vanity_give_count.php")) {
			$vanity_give_count	= 0;
		} else {
			$vanity_give_count = VarLoad($author_folder, "vanity_give_count.php");
		}
		if (!CheckFile($author_folder, "hugger_count.php")) {
			$hugger_count		= 0;
		} else {
			$hugger_count 	 = VarLoad($author_folder, "hugger_count.php");
		}
		if (!CheckFile($author_folder, "kisser_count.php")) {
			$kisser_count		= 0;
		} else {
			$kisser_count 	 = VarLoad($author_folder, "kisser_count.php");
		}
		if (!CheckFile($author_folder, "nuzzler_count.php")) {
			$nuzzler_count		= 0;
		} else {
			$nuzzler_count	 = VarLoad($author_folder, "nuzzler_count.php");
		}
		if (!CheckFile($author_folder, "booper_count.php")) {
			$booper_count		= 0;
		} else {
			$booper_count	 = VarLoad($author_folder, "booper_count.php");
		}
		if (!CheckFile($author_folder, "baper_count.php")) {
			$baper_count		= 0;
		} else {
			$baper_count	 = VarLoad($author_folder, "baper_count.php");
		}
		if (!CheckFile($author_folder, "peter_count.php")) {
			$peter_count		= 0;
		} else {
			$peter_count	 = VarLoad($author_folder, "peter_count.php");
		}

		//	Load author get statistics
		if (!CheckFile($author_folder, "vanity_get_count.php")) {
			$vanity_get_count	= 0;
		} else {
			$vanity_get_count  = VarLoad($author_folder, "vanity_get_count.php");
		}
		if (!CheckFile($author_folder, "hugged_count.php")) {
			$hugged_count		= 0;
		} else {
			$hugged_count 	 = VarLoad($author_folder, "hugged_count.php");
		}
		if (!CheckFile($author_folder, "kissed_count.php")) {
			$kissed_count		= 0;
		} else {
			$kissed_count 	 = VarLoad($author_folder, "kissed_count.php");
		}
		if (!CheckFile($author_folder, "nuzzled_count.php")) {
			$nuzzled_count		= 0;
		} else {
			$nuzzled_count	 = VarLoad($author_folder, "nuzzled_count.php");
		}
		if (!CheckFile($author_folder, "booped_count.php")) {
			$booped_count		= 0;
		} else {
			$booped_count	 = VarLoad($author_folder, "booped_count.php");
		}
		if (!CheckFile($author_folder, "baped_count.php")) {
			$baped_count		= 0;
		} else {
			$baped_count	 = VarLoad($author_folder, "baped_count.php");
		}
		if (!CheckFile($author_folder, "peted_count.php")) {
			$peted_count		= 0;
		} else {
			$peted_count	 = VarLoad($author_folder, "peted_count.php");
		}
		
		if (($message_content_lower == 'cooldown') || ($message_content_lower == 'cd')) {//;cooldown ;cd
			if($GLOBALS['debug_echo']) echo "[COOLDOWN CHECK]" . PHP_EOL;
			//		Check Cooldown Timer
			//$cooldown = CheckCooldown($author_folder, "vanity_time.php", $vanity_limit);
			$cooldown = CheckCooldownMem($author_id, "vanity", $vanity_limit);
			if (($cooldown[0]) || ($bypass)) {
				return $message->reply("No cooldown.");
			} else {
				//			Reply with remaining time
				$waittime = $avatar_limit_seconds - $cooldown[1];
				$formattime = FormatTime($waittime);
				return $message->reply("You must wait $formattime before using this command again.");
			}
		}
		if ( (str_starts_with($message_content_lower, 'hug ')) || (str_starts_with($message_content_lower, 'snuggle ')) ) { //;hug ;snuggle
			if($GLOBALS['debug_echo']) echo "[HUG/SNUGGLE]" . PHP_EOL;
			//		Check Cooldown Timer
			//$cooldown = CheckCooldown($author_folder, "vanity_time.php", $vanity_limit);
			$cooldown = CheckCooldownMem($author_id, "vanity", $vanity_limit);
			if (($cooldown[0]) || ($bypass)) {
				//			Get an array of people mentioned
				$mentions_arr 										= $message->mentions; 									//if($GLOBALS['debug_echo']) echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
				foreach ($mentions_arr as $mention_param) {
					$mention_param_encode 							= json_encode($mention_param); 									//if($GLOBALS['debug_echo']) echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
					$mention_json 									= json_decode($mention_param_encode, true); 					//if($GLOBALS['debug_echo']) echo "mention_json: " . PHP_EOL; var_dump($mention_json);
					$mention_id 									= $mention_json['id']; 											//if($GLOBALS['debug_echo']) echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
					
					if ($author_id != $mention_id && $mention_id != $discord->id) {
						$hug_messages								= array();
						$hug_messages[]								= "<@$author_id> has given <@$mention_id> a hug! How sweet!";
						$hug_messages[]								= "<@$author_id> saw that <@$mention_id> needed attention, so <@$author_id> gave them a hug!";
						$hug_messages[]								= "<@$author_id> gave <@$mention_id> a hug! Isn't this adorable?";
						$index_selection							= GetRandomArrayIndex($hug_messages);

						//Send the message
						$author_channel->sendMessage($hug_messages[$index_selection]);
						//Increment give stat counter of author
						$vanity_give_count++;
						VarSave($author_folder, "vanity_give_count.php", $vanity_give_count);
						$hugger_count++;
						VarSave($author_folder, "hugger_count.php", $hugger_count);
						//Load target get statistics
						if (!CheckFile($guild_folder."/".$mention_id, "vanity_get_count.php")) {
							$vanity_get_count	= 0;
						} else {
							$vanity_get_count  = VarLoad($guild_folder."/".$mention_id, "vanity_get_count.php");
						}
						if (!CheckFile($guild_folder."/".$mention_id, "hugged_count.php")) {
							$hugged_count		= 0;
						} else {
							$hugged_count 	 = VarLoad($guild_folder."/".$mention_id, "hugged_count.php");
						}
						//Increment get stat counter of target
						$vanity_get_count++;
						VarSave($guild_folder."/".$mention_id, "vanity_get_count.php", $vanity_get_count);
						$hugged_count++;
						VarSave($guild_folder."/".$mention_id, "hugged_count.php", $hugged_count);
						//					Set Cooldown
						//SetCooldown($author_folder, "vanity_time.php");
						SetCooldownMem($author_id, "vanity");
						return; //No more processing, we only want to process the first person mentioned
					} else {
						$self_hug_messages							= array();
						$self_hug_messages[]						= "<@$author_id> hugs themself. What a wierdo!";
						$index_selection							= GetRandomArrayIndex($self_hug_messages);
						//Send the message
						$author_channel->sendMessage($self_hug_messages[$index_selection]);
						//Increment give stat counter of author
						$vanity_give_count++;
						VarSave($author_folder, "vanity_give_count.php", $vanity_give_count);
						$hugger_count++;
						VarSave($author_folder, "hugger_count.php", $hugger_count);
						//Increment get stat counter of author
						$vanity_get_count++;
						VarSave($author_folder, "vanity_get_count.php", $vanity_get_count);
						$hugged_count++;
						VarSave($author_folder, "hugged_count.php", $hugged_count);
						//Set Cooldown
						//SetCooldown($author_folder, "vanity_time.php");
						SetCooldownMem($author_id, "vanity");
						return; //No more processing, we only want to process the first person mentioned
					}
				}
				//foreach method didn't return, so nobody was mentioned
				return $message->reply("You need to mention someone!");
			} else {
				//		Reply with remaining time
				$waittime = $vanity_limit_seconds - $cooldown[1];
				$formattime = FormatTime($waittime);
				$message->reply("You must wait $formattime before using vanity commands again.");
				return;
			}
		}
		if ( (str_starts_with($message_content_lower, 'kiss ')) || (str_starts_with($message_content_lower, 'smooch ')) ) { //;kiss ;smooch
			if($GLOBALS['debug_echo']) echo "[KISS]" . PHP_EOL;
			//		Check Cooldown Timer
			//$cooldown = CheckCooldown($author_folder, "vanity_time.php", $vanity_limit);
			$cooldown = CheckCooldownMem($author_id, "vanity", $vanity_limit);
			if (($cooldown[0]) || ($bypass)) {
				//			Get an array of people mentioned
				$mentions_arr 										= $message->mentions; 									//if($GLOBALS['debug_echo']) echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
				foreach ($mentions_arr as $mention_param) {
					$mention_param_encode 							= json_encode($mention_param); 									//if($GLOBALS['debug_echo']) echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
					$mention_json 									= json_decode($mention_param_encode, true); 					//if($GLOBALS['debug_echo']) echo "mention_json: " . PHP_EOL; var_dump($mention_json);
					$mention_id 									= $mention_json['id']; 											//if($GLOBALS['debug_echo']) echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
					
					if ($author_id != $mention_id && $mention_id != $discord->id) {
						$kiss_messages								= array();
						$kiss_messages[]							= "<@$author_id> put their nose to <@$mention_id>’s for a good old smooch! Now that’s cute!";
						$kiss_messages[]							= "<@$mention_id> was surprised when <@$author_id> leaned in and gave them a kiss! Hehe!";
						$kiss_messages[]							= "<@$author_id> has given <@$mention_id> the sweetest kiss on the cheek! Yay!";
						$kiss_messages[]							= "<@$author_id> gives <@$mention_id> a kiss on the snoot.";
						$kiss_messages[]							= "<@$author_id> rubs their snoot on <@$mention_id>, how sweet!";
						$index_selection							= GetRandomArrayIndex($kiss_messages);						//if($GLOBALS['debug_echo']) echo "random kiss_message: " . $kiss_messages[$index_selection];
						//					Send the message
						$author_channel->sendMessage($kiss_messages[$index_selection]);
						//Increment give stat counter of author
						$vanity_give_count++;
						VarSave($author_folder, "vanity_give_count.php", $vanity_give_count);
						$kisser_count++;
						VarSave($author_folder, "kisser_count.php", $kisser_count);
						//Load target get statistics
						if (!CheckFile($guild_folder."/".$mention_id, "vanity_get_count.php")) {
							$vanity_get_count	= 0;
						} else {
							$vanity_get_count  = VarLoad($guild_folder."/".$mention_id, "vanity_get_count.php");
						}
						if (!CheckFile($guild_folder."/".$mention_id, "kissed_count.php")) {
							$kissed_count		= 0;
						} else {
							$kissed_count 	 = VarLoad($guild_folder."/".$mention_id, "kissed_count.php");
						}
						//Increment get stat counter of target
						$vanity_get_count++;
						VarSave($guild_folder."/".$mention_id, "vanity_get_count.php", $vanity_get_count);
						$kissed_count++;
						VarSave($guild_folder."/".$mention_id, "kissed_count.php", $kissed_count);
	//					Set Cooldown
						//SetCooldown($author_folder, "vanity_time.php");
						SetCooldownMem($author_id, "vanity");
						return; //No more processing, we only want to process the first person mentioned
					} else {
						$self_kiss_messages							= array();
						$self_kiss_messages[]						= "<@$author_id> tried to kiss themselves in the mirror. How silly!";
						$index_selection							= GetRandomArrayIndex($self_kiss_messages);
						//Send the message
						$author_channel->sendMessage($self_kiss_messages[$index_selection]);
						//Increment give stat counter of author
						$vanity_give_count++;
						VarSave($author_folder, "vanity_give_count.php", $vanity_give_count);
						$kisser_count++;
						VarSave($author_folder, "kisser_count.php", $kisser_count);
						//Increment get stat counter of author
						$vanity_get_count++;
						VarSave($author_folder, "vanity_get_count.php", $vanity_get_count);
						$kissed_count++;
						VarSave($author_folder, "kissed_count.php", $kissed_count);
						//							Set Cooldown
						//SetCooldown($author_folder, "vanity_time.php");
						SetCooldownMem($author_id, "vanity");
						return; //No more processing, we only want to process the first person mentioned
					}
				}
				//foreach method didn't return, so nobody was mentioned
				return $message->reply("You need to mention someone!");
			} else {
				//					Reply with remaining time
				$waittime = $vanity_limit_seconds - $cooldown[1];
				$formattime = FormatTime($waittime);
				$message->reply("You must wait $formattime before using vanity commands again.");
				return;
			}
		}
		if (str_starts_with($message_content_lower, 'nuzzle ')) { //;nuzzle @
			if($GLOBALS['debug_echo']) echo "[NUZZLE]" . PHP_EOL;
			//		Check Cooldown Timer
			//$cooldown = CheckCooldown($author_folder, "vanity_time.php", $vanity_limit);
			$cooldown = CheckCooldownMem($author_id, "vanity", $vanity_limit);
			if (($cooldown[0]) || ($bypass)) {
				//			Get an array of people mentioned
				$mentions_arr 										= $message->mentions; 									//if($GLOBALS['debug_echo']) echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
				foreach ($mentions_arr as $mention_param) {
					$mention_param_encode 							= json_encode($mention_param); 									//if($GLOBALS['debug_echo']) echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
					$mention_json 									= json_decode($mention_param_encode, true); 					//if($GLOBALS['debug_echo']) echo "mention_json: " . PHP_EOL; var_dump($mention_json);
					$mention_id 									= $mention_json['id']; 											//if($GLOBALS['debug_echo']) echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
					
					if ($author_id != $mention_id && $mention_id != $discord->id) {
						$nuzzle_messages							= array();
						$nuzzle_messages[]							= "<@$author_id> nuzzled into <@$mention_id>’s neck! Sweethearts~ :blue_heart:";
						$nuzzle_messages[]							= "<@$mention_id> was caught off guard when <@$author_id> nuzzled into their chest! How cute!";
						$nuzzle_messages[]							= "<@$author_id> wanted to show <@$mention_id> some more affection, so they nuzzled into <@$mention_id>’s fluff!";
						$nuzzle_messages[]							= "<@$author_id> rubs their snoot softly against <@$mention_id>, look at those cuties!";
						$nuzzle_messages[]							= "<@$author_id> takes their snoot and nuzzles <@$mention_id> cutely.";
						$index_selection							= GetRandomArrayIndex($nuzzle_messages);
						//					if($GLOBALS['debug_echo']) echo "random nuzzle_messages: " . $nuzzle_messages[$index_selection];
						//					Send the message
						$author_channel->sendMessage($nuzzle_messages[$index_selection]);
						//Increment give stat counter of author
						$vanity_give_count++;
						VarSave($author_folder, "vanity_give_count.php", $vanity_give_count);
						$nuzzler_count++;
						VarSave($author_folder, "nuzzler_count.php", $nuzzler_count);
						//Load target get statistics
						if (!CheckFile($guild_folder."/".$mention_id, "vanity_get_count.php")) {
							$vanity_get_count	= 0;
						} else {
							$vanity_get_count  = VarLoad($guild_folder."/".$mention_id, "vanity_get_count.php");
						}
						if (!CheckFile($guild_folder."/".$mention_id, "nuzzled_count.php")) {
							$nuzzled_count		= 0;
						} else {
							$nuzzled_count 	 = VarLoad($guild_folder."/".$mention_id, "nuzzled_count.php");
						}
						//Increment get stat counter of target
						$vanity_get_count++;
						VarSave($guild_folder."/".$mention_id, "vanity_get_count.php", $vanity_get_count);
						$nuzzled_count++;
						VarSave($guild_folder."/".$mention_id, "nuzzled_count.php", $nuzzled_count);
						//					Set Cooldown
						//SetCooldown($author_folder, "vanity_time.php");
						SetCooldownMem($author_id, "vanity");
						return; //No more processing, we only want to process the first person mentioned
					} else {
						$self_nuzzle_messages						= array();
						$self_nuzzle_messages[]						= "<@$author_id> curled into a ball in an attempt to nuzzle themselves.";
						$index_selection							= GetRandomArrayIndex($self_nuzzle_messages);
						//					Send the mssage
						$author_channel->sendMessage($self_nuzzle_messages[$index_selection]);
						//Increment give stat counter of author
						$vanity_give_count++;
						VarSave($author_folder, "vanity_give_count.php", $vanity_give_count);
						$nuzzler_count++;
						VarSave($author_folder, "nuzzler_count.php", $nuzzler_count);
						//Increment get stat counter of author
						$vanity_get_count++;
						VarSave($author_folder, "vanity_get_count.php", $vanity_get_count);
						$nuzzled_count++;
						VarSave($author_folder, "nuzzled_count.php", $nuzzled_count);
						//					Set Cooldown
						//SetCooldown($author_folder, "vanity_time.php");
						SetCooldownMem($author_id, "vanity");
						return; //No more processing, we only want to process the first person mentioned
					}
				}
				//Foreach method didn't return, so nobody was mentioned
				$message->reply("You need to mention someone!");
				return;
			} else {
				//					Reply with remaining time
				$waittime = $vanity_limit_seconds - $cooldown[1];
				$formattime = FormatTime($waittime);
				$message->reply("You must wait $formattime before using vanity commands again.");
				return;
			}
		}
		if (str_starts_with($message_content_lower, 'boop ')) { //;boop @
			if($GLOBALS['debug_echo']) echo "[BOOP]" . PHP_EOL;
			//		Check Cooldown Timer
			//$cooldown = CheckCooldown($author_folder, "vanity_time.php", $vanity_limit);
			$cooldown = CheckCooldownMem($author_id, "vanity", $vanity_limit);
			if (($cooldown[0]) || ($bypass)) {
				//			Get an array of people mentioned
				$mentions_arr 										= $message->mentions; 									//if($GLOBALS['debug_echo']) echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
				foreach ($mentions_arr as $mention_param) {
					$mention_param_encode 							= json_encode($mention_param); 									//if($GLOBALS['debug_echo']) echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
					$mention_json 									= json_decode($mention_param_encode, true); 					//if($GLOBALS['debug_echo']) echo "mention_json: " . PHP_EOL; var_dump($mention_json);
					$mention_id 									= $mention_json['id']; 											//if($GLOBALS['debug_echo']) echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
					
					if ($author_id != $mention_id && $mention_id != $discord->id) {
						$boop_messages								= array();
						$boop_messages[]							= "<@$author_id> slowly and strategically booped the snoot of <@$mention_id>.";
						$boop_messages[]							= "With a playful smile, <@$author_id> booped <@$mention_id>'s snoot.";
						$index_selection							= GetRandomArrayIndex($boop_messages);
						//					if($GLOBALS['debug_echo']) echo "random boop_messages: " . $boop_messages[$index_selection];
						//					Send the message
						$author_channel->sendMessage($boop_messages[$index_selection]);
						//Increment give stat counter of author
						$vanity_give_count++;
						VarSave($author_folder, "vanity_give_count.php", $vanity_give_count);
						$booper_count++;
						VarSave($author_folder, "booper_count.php", $booper_count);
						//Load target get statistics
						if (!CheckFile($guild_folder."/".$mention_id, "vanity_get_count.php")) {
							$vanity_get_count	= 0;
						} else {
							$vanity_get_count  = VarLoad($guild_folder."/".$mention_id, "vanity_get_count.php");
						}
						if (!CheckFile($guild_folder."/".$mention_id, "booped_count.php")) {
							$booped_count		= 0;
						} else {
							$booped_count 	 = VarLoad($guild_folder."/".$mention_id, "booped_count.php");
						}
						//Increment get stat counter of target
						$vanity_get_count++;
						VarSave($guild_folder."/".$mention_id, "vanity_get_count.php", $vanity_get_count);
						$booped_count++;
						VarSave($guild_folder."/".$mention_id, "booped_count.php", $booped_count);
						//					Set Cooldown
						//SetCooldown($author_folder, "vanity_time.php");
						SetCooldownMem($author_id, "vanity");
						return; //No more processing, we only want to process the first person mentioned
					} else {
						$self_boop_messages							= array();
						$self_boop_messages[]						= "<@$author_id> placed a paw on their own nose. How silly!";
						$index_selection							= GetRandomArrayIndex($self_boop_messages);
						//					Send the mssage
						$author_channel->sendMessage($self_boop_messages[$index_selection]);
						//Increment give stat counter of author
						$vanity_give_count++;
						VarSave($author_folder, "vanity_give_count.php", $vanity_give_count);
						$booper_count++;
						VarSave($author_folder, "booper_count.php", $booper_count);
						//Increment get stat counter of author
						$vanity_get_count++;
						VarSave($author_folder, "vanity_get_count.php", $vanity_get_count);
						$booped_count++;
						VarSave($author_folder, "booped_count.php", $booped_count);
						//					Set Cooldown
						//SetCooldown($author_folder, "vanity_time.php");
						SetCooldownMem($author_id, "vanity");
						return; //No more processing
					}
				}
				//Foreach method didn't return, so nobody was mentioned
				$message->reply("You need to mention someone!");
				return;
			} else {
				//			Reply with remaining time
				$waittime = $vanity_limit_seconds - $cooldown[1];
				$formattime = FormatTime($waittime);
				$message->reply("You must wait $formattime before using vanity commands again.");
				return;
			}
		}
		if (str_starts_with($message_content_lower, 'bap ')) { //;bap @
			if($GLOBALS['debug_echo']) echo "[BAP]" . PHP_EOL;
			//				Check Cooldown Timer
			//$cooldown = CheckCooldown($author_folder, "vanity_time.php", $vanity_limit);
			$cooldown = CheckCooldownMem($author_id, "vanity", $vanity_limit);
			if (($cooldown[0]) || ($bypass)) {
				//					Get an array of people mentioned
				$mentions_arr 										= $message->mentions; 									//if($GLOBALS['debug_echo']) echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
				foreach ($mentions_arr as $mention_param) {
					$mention_param_encode 							= json_encode($mention_param); 									//if($GLOBALS['debug_echo']) echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
					$mention_json 									= json_decode($mention_param_encode, true); 					//if($GLOBALS['debug_echo']) echo "mention_json: " . PHP_EOL; var_dump($mention_json);
					$mention_id 									= $mention_json['id']; 											//if($GLOBALS['debug_echo']) echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
					
					if ($author_id != $mention_id && $mention_id != $discord->id) {
						$bap_messages								= array();
						$bap_messages[]								= "<@$mention_id> was hit on the snoot by <@$author_id>!";
						$bap_messages[]								= "<@$author_id> glared at <@$mention_id>, giving them a bap on the snoot!";
						$bap_messages[]								= "Snoot of <@$mention_id> was attacked by <@$author_id>!";
						$index_selection							= GetRandomArrayIndex($bap_messages);
						//							if($GLOBALS['debug_echo']) echo "random bap_messages: " . $bap_messages[$index_selection];
						//					Send the message
						$author_channel->sendMessage($bap_messages[$index_selection]);
						//Increment give stat counter of author
						$vanity_give_count++;
						VarSave($author_folder, "vanity_give_count.php", $vanity_give_count);
						$baper_count++;
						VarSave($author_folder, "baper_count.php", $baper_count);
						//Load target get statistics
						if (!CheckFile($guild_folder."/".$mention_id, "vanity_get_count.php")) {
							$vanity_get_count	= 0;
						} else {
							$vanity_get_count  = VarLoad($guild_folder."/".$mention_id, "vanity_get_count.php");
						}
						if (!CheckFile($guild_folder."/".$mention_id, "baped_count.php")) {
							$baped_count		= 0;
						} else {
							$baped_count 	 = VarLoad($guild_folder."/".$mention_id, "baped_count.php");
						}
						//Increment get stat counter of target
						$vanity_get_count++;
						VarSave($guild_folder."/".$mention_id, "vanity_get_count.php", $vanity_get_count);
						$baped_count++;
						VarSave($guild_folder."/".$mention_id, "baped_count.php", $baped_count);
						//					Set Cooldown
						//SetCooldown($author_folder, "vanity_time.php");
						SetCooldownMem($author_id, "vanity");
						return; //No more processing, we only want to process the first person mentioned
					} else {
						$self_bap_messages							= array();
						$self_bap_messages[]						= "<@$author_id> placed a paw on their own nose. How silly!";
						$index_selection							= GetRandomArrayIndex($self_bap_messages);
						//					Send the mssage
						$author_channel->sendMessage($self_bap_messages[$index_selection]);
						//Increment give stat counter of author
						$vanity_give_count++;
						VarSave($author_folder, "vanity_give_count.php", $vanity_give_count);
						$baper_count++;
						VarSave($author_folder, "baper_count.php", $baper_count);
						//Increment get stat counter of author
						$vanity_get_count++;
						VarSave($author_folder, "vanity_get_count.php", $vanity_get_count);
						$baped_count++;
						VarSave($author_folder, "baped_count.php", $baped_count);
						//					Set Cooldown
						//SetCooldown($author_folder, "vanity_time.php");
						SetCooldownMem($author_id, "vanity");
						return; //No more processing
					}
				}
				//Foreach method didn't return, so nobody was mentioned
				$message->reply("You need to mention someone!");
				return;
			} else {
				//					Reply with remaining time
				$waittime = $vanity_limit_seconds - $cooldown[1];
				$formattime = FormatTime($waittime);
				$message->reply("You must wait $formattime before using vanity commands again.");
				return;
			}
		}
		if (str_starts_with($message_content_lower, 'pet ')) { //;pet @
			if($GLOBALS['debug_echo']) echo "[PET]" . PHP_EOL;
			//				Check Cooldown Timer
			//$cooldown = CheckCooldown($author_folder, "vanity_time.php", $vanity_limit);
			$cooldown = CheckCooldownMem($author_id, "vanity", $vanity_limit);
			if (($cooldown[0]) || ($bypass)) {
				//					Get an array of people mentioned
				$mentions_arr 										= $message->mentions; 									//if($GLOBALS['debug_echo']) echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
				foreach ($mentions_arr as $mention_param) {
					$mention_param_encode 							= json_encode($mention_param); 									//if($GLOBALS['debug_echo']) echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
					$mention_json 									= json_decode($mention_param_encode, true); 					//if($GLOBALS['debug_echo']) echo "mention_json: " . PHP_EOL; var_dump($mention_json);
					$mention_id 									= $mention_json['id']; 											//if($GLOBALS['debug_echo']) echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
					
					if ($author_id != $mention_id && $mention_id != $discord->id) {
						$pet_messages								= array();
						$pet_messages[]								= "<@$author_id> pets <@$mention_id>";
						$index_selection							= GetRandomArrayIndex($pet_messages);
						//							if($GLOBALS['debug_echo']) echo "random pet_messages: " . $pet_messages[$index_selection];
						//					Send the message
						$author_channel->sendMessage($pet_messages[$index_selection]);
						//Increment give stat counter of author
						$vanity_give_count++;
						VarSave($author_folder, "vanity_give_count.php", $vanity_give_count);
						$peter_count++;
						VarSave($author_folder, "peter_count.php", $peter_count);
						//Load target get statistics
						if (!CheckFile($guild_folder."/".$mention_id, "vanity_get_count.php")) {
							$vanity_get_count	= 0;
						} else {
							$vanity_get_count  = VarLoad($guild_folder."/".$mention_id, "vanity_get_count.php");
						}
						if (!CheckFile($guild_folder."/".$mention_id, "peted_count.php")) {
							$peted_count		= 0;
						} else {
							$peted_count 	 = VarLoad($guild_folder."/".$mention_id, "peted_count.php");
						}
						//Increment get stat counter of target
						$vanity_get_count++;
						VarSave($guild_folder."/".$mention_id, "vanity_get_count.php", $vanity_get_count);
						$peted_count++;
						VarSave($guild_folder."/".$mention_id, "peted_count.php", $peted_count);
						//					Set Cooldown
						//SetCooldown($author_folder, "vanity_time.php");
						SetCooldownMem($author_id, "vanity");
						return; //No more processing, we only want to process the first person mentioned
					} else {
						$self_pet_messages							= array();
						$self_pet_messages[]						= "<@$author_id> placed a paw on their own nose. How silly!";
						$index_selection							= GetRandomArrayIndex($self_pet_messages);
						//					Send the mssage
						$author_channel->sendMessage($self_pet_messages[$index_selection]);
						//Increment give stat counter of author
						$vanity_give_count++;
						VarSave($author_folder, "vanity_give_count.php", $vanity_give_count);
						$peter_count++;
						VarSave($author_folder, "peter_count.php", $peter_count);
						//Increment get stat counter of author
						$vanity_get_count++;
						VarSave($author_folder, "vanity_get_count.php", $vanity_get_count);
						$peted_count++;
						VarSave($author_folder, "peted_count.php", $peted_count);
						//					Set Cooldown
						//SetCooldown($author_folder, "vanity_time.php");
						SetCooldownMem($author_id, "vanity");
						return; //No more processing
					}
				}
				//Foreach method didn't return, so nobody was mentioned
				$message->reply("You need to mention someone!");
				return;
			} else {
				//					Reply with remaining time
				$waittime = $vanity_limit_seconds - $cooldown[1];
				$formattime = FormatTime($waittime);
				$message->reply("You must wait $formattime before using vanity commands again.");
				return;
			}
		}
		
		//ymdhis cooldown time
		$vstats_limit['year'] = 0;
		$vstats_limit['month'] = 0;
		$vstats_limit['day'] = 0;
		$vstats_limit['hour'] = 0;
		$vstats_limit['min'] = 30;
		$vstats_limit['sec'] = 0;
		$vstats_limit_seconds = TimeArrayToSeconds($vstats_limit);
		
		if ($message_content_lower == 'vstats') { //;vstats //Give the author their vanity stats as an embedded message
			//		Check Cooldown Timer
			//$cooldown = CheckCooldown($author_folder, "vstats_limit.php", $vstats_limit);
			$cooldown = CheckCooldownMem($author_id, "vstats", $vanity_limit);
			if (($cooldown[0]) || ($bypass)) {
				//			Build the embed
				$embed = new \Discord\Parts\Embed\Embed($discord);
				$embed
					->setTitle("Vanity Stats")																// Set a title
					->setColor(0xe1452d)																	// Set a color (the thing on the left side)
					->setDescription("$author_guild_name")												// Set a description (below title, above fields)
					->addFieldValues("Total Given", "$vanity_give_count")									// New line after this
					->addFieldValues("Hugs", "$hugger_count", true)
					->addFieldValues("Kisses", "$kisser_count", true)
					->addFieldValues("Nuzzles", "$nuzzler_count", true)
					->addFieldValues("Boops", "$booper_count", true)
					->addFieldValues("Baps", "$baper_count", true)
					->addFieldValues("Pets", "$peter_count", true)
					->addFieldValues("⠀", "⠀", true)												// Invisible unicode for separator
					->addFieldValues("Total Received", "$vanity_get_count")									// New line after this
					->addFieldValues("Hugs", "$hugged_count", true)
					->addFieldValues("Kisses", "$kissed_count", true)
					->addFieldValues("Nuzzles", "$nuzzled_count", true)
					->addFieldValues("Boops", "$booped_count", true)
					->addFieldValues("Baps", "$baped_count", true)
					->addFieldValues("Pets", "$peted_count", true)
					
					->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
	//				->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')			 	// Set an image (below everything except footer)
					->setTimestamp()																	 	// Set a timestamp (gets shown next to footer)
					->setAuthor("$author_check", "$author_guild_avatar")  									// Set an author with icon
					->setFooter("Palace Bot by Valithor#5947")							 					// Set a footer without icon
					->setURL("");							 												// Set the URL
				
	//			Send the message
				//			We do not need another promise here, so we call done, because we want to consume the promise
				if ($react) $message->react("👍");
				$author_channel->sendEmbed($embed);
				//			Set Cooldown
				//SetCooldown($author_folder, "vstats_limit.php");
				SetCooldownMem($author_id, "vstats");
				return;
			} else {
				//			Reply with remaining time
				$waittime = ($vstats_limit_seconds - $cooldown[1]);
				$formattime = FormatTime($waittime);
				if ($react) $message->react("👎");
				$message->reply("You must wait $formattime before using vstats on yourself again.");
				return;
			}
		}
		if (str_starts_with($message_content_lower, 'vstats ')) { //;vstats @
			if($GLOBALS['debug_echo']) echo "[GET MENTIONED VANITY STATS]" . PHP_EOL;
			//		Check Cooldown Timer
			//$cooldown = CheckCooldown($author_folder, "vstats_limit.php", $vstats_limit);
			$cooldown = CheckCooldownMem($author_id, "vstats", $vanity_limit);
			if (($cooldown[0]) || ($bypass)) {
				//			Get an array of people mentioned
				$mentions_arr 										= $message->mentions; 									//if($GLOBALS['debug_echo']) echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
				foreach ($mentions_arr as $mention_param) {																				//if($GLOBALS['debug_echo']) echo "mention_param: " . PHP_EOL; var_dump ($mention_param);
	//				id, username, discriminator, bot, avatar, email, mfaEnabled, verified, webhook, createdTimestamp
					$mention_param_encode 							= json_encode($mention_param); 									//if($GLOBALS['debug_echo']) echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
					$mention_json 									= json_decode($mention_param_encode, true); 					//if($GLOBALS['debug_echo']) echo "mention_json: " . PHP_EOL; var_dump($mention_json);
					$mention_id 									= $mention_json['id']; 											//if($GLOBALS['debug_echo']) echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
					$mention_username 								= $mention_json['username']; 									//if($GLOBALS['debug_echo']) echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
					$mention_discriminator 							= $mention_json['discriminator']; 								//if($GLOBALS['debug_echo']) echo "mention_discriminator: " . $mention_discriminator . PHP_EOL; //Just the discord ID
					$mention_check 									= $mention_username ."#".$mention_discriminator; 				//if($GLOBALS['debug_echo']) echo "mention_check: " . $mention_check . PHP_EOL; //Just the discord ID
					
					if ($mention_id != $discord->id) {
		//				Get the avatar URL
						$target_guildmember 							= $message->guild->members->get('id', $mention_id); 	//This is a GuildMember object
						$target_guildmember_user						= $target_guildmember->user;									//if($GLOBALS['debug_echo']) echo "member_class: " . get_class($target_guildmember_user) . PHP_EOL;
						$mention_avatar 								= "{$target_guildmember_user->avatar}";					//if($GLOBALS['debug_echo']) echo "mention_avatar: " . $mention_avatar . PHP_EOL;
						
						
						//Load target get statistics
						if (!CheckFile($guild_folder."/".$mention_id, "vanity_get_count.php")) {
							$target_vanity_get_count	= 0;
						} else {
							$target_vanity_get_count  = VarLoad($guild_folder."/".$mention_id, "vanity_get_count.php");
						}
						if (!CheckFile($guild_folder."/".$mention_id, "vanity_give_count.php")) {
							$target_vanity_give_count	= 0;
						} else {
							$target_vanity_give_count  = VarLoad($guild_folder."/".$mention_id, "vanity_give_count.php");
						}
						if (!CheckFile($guild_folder."/".$mention_id, "hugged_count.php")) {
							$target_hugged_count		= 0;
						} else {
							$target_hugged_count 	 = VarLoad($guild_folder."/".$mention_id, "hugged_count.php");
						}
						if (!CheckFile($guild_folder."/".$mention_id, "hugger_count.php")) {
							$target_hugger_count		= 0;
						} else {
							$target_hugger_count 	 = VarLoad($guild_folder."/".$mention_id, "hugger_count.php");
						}
						if (!CheckFile($guild_folder."/".$mention_id, "kissed_count.php")) {
							$target_kissed_count		= 0;
						} else {
							$target_kissed_count 	 = VarLoad($guild_folder."/".$mention_id, "kissed_count.php");
						}
						if (!CheckFile($guild_folder."/".$mention_id, "kisser_count.php")) {
							$target_kisser_count		= 0;
						} else {
							$target_kisser_count 	 = VarLoad($guild_folder."/".$mention_id, "kisser_count.php");
						}
						if (!CheckFile($guild_folder."/".$mention_id, "nuzzled_count.php")) {
							$target_nuzzled_count		= 0;
						} else {
							$target_nuzzled_count 	 = VarLoad($guild_folder."/".$mention_id, "nuzzled_count.php");
						}
						if (!CheckFile($guild_folder."/".$mention_id, "nuzzler_count.php")) {
							$target_nuzzler_count		= 0;
						} else {
							$target_nuzzler_count 	 = VarLoad($guild_folder."/".$mention_id, "nuzzler_count.php");
						}
						if (!CheckFile($guild_folder."/".$mention_id, "booped_count.php")) {
							$target_booped_count		= 0;
						} else {
							$target_booped_count 	 = VarLoad($guild_folder."/".$mention_id, "booped_count.php");
						}
						if (!CheckFile($guild_folder."/".$mention_id, "booper_count.php")) {
							$target_booper_count		= 0;
						} else {
							$target_booper_count 	 = VarLoad($guild_folder."/".$mention_id, "booper_count.php");
						}
						
						//Build the embed
						$embed = new \Discord\Parts\Embed\Embed($discord);
						$embed
							->setTitle("Vanity Stats")																// Set a title
							->setColor(0xe1452d)																	// Set a color (the thing on the left side)
							->setDescription("$author_guild_name")												// Set a description (below title, above fields)
							->addFieldValues("Total Given", "$target_vanity_give_count")							// New line after this
							->addFieldValues("Hugs", "$target_hugger_count", true)
							->addFieldValues("Kisses", "$target_kisser_count", true)
							->addFieldValues("Nuzzles", "$target_nuzzler_count", true)
							->addFieldValues("Boops", "$target_booper_count", true)
							->addFieldValues("⠀", "⠀", true)												// Invisible unicode for separator
							->addFieldValues("Total Received", "$target_vanity_get_count")								// New line after this
							->addFieldValues("Hugs", "$target_hugged_count", true)
							->addFieldValues("Kisses", "$target_kissed_count", true)
							->addFieldValues("Nuzzles", "$target_nuzzled_count", true)
							->addFieldValues("Boops", "$target_booped_count", true)
							
							->setThumbnail("$mention_avatar")														// Set a thumbnail (the image in the top right corner)
		//					->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')			 		// Set an image (below everything except footer)
							->setTimestamp()																	 	// Set a timestamp (gets shown next to footer)
							->setAuthor("$mention_check", "$author_guild_avatar")  // Set an author with icon
							->setFooter("Palace Bot by Valithor#5947")							 					// Set a footer without icon
							->setURL("");							 												// Set the URL
						
		//				Send the message
						//				We do not need another promise here, so we call done, because we want to consume the promise
						if ($react) $message->react("👍");
						$author_channel->sendEmbed($embed);
						//				Set Cooldown
						//SetCooldown($author_folder, "vstats_limit.php");
						SetCooldownMem($author_id, "vstats");
						return; //No more processing, we only want to process the first person mentioned
					}
				}
				//Foreach method didn't return, so nobody was mentioned
				$message->reply("You need to mention someone!");
				return;
			} else {
				//			Reply with remaining time
				$waittime = ($vstats_limit_seconds - $cooldown[1]);
				$formattime = FormatTime($waittime);
				if ($react) $message->react("👎");
				$message->reply("You must wait $formattime before using vstats on yourself again.");
				return;
			}
		}
	} //End of vanity commands

	/*
	*********************
	*********************
	Role picker functions
	*********************
	*********************
	*/

	//TODO? (This is already done with messageReactionAdd)

	/*
	*********************
	*********************
	Restricted command functions
	*********************
	*********************
	*/

	/*
	if($creator || $owner || $dev || $admin || $mod) { //Only allow these roles to use this
	}
	*/
	if ($creator || ($author_id == '68828609288077312') || ($author_id == '68847303431041024')) { //Special use-case
		if ($message_content_lower == 'pull') { //;pull
			//if(shell_exec("start ". 'cmd /c "'. 'C:\WinNMP2021\WWW\lucky-komainu' . '\gitpull.bat"'))
			
			if( ($handle = popen('start cmd /c "C:\WinNMP2021\WWW\lucky-komainu\gitpullbot.bat"', 'r'))
			&& ($handle2 = popen('start cmd /c "C:\WinNMP2021\WWW\wylderkind\gitpullbot.bat"', 'r'))
			&& ($handle3 = popen('start cmd /c "C:\WinNMP2021\WWW\wylderkind-dev\gitpullbot.bat"', 'r'))
			) return $message->react("👍");
			
			/*
			$process = new React\ChildProcess\Process('start '. 'cmd /c "'. 'C:\WinNMP2021\WWW\lucky-komainu' . '\gitpullbot.bat"', null, null, array(
				array('file', 'nul', 'r'),
				$stdout = tmpfile(),
				array('file', 'nul', 'w')
			));
			$process->start($discord->getLoop());

			$process->on('exit', function ($exitcode) use ($stdout) {
				if($GLOBALS['debug_echo']) echo 'exit with ' . $exitcode . PHP_EOL;

				// rewind to start and then read full file (demo only, this is blocking).
				// reading from shared file is only safe if you have some synchronization in place
				// or after the child process has terminated.
				rewind($stdout);
				$message->reply(stream_get_contents($stdout));
				fclose($stdout);
			});
			*/
			//$output = pclose(popen("start ". 'cmd /c "'. 'C:\WinNMP2021\WWW\lucky-komainu' . '\run.bat"', "r"));
			return $message->react("👎");
		}
	}
	if ($creator) { //Mostly just debug commands
		include 'dev-message-include.php';
		include 'creator-message-include.php';
	}

	if ($creator || ($author_guild_id == "468979034571931648") || ($author_guild_id == "744022293021458464")) { //These commands should only be relevant for use on this server
		switch ($author_guild_id) {
			case "468979034571931648":
				$staff_channel_id = "562715700360380434";
				$staff_bot_channel_id = "712685552155230278";
				break;
			case "744022293021458464":
				$staff_channel_id = "744022293533032541";
				$staff_bot_channel_id = "744022293533032542";
				break;
		}
		//Don't let people use these in #general
		switch ($message_content_lower) {
			case 'status': //;status
				if($GLOBALS['debug_echo']) echo "[STATUS] $author_check" . PHP_EOL;
				$ch = curl_init(); //create curl resource
				curl_setopt($ch, CURLOPT_URL, "http://192.168.1.23:81/civ13/serverstate.txt"); // set url
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //return the transfer as a string
				return $message->reply(curl_exec($ch));
				break;
		}
		/*VMWare
		if ($creator || $owner || $dev || $tech || $assistant) {
			switch ($message_content_lower) {
				case 'resume': //;resume
					if($GLOBALS['debug_echo']) echo "[RESUME] $author_check" .  PHP_EOL;
					//Trigger the php script remotely
					$ch = curl_init(); //create curl resource
					curl_setopt($ch, CURLOPT_URL, "http://192.168.1.23:81/civ13/resume.php"); // set url
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //return the transfer as a string
					curl_setopt($ch, CURLOPT_POST, true);
					$message->reply(curl_exec($ch));
					return;
					break;
				case 'save 1': //;save 1
					if($GLOBALS['debug_echo']) echo "[SAVE SLOT 1] $author_check" .  PHP_EOL;
					$manual_saving = VarLoad(null, "manual_saving.php");
					if ($manual_saving) {
						if ($react) {
							$message->react("👎");
						}
						$message->reply("A manual save is already in progress!");
					} else {
						if ($react) {
							$message->react("👍");
						}
						VarSave(null, "manual_saving.php", true);
						$message->react("⏰")->done(function ($author_channel) use ($message) {	//Promise
							//Trigger the php script remotely
							$ch = curl_init(); //create curl resource
							curl_setopt($ch, CURLOPT_URL, "http://192.168.1.23:81/civ13/savemanual1.php"); // set url
							curl_setopt($ch, CURLOPT_POST, true);
							
							curl_setopt($ch, CURLOPT_USERAGENT, 'Palace Bot');
							
							curl_setopt($ch, CURLOPT_TIMEOUT, 1);
							curl_setopt($ch, CURLOPT_HEADER, 0);
							curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
							curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
							curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
							curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);
							
							curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
							
							curl_exec($ch);
							curl_close($ch);
							
							
							$dt = new DateTime("now", new DateTimeZone('America/New_York'));  // convert UNIX timestamp to PHP DateTime
							$time = $dt->format('d-m-Y H:i:s'); // output = 2017-01-01 00:00:00
							$message->reply("$time EST");
							VarSave(null, "manual_saving.php", false);
							return;
						});
					}
					return;
					break;
				case 'save 2': //;save 2
					if($GLOBALS['debug_echo']) echo "[SAVE SLOT 2] $author_check" .  PHP_EOL;
					$manual_saving = VarLoad(null, "manual_saving.php");
					if ($manual_saving) {
						if ($react) {
							$message->react("👎");
						}
						$message->reply("A manual save is already in progress!");
					} else {
						if ($react) {
							$message->react("👍");
						}
						VarSave(null, "manual_saving.php", true);
						//$message->react("⏰")->done(function($author_channel) use ($message) {	//Promise
							//Trigger the php script remotely
							$ch = curl_init(); //create curl resource
							curl_setopt($ch, CURLOPT_URL, "http://192.168.1.23:81/civ13/savemanual2.php"); // set url
							curl_setopt($ch, CURLOPT_POST, true);
							
						curl_setopt($ch, CURLOPT_USERAGENT, 'Palace Bot');
							
						curl_setopt($ch, CURLOPT_TIMEOUT, 1);
						curl_setopt($ch, CURLOPT_HEADER, 0);
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
						curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
						curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
						curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);
							
						curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
							
						curl_exec($ch);
						curl_close($ch);
							
						$dt = new DateTime("now", new DateTimeZone('America/New_York'));  // convert UNIX timestamp to PHP DateTime
							$time = $dt->format('d-m-Y H:i:s'); // output = 2017-01-01 00:00:00
							$message->reply("$time EST");
						VarSave(null, "manual_saving.php", false);
						return;
						//});
					}
					return;
					break;
				case 'save 3': //;save 3
					if($GLOBALS['debug_echo']) echo "[SAVE SLOT 3] $author_check" .  PHP_EOL;
					$manual_saving = VarLoad(null, "manual_saving.php");
					if ($manual_saving) {
						if ($react) {
							$message->react("👎");
						}
						$message->reply("A manual save is already in progress!");
					} else {
						if ($react) {
							$message->react("👍");
						}
						VarSave(null, "manual_saving.php", true);
						//$message->react("⏰")->done(function($author_channel) use ($message) {	//Promise
							//Trigger the php script remotely
							$ch = curl_init(); //create curl resource
							curl_setopt($ch, CURLOPT_URL, "http://192.168.1.23:81/civ13/savemanual3.php"); // set url
							curl_setopt($ch, CURLOPT_POST, true);
							
						curl_setopt($ch, CURLOPT_USERAGENT, 'Palace Bot');
							
						curl_setopt($ch, CURLOPT_TIMEOUT, 1);
						curl_setopt($ch, CURLOPT_HEADER, 0);
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
						curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
						curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
						curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);
							
						curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
							
						curl_exec($ch);
						curl_close($ch);
							
						$dt = new DateTime("now", new DateTimeZone('America/New_York'));  // convert UNIX timestamp to PHP DateTime
							$time = $dt->format('d-m-Y H:i:s'); // output = 2017-01-01 00:00:00
							$message->reply("$time EST");
						VarSave(null, "manual_saving.php", false);
						return;
						//});
					}
					return;
					break;
				case 'delete 1': //;delete 1
					if (!($creator || $owner || $dev)) {
						return;
						break;
					}
					if($GLOBALS['debug_echo']) echo "[DELETE SLOT 1] $author_check" . PHP_EOL;
					if ($react) {
						$message->react("👍");
					}
					//$message->react("⏰")->done(function($author_channel) use ($message) {	//Promise
						//Trigger the php script remotely
						$ch = curl_init(); //create curl resource
						curl_setopt($ch, CURLOPT_URL, "http://192.168.1.23:81/civ13/deletemanual1.php"); // set url
						curl_setopt($ch, CURLOPT_POST, true);
						
						curl_setopt($ch, CURLOPT_USERAGENT, 'Palace Bot');
						
						curl_setopt($ch, CURLOPT_TIMEOUT, 1);
						curl_setopt($ch, CURLOPT_HEADER, 0);
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
						curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
						curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
						curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);
						
						curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
						
						curl_exec($ch);
						curl_close($ch);
						
						$dt = new DateTime("now", new DateTimeZone('America/New_York'));  // convert UNIX timestamp to PHP DateTime
						$time = $dt->format('d-m-Y H:i:s'); // output = 2017-01-01 00:00:00
						$message->reply("$time EST");
						return;
					//});
					return;
					break;
			}
		}
		if ($creator || $owner || $dev || $tech) {
			switch ($message_content_lower) {
				case 'load 1': //;load 1
					if($GLOBALS['debug_echo']) echo "[LOAD SLOT 1] $author_check" . PHP_EOL;
					if ($react) {
						$message->react("👍");
					}
					//$message->react("⏰")->done(function($author_channel) use ($message) {	//Promise
						//Trigger the php script remotely
						$ch = curl_init(); //create curl resource
						curl_setopt($ch, CURLOPT_URL, "http://192.168.1.23:81/civ13/loadmanual1.php"); // set url
						curl_setopt($ch, CURLOPT_POST, true);
							
						curl_setopt($ch, CURLOPT_USERAGENT, 'Palace Bot');
						
						curl_setopt($ch, CURLOPT_TIMEOUT, 1);
						curl_setopt($ch, CURLOPT_HEADER, 0);
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
						curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
						curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
						curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);
						
						curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
						
						curl_exec($ch);
						curl_close($ch);
							
						$dt = new DateTime("now", new DateTimeZone('America/New_York'));  // convert UNIX timestamp to PHP DateTime
						$time = $dt->format('d-m-Y H:i:s'); // output = 2017-01-01 00:00:00
						$message->reply("$time EST");
						return;
					//});
					return;
					break;
				case 'load 2': //;load 2
					if($GLOBALS['debug_echo']) echo "[LOAD SLOT 2] $author_check" . PHP_EOL;
					if ($react) {
						$message->react("👍");
					}
					//$message->react("⏰")->done(function($author_channel) use ($message) {	//Promise
						//Trigger the php script remotely
						$ch = curl_init(); //create curl resource
						curl_setopt($ch, CURLOPT_URL, "http://192.168.1.23:81/civ13/loadmanual2.php"); // set url
						curl_setopt($ch, CURLOPT_POST, true);
							
						curl_setopt($ch, CURLOPT_USERAGENT, 'Palace Bot');
						
						curl_setopt($ch, CURLOPT_TIMEOUT, 1);
						curl_setopt($ch, CURLOPT_HEADER, 0);
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
						curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
						curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
						curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);
						
						curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
						
						curl_exec($ch);
						curl_close($ch);
						
						$dt = new DateTime("now", new DateTimeZone('America/New_York'));  // convert UNIX timestamp to PHP DateTime
						$time = $dt->format('d-m-Y H:i:s'); // output = 2017-01-01 00:00:00
						$message->reply("$time EST");
						return;
					//});
					return;
					break;
				case 'load 3': //;load 3
					if($GLOBALS['debug_echo']) echo "[LOAD SLOT 3] $author_check" . PHP_EOL;
					if ($react) {
						$message->react("👍");
					}
					//$message->react("⏰")->done(function($author_channel) use ($message) {	//Promise
						//Trigger the php script remotely
						$ch = curl_init(); //create curl resource
						curl_setopt($ch, CURLOPT_URL, "http://192.168.1.23:81/civ13/loadmanual3.php"); // set url
						curl_setopt($ch, CURLOPT_POST, true);
							
						curl_setopt($ch, CURLOPT_USERAGENT, 'Palace Bot');
						
						curl_setopt($ch, CURLOPT_TIMEOUT, 1);
						curl_setopt($ch, CURLOPT_HEADER, 0);
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
						curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
						curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
						curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);
						
						curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
						
						curl_exec($ch);
						curl_close($ch);
						
						$dt = new DateTime("now", new DateTimeZone('America/New_York'));  // convert UNIX timestamp to PHP DateTime
						$time = $dt->format('d-m-Y H:i:s'); // output = 2017-01-01 00:00:00
						$message->reply("$time EST");
						return;
					//});
					return;
					break;
				case 'load1h': //;load1h
					if($GLOBALS['debug_echo']) echo "[LOAD 1H] $author_check" . PHP_EOL;
					if ($react) {
						$message->react("👍");
					}
					//$message->react("⏰")->done(function($author_channel) use ($message) {	//Promise
						//Trigger the php script remotely
						$ch = curl_init(); //create curl resource
						curl_setopt($ch, CURLOPT_URL, "http://192.168.1.23:81/civ13/load1h.php"); // set url
						curl_setopt($ch, CURLOPT_POST, true);
							
						curl_setopt($ch, CURLOPT_USERAGENT, 'Palace Bot');
						
						curl_setopt($ch, CURLOPT_TIMEOUT, 1);
						curl_setopt($ch, CURLOPT_HEADER, 0);
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
						curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
						curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
						curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);
						
						curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
						
						curl_exec($ch);
						curl_close($ch);
						
						$dt = new DateTime("now", new DateTimeZone('America/New_York'));  // convert UNIX timestamp to PHP DateTime
						$time = $dt->format('d-m-Y H:i:s'); // output = 2017-01-01 00:00:00
						$message->reply("$time EST");
						return;
					//});
					return;
					break;
				case 'load2h': //;load2h
					if($GLOBALS['debug_echo']) echo "[LOAD 2H] $author_check" . PHP_EOL;
					if ($react) {
						$message->react("👍");
					}
					//$message->react("⏰")->done(function($author_channel) use ($message) {	//Promise
						//Trigger the php script remotely
						$ch = curl_init(); //create curl resource
						curl_setopt($ch, CURLOPT_URL, "http://192.168.1.23:81/civ13/load2h.php"); // set url
						curl_setopt($ch, CURLOPT_POST, true);
							
						curl_setopt($ch, CURLOPT_USERAGENT, 'Palace Bot');
						
						curl_setopt($ch, CURLOPT_TIMEOUT, 1);
						curl_setopt($ch, CURLOPT_HEADER, 0);
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
						curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
						curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
						curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);
						
						curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
						
						curl_exec($ch);
						curl_close($ch);
						
						$dt = new DateTime("now", new DateTimeZone('America/New_York'));  // convert UNIX timestamp to PHP DateTime
						$time = $dt->format('d-m-Y H:i:s'); // output = 2017-01-01 00:00:00
						$message->reply("$time EST");
						return;
					//});
					return;
					break;
				case 'host persistence':
				case 'host pers':
					if($GLOBALS['debug_echo']) echo "[HOST PERSISTENCE] $author_check" . PHP_EOL;
					//$message->react("⏰")->done(function($author_channel) use ($message) {	//Promise
						//Trigger the php script remotely
						$ch = curl_init(); //create curl resource
						curl_setopt($ch, CURLOPT_URL, "http://192.168.1.23:81/civ13/host.php"); // set url
						curl_setopt($ch, CURLOPT_POST, true);
							
						curl_setopt($ch, CURLOPT_USERAGENT, 'Palace Bot');
						
						curl_setopt($ch, CURLOPT_TIMEOUT, 1);
						curl_setopt($ch, CURLOPT_HEADER, 0);
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
						curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
						curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
						curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);
						
						curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
						
						curl_exec($ch);
						curl_close($ch);
						
						
						//$dt = new DateTime("now", new DateTimeZone('America/New_York'));  // convert UNIX timestamp to PHP DateTime
						//$time = $dt->format('d-m-Y H:i:s'); // output = 2017-01-01 00:00:00
						//$message->reply("$time EST");
						
						if ($react) {
							$message->react("👍");
						}
						return;
					//});
					return;
					break;
				case 'kill persistence':
				case 'kill pers':
					if($GLOBALS['debug_echo']) echo "[HOST PERSISTENCE] $author_check" . PHP_EOL;
					//$message->react("⏰")->done(function($author_channel) use ($message) {	//Promise
						//Trigger the php script remotely
						$ch = curl_init(); //create curl resource
						curl_setopt($ch, CURLOPT_URL, "http://192.168.1.23:81/civ13/kill.php"); // set url
						curl_setopt($ch, CURLOPT_POST, true);
							
						curl_setopt($ch, CURLOPT_USERAGENT, 'Palace Bot');
						
						curl_setopt($ch, CURLOPT_TIMEOUT, 1);
						curl_setopt($ch, CURLOPT_HEADER, 0);
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
						curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
						curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
						curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);
						
						curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
						
						curl_exec($ch);
						curl_close($ch);
						
						//$dt = new DateTime("now", new DateTimeZone('America/New_York'));  // convert UNIX timestamp to PHP DateTime
						//$time = $dt->format('d-m-Y H:i:s'); // output = 2017-01-01 00:00:00
						//$message->reply("$time EST");
						
						if ($react) {
							$message->react("👍");
						}
						return;
					//});
					return;
					break;
				case 'update persistence':
				case 'update pers':
					if($GLOBALS['debug_echo']) echo "[HOST PERSISTENCE] $author_check" . PHP_EOL;
					
					//$message->react("⏰")->done(function($author_channel) use ($message) {	//Promise
						//Trigger the php script remotely
						//$ch = curl_init(); //create curl resource
						//curl_setopt($ch, CURLOPT_URL, "http://192.168.1.23:81/civ13/update.php"); // set url
						//curl_setopt($ch, CURLOPT_POST, true);

						//curl_setopt($ch, CURLOPT_USERAGENT, 'Palace Bot');

						//curl_setopt($ch, CURLOPT_TIMEOUT, 1);
						//curl_setopt($ch, CURLOPT_HEADER, 0);
						//curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
						//curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
						//curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
						//curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);

						//curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);

						//curl_exec($ch);
						//curl_close($ch);

						//$dt = new DateTime("now", new DateTimeZone('America/New_York'));  // convert UNIX timestamp to PHP DateTime
						//$time = $dt->format('d-m-Y H:i:s'); // output = 2017-01-01 00:00:00
						//$message->reply("$time EST");

						//if($react) $message->react("👍");
						//return;
					//});
					
					if ($react) {
						$message->react("👎");
					}
					return;
					break;
			}
		}
		if ($creator || $owner || $dev) {
			switch ($message_content_lower) {
				case '?status': //;?status
					include "../servers/getserverdata.php";
					$debug = var_export($serverinfo, true);
					if ($debug) {
						$author_channel->sendMessage(urldecode($debug));
					} else {
						$author_channel->sendMessage("No debug info found!");
					}
					return;
					break;
				case 'pause': //;pause
					//Trigger the php script remotely
					$ch = curl_init(); //create curl resource
					curl_setopt($ch, CURLOPT_URL, "http://192.168.1.23:81/civ13/pause.php"); // set url
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //return the transfer as a string
					curl_setopt($ch, CURLOPT_POST, true);
					$message->reply(curl_exec($ch));
					return;
					break;
				case 'loadnew': //;loadnew
					//Trigger the php script remotely
					$ch = curl_init(); //create curl resource
					curl_setopt($ch, CURLOPT_URL, "http://192.168.1.23:81/civ13/loadnew.php"); // set url
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //return the transfer as a string
					curl_setopt($ch, CURLOPT_POST, true);
					$message->reply(curl_exec($ch));
					return;
					break;
				case 'VM_restart': //;VM_restart
					if (!($creator || $dev)) {
						return;
						break;
					}
					//Trigger the php script remotely
					$ch = curl_init(); //create curl resource
					curl_setopt($ch, CURLOPT_URL, "http://192.168.1.23:81/civ13/VM_restart.php"); // set url
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //return the transfer as a string
					curl_setopt($ch, CURLOPT_POST, true);
					$message->reply(curl_exec($ch));
					return;
					break;
			}
		}
		*/
	}
	/*
	if ($author_id == "352898973578690561") { //magmacreeper
		if ($message_content_lower == 'start') { //;start
			if($GLOBALS['debug_echo']) echo "[START] $author_check" .  PHP_EOL;
			//Trigger the php script remotely
			$ch = curl_init(); //create curl resource
			curl_setopt($ch, CURLOPT_URL, "http://192.168.1.97/magmacreeper/start.php"); // set url
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //return the transfer as a string
			curl_setopt($ch, CURLOPT_POST, true);
			$message->reply(curl_exec($ch));
			return;
		}
		if ($message_content_lower == 'pull') { //;pull
			if($GLOBALS['debug_echo']) echo "[START] $author_check" .  PHP_EOL;
			//Trigger the php script remotely
			$ch = curl_init(); //create curl resource
			curl_setopt($ch, CURLOPT_URL, "http://192.168.1.97/magmacreeper/pull.php"); // set url
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //return the transfer as a string
			curl_setopt($ch, CURLOPT_POST, true);
			$message->reply(curl_exec($ch));
			return;
		}
	}
	*/

	if ($creator || $owner || $dev || $admin || $mod) { //Only allow these roles to use this
		if (str_starts_with($message_content_lower, 'poll ')) { //;poll
			//return; //Reactions are bugged?!
			if($GLOBALS['debug_echo']) echo "[POLL] $author_check" . PHP_EOL;
			$filter = "poll ";
			$poll = str_replace($filter, "", $message_content);
			$filter = "@";
			$poll = str_replace($filter, "@ ", $poll);
			$arr = explode(" ", $message_content);
			$duration = $arr[1];
			$poll = str_replace($duration, "", $poll);
			if (isset($poll) && $poll != "" && is_numeric($duration)) {
				$author_channel->sendMessage("**VOTE TIME! ($duration seconds)**\n`".trim($poll)."`")->done(function ($message) use ($discord, $author_channel, $duration) {
					$storage = [];
					$message->createReactionCollector(function ($reaction) use (&$storage) {
						if (! isset($storage[$reaction->emoji->name])) {
							$storage[$reaction->emoji->name] = 0;
						}

						$storage[$reaction->emoji->name]++;
					}, ['time' => $duration * 1000])->done(function ($reactions) use (&$storage, $message) {
						$yes_count = 0;
						$no_count = 0;
						//$msg = '';
						foreach ($storage as $emoji => $count) {
							var_dump($emoji);
							if($GLOBALS['debug_echo']) echo PHP_EOL;
							if ($emoji == "👍") $yes_count = (int)$count-1;
							if ($emoji == "👎") $no_count = (int)$count-1;
							//$msg .= $emoji.': '.$count.', ';
						}
						//Count reacts
						if (($yes_count - $no_count) == 0) return $message->channel->sendMessage("**Vote tied! ($yes_count:$no_count)**");							
						if (($yes_count - $no_count) > 0) return $message->channel->sendMessage("**Vote passed! ($yes_count:$no_count)**");
						if (($yes_count - $no_count) < 0) return $message->channel->sendMessage("**Vote failed! ($yes_count:$no_count)**");
						return $author_channel->sendMessage("**Vote errored! ($yes_count:$no_count)**");
						//$message->reply($msg);
					});
					return $message->react("👍")->done(function($result) use ($message) {
						return $message->react("👎");
					});
				});
			} return $message->reply("Invalid input!");
		}
		if (str_starts_with($message_content_lower, 'whois ')) { //;whois
			if($GLOBALS['debug_echo']) echo "[WHOIS] $author_check" . PHP_EOL;
			$filter = "whois ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = str_replace("<@!", "", $value);
			$value = str_replace("<@", "", $value);
			$value = str_replace("<@", "", $value);
			$value = str_replace(">", "", $value);
			$value = trim($value);
			if (is_numeric($value)) {
				if (!preg_match('/^[0-9]{16,20}$/', $value)) return $message->react('❌');				
				if ($mention_member	= $author_guild->members->get('id', $value)) { //$message->reply("Invalid input! Please enter an ID or @mention the user");
					if (get_class($mention_member) == "Discord\Parts\User\Member") {
						$mention_user = $mention_member->user;
						$mention_member = $mention_member;
					} else $mention_user = $mention_member;
					include 'whois-include.php';
				}
				else {
					//attempt to fetch user info
					$discord->users->fetch($value)->done(
						function ($mention_user) use ($discord, $author_channel) {
							include 'whois-include.php';
						}, function ($error) use ($message) {
							return $message->react("👎");
						}					
					);
				}
			} else {
				if ($react) $message->react('❌');
				return $message->reply("Invalid input! Please enter an ID or @mention the user");
			}
			return;
		}
		if (str_starts_with($message_content_lower, 'lookup ')) { //;lookup
			if($GLOBALS['debug_echo']) echo "[LOOKUP] $author_check" . PHP_EOL;
			$filter = "lookup ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = str_replace("<@!", "", $value);
			$value = str_replace("<@", "", $value);
			$value = str_replace(">", "", $value);
			$value = trim($value);
			if (is_numeric($value)) {
				if($GLOBALS['debug_echo']) echo '[VALID] ' . $value . PHP_EOL;
				if (!preg_match('/^[0-9]{16,20}$/', $value)) return $message->react('❌');
				$discord->users->fetch($value)->done(
					function ($target_user) use ($message, $value) {
						$target_username = $target_user->username;
						$target_discriminator = $target_user->discriminator;
						$target_id = $target_user->id;
						$target_avatar = $target_user->avatar;
						$target_check = $target_username . '#' . $target_discriminator;
						return $message->reply("Discord ID is registered to $target_check (<@$value>)");
					},
					function ($error) use ($message, $value) {
						return $message->reply("Unable to locate user for ID $value");
					}
				);
				return;
			}
		}
		/*if (str_starts_with($message_content_lower, 'watch ')) { //;watch @
			if($GLOBALS['debug_echo']) echo "[WATCH] $author_check" . PHP_EOL;
			//			Get an array of people mentioned
			$mentions_arr 												= $message->mentions; 									//if($GLOBALS['debug_echo']) echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
			if ($watch_channel) {
				$mention_watch_name_mention_default		= "<@$author_id>";
			}
			$mention_watch_name_queue_default							= $mention_watch_name_mention_default."is watching the following users:" . PHP_EOL;
			$mention_watch_name_queue_full 								= "";
			
			if (!strpos($message_content_lower, "<")) { //String doesn't contain a mention
				$filter = "watch ";
				$value = str_replace($filter, "", $message_content_lower);
				$value = str_replace("<@!", "", $value);
				$value = str_replace("<@", "", $value);
				$value = str_replace("<@", "", $value);
				$value = str_replace(">", "", $value);
				if (is_numeric($value)) {
					if (!preg_match('/^[0-9]{16,20}$/', $value)) return $message->react('❌');
					$mention_member				= $author_guild->members->get('id', $value);
					$mention_user				= $mention_member->user;
					$mentions_arr				= array($mention_user);
				} else return $message->reply("Invalid input! Please enter a valid ID or @mention the user");
				if (is_null($mention_member)) return $message->reply("Invalid input! Please enter an ID or @mention the user");
			}
			
			foreach ($mentions_arr as $mention_param) {																				//if($GLOBALS['debug_echo']) echo "mention_param: " . PHP_EOL; var_dump ($mention_param);
		//		id, username, discriminator, bot, avatar, email, mfaEnabled, verified, webhook, createdTimestamp
				$mention_param_encode 									= json_encode($mention_param); 									//if($GLOBALS['debug_echo']) echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
				$mention_json 											= json_decode($mention_param_encode, true); 					//if($GLOBALS['debug_echo']) echo "mention_json: " . PHP_EOL; var_dump($mention_json);
				$mention_id 											= $mention_json['id']; 											//if($GLOBALS['debug_echo']) echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
				
		//		Place watch info in target's folder
				$watchers[] = VarLoad($guild_folder."/".$mention_id, "$watchers.php");
				$watchers = array_unique($arr);
				$watchers[] = $author_id;
				VarSave($guild_folder."/".$mention_id, "watchers.php", $watchers);
				$mention_watch_name_queue 								= "**<@$mention_id>** ";
				$mention_watch_name_queue_full 							= $mention_watch_name_queue_full . PHP_EOL . $mention_watch_name_queue;
			}
			//	Send a message
			if ($mention_watch_name_queue != "") {
				if ($watch_channel) {
					$watch_channel->sendMessage($mention_watch_name_queue_default . $mention_watch_name_queue_full . PHP_EOL);
				} else {
					$message->reply($mention_watch_name_queue_default . $mention_watch_name_queue_full . PHP_EOL);
				}
				//		React to the original message
				//		if($react) $message->react("👀");
				if ($react) {
					$message->react("👁");
				}
				return;
			} else {
				if ($react) {
					$message->react("👎");
				}
				$message->reply("Nobody in the guild was mentioned!");
				return;
			}
			//
		}
		*/
		if (str_starts_with($message_content_lower, 'unwatch ')) { //;unwatch @
			if($GLOBALS['debug_echo']) echo "[UNWATCH] $author_check" . PHP_EOL;
			//	Get an array of people mentioned
			$mentions_arr 												= $message->mentions; 									//if($GLOBALS['debug_echo']) echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
			$mention_watch_name_queue_default							= "<@$author_id> is no longer watching the following users:" . PHP_EOL;
			$mention_watch_name_queue_full 								= "";
			
			if (!strpos($message_content_lower, "<")) { //String doesn't contain a mention
				$filter = "unwatch ";
				$value = str_replace($filter, "", $message_content_lower);
				$value = str_replace("<@!", "", $value);
				$value = str_replace("<@", "", $value);
				$value = str_replace("<@", "", $value);
				$value = str_replace(">", "", $value);
				if (is_numeric($value)) {
					if (!preg_match('/^[0-9]{16,20}$/', $value)) return $message->react('❌');
					$mention_member				= $author_guild->members->get('id', $value);
					$mention_user				= $mention_member->user;
					$mentions_arr				= array($mention_user);
				} else return $message->reply("Invalid input! Please enter a valid ID or @mention the user");
				if (is_null($mention_member)) return $message->reply("Invalid input! Please enter an ID or @mention the user");
			}
			
			foreach ($mentions_arr as $mention_param) {																				//if($GLOBALS['debug_echo']) echo "mention_param: " . PHP_EOL; var_dump ($mention_param);
		//		id, username, discriminator, bot, avatar, email, mfaEnabled, verified, webhook, createdTimestamp
				$mention_param_encode 									= json_encode($mention_param); 									//if($GLOBALS['debug_echo']) echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
				$mention_json 											= json_decode($mention_param_encode, true); 					//if($GLOBALS['debug_echo']) echo "mention_json: " . PHP_EOL; var_dump($mention_json);
				$mention_id 											= $mention_json['id']; 											//if($GLOBALS['debug_echo']) echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
				
				if ($mention_id != $discord->id) {
			//		Place watch info in target's folder
					$watchers[] = VarLoad($guild_folder."/".$mention_id, "$watchers.php");
					$watchers = array_value_remove($author_id, $watchers);
					VarSave($guild_folder."/".$mention_id, "watchers.php", $watchers);
					$mention_watch_name_queue 								= "**<@$mention_id>** ";
					$mention_watch_name_queue_full 							= $mention_watch_name_queue_full . PHP_EOL . $mention_watch_name_queue;
				}
			}
			//	React to the original message
			if ($react) $message->react("👍");
			//	Send the message
			if ($watch_channel) return $watch_channel->sendMessage($mention_watch_name_queue_default . $mention_watch_name_queue_full . PHP_EOL);
			else return $author_channel->sendMessage($mention_watch_name_queue_default . $mention_watch_name_queue_full . PHP_EOL);
		}
		if (str_starts_with($message_content_lower, 'infractions ')) { //;infractions @
			if($GLOBALS['debug_echo']) echo "[INFRACTIONS] $author_check" . PHP_EOL;
			//		Get an array of people mentioned
			$mentions_arr = $message->mentions; 									//if($GLOBALS['debug_echo']) echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
			$GetMentionResult = GetMention([&$author_guild,  substr($message_content_lower, 12, strlen($message_content_lower)), null, 1, &$restcord]);
			if (!$GetMentionResult) return $message->reply("Invalid input! Please enter a valid ID or @mention the user");

			if (!strpos($message_content_lower, "<")) { //String doesn't contain a mention
				$filter = "infractions ";
				$value = str_replace($filter, "", $message_content_lower);
				$value = str_replace("<@!", "", $value);
				$value = str_replace("<@", "", $value);
				$value = str_replace(">", "", $value);
				if (is_numeric($value)) {
					if (!preg_match('/^[0-9]{16,20}$/', $value)) return $message->react('❌');
					$mention_member = $author_guild->members->get('id', $value);
					$mention_user = $mention_member->user;
					$mentions_arr = array($mention_user);
				} else return $message->reply("Invalid input! Please enter a valid ID or @mention the user");
				if (is_null($mention_member)) return $message->reply("Invalid input! Please enter an ID or @mention the user");
			}
			
			//update
			$x = 0;
			$mention_user = $GetMentionResult[0];
			$mention_member = $GetMentionResult[1];
			$mentions_arr = $mentions_arr ?? $GetMentionResult[2];
			foreach ($mentions_arr as $mention_param) {																				//if($GLOBALS['debug_echo']) echo "mention_param: " . PHP_EOL; var_dump ($mention_param);
				if ($x == 0) { //We only want the first person mentioned
	//				id, username, discriminator, bot, avatar, email, mfaEnabled, verified, webhook, createdTimestamp
					$mention_param_encode 									= json_encode($mention_param); 									//if($GLOBALS['debug_echo']) echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
					$mention_json 											= json_decode($mention_param_encode, true); 					//if($GLOBALS['debug_echo']) echo "mention_json: " . PHP_EOL; var_dump($mention_json);
					$mention_id 											= $mention_json['id']; 											//if($GLOBALS['debug_echo']) echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
					$mention_username 										= $mention_json['username']; 									//if($GLOBALS['debug_echo']) echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
					$mention_discriminator 									= $mention_json['discriminator']; 								//if($GLOBALS['debug_echo']) echo "mention_discriminator: " . $mention_discriminator . PHP_EOL; //Just the discord ID
					$mention_check 											= $mention_username ."#".$mention_discriminator; 				//if($GLOBALS['debug_echo']) echo "mention_check: " . $mention_check . PHP_EOL; //Just the discord ID
					
					if ($mention_id != $discord->id) {
		//				Place infraction info in target's folder
						$infractions = VarLoad($guild_folder."/".$mention_id, "infractions.php"); //if($GLOBALS['debug_echo']) echo "path: $guild_folder\\$mention_id/infractions.php" . PHP_EOL;
						//if($GLOBALS['debug_echo']) echo "infractions:" . PHP_EOL; var_dump($infractions);
						$y = 0;
						$mention_infraction_queue = "";
						$mention_infraction_queue_full = "";
						foreach ($infractions as $infraction) {
							//Build a string
							$mention_infraction_queue = $mention_infraction_queue . "$y: " . $infraction . PHP_EOL;
							$y++;
						}
						$mention_infraction_queue_full 								= $mention_infraction_queue_full . PHP_EOL . $mention_infraction_queue;
					}
				}
				$x++;
			}
			//			Send a message
			if ($mention_infraction_queue != "") {
				$length = strlen($mention_infraction_queue_full);
				if ($length < 1025) {
					$embed = new \Discord\Parts\Embed\Embed($discord);
					$embed
	//					->setTitle("Commands")																	// Set a title
					->setColor(0xe1452d)																	// Set a color (the thing on the left side)
	//					->setDescription("Infractions for $mention_check")										// Set a description (below title, above fields)
					->addFieldValues("Infractions for $mention_check", "$mention_infraction_queue_full")			// New line after this
	//					->addFieldValues("⠀", "Use '" . "removeinfraction <@user_id> #' to remove")	// New line after this
					
	//					->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
	//					->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')			 	// Set an image (below everything except footer)
	//					->setTimestamp()																	 	// Set a timestamp (gets shown next to footer)
	//					->setAuthor("$author_check", "$author_guild_avatar")  									// Set an author with icon
					->setFooter("Palace Bot by Valithor#5947")							 					// Set a footer without icon
					->setURL("");							 												// Set the URL
	//					Send the embed to the author's channel
					return $author_channel->sendEmbed($embed);
				} else { //Too long, send reply instead of embed
					if ($react) $message->react("🗒️");
					return $message->reply($mention_infraction_queue_full . PHP_EOL);
				}
			} else {
				//if($react) $message->react("👎");
				return $message->reply("No infractions found!");
			}
		}
	}
	
	if ( ($creator || $author_perms['manage_messages']) && $message_content_lower == 'clearall') { //;clearall Clear as many messages in the author's channel at once as possible
		if($GLOBALS['debug_echo']) echo "[CLEARALL] $author_check" . PHP_EOL;
		$author_channel->limitDelete(100);
		
		$author_channel->getMessageHistory()->done(function ($message_collection) use ($author_channel) {
			//$author_channel->message->delete();
			//foreach ($message_collection as $message) {
				//limitDelete handles this
			//}
		});
		return;
	};
	if ( ($creator || $author_perms['manage_messages']) && str_starts_with($message_content_lower, 'clear ')) { //;clear #
		if($GLOBALS['debug_echo']) echo "[CLEAR #] $author_check" . PHP_EOL;
		$filter = "clear ";
		$value = str_replace($filter, "", $message_content_lower);
		if (is_numeric($value)) {
			$author_channel->limitDelete($value);
			/*$author_channel->fetchMessages()->done(function($message_collection) use ($author_channel) {
				foreach ($message_collection as $message) {
					$author_channel->message->delete();
				}
			});
*/
		}
		if ($modlog_channel) {
			$embed = new \Discord\Parts\Embed\Embed($discord);
			$embed
//				->setTitle("Commands")																	// Set a title
				->setColor(0xe1452d)																	// Set a color (the thing on the left side)
//				->setDescription("Infractions for $mention_check")										// Set a description (below title, above fields)
				->addFieldValues("Clear", "Deleted $value messages in <#$author_channel_id>")			// New line after this
//				->addFieldValues("⠀", "Use '" . "removeinfraction <@user_id> #' to remove")	// New line after this
				
				->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
//				->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')			 	// Set an image (below everything except footer)
//				->setTimestamp()																	 	// Set a timestamp (gets shown next to footer)
				->setAuthor("$author_check", "$author_avatar")  									// Set an author with icon
				->setFooter("Palace Bot by Valithor#5947")							 					// Set a footer without icon
				->setURL("");
			$modlog_channel->sendEmbed($embed);
		}
		
		$duration = 3;
		$author_channel->sendMessage("$author_check ($author_id) deleted $value messages!")->done(function ($new_message) use ($discord, $duration) { //Send message to channel confirming the message deletions then delete the new message after 3 seconds
			$discord->getLoop()->addTimer($duration, function () use ($new_message) {
				return $new_message->delete();
			});
			return;
		});
		return;
	};
	/*if ( ($creator || $author_perms['manage_roles']) && ((str_starts_with($message_content_lower, 'vwatch ')) || (str_starts_with($message_content_lower, 'vw ')))) { //;vwatch @
		if($GLOBALS['debug_echo']) echo "[VWATCH] $author_check" . PHP_EOL;
		//		Get an array of people mentioned
		$mentions_arr 												= $message->mentions; 									//if($GLOBALS['debug_echo']) echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
		if ($watch_channel) {
			$mention_watch_name_mention_default		= "<@$author_id>";
		}
		$mention_watch_name_queue_default							= $mention_watch_name_mention_default."is watching the following users:" . PHP_EOL;
		$mention_watch_name_queue_full 								= "";
		
		if (!strpos($message_content_lower, "<")) { //String doesn't contain a mention
			$filter = "vwatch ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = str_replace("<@!", "", $value);
			$value = str_replace("<@", "", $value);
			$value = str_replace("<@", "", $value);
			$value = str_replace(">", "", $value);
			$filter = "vw ";
			$value = str_replace($filter, "", $value);
			$value = str_replace("<@!", "", $value);
			$value = str_replace("<@", "", $value);
			$value = str_replace("<@", "", $value);
			$value = str_replace(">", "", $value);
			if (is_numeric($value)) {
				if (!preg_match('/^[0-9]{16,20}$/', $value)) return $message->react('❌');
				$mention_member				= $author_guild->members->get('id', $value);
				$mention_user				= $mention_member->user;
				$mentions_arr				= array($mention_user);
			} else return $message->reply("Invalid input! Please enter a valid ID or @mention the user");
			if (is_null($mention_member)) return $message->reply("Invalid input! Please enter an ID or @mention the user");
		}
		
		foreach ($mentions_arr as $mention_param) {																				//if($GLOBALS['debug_echo']) echo "mention_param: " . PHP_EOL; var_dump ($mention_param);
	//				id, username, discriminator, bot, avatar, email, mfaEnabled, verified, webhook, createdTimestamp
			$mention_param_encode 									= json_encode($mention_param); 									//if($GLOBALS['debug_echo']) echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
			$mention_json 											= json_decode($mention_param_encode, true); 					//if($GLOBALS['debug_echo']) echo "mention_json: " . PHP_EOL; var_dump($mention_json);
			$mention_id 											= $mention_json['id']; 											//if($GLOBALS['debug_echo']) echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
			
	//				Place watch info in target's folder
			$watchers[] = VarLoad($guild_folder."/".$mention_id, "$watchers.php");
			$watchers = array_unique($arr);
			$watchers[] = $author_id;
			VarSave($guild_folder."/".$mention_id, "watchers.php", $watchers);
			$mention_watch_name_queue 								= "**<@$mention_id>** ";
			$mention_watch_name_queue_full 							= $mention_watch_name_queue_full . PHP_EOL . $mention_watch_name_queue;
			
			if($GLOBALS['debug_echo']) echo "mention_id: " . $mention_id . PHP_EOL;
			$target_guildmember 									= $message->guild->members->get('id', $mention_id);
			$target_guildmember_role_collection 					= $target_guildmember->roles;									//if($GLOBALS['debug_echo']) echo "target_guildmember_role_collection: " . (count($author_guildmember_role_collection)-1);
			
			//				Populate arrays of the info we need
			$target_verified = false;
			foreach ($target_guildmember_role_collection as $role)
				if ($role->id == $role_verified_id) $target_verified = true;
			
			if (!$target_verified) {
				//					Build the string for the reply
				$mention_role_name_queue 							= "**<@$mention_id>** ";
				$mention_role_name_queue_full 						= $mention_role_name_queue_full . PHP_EOL . $mention_role_name_queue;
				//					Add the verified role to the member
				$target_guildmember->addRole($role_verified_id)->done(
					function () {
						//if ($general_channel) $general_channel->sendMessage( 'Welcome to the Palace, <@$mention_id>! Feel free to pick out some roles in #role-picker!');
					},
					function ($error) {
						var_dump($error->getMessage());
					}
				);
				if($GLOBALS['debug_echo']) echo "Verify role added to $mention_id" . PHP_EOL;
			}
		}
		//			Send a message
		if ($mention_watch_name_queue != "") {
			if ($watch_channel) {
				$watch_channel->sendMessage($mention_watch_name_queue_default . $mention_watch_name_queue_full . PHP_EOL);
			} else {
				$message->reply($mention_watch_name_queue_default . $mention_watch_name_queue_full . PHP_EOL);
			}
			//				React to the original message
			//				if($react) $message->react("👀");
			if ($react) {
				$message->react("👁");
			}
			if ($general_channel) {
				$msg = "Welcome to the Palace, <@$mention_id>!";
				if ($rolepicker_channel) {
					$msg = $msg . " Feel free to pick out some roles in <#$rolepicker_channel_id>.";
				}
				if ($general_channel) {
					$general_channel->sendMessage($msg);
				}
			}
			return;
		} else {
			if ($react) {
				$message->react("👎");
			}
			$message->reply("Nobody in the guild was mentioned!");
			return;
		}
	}
	*/
	if ( ($creator || $author_perms['ban_members']) && str_starts_with($message_content_lower, 'ban ')) { //;ban
		if($GLOBALS['debug_echo']) echo "[BAN]" . PHP_EOL;
		$mention_id_array = [];
		preg_match_all('/<@([0-9]*)>/', $message->content, $matches1);
		preg_match_all('/<@!([0-9]*)>/', $message->content, $matches2);
		$matches = array_merge($matches1, $matches2);
		if ($matches) {
			foreach($matches as $array) {
				foreach ($array as $match) {
					if (is_numeric($match)) {
						if ($match != $discord->id && $match != $author_id) { // Don't let users ban themselves or the bot with this command
							if ($member = $author_guild->members->get('id', $match)) {
								$mention_id_array[] = $match;
							} else $invalid_mention_id_array = $match; // Used to inform the user who couldn't be banned because they don't exist in the server (or they're not cached in the members repository)
						}
					}
				}
			}
		}
		$msg = "The following users have been banned: ";
        $banned = '';
		foreach ($mention_id_array as $target_member_id) {
			if($GLOBALS['debug_echo']) '[BAN ID] ' . $target_member_id . PHP_EOL . ' [BAN MEMBER] ' . $author_guild->members->get('id', "$target_member_id") . PHP_EOL . '[MESSAGE CONTENT] ' . $message->content . PHP_EOL;
			$author_guild->bans->ban($target_member = $author_guild->members->get('id', "$target_member_id"), 0, $message->content);
			$banned .= $target_member;
		}
        if ($banned) return $message->reply($msg . $banned);
        else {
			if ($author_guild->id != '468979034571931648') return $message->reply('No discord members were mentioned to ban!');
			return;
		}
	}
	if ( ($creator || $author_perms['ban_members']) && str_starts_with($message_content_lower, 'unban ')) { //;ban
		if($GLOBALS['debug_echo']) echo "[UNBAN]" . PHP_EOL;
		//Get an array of people mentioned
		$mentions_arr 	= $message->mentions; //if($GLOBALS['debug_echo']) echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
		
		$GetMentionResult = GetMention([&$author_guild,  substr($message_content_lower, 6, strlen($message_content_lower)), null, 1, &$restcord]);
		if (!$GetMentionResult) return $message->reply("Invalid input! Please enter a valid ID or @mention the user");
		$mention_id_array = array();
		$reason_text = null;
		$keys = array_keys($GetMentionResult);
		for ($i = 0; $i < count($GetMentionResult); $i++) {
			if (is_numeric($keys[$i])) {
				$mention_id_array[] = $keys[$i];
			} else {
				foreach ($GetMentionResult[$keys[$i]] as $key => $value) {
					$reason_text = $value ?? "None";
				}
			}
		}
		$mention_user = $GetMentionResult[0];
		$mention_member = $GetMentionResult[1];
		/*
		$mentions_arr = $mentions_arr ?? $GetMentionResult[2];
		foreach ( $mentions_arr as $mention_param ) { //This should skip because there is no member object
			$mention_param_encode 									= json_encode($mention_param); 									//if($GLOBALS['debug_echo']) echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
			$mention_json 											= json_decode($mention_param_encode, true); 				//if($GLOBALS['debug_echo']) echo "mention_json: " . PHP_EOL; var_dump($mention_json);
			$mention_id 											= $mention_json['id']; 											//if($GLOBALS['debug_echo']) echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
			$mention_discriminator 									= $mention_json['discriminator']; 								//if($GLOBALS['debug_echo']) echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
			$mention_username 										= $mention_json['username']; 									//if($GLOBALS['debug_echo']) echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
			$mention_check 											= $mention_username ."#".$mention_discriminator;
			//Build the string to log
			$filter = "unban <@!$mention_id>";
			$warndate = date("m/d/Y");
			$reason = "**User:** <@$mention_id>
			**🗓️Date:** $warndate
			**📝Reason:** $reason_text";
			//$target_guildmember->ban(1, $reason);
			$author_guild->unban($mention_id)->done(function ($r) {
			  var_dump($r);
			}, function ($error) {
			  var_dump($error->getMessage());
			});

			//$author_guild->bans->fetch($mention_id)->done(function ($ban) use ($guild) {
			//	$author_guild->bans->delete($ban);
			//});


			//Build the embed message
			$embed = new \Discord\Parts\Embed\Embed($discord);
			$embed
//					->setTitle("Commands")																	// Set a title
				->setColor(0xe1452d)																	// Set a color (the thing on the left side)
				->setDescription("$reason")																// Set a description (below title, above fields)
//					->addFieldValues("⠀", "$reason")																// New line after this

//					->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
//					->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')			 	// Set an image (below everything except footer)
				->setTimestamp()																	 	// Set a timestamp (gets shown next to footer)
				->setAuthor("$author_check ($author_id)", "$author_avatar")  							// Set an author with icon
				->setFooter("Palace Bot by Valithor#5947")							 					// Set a footer without icon
				->setURL("");							 												// Set the URL
//						Send the message
			if($modlog_channel)$modlog_channel->sendEmbed($embed);
			if($react) $message->react("🔨"); //Hammer
			return; //No more processing, we only want to process the first person mentioned
		} //foreach method didn't return, so nobody in the guild was mentioned
		*/
		$output_string = "Mentions IDs: ";
		$keys = array_keys($GetMentionResult);
		$ids = array();
		for ($i = 0; $i < count($GetMentionResult); $i++) {
			if (is_numeric($keys[$i])) {
				//$output_string = $output_string . " " . $keys[$i];
				$ids[] = $keys[$i];
			} else {
				foreach ($GetMentionResult[$keys[$i]] as $key => $value) {
					$clean_string = $value;
				}
			}
		}
		/*
		$output_string = $output_string  . PHP_EOL . "Clean string: " . $clean_string;
		$author_channel->sendMessage( $output_string);
		*/
		foreach ($ids as $id) {
			$author_guild->unban($id)->done(function ($r) {
				var_dump($r);
			}, function ($error) {
				var_dump($error->getMessage());
			});
		}
		return;
	}
	if ( ($creator || $author_perms['kick_members']) && str_starts_with($message_content_lower, 'kick ')) { //;kick
		if($GLOBALS['debug_echo']) echo "[KICK]" . PHP_EOL;
		//Get an array of people mentioned
		if(!($mentions_arr = $message->mentions)) { //if($GLOBALS['debug_echo']) echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
			$mentions_arr = array();
			$value = str_replace($filter, "", $message_content_lower);
			$value = str_replace("<@!", "", $value);
			$value = str_replace("<@", "", $value);
			$value = str_replace(">", "", $value);
			$arr = explode(' ', $value);
			foreach ($arr as $val) {
				if (is_numeric($val))
					if (preg_match('/^[0-9]{16,20}$/', $val))
						if ($target_user = $discord->users->get('id', $val))
							$mentions_arr[] = $target_user;
			}
		}
		if(empty($mentions_arr)) return $message->reply("Invalid input! Please enter a valid ID or @mention the user");
		foreach ($mentions_arr as $mention_param) {
			$mention_param_encode 									= json_encode($mention_param); 									//if($GLOBALS['debug_echo']) echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
			$mention_json 											= json_decode($mention_param_encode, true); 					//if($GLOBALS['debug_echo']) echo "mention_json: " . PHP_EOL; var_dump($mention_json);
			$mention_id 											= $mention_json['id']; 											//if($GLOBALS['debug_echo']) echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
			$mention_discriminator 									= $mention_json['discriminator']; 								//if($GLOBALS['debug_echo']) echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
			$mention_username 										= $mention_json['username']; 									//if($GLOBALS['debug_echo']) echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
			$mention_check 											= $mention_username ."#".$mention_discriminator;
			 
			if ($author_id != $mention_id && $mention_id != $discord->id) { //Don't let anyone kick themselves or the bot
				//Get the roles of the mentioned user
				$target_guildmember 								= $message->guild->members->get('id', $mention_id); 	//This is a GuildMember object
				$target_guildmember_role_collection 				= $target_guildmember->roles;					//This is the Role object for the GuildMember
				
				$target_dev = false;
				$target_owner = false;
				$target_admin = false;
				$target_mod = false;
				$target_vzgbot = false;
				$target_guildmember_roles_ids = array();
				foreach ($target_guildmember_role_collection as $role) {
					$target_guildmember_roles_ids[] = $role->id; 													//if($GLOBALS['debug_echo']) echo "role[$x] id: " . PHP_EOL; //var_dump($role->id);
					if ($role->id == $role_18_id) $target_adult = true; //Author has the 18+ role
					if ($role->id == $role_dev_id) $target_dev = true; //Author has the dev role
					if ($role->id == $role_owner_id) $target_owner = true; //Author has the owner role
					if ($role->id == $role_admin_id) $target_admin = true; //Author has the admin role
					if ($role->id == $role_mod_id) $target_mod = true; //Author has the mod role
					if ($role->id == $role_verified_id) $target_verified = true; //Author has the verified role
					if ($role->id == $role_bot_id) $target_bot = true; //Author has the bot role
					if ($role->id == $role_vzgbot_id) $target_vzgbot = true; //Author is this bot
					if ($role->id == $role_muted_id) $target_muted = true; //Author is this bot
				}
				if ((!$target_dev && !$target_owner && !$target_admin && !$target_mod && !$target_vzg) || ($creator || $owner || $dev)) { //Bot creator, guild owner, and devs can kick anyone
					if ($mention_id == $creator_id) return; //Don't kick the creator
					//Build the string to log
					$filter = "kick <@!$mention_id>";
					$warndate = date("m/d/Y");
					$reason = "**🥾Kicked:** <@$mention_id>
					**🗓️Date:** $warndate
					**📝Reason:** " . str_replace($filter, "", $message_content);
					//Kick the user
					$message->guild->members->kick($target_guildmember);
					/*
					$target_guildmember->kick($reason)->done(null, function ($error) {
						var_dump($error->getMessage()); //if($GLOBALS['debug_echo']) echo any errors
					});
					*?
					if ($react) {
						$message->react("🥾");
					} //Boot
					/*
					//Build the embed message
					$embed = new \Discord\Parts\Embed\Embed($discord);
					$embed
	//							->setTitle("Commands")																	// Set a title
						->setColor(0xe1452d)																	// Set a color (the thing on the left side)
						->setDescription("$reason")																// Set a description (below title, above fields)
	//							->addFieldValues("⠀", "$reason")																// New line after this

	//							->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
	//							->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')			 	// Set an image (below everything except footer)
						->setTimestamp()																	 	// Set a timestamp (gets shown next to footer)
						->setAuthor("$author_check ($author_id)", "$author_avatar")  									// Set an author with icon
						->setFooter("Palace Bot by Valithor#5947")							 					// Set a footer without icon
						->setURL("");							 												// Set the URL
	//						Send the message
					if($modlog_channel)$modlog_channel->sendEmbed($embed);
					*/
					return;
				} else return  $author_channel->sendMessage("<@$mention_id> cannot be kicked because of their roles!");
			} else {
				if ($react) $message->react("👎");
				return $message->reply("You can't kick yourself!");
			}
		} //foreach method didn't return, so nobody was mentioned
		if ($react) $message->react("👎");
		return $message->reply("You need to mention someone!");
	}
	if ( ($creator || $author_perms['kick_members']) && str_starts_with($message_content_lower, 'warn ')) { //;warn @
		if($GLOBALS['debug_echo']) echo "[WARN] $author_check" . PHP_EOL;
		//$message->reply("Not yet implemented!");
//		Get an array of people mentioned
		$mentions_arr 												= $message->mentions; 									//if($GLOBALS['debug_echo']) echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
		if ($modlog_channel) {
			$mention_warn_name_mention_default		= "<@$author_id>";
		}
		$mention_warn_queue_default									= $mention_warn_name_mention_default." warned the following users:" . PHP_EOL;
		$mention_warn_queue_full 									= "";
		
		foreach ($mentions_arr as $mention_param) {																				//if($GLOBALS['debug_echo']) echo "mention_param: " . PHP_EOL; var_dump ($mention_param);
//			id, username, discriminator, bot, avatar, email, mfaEnabled, verified, webhook, createdTimestamp
			$mention_param_encode 									= json_encode($mention_param); 									//if($GLOBALS['debug_echo']) echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
			$mention_json 											= json_decode($mention_param_encode, true); 					//if($GLOBALS['debug_echo']) echo "mention_json: " . PHP_EOL; var_dump($mention_json);
			$mention_id 											= $mention_json['id']; 											//if($GLOBALS['debug_echo']) echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
			$mention_username 										= $mention_json['username']; 									//if($GLOBALS['debug_echo']) echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
			$mention_discriminator 									= $mention_json['discriminator']; 								//if($GLOBALS['debug_echo']) echo "mention_discriminator: " . $mention_discriminator . PHP_EOL; //Just the discord ID
			$mention_check 											= $mention_username ."#".$mention_discriminator; 				//if($GLOBALS['debug_echo']) echo "mention_check: " . $mention_check . PHP_EOL; //Just the discord ID
			
			if ($mention_id != $discord->id) {
				//Build the string to log
				$filter = "warn <@!$mention_id>";
				$warndate = date("m/d/Y");
				$mention_warn_queue = "**$mention_check was warned by $author_check on $warndate for reason: **" . str_replace($filter, "", $message_content);
				
				//Place warn info in target's folder
				$infractions = VarLoad($guild_folder."/".$mention_id, "infractions.php");
				$infractions[] = $mention_warn_queue;
				VarSave($guild_folder."/".$mention_id, "infractions.php", $infractions);
				$mention_warn_queue_full = $mention_warn_queue_full . PHP_EOL . $mention_warn_queue;
			}
		}
		//		Send a message
		if ($mention_warn_queue != "") {
			if ($watch_channel) $watch_channel->sendMessage($mention_warn_queue_default . $mention_warn_queue_full . PHP_EOL);
			else $message->channel->sendMessage($mention_warn_queue_default . $mention_warn_queue_full . PHP_EOL);
			//			React to the original message
			//			if($react) $message->react("👀");
			if ($react) $message->react("👁");
			return;
		} else {
			if ($react) $message->react("👎");
			return $message->reply("Nobody in the guild was mentioned!");
		}
	}
	if ( ($creator || $author_perms['kick_members']) && str_starts_with($message_content_lower, 'removeinfraction ')) { //;removeinfractions <@user_id> #
		if($GLOBALS['debug_echo']) echo "[REMOVE INFRACTION] $author_check" . PHP_EOL;
		//	Get an array of people mentioned
		$mentions_arr = $message->mentions; 									//if($GLOBALS['debug_echo']) echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object

		$filter = "removeinfraction ";
		$value = str_replace($filter, "", $message_content_lower);
		$value = str_replace("<@!", "", $value);
		$value = str_replace("<@", "", $value);
		$value = str_replace(">", "", $value);
		$arr = explode(' ', $value); //[mention_id, index]
		if (is_numeric($arr[0])) {
			if (!preg_match('/^[0-9]{16,20}$/', $arr[0])) return $message->react('❌');
			$mention_member				= $author_guild->members->get('id', $arr[0]);
			$mention_user				= $mention_member->user;
			$mentions_arr				= array($mention_user);
		} else return $message->reply("Invalid input! Please enter a valid ID or @mention the user");
		if (is_null($mention_member)) return $message->reply("Invalid input! Please enter an ID or @mention the user");
		
		$x = 0;
		foreach ($mentions_arr as $mention_param) {																				//if($GLOBALS['debug_echo']) echo "mention_param: " . PHP_EOL; var_dump ($mention_param);
			if ($x == 0) { //We only want the first person mentioned
	//			id, username, discriminator, bot, avatar, email, mfaEnabled, verified, webhook, createdTimestamp
				$mention_param_encode 									= json_encode($mention_param); 									//if($GLOBALS['debug_echo']) echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
				$mention_json 											= json_decode($mention_param_encode, true); 					//if($GLOBALS['debug_echo']) echo "mention_json: " . PHP_EOL; var_dump($mention_json);
				$mention_id 											= $mention_json['id']; 											//if($GLOBALS['debug_echo']) echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
				$mention_username 										= $mention_json['username']; 									//if($GLOBALS['debug_echo']) echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
				$mention_discriminator 									= $mention_json['discriminator']; 								//if($GLOBALS['debug_echo']) echo "mention_discriminator: " . $mention_discriminator . PHP_EOL; //Just the discord ID
				$mention_check 											= $mention_username ."#".$mention_discriminator; 				//if($GLOBALS['debug_echo']) echo "mention_check: " . $mention_check . PHP_EOL; //Just the discord ID
				
				if ($mention_id != $discord->id) {
		//			Get infraction info in target's folder
					$infractions = VarLoad($guild_folder."/".$mention_id, "infractions.php");
					//Check if $$arr[1] is a number
					if (isset($arr[1]) && (is_numeric(intval($arr[1])))) {
						//Remove array element and reindex
						if (isset($infractions[$arr[1]])) {
							$infractions[$arr[1]] = "Infraction removed by $author_check on " . date("m/d/Y"); // for arrays where key equals offset
							VarSave($guild_folder."/".$mention_id, "infractions.php", $infractions);//Save the new infraction log 
							//Send a message
							if ($react) $message->react("👍");
							return $message->reply("Infraction `".$arr[1]."` removed from $mention_check!");
						} else {
							if ($react) $message->react("👎");
							return $message->reply("Infraction '".$arr[1]."' not found!");
						}
					} else {
						if ($react) $message->react("👎");
						return $message->reply("'".$arr[1]."' is not a number");
					}
				}
			}
			$x++;
		}
	}
	if ( ($creator || $author_perms['manage_roles']) && str_starts_with($message_content_lower, 'mute ')) { //;mute
		if($GLOBALS['debug_echo']) echo "[MUTE]" . PHP_EOL;
		//			Get an array of people mentioned
		$mentions_arr = $message->mentions;
		if (!strpos($message_content_lower, "<")) { //String doesn't contain a mention
			$filter = "mute ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = str_replace("<@!", "", $value);
			$value = str_replace("<@", "", $value);
			$value = str_replace(">", "", $value);//if($GLOBALS['debug_echo']) echo "value: " . $value . PHP_EOL;
			if (is_numeric($value) && $value != $discord->id && $value != $author_id && $value != $guild_owner_id) { // //Don't let anyone mute themselves or the bot or the guild owner
				if (!preg_match('/^[0-9]{16,20}$/', $value)) return $message->react('❌');
				$mention_member				= $author_guild->members->get('id', $value);
				$mention_user				= $mention_member->user;
				$mentions_arr				= array($mention_user);
			} else return $message->reply("Invalid input! Please enter a valid ID or @mention the user");
			if (is_null($mention_member)) return $message->reply("Invalid input! Please enter an ID or @mention the user");
		}
		foreach ($mentions_arr as $mention_param) {
			$mention_param_encode 									= json_encode($mention_param); 									//if($GLOBALS['debug_echo']) echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
			$mention_json 											= json_decode($mention_param_encode, true); 					//if($GLOBALS['debug_echo']) echo "mention_json: " . PHP_EOL; var_dump($mention_json);
			$mention_id 											= $mention_json['id']; 											//if($GLOBALS['debug_echo']) echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
			$mention_discriminator 									= $mention_json['discriminator']; 								//if($GLOBALS['debug_echo']) echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
			$mention_username 										= $mention_json['username']; 									//if($GLOBALS['debug_echo']) echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
			$mention_check 											= $mention_username ."#".$mention_discriminator;
			
			//Get the roles of the mentioned user
			$target_guildmember 								= $message->guild->members->get('id', $mention_id); 	//This is a GuildMember object
			$target_guildmember_role_collection 				= $target_guildmember->roles;					//This is the Role object for the GuildMember
			
//  			Populate arrays of the info we need
			//				$target_guildmember_roles_names 					= array();
			
			$target_dev = false;
			$target_owner = false;
			$target_admin = false;
			$target_mod = false;
			$target_vzgbot = false;
			$target_guildmember_roles_ids = array();
			$removed_roles = array();
			foreach ($target_guildmember_role_collection as $role) {
					$removed_roles[] = $role->id;
					$target_guildmember_roles_ids[] = $role->id; 													//if($GLOBALS['debug_echo']) echo "role[$x] id: " . PHP_EOL; //var_dump($role->id);
					if ($role->id == $role_dev_id) $target_dev = true; //Author has the dev role
					if ($role->id == $role_owner_id) $target_owner = true; //Author has the owner role
					if ($role->id == $role_admin_id) $target_admin = true; //Author has the admin role
					if ($role->id == $role_mod_id) $target_mod = true; //Author has the mod role
					if ($role->id == $role_vzgbot_id) $target_vzgbot = true; //Author is this bot
				
			}
			if ((!$target_dev && !$target_owner && !$target_admin && !$target_mod && !$target_vzg) || ($creator || $owner || $dev)) { //Guild owner and bot creator can mute anyone
				if ($mention_id == $creator_id) return; //Don't mute the creator
				//Save current roles in a file for the user
				VarSave($guild_folder."/".$mention_id, "removed_roles.php", $removed_roles);
				//Build the string to log
				$filter = "mute <@!$mention_id>";
				$warndate = date("m/d/Y");
				$reason = "**🥾Muted:** <@$mention_id>
				**🗓️Date:** $warndate
				**📝Reason:** " . str_replace($filter, "", $message_content);
				//Remove all roles and add the muted role (TODO: REMOVE ALL ROLES AND RE-ADD THEM UPON BEING UNMUTED)
				/*foreach ($removed_roles as $role)
					$target_guildmember->removeRole($role);*/
				$remove = function ($removed_roles, $role_muted_id) use (&$remove) {
					if (count($removed_roles) != 0) {
						$target_guildmember->removeRole(array_shift($removed_roles))->done(function () use ($remove, $removed_roles) {
							$remove($removed_roles);
						});
					} else $target_guildmember->addRole($role_muted_id);
				};
				$remove($removed_roles);
				if ($role_muted_id) $target_guildmember->addRole($role_muted_id);
				if ($react) $message->react("🤐");
				/*
				//Build the embed message
				$embed = new \Discord\Parts\Embed\Embed($discord);
				$embed
//							->setTitle("Commands")																	// Set a title
					->setColor(0xe1452d)																	// Set a color (the thing on the left side)
					->setDescription("$reason")																// Set a description (below title, above fields)
//							->addFieldValues("⠀", "$reason")																// New line after this

//							->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
//							->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')			 	// Set an image (below everything except footer)
					->setTimestamp()																	 	// Set a timestamp (gets shown next to footer)
					->setAuthor("$author_check ($author_id)", "$author_avatar")  									// Set an author with icon
					->setFooter("Palace Bot by Valithor#5947")							 					// Set a footer without icon
					->setURL("");							 												// Set the URL
//						Send the message
				if($modlog_channel)$modlog_channel->sendEmbed($embed);
				*/
				return;
			} else return $author_channel->sendMessage("<@$mention_id> cannot be muted because of their roles!");
		} //foreach method didn't return, so nobody was mentioned
		if ($react) $message->react("👎");
		return $message->reply("You need to mention someone!");
	}
	if ( ($creator || $author_perms['manage_roles']) && str_starts_with($message_content_lower, 'unmute ')) { //;unmute
		if($GLOBALS['debug_echo']) echo "[UNMUTE]" . PHP_EOL;
		//			Get an array of people mentioned
		$mentions_arr 												= $message->mentions; 									//if($GLOBALS['debug_echo']) echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
		if (!strpos($message_content_lower, "<")) { //String doesn't contain a mention
			$filter = "unmute ";
			$value = str_replace($filter, "", $message_content_lower);
			$value = str_replace("<@!", "", $value);
			$value = str_replace("<@", "", $value);
			$value = str_replace(">", "", $value);//if($GLOBALS['debug_echo']) echo "value: " . $value . PHP_EOL;
			if (is_numeric($value) && $value != $discord->id && $value != $guild_owner_id) {
				if (!preg_match('/^[0-9]{16,20}$/', $value)) return $message->react('❌');
				$mention_member				= $author_guild->members->get('id', $value);
				$mention_user				= $mention_member->user;
				$mentions_arr				= array($mention_user);
			} else return $message->reply("Invalid input! Please enter a valid ID or @mention the user");
			if (is_null($mention_member)) return $message->reply("Invalid input! Please enter an ID or @mention the user");
		}
		foreach ($mentions_arr as $mention_param) {
			$mention_param_encode 									= json_encode($mention_param); 									//if($GLOBALS['debug_echo']) echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
			$mention_json 											= json_decode($mention_param_encode, true); 					//if($GLOBALS['debug_echo']) echo "mention_json: " . PHP_EOL; var_dump($mention_json);
			$mention_id 											= $mention_json['id']; 											//if($GLOBALS['debug_echo']) echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
			$mention_discriminator 									= $mention_json['discriminator']; 								//if($GLOBALS['debug_echo']) echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
			$mention_username 										= $mention_json['username']; 									//if($GLOBALS['debug_echo']) echo "mention_username: " . $mention_username . PHP_EOL; //Just the discord ID
			$mention_check 											= $mention_username ."#".$mention_discriminator;
			
			//Get the roles of the mentioned user
			$target_guildmember 								= $message->guild->members->get('id', $mention_id);
			$target_guildmember_role_collection 				= $target_guildmember->roles;

			//				Get the roles of the mentioned user
			$target_dev = false;
			$target_owner = false;
			$target_admin = false;
			$target_mod = false;
			$target_vzgbot = false;
			//				Populate arrays of the info we need
			$target_guildmember_roles_ids = array();
			
			foreach ($target_guildmember_role_collection as $role) {
					$target_guildmember_roles_ids[]= $role->id; 													//if($GLOBALS['debug_echo']) echo "role[$x] id: " . PHP_EOL; //var_dump($role->id);
					if ($role->id == $role_dev_id) $target_dev = true; //Author has the dev role
					if ($role->id == $role_owner_id) $target_owner = true; //Author has the owner role
					if ($role->id == $role_admin_id) $target_admin = true; //Author has the admin role
					if ($role->id == $role_mod_id) $target_mod = true; //Author has the mod role
					if ($role->id == $role_vzgbot_id) $target_vzgbot = true; //Author is this bot
					if ($role->name == "Palace Bot") $target_vzgbot = true; //Author is this bot
			}
			if ((!$target_dev && !$target_owner && !$target_admin && !$target_mod && !$target_vzg) || ($creator || $owner || $dev)) {
				if ($mention_id == $creator_id) return;
				//Build the string to log
				$filter = "unmute <@!$mention_id>";
				$warndate = date("m/d/Y");
				$reason = "**🥾Unmuted:** <@$mention_id>
				**🗓️Date:** $warndate
				**📝Reason:** " . str_replace($filter, "", $message_content);
				//Unmute the user and readd the verified role (TODO: READD REMOVED ROLES)
				//Save current roles in a file for the user
				$removed_roles = VarLoad($guild_folder."/".$mention_id, "removed_roles.php");
				foreach ($removed_roles as $role) $target_guildmember->addRole($role);
				if ($role_muted_id) $target_guildmember->removeRole($role_muted_id);
				if ($react) $message->react("😩");
				//Build the embed message
				/*
				$embed = new \Discord\Parts\Embed\Embed($discord);
				$embed
//							->setTitle("Commands")																	// Set a title
					->setColor(0xe1452d)																	// Set a color (the thing on the left side)
					->setDescription("$reason")																// Set a description (below title, above fields)
//							->addFieldValues("⠀", "$reason")																// New line after this

//							->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
//							->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')			 	// Set an image (below everything except footer)
					->setTimestamp()																	 	// Set a timestamp (gets shown next to footer)
					->setAuthor("$author_check ($author_id)", "$author_avatar")  							// Set an author with icon
					->setFooter("Palace Bot by Valithor#5947")							 					// Set a footer without icon
					->setURL("");							 												// Set the URL
//						Send the message
				if($modlog_channel)$modlog_channel->sendEmbed($embed);
				*/
				return;
			} else return $author_channel->sendMessage("<@$mention_id> cannot be unmuted because of their roles!");
		} //foreach method didn't return, so nobody was mentioned
		if ($react) $message->react("👎");
		return $message->reply("You need to mention someone!");
	}
	if ( ($creator || $author_perms['manage_roles']) && ((str_starts_with($message_content_lower, 'v ')) || (str_starts_with($message_content_lower, 'verify ')))) { //Verify ;v ;verify
		if ($role_verified_id) { //This command only works if the Verified Role is setup
			if($GLOBALS['debug_echo']) echo "[VERIFY] $author_check" . PHP_EOL;
			//	Get an array of people mentioned
			$mentions_arr 												= $message->mentions; 									//if($GLOBALS['debug_echo']) echo "mentions_arr: " . PHP_EOL; var_dump ($mentions_arr); //Shows the collection object
			$mention_role_name_queue_default							= "<@$author_id> verified the following users:" . PHP_EOL;
			$mention_role_name_queue_full 								= $mention_role_name_queue_default;
			
			$filter = "v ";
			$value = str_replace($filter, "", $message_content_lower);
			$filter = "verify ";
			$value = str_replace($filter, "", $value);
			$value = str_replace("<@!", "", $value);
			$value = str_replace("<@", "", $value);
			$value = str_replace("<@", "", $value);
			$value = str_replace(">", "", $value);
			
			if (is_numeric($value)) {
				if (!preg_match('/^[0-9]{16,20}$/', $value)) return $message->react('❌');
				$mention_member				= $author_guild->members->get('id', $value);
				$mention_user				= $mention_member->user;
				$mentions_arr				= array($mention_user);
			} else return $message->reply("Invalid input! Please enter a valid ID or @mention the user.");
			if (is_null($mention_member)) return $message->reply("Invalid ID or user not found! Are they in the server?");
			
			foreach ($mentions_arr as $mention_param) {																				//if($GLOBALS['debug_echo']) echo "mention_param: " . PHP_EOL; var_dump ($mention_param);
		//		id, username, discriminator, bot, avatar, email, mfaEnabled, verified, webhook, createdTimestamp
				$mention_param_encode 									= json_encode($mention_param); 									//if($GLOBALS['debug_echo']) echo "mention_param_encode: " . $mention_param_encode . PHP_EOL;
				$mention_json 											= json_decode($mention_param_encode, true); 					//if($GLOBALS['debug_echo']) echo "mention_json: " . PHP_EOL; var_dump($mention_json);
				$mention_id 											= $mention_json['id']; 											//if($GLOBALS['debug_echo']) echo "mention_id: " . $mention_id . PHP_EOL; //Just the discord ID
				
		//		$mention_discriminator 									= $mention_json['discriminator']; 								//if($GLOBALS['debug_echo']) echo "mention_discriminator: " . $mention_discriminator . PHP_EOL; //Just the discord ID
				//		$mention_check 											= $mention_username ."#".$mention_discriminator; 				//if($GLOBALS['debug_echo']) echo "mention_check: " . $mention_check . PHP_EOL; //Just the discord ID
				
				if (is_numeric($mention_id) && $mention_id != $discord->id) {
					//		Get the roles of the mentioned user
					$target_guildmember 									= $message->guild->members->get('id', $mention_id);
					$target_guildmember_role_collection 					= $target_guildmember->roles;									//if($GLOBALS['debug_echo']) echo "target_guildmember_role_collection: " . (count($author_guildmember_role_collection)-1);

					//		Get the avatar URL of the mentioned user
					$target_guildmember_user								= $target_guildmember->user;									//if($GLOBALS['debug_echo']) echo "member_class: " . get_class($target_guildmember_user) . PHP_EOL;
					$mention_avatar 										= "{$target_guildmember_user->avatar}";					//if($GLOBALS['debug_echo']) echo "mention_avatar: " . $mention_avatar . PHP_EOL;				//if($GLOBALS['debug_echo']) echo "target_guildmember_role_collection: " . (count($target_guildmember_role_collection)-1);
					
					$target_verified = false; //Default
					foreach ($target_guildmember_role_collection as $role)
						if ($role->id == $role_verified_id) $target_verified = true;
					if (!$target_verified) { //Add the verified role to the member
						$target_guildmember->addRole($role_verified_id)->done(
							null,
							function ($error) {
								var_dump($error->getMessage());
							}
						); //if($GLOBALS['debug_echo']) echo "Verify role added ($role_verified_id)" . PHP_EOL;
					
						//			Build the embed
						/*
						$embed = new \Discord\Parts\Embed\Embed($discord);
						$embed
			//				->setTitle("Roles")																		// Set a title
							->setColor(0xe1452d)																	// Set a color (the thing on the left side)
			//				->setDescription("$author_guild_name")													// Set a description (below title, above fields)
							->addFieldValues("Verified", 		"<@$mention_id>")											// New line after this if ,true

							->setThumbnail("$mention_avatar")														// Set a thumbnail (the image in the top right corner)
			//				->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')			 	// Set an image (below everything except footer)
							->setTimestamp()																	 	// Set a timestamp (gets shown next to footer)
							->setAuthor("$author_check", "$author_avatar")  									// Set an author with icon
							->setFooter("Palace Bot by Valithor#5947")							 					// Set a footer without icon
							->setURL("");							 												// Set the URL
			//			Send the message
						if($react) $message->react("👍");
						//Log the verification
						if($verifylog_channel) {
							$verifylog_channel->sendEmbed($embed);
						}elseif($modlog_channel) {
							$modlog_channel->sendEmbed($embed);
						}
						*/
						//Welcome the verified user
						if ($general_channel) {
							$msg = "Welcome to $author_guild_name, <@$mention_id>!";
							if ($rolepicker_channel) $msg = $msg . " Feel free to pick out some roles in <#$rolepicker_channel_id>.";
							$general_channel->sendMessage($msg)->done(
								function ($message) use ($discord) {
									$discord->getLoop()->addTimer(3000, function() use ($message) {
										return $message->delete();
									});
								}
							);
						}
						return;
					} else {
						if ($react) $message->react("👎");
						return $message->reply("$mention_check does not need to be verified!" . PHP_EOL);
					}
				}
			}
		}
	}
	if ( ($creator || ($author_perms['manage_messages'] && $author_perms['manage_roles'])) && (($message_content_lower == 'cv') || ($message_content_lower == 'clearv'))) { //;clearv ;cv Clear all messages in the get-verified channel
		if ($getverified_channel_id) { //This command only works if the Get Verified Channel is setup
			if($GLOBALS['debug_echo']) echo "[CV] $author_check" . PHP_EOL;
			if ($getverified_channel) {
				$getverified_channel->limitDelete(100);
				//Delete any messages that aren't cached
				$getverified_channel->getMessageHistory()->done(function ($message_collection) use ($getverified_channel) {
					foreach ($message_collection as $message) {
						$getverified_channel->message->delete();
					}
				});
				$getverified_channel->sendMessage("Welcome to $author_guild_name! Please take a moment to read the rules and fill out the questions below:
				1. How did you find the server?
				2. How old are you?
				3. Do you understand the rules?
				4. Do you have any other questions?");
			}
			return;
		}
	}
}