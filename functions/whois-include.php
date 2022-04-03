<?php
$mention_username			= $mention_user->username; if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "mention_username: $mention_username" . PHP_EOL;
$mention_id					= $mention_user->id; if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "mention_id: $mention_id" . PHP_EOL;
$mention_discriminator		= $mention_user->discriminator; if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "mention_discriminator: $mention_discriminator" . PHP_EOL;
$mention_check				= $mention_username."#".$mention_discriminator; if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "mention_check: $mention_check" . PHP_EOL;
$mention_nickname			= $mention_user->nick; if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "mention_nickname: $mention_nickname" . PHP_EOL;
$mention_avatar 			= $mention_user->avatar; if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "mention_avatar: $mention_avatar" . PHP_EOL;

if ($mention_member) {
	$mention_joinedTimestamp = $mention_member->joined_at->timestamp;
	$mention_joinedDate	= date("D M j H:i:s Y", $mention_joinedTimestamp); //if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "Joined Server: " . $mention_joinedDate . PHP_EOL;
	$mention_joinedDateTime = new \Carbon\Carbon('@' . $mention_joinedTimestamp);
	$mention_joinedAge = \Carbon\Carbon::now()->diffInDays($mention_member->joined_at) . " days"; //var_dump( \Carbon\Carbon::now());
}
//$mention_created			= $mention_user->createdAt;
$mention_createdTimestamp = $mention_user->createdTimestamp(); //if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "mention_createdTimestamp: " . $mention_createdTimestamp . PHP_EOL;
$mention_createdDate = date("D M j H:i:s Y", $mention_createdTimestamp);
$mention_createdAge = \Carbon\Carbon::now()->diffInDays($mention_createdDate) . " days";

//Load history
$mention_folder = "\\users\\$mention_id";
CheckDir($mention_folder);
$mention_nicknames_array = VarLoad($mention_folder, "nicknames.php");
$mention_nicknames = "";
if (is_array($mention_nicknames_array)) {
	$mention_nicknames_array = array_reverse($mention_nicknames_array);
	$x=0;
	foreach ($mention_nicknames_array as $nickname) {
		if ($nickname != "") {
			if ($x<5) {
				$mention_nicknames = $mention_nicknames . $nickname . "\n";
			}
			$x++;
		}
	}
}
if ($mention_nicknames == "") {
	$mention_nicknames = "No nicknames tracked";
}
//if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "mention_nicknames: " . $mention_nicknames . PHP_EOL;

$mention_tags_array = VarLoad($mention_folder, "tags.php");
$mention_tags = "";
if (is_array($mention_tags_array)) {
	$mention_tags_array = array_reverse($mention_tags_array);
	$x=0;
	foreach ($mention_tags_array as $tag) {
		if($tag != "") {
			if ($x<5) {
				$mention_tags = $mention_tags . $tag . "\n";
			}
			$x++;
		}
	}
}
if ($mention_tags == "") {
	$mention_tags = "No tags tracked";
}
$guildstring = "";
//Check if user is in a shared guild
foreach($discord->guilds as $guild) {
	if($member = $guild->members->offsetGet($mention_id)) {
			$guildstring .= $guild->name . ' (' . $guild->id . ")\n";
	}
}
//var_dump(\Discord\Parts\Embed\Embed::class);
$embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
$embed
	->setTitle("$mention_check ($mention_id)")																// Set a title
	->setColor(0xe1452d)																	// Set a color (the thing on the left side)
//					->setDescription("$author_guild_name")									// Set a description (below title, above fields)
	->addFieldValues("ID", "$mention_id", true)
	->addFieldValues("Avatar", "[Link]($mention_avatar)", true)
	->addFieldValues("Account Created", "$mention_createdDate", true)
	->addFieldValues("Account Age", '<t:' . $mention_member->joined_at->timestamp . ':R>', true);
if($mention_joinedDate) $embed->addFieldValues("Joined Server", "$mention_joinedDate", true);
if($mention_joinedAge) $embed->addFieldValues("Server Age", "$mention_joinedAge");
$embed
	->addFieldValues("Tag history", "`$mention_tags`", true)
	->addFieldValues("Nick history", "`$mention_nicknames`", true)
	->setThumbnail("$mention_avatar")														// Set a thumbnail (the image in the top right corner)
//			->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')             	// Set an image (below everything except footer)
//			->setImage("$image_path")             													// Set an image (below everything except footer)
	->setTimestamp()                                                                     	// Set a timestamp (gets shown next to footer)
//			->setAuthor("$author_check", "$author_guild_avatar")  									// Set an author with icon
	->setFooter("Palace Bot by Valithor#5947")                             					// Set a footer without icon
	->setURL("");
	if ($guildstring != "") $embed->addFieldValues("Shared Servers", $guildstring);
if ($embed) {
	$author_channel->sendEmbed($embed);
	return;
}