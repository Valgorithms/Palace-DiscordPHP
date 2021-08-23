<?php
function guildMemberUpdate($member, $discord, $member_old) {
	if (is_null($member)) return; //either the loadAllMembers option or the privileged GUILD_MEMBERS intent may be missing
	if ($member->id === $discord->id) return; //Don't process changes for the bot (not compatible with $diff)
	include_once "custom_functions.php";
	$author_guild = $member->guild;
	$author_guild_id = $member->guild->id;
	//Leave the guild if blacklisted
	//GLOBAL $blacklisted_guilds;
	include 'blacklisted_owners.php'; //Array of user IDs that are not allowed to use this bot in their guilds
	if (isset($blacklisted_owners)) {
		if (in_array($member->guild->owner_id, $blacklisted_owners)) {
			/*
			$author_guild->leave($author_guild_id)->done(null, function ($error) {
				var_dump($error->getMessage()); //if($GLOBALS['debug_echo']) echo any errors
			});
			*/
			if($GLOBALS['debug_echo']) echo "[LEAVE BLACKLISTED OWNER GUILD - $author_guild_id]" . PHP_EOL;
			$discord->guilds->leave($author_guild);
		}
	}
	include 'blacklisted_guilds.php'; //Array of Guilds that are not allowed to use this bot
	if (isset($blacklisted_guilds)) {
		if (in_array($author_guild_id, $blacklisted_guilds)) {
			/*
			$author_guild->leave($author_guild_id)->done(null, function ($error) {
				var_dump($error->getMessage()); //if($GLOBALS['debug_echo']) echo any errors
			});
			*/
			if($GLOBALS['debug_echo']) echo "[LEAVE BLACKLISTED GUILD - $author_guild_id]" . PHP_EOL;
			$discord->guilds->leave($author_guild);
		}
	}
	//Leave the guild if not whitelisted
	global $whitelisted_guilds;
	if (isset($whitelisted_guilds)) {
		if (!in_array($author_guild_id, $whitelisted_guilds)) {
			$author_guild->leave($author_guild_id)->done(null,
				function ($error) {
					var_dump($error->getMessage());
				}
			);
		}
	}

	if($member) {
		/*
		ob_flush();
		ob_start();
		var_dump($member);
		file_put_contents("update_member_new.txt", ob_get_flush());
		*/
		
		$new_roles		= $member['roles'];
		$new_nick		= $member['nick'];
		$member_id		= $member['id'];
		$member_guild	= $member['guild'];
		$new_user		= $member['user'];
		$new_username	= $new_user['username'];
		$new_tag		= $new_user['username'] . '#' . $new_user['discriminator'];
		$new_avatar		= $new_user['avatar'];
	}
	if ($member_old) {
		/*
		ob_flush();
		ob_start();
		var_dump($member_old);
		file_put_contents("update_member_old.txt", ob_get_flush());
		*/
		
		$old_roles		= $member_old['roles'];
		$old_nick		= $member_old['nick'];
		$old_user		= $member_old['user'];
		$old_username	= $old_user['username'];
		$old_tag		= $old_user['username'] . '#' . $old_user['discriminator'];
		$old_avatar		= $old_user['avatar'];
	}

	if($GLOBALS['debug_echo']) echo "guildMemberUpdate ($author_guild_id - $member_id)" . PHP_EOL;

	$user_folder = "\\users\\$member_id";
	CheckDir($user_folder);

	$guild_folder = "\\guilds\\$author_guild_id";
	if (!CheckDir($guild_folder)) {
		//
	}

	//Load config variables for the guild
	$guild_config_path = getcwd() . "$guild_folder\\guild_config.php"; //if($GLOBALS['debug_echo']) echo "guild_config_path: " . $guild_config_path . PHP_EOL;
	if (!include "$guild_config_path") {
		if($GLOBALS['debug_echo']) echo "CONFIG CATCH!" . PHP_EOL;
		$counter = $GLOBALS[$author_guild_id."_config_counter"] ?? 0;
		if ($counter <= 10) {
			$GLOBALS[$author_guild_id."_config_counter"]++;
		} else {
			$author_guild->leave($author_guild_id)->done(null,
				function ($error) {
					var_dump($error->getMessage());
				}
			);
			rmdir(getcwd() . $guild_folder);
			if($GLOBALS['debug_echo']) echo "GUILD DIR REMOVED" . PHP_EOL;
		}
	}

	$modlog_channel	= $member_guild->channels->get('id', $modlog_channel_id);

	//		Populate roles
	$old_member_roles_names = array();
	$old_member_roles_ids = array();

	foreach ($old_roles as $role) {
		$old_member_roles_names[] = $role['name']; 											//if($GLOBALS['debug_echo']) echo "role[$x] name: " . PHP_EOL; //var_dump($role->name);
		$old_member_roles_ids[]	= $role['id']; 												//if($GLOBALS['debug_echo']) echo "role[$x] id: " . PHP_EOL; //var_dump($role->id);
	}

	$new_member_roles_names = array();
	$new_member_roles_ids = array();

	foreach ($new_roles as $role) {
		$new_member_roles_names[] = $role['name']; 											//if($GLOBALS['debug_echo']) echo "role[$x] name: " . PHP_EOL; //var_dump($role->name);
		$new_member_roles_ids[]	= $role['id']; 												//if($GLOBALS['debug_echo']) echo "role[$x] id: " . PHP_EOL; //var_dump($role->id);
	}


	//		Compare changes
	$changes = "";
	if ($old_tag != $new_tag) {
		//if($GLOBALS['debug_echo']) echo "old_tag: " . $old_tag . PHP_EOL;
		//if($GLOBALS['debug_echo']) echo "new_tag: " . $new_tag . PHP_EOL;
		if ($old_tag && $new_tag)
			$changes = $changes . "Tag Changed:\n`$old_tag`→`$new_tag`\n";
		elseif ($old_tag && !$new_tag)
			$changes = $changes . "Removed Tag:\n`$old_tag`\n";
		elseif (!$old_tag && $new_tag)
			$changes = $changes . "Added Tag:\n`$new_tag`";
		//Place user info in target's folder
		$array = VarLoad($user_folder, "tags.php");
		if (!is_array($array))
			$array = array();
		if (!in_array($old_tag, $array))
			$array[] = $old_tag;
		if (!in_array($new_tag, $array))
			$array[] = $new_tag;
		VarSave($user_folder, "tags.php", $array);
	}
	if ($old_avatar != $new_avatar) {
		//if($GLOBALS['debug_echo']) echo "old_avatar: " . $old_avatar . PHP_EOL;
		//if($GLOBALS['debug_echo']) echo "new_avatar: " . $new_avatar . PHP_EOL;
		if ($old_avatar && $new_avatar)
			$changes = $changes . "Avatar Changed:\n`$old_avatar`→`$new_tag`\n";
		elseif ($old_avatar && !$new_avatar)
			$changes = $changes . "Removed Avatar:\n`$old_avatar`\n";
		elseif (!$old_avatar && $new_avatar)
			$changes = $changes . "Added Avatar:\n`$new_avatar`";

		//Place user info in target's folder
		$array = VarLoad($user_folder, "avatars.php");
		if (!is_array($array))
			$array = array();
		if (!in_array($old_avatar, $array))
			$array[] = $old_avatar;
		if (!in_array($new_avatar, $array))
			$array[] = $new_avatar;
		VarSave($user_folder, "avatars.php", $array);
	}
	if ($old_username != $new_username) {
		//if($GLOBALS['debug_echo']) echo "old_username: " . $old_username . PHP_EOL;
		//if($GLOBALS['debug_echo']) echo "new_username: " . $new_username . PHP_EOL;
		if ($old_username && $new_username)
			$changes = $changes . "Username Changed:\n`$old_username`→`$new_username`\n";
		elseif ($old_username && !$new_username)
			$changes = $changes . "Removed Username:\n`$old_username`\n";
		elseif (!$old_username && $new_username)
			$changes = $changes . "Added Username:\n`$new_username`";
		
		//Place user info in target's folder
		$array = VarLoad($user_folder, "nicknames.php");
		if (!is_array($array))
			$array = array();
		if (!in_array($old_username, $array))
			$array[] = $old_username;
		if (!in_array($new_username, $array))
			$array[] = $new_username;
		VarSave($user_folder, "usernames.php", $array);
	}
	if ($old_nick != $new_nick) {
		//if($GLOBALS['debug_echo']) echo "old_nick: " . $old_nick . PHP_EOL;
		//if($GLOBALS['debug_echo']) echo "new_nick: " . $new_nick . PHP_EOL;
		if ($old_nick && $new_nick)
			$changes = $changes . "Nickname Changed:\n`$old_nick`→`$new_nick`\n";
		elseif ($old_nick && !$new_nick)
			$changes = $changes . "Removed Nickname:\n`$old_nick`\n";
		elseif (!$old_nick && $new_nick)
			$changes = $changes . "Added Nickname:\n`$new_nick`";
		
		//Place user info in target's folder
		$array = VarLoad($user_folder, "nicknames.php");
		if (!is_array($array))
			$array = array();
		if (!in_array($old_nick, $array))
			$array[] = $old_nick;
		if (!in_array($new_nick, $array))
			$array[] = $new_nick;
		VarSave($user_folder, "nicknames.php", $array);
	}
	if ($old_member_roles_ids != $new_member_roles_ids) { //Only log the added/removed difference
		//New Roles
		$role_difference_ids = array_diff($old_member_roles_ids, $new_member_roles_ids);
		foreach ($role_difference_ids as $role_diff) {
			if (in_array($role_diff, $old_member_roles_ids)) {
				$switch = "Removed roles: ";
			} else {
				$switch = "Added Roles: ";
			}
			$changes = $changes . $switch . "<@&$role_diff>";
		}
		//Old roles
		$role_difference_ids = array_diff($new_member_roles_ids, $old_member_roles_ids);
		$added = false;
		$removed = false;
		foreach ($role_difference_ids as $role_diff) {
			if (in_array($role_diff, $old_member_roles_ids)) {
				$switch = "Removed roles: ";
				if (!$removed) {
					$changes = $changes . $switch . "<@&$role_diff>";
					$removed = true;
				} else {
					$changes = $changes . "<@&$role_diff>";
				}
			} else {
				$switch = "Added Roles: ";
				if (!$added) {
					$changes = $changes . $switch . "<@&$role_diff>";
					$added = true;
				} else {
					$changes = $changes . "<@&$role_diff>";
				}
			}
		}
	}

	//if($GLOBALS['debug_echo']) echo "switch: " . $switch . PHP_EOL;
	//if( ($switch != "") || ($switch != NULL)) //User was kicked (They have no roles anymore)
	if (($modlog_channel_id != null) && ($modlog_channel_id != "")) {
		if ($changes != "") {
			//$changes = "<@$member_id>'s information has changed:\n" . $changes;
			if (strlen($changes) < 1025) {
				$embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
				$embed
	//				->setTitle("")																			// Set a title
					->setColor(0xa7c5fd)																	// Set a color (the thing on the left side)
					->setDescription("<@$member_id>\n**User Update**\n$changes")							// Set a description (below title, above fields)
	//				->addFieldValues("<@$member_id>\n**User Update**", "$changes")							// New line after this
	//				->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
	//				->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             	// Set an image (below everything except footer)
					->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
					->setAuthor("$new_tag", "$new_avatar")  												// Set an author with icon
					->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
					->setURL("");                             												// Set the URL
	//				Send a message
				if ($modlog_channel) {
					$modlog_channel->sendEmbed($embed);
				}
				return;
			} else {
				if ($modlog_channel) {
					$modlog_channel->sendMessage("**User Update**\n$changes");
				}
				return;
			}
		} else { //No info we want to capture was changed
			return;
		}
	}
}