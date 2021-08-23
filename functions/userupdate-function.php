<?php
function userUpdate($user_new, $user_old, $discord) {
	//This event listener will never be used for guild-related functions because guildMemberUpdate already does everything we want, but is useful for logging purposes
	//For example, this will get triggered if a Nitro user changes their discriminator
	if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "[USER UPDATE]" . PHP_EOL;
	//id, username, discriminator bot, webhook, email, mfaEnabled, verified, tag, createdTimestamp, createdAt
	$user_id				= $user_new->id;

	$user_folder			= "users/$user_id";
	CheckDir($user_folder);

	$new_username			= $user_new->username;
	$new_discriminator		= $user_new->discriminator;
	$new_tag				= $user_new->tag;
	$new_avatar				= $user_new->avatar;

	$old_username			= $user_old->username;
	$old_discriminator		= $user_old->discriminator;
	$old_tag				= $user_old->tag;
	$old_avatar				= $user_old->avatar;

	$changes = "";

	if ($old_tag != $new_tag) {
		//if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "old_tag: " . $old_tag . PHP_EOL;
		//if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "new_tag: " . $new_tag . PHP_EOL;
		$changes = $changes . "Old tag: $old_tag\nNew tag: $new_tag\n";
		
		//Place user info in target's folder
		$array = VarLoad($user_folder, "tags.php");
		if ($old_tag && $array) {
			if (!in_array($old_tag, $array)) {
				$array[] = $old_tag;
			}
		}
		if ($new_tag && $array) {
			if (!in_array($new_tag, $array)) {
				$array[] = $new_tag;
			}
		}
		VarSave($user_folder, "tags.php", $array);
	}

	if ($old_avatar != $new_avatar) {
		//if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "old_avatar: " . $old_avatar . PHP_EOL;
		//if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "new_avatar: " . $new_avatar . PHP_EOL;
		$changes = $changes . "Old avatar: $old_avatar\nNew avatar: $new_avatar\n";
		
		//Place user info in target's folder
		VarSave($user_folder, "avatars.php", $new_avatar);
	}

	if ($changes != "") {
		if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "[USER UPDATE] $old_username => :\n" . $changes . PHP_EOL;
	}
}
