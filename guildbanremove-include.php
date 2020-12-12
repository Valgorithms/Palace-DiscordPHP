<?php
include_once "custom_functions.php";

$guild_id = $ban->guild_id;
$guild = $ban->guild;
$user_id = $user_id;
$user = $ban->user;
$reason = $ban->reason;

echo "[guildBanRemove] ($guild_id)" . PHP_EOL;
$author_user = $user;
//

$author_guild = $guild;
$author_guild_id = $guild_id; //$guild->id;
$author_guild_name = $guild->name;
$author_guild_avatar = $guild->icon;
//$author_user = $user;
$author_username = $author_user->username;
$author_discriminator = $author_user->discriminator;
$author_id = $user_id; //$author_user->id;
$author_avatar = $author_user->avatar;
$author_check = "$author_username#$author_discriminator";

$user_folder = "\\users\\$author_id";
CheckDir($user_folder);
$guild_folder = "\\guilds\\$author_guild_id";
if (!CheckDir($guild_folder)) {
    /*
    if(!CheckFile($guild_folder, "guild_owner_id.php")){
        VarSave($guild_folder, "guild_owner_id.php", $guild_owner_id);
    }else $guild_owner_id	= VarLoad($guild_folder, "guild_owner_id.php");
    */
}

//Load config variables for the guild
$guild_config_path = __DIR__  . "$guild_folder\\guild_config.php"; //echo "guild_config_path: " . $guild_config_path . PHP_EOL;
if (!include "$guild_config_path") {
    echo "CONFIG CATCH!" . PHP_EOL;
    $counter = $GLOBALS[$author_guild_id."_config_counter"] ?? 0;
    if ($counter <= 10) {
        $GLOBALS[$author_guild_id."_config_counter"]++;
    } else {
        $discord->guilds->leave($author_guild);
        rmdir(__DIR__  . $guild_folder);
        echo "[GUILD DIR REMOVED - BAN]" . PHP_EOL;
    }
}

if ($modlog_channel_id && $author_guild) {
    $modlog_channel	= $author_guild->channels->get('id', $modlog_channel_id);
    if ($modlog_channel != null) {
        //Build the embed message
        $embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
        $embed
        //	->setTitle("Commands")																	// Set a title
            ->setColor(0xe1452d)																	// Set a color (the thing on the left side)
            ->setDescription("$author_guild_name")																// Set a description (below title, above fields)
            ->addFieldValues("Unbanned", "<@$author_id>")																// New line after this
            
        //	->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
        //	->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             	// Set an image (below everything except footer)
            ->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
            ->setAuthor("$author_check ($author_id)", "$author_avatar")  							// Set an author with icon
            ->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
            ->setURL("");                             												// Set the URL
        $modlog_channel->sendEmbed($embed);
    }
}
