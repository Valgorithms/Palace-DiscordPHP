<?php
//This file and the variables it loads get reacquired every time they're needed, allowing for persistence
//Changes made do not require for the bot to be restarted

//Command Symbol
if (!CheckFile($guild_folder, "command_symbol.php")) {
    $command_symbol = ";"; //Channel where a detailed message about the user gets posted
    VarSave($guild_folder, "command_symbol.php", $command_symbol);
} else {
    $command_symbol = strval(VarLoad($guild_folder, "command_symbol.php"));
}

//Channel IDs
if (!CheckFile($guild_folder, "welcome_log_channel_id.php")) {
    $welcome_log_channel_id = ""; //Channel where a detailed message about the user gets posted
    VarSave($guild_folder, "welcome_log_channel_id.php", strval($welcome_log_channel_id));
} else {
    $welcome_log_channel_id = strval(VarLoad($guild_folder, "welcome_log_channel_id.php"));
}

if (!CheckFile($guild_folder, "welcome_public_channel_id.php")) {
    $welcome_public_channel_id = ""; //Simple welcome message tagging new users
    VarSave($guild_folder, "welcome_public_channel_id.php", strval($welcome_public_channel_id));
} else {
    $welcome_public_channel_id = strval(VarLoad($guild_folder, "welcome_public_channel_id.php"));
}

if (!CheckFile($guild_folder, "general_channel_id.php")) {
    $general_channel_id = ""; //Usually #introductions or #general, used to welcome verified users
    VarSave($guild_folder, "general_channel_id.php", strval($general_channel_id));
} else {
    $general_channel_id = strval(VarLoad($guild_folder, "general_channel_id.php"));
}

if (!CheckFile($guild_folder, "modlog_channel_id.php")) {
    $modlog_channel_id = ""; //Usually #introductions or #general (Not currently implemented)
    VarSave($guild_folder, "modlog_channel_id.php", strval($modlog_channel_id));
} else {
    $modlog_channel_id	= strval(VarLoad($guild_folder, "modlog_channel_id.php"));
}

if (!CheckFile($guild_folder, "getverified_channel_id.php")) {
    $getverified_channel_id	= ""; //Where users should be requesting server verification
    VarSave($guild_folder, "getverified_channel_id.php", strval($getverified_channel_id));
} else {
    $getverified_channel_id = strval(VarLoad($guild_folder, "getverified_channel_id.php"));
}

if (!CheckFile($guild_folder, "verifylog_channel_id.php")) {
    $verifylog_channel_id = ""; //Log verifications (Not currently implemented)
    VarSave($guild_folder, "verifylog_channel_id.php", strval($verifylog_channel_id));
} else {
    $verifylog_channel_id = strval(VarLoad($guild_folder, "verifylog_channel_id.php"));
}

if (!CheckFile($guild_folder, "watch_channel_id.php")) {
    $watch_channel_id = ""; //Someone being watched has their messages duplicated to this channel instead of a DM (Leave commented to use DMs)
    VarSave($guild_folder, "watch_channel_id.php", strval($watch_channel_id));
} else {
    $watch_channel_id	= strval(VarLoad($guild_folder, "watch_channel_id.php"));
}

