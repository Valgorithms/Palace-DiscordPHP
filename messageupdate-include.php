<?php
//This event listener gets triggered willy-nilly so we need to do some checks here if we want to get anything useful out of it

$message_content_new = $message_new->content; //Null if message is too old
$message_content_old = $message_old->content; //Null if message is too old
$message_id_new = $message_new->id; //This doesn't match any message id if the message is too old

//Only process message changes
if ($message_content_new === $message_content_old) {
    //echo "NO MESSAGE CONTENT CHANGE OR MESSAGE TOO OLD" . PHP_EOL;
    return true;
}

//Make sure the messages aren't blank
if (($message_content_new == null) || ($message_content_new == "")) { //This should never trigger, but just in case...
    //echo "BLANK OR OLD MESSAGE EDITED" . PHP_EOL;
    return true;
}

echo "[messageUpdate]" . PHP_EOL;

/*
Debug output

//Check the timestamp
$createdTimestamp_new = $message_new->createdTimestamp;
$createdTimestamp_old = $message_old->createdTimestamp;

$editedTimestamp_new = $message_new->editedTimestamp;
$editedTimestamp_old = $message_old->editedTimestamp;

echo "message_content_new: " . $message_content_new . PHP_EOL;
echo "message_content_old: " . $message_content_old . PHP_EOL;
echo PHP_EOL;
echo "createdTimestamp_new: " . $createdTimestamp_new . PHP_EOL;
echo "createdTimestamp_old: " . $createdTimestamp_old . PHP_EOL;
echo PHP_EOL;
echo "editedTimestamp_new: " . $editedTimestamp_new . PHP_EOL;
echo "editedTimestamp_old: " . $editedTimestamp_old . PHP_EOL;
echo PHP_EOL;
*/

//Load global variables
$guild = $message_new->guild;
$author_guild_id = $guild->id;

//Load author info
$author_user				= $message_new->author; //User object
$author_channel 			= $message_new->channel;
$author_channel_id			= $author_channel->id; 												//echo "author_channel_id: " . $author_channel_id . PHP_EOL;
$author_channel_class		= get_class($author_channel);
$is_dm = false;
if (get_class($message->author) == "Discord\Parts\User\User") { //True if direct message
    $is_dm = true;
    return true; //Don't try to process direct messages
}
if ($author_guild_id == null || $author_guild_id == "") {
    return true;
} //Double check for direct message
//Load config variables for the guild
$guild_folder = "\\guilds\\$author_guild_id"; //echo "guild_folder: $guild_folder" . PHP_EOL;
//Create a folder for the guild if it doesn't exist already
if (!CheckDir($guild_folder)) {
    if (!CheckFile($guild_folder, "guild_owner_id.php")) {
        VarSave($guild_folder, "guild_owner_id.php", $guild_owner_id);
    } else {
        $guild_owner_id = VarLoad($guild_folder, "guild_owner_id.php");
    }
}
if ($guild_owner_id == $author_id) {
    $owner = true; //Enable usage of restricted commands
} else {
    $owner = false;
}

//Load config variables for the guild
$guild_config_path = __DIR__  . "\\$guild_folder\\guild_config.php";														//echo "guild_config_path: " . $guild_config_path . PHP_EOL;
if (!CheckFile($guild_folder, "guild_config.php")) {
    $file = 'guild_config_template.php';
    if (!copy($file, $guild_config_path)) {
        $message->reply("Failed to create guild_config file! Please contact <@116927250145869826> for assistance.");
    } else {
        $author_channel->sendMessage("<@$guild_owner_id>, I'm here! Please ;setup the bot." . PHP_EOL . "While interacting with this bot, any conversations made through direct mention of the bot name are stored anonymously in a secure database. Avatars, IDs, Names, or any other unique user identifier is not stored with these messages. Through continuing to use this bot, you agree to allow it to track user information to support its functions and for debugging purposes. Your message data will never be used for anything more. If you wish to have any associated information removed, please contact Valithor#5937.");
        $author_channel->sendMessage("(Maintenance is currently ongoing and many commands are currently not working. We are aware of the issue and working on a fix.)");
    }
}
include "$guild_config_path";