if (!CheckFile($guild_folder, "rolepicker_channel_id.php")) {
if (!CheckFile($guild_folder, "games_channel_id.php")) {
    $games_channel_id = "";	//Channel where a detailed message about the user gets posted
    VarSave($guild_folder, "games_channel_id.php", strval($games_channel_id));
} else {
    $games_channel_id = strval(VarLoad($guild_folder, "games_channel_id.php"));
}

if (!CheckFile($guild_folder, "suggestion_pending_channel_id.php")) {
    $suggestion_pending_channel_id = ""; //Channel where moderators can see pending suggestions
    VarSave($guild_folder, "suggestion_pending_channel_id.php", strval($suggestion_pending_channel_id));
} else {
    $suggestion_pending_channel_id = strval(VarLoad($guild_folder, "suggestion_pending_channel_id.php"));
}

if (!CheckFile($guild_folder, "suggestion_approved_channel_id.php")) {
    $suggestion_approved_channel_id = ""; //Channel where approved suggestions get reposted to for community voting
    VarSave($guild_folder, "suggestion_approved_channel_id.php", strval($suggestion_approved_channel_id));
} else {
    $suggestion_approved_channel_id = strval(VarLoad($guild_folder, "suggestion_approved_channel_id.php"));
}

if (!CheckFile($guild_folder, "tip_pending_channel_id.php")) {
    $tip_pending_channel_id = ""; //Channel where moderators can see pending tips
    VarSave($guild_folder, "tip_pending_channel_id.php", strval($tip_pending_channel_id));
} else {
    $tip_pending_channel_id = strval(VarLoad($guild_folder, "tip_pending_channel_id.php"));
}

if (!CheckFile($guild_folder, "tip_approved_channel_id.php")) {
    $tip_approved_channel_id = ""; //Channel where approved tips get reposted to for community voting
    VarSave($guild_folder, "tip_approved_channel_id.php", strval($tip_approved_channel_id));
} else {
    $tip_approved_channel_id = strval(VarLoad($guild_folder, "tip_approved_channel_id.php"));
}
if (!CheckFile($guild_folder, "rolepicker_channel_id.php")) {
    $rolepicker_channel_id = ""; //Channel where approved tips get reposted to for community voting
    VarSave($guild_folder, "rolepicker_channel_id.php", strval($rolepicker_channel_id));
} else {
    $rolepicker_channel_id = strval(VarLoad($guild_folder, "rolepicker_channel_id.php"));
}
if (!CheckFile($guild_folder, "games_rolepicker_channel_id.php")) {
    $games_rolepicker_channel_id = ""; //Channel where approved tips get reposted to for community voting
    VarSave($guild_folder, "games_rolepicker_channel_id.php", strval($games_rolepicker_channel_id));
} else {
    $games_rolepicker_channel_id = strval(VarLoad($guild_folder, "games_rolepicker_channel_id.php"));
}

//Optional Role IDs
if (!CheckFile($guild_folder, "role_18_id.php")) {
    $role_18_id = ""; //Someone being watched has their messages duplicated to this channel instead of a DM (Leave commented to use DMs)
    VarSave($guild_folder, "role_18_id.php", strval($role_18_id));
} else {
    $role_18_id = strval(VarLoad($guild_folder, "role_18_id.php"));
}

if (!CheckFile($guild_folder, "role_verified_id.php")) {
    $role_verified_id = ""; //Verified role that gives people access to channels
    VarSave($guild_folder, "role_verified_id.php", strval($role_verified_id));
} else {
    $role_verified_id = strval(VarLoad($guild_folder, "role_verified_id.php"));
}

//Required Role IDs
if (!CheckFile($guild_folder, "role_dev_id.php")) {
    $role_dev_id = ""; //Developer role (overrides certain restrictions)
    VarSave($guild_folder, "role_dev_id.php", strval($role_dev_id));
} else {
    $role_dev_id = strval(VarLoad($guild_folder, "role_dev_id.php"));
}

if (!CheckFile($guild_folder, "role_owner_id.php")) {
    $role_owner_id = ""; //Owner of the guild
    VarSave($guild_folder, "role_owner_id.php", strval($role_owner_id));
} else {
    $role_owner_id = strval(VarLoad($guild_folder, "role_owner_id.php"));
}

if (!CheckFile($guild_folder, "role_admin_id.php")) {
    $role_admin_id = ""; //Admins
    VarSave($guild_folder, "role_admin_id.php", strval($role_admin_id));
} else {
    $role_admin_id = strval(VarLoad($guild_folder, "role_admin_id.php"));
}

if (!CheckFile($guild_folder, "role_mod_id.php")) {
    $role_mod_id = ""; //Moderators
    VarSave($guild_folder, "role_mod_id.php", strval($role_mod_id));
} else {
    $role_mod_id = strval(VarLoad($guild_folder, "role_mod_id.php"));
}

if (!CheckFile($guild_folder, "role_bot_id.php")) {
    $role_bot_id = ""; //Bots
    VarSave($guild_folder, "role_bot_id.php", strval($role_bot_id));
} else {
    $role_bot_id = strval(VarLoad($guild_folder, "role_bot_id.php"));
}

if (!CheckFile($guild_folder, "role_vzgbot_id.php")) {
    $role_vzgbot_id = ""; //Palace Bot: THIS ROLE MUST HAVE ADMINISTRATOR PRIVILEGES!
    VarSave($guild_folder, "role_vzgbot_id.php", strval($role_vzgbot_id));
} else {
    $role_vzgbot_id = strval(VarLoad($guild_folder, "role_vzgbot_id.php"));
}

if (!CheckFile($guild_folder, "role_muted_id.php")) {
    $role_muted_id = ""; //This role should not be allowed access any channels
    VarSave($guild_folder, "role_muted_id.php", strval($role_muted_id));
} else {
    $role_muted_id = strval(VarLoad($guild_folder, "role_muted_id.php"));
}

//Rolepicker user ID
if (!CheckFile($guild_folder, "rolepicker_id.php")) {
    $rolepicker_id = $discord->user->id; //id of the user that posted the role picker messages
    VarSave($guild_folder, "rolepicker_id.php", strval($rolepicker_id));
} else {
    $rolepicker_id = strval(VarLoad($guild_folder, "rolepicker_id.php"));
}

//Rolepicker message IDs
if (!CheckFile($guild_folder, "gameroles_message_id.php")) {
    $gameroles_message_id = ""; //id of the Species Menu message
    VarSave($guild_folder, "gameroles_message_id.php", strval($gameroles_message_id));
} else {
    $gameroles_message_id = strval(VarLoad($guild_folder, "gameroles_message_id.php"));
}

if (!CheckFile($guild_folder, "species_message_id.php")) {
    $species_message_id = ""; //id of the Species Menu message
    VarSave($guild_folder, "species_message_id.php", strval($species_message_id));
} else {
    $species_message_id = strval(VarLoad($guild_folder, "species_message_id.php"));
}

if (!CheckFile($guild_folder, "species2_message_id.php")) {
    $species2_message_id = ""; //id of the Species Menu message
    VarSave($guild_folder, "species2_message_id.php", strval($species2_message_id));
} else {
    $species2_message_id = strval(VarLoad($guild_folder, "species2_message_id.php"));
}

//Rolepicker message IDs
if (!CheckFile($guild_folder, "species3_message_id.php")) {
    $species3_message_id = ""; //id of the Species Menu message
    VarSave($guild_folder, "species3_message_id.php", strval($species3_message_id));
} else {
    $species3_message_id = strval(VarLoad($guild_folder, "species3_message_id.php"));
}

if (!CheckFile($guild_folder, "sexuality_message_id.php")) {
    $sexuality_message_id = ""; //id of the Sexualities Menu message
    VarSave($guild_folder, "sexuality_message_id.php", strval($sexuality_message_id));
} else {
    $sexuality_message_id = strval(VarLoad($guild_folder, "sexuality_message_id.php"));
}

if (!CheckFile($guild_folder, "gender_message_id.php")) {
    $gender_message_id = ""; //id of the Gender Menu message
    VarSave($guild_folder, "gender_message_id.php", strval($gender_message_id));
} else {
    $gender_message_id = strval(VarLoad($guild_folder, "gender_message_id.php"));
}

if (!CheckFile($guild_folder, "pronouns_message_id.php")) {
    $pronouns_message_id = ""; //id of the Gender Menu message
    VarSave($guild_folder, "pronouns_message_id.php", strval($pronouns_message_id));
} else {
    $pronouns_message_id = strval(VarLoad($guild_folder, "pronouns_message_id.php"));
}

if (!CheckFile($guild_folder, "nsfw_message_id.php")) {
    $nsfw_message_id = ""; //id of the NSFW Menu message
    VarSave($guild_folder, "nsfw_message_id.php", strval($nsfw_message_id));
} else {
    $nsfw_message_id = strval(VarLoad($guild_folder, "nsfw_message_id.php"));
}

if (!CheckFile($guild_folder, "channelroles_message_id.php")) {
    $channelroles_message_id = ""; //id of the NSFW Menu message
    VarSave($guild_folder, "channelroles_message_id.php", strval($channelroles_message_id));
} else {
    $channelroles_message_id = strval(VarLoad($guild_folder, "channelroles_message_id.php"));
}

//You can add your own custom roles too! Locate the Discord emoji on https://emojipedia.org/discord/ and use it as the unicode in custom_roles.php
if (!CheckFile($guild_folder, "customroles_message_id.php")) {
    $customroles_message_id = ""; //id of the Gender Menu message
    VarSave($guild_folder, "customroles_message_id.php", strval($customroles_message_id));
} else {
    $customroles_message_id = strval(VarLoad($guild_folder, "customroles_message_id.php"));
}