$modlog_channel	= $guild->channels->get('id', $modlog_channel_id);

$author_username 			= $author_user->username; 											//echo "author_username: " . $author_username . PHP_EOL;
$author_discriminator 		= $author_user->discriminator;										//echo "author_discriminator: " . $author_discriminator . PHP_EOL;
$author_id 					= $author_user->id;													//echo "author_id: " . $author_id . PHP_EOL;
$author_avatar 				= $author_user->avatar;										//echo "author_avatar: " . $author_avatar . PHP_EOL;
$author_check 				= "$author_username#$author_discriminator"; 						//echo "author_check: " . $author_check . PHP_EOL;

$changes = "";
//if (($message_content_new != $message_content_old) || (($message_content_old == "") || ($message_content_old == NULL))) 	{
if ($message_content_new != $message_content_old) {
    //			Build the string for the reply
    $changes = $changes . "[Link](https://discord.com/channels/$author_guild_id/$author_channel_id/$message_id_new)\n";
    $changes = $changes . "**Channel:** <#$author_channel_id>\n";
    $changes = $changes . "**Message ID:**: $message_id_new\n";
    
    $changes = $changes . "**Before:** ```⠀$message_content_old\n```\n";
    $changes = $changes . "**After:**```⠀$message_content_new\n```\n";
}

if ($modlog_channel) {
    if ($changes != "") {
        //Build the embed
        //$changes = "**Message edit**:\n" . $changes;
        if (strlen($changes) <= 1024) {
            //		Build the embed message
            $embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
            $embed
//			->setTitle("Commands")																	// Set a title
            ->setColor("a7c5fd")																	// Set a color (the thing on the left side)
//			->setDescription("Commands for $author_guild_name")										// Set a description (below title, above fields)
            ->addField("Message Update", "$changes")												// New line after this
            
//			->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
//			->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             	// Set an image (below everything except footer)
            ->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
            ->setAuthor("$author_check ($author_id)", "$author_avatar")  							// Set an author with icon
            ->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
            ->setURL("");                             												// Set the URL
//			Send the message
//			We do not need another promise here, so we call done, because we want to consume the promise
        if ($modlog_channel) {
            $modlog_channel->sendEmbed($embed);
        }
            return true;
        } elseif (strlen($changes) <= 2000) {
            $modlog_channel->sendMessage($changes);
        } else { //Send changes as a file
            //Rebuild the string so we can send some stuff as a message
            $changes = "[Link](https://discord.com/channels/$author_guild_id/$author_channel_id/$message_id_new)\n";
            $changes = $changes . "**Channel:** <#$author_channel_id>\n";
            $changes = $changes . "**Message ID:**: $message_id_new\n";
        
            $changes_file = "**Before:** ```⠀$message_content_old\n```\n";
            $changes_file = $changes_file . "**After:**```⠀$message_content_new\n```\n";
        
            //		Rebuild the embed message
            $embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
            $embed
//			->setTitle("Commands")																	// Set a title
            ->setColor("a7c5fd")																	// Set a color (the thing on the left side)
//			->setDescription("Commands for $author_guild_name")										// Set a description (below title, above fields)
            ->addField("Message Update", "$changes")												// New line after this
            
//			->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
//			->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             	// Set an image (below everything except footer)
            ->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
            ->setAuthor("$author_check ($author_id)", "$author_avatar")  							// Set an author with icon
            ->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
            ->setURL("");                             												// Set the URL
        
        $modlog_channel->sendMessage("A message was updated but it was too long to log within an embed. Please see the attached file.", array('embed' => $embed, 'files' => [['name' => "changes.txt", 'data' => $changes_file]]))->done(null, function ($error) {
            echo $error.PHP_EOL; //Echo any errors
        });
        }
    } else { //No info we want to check was changed
        return true;
    }
}
