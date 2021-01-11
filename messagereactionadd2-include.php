<?php
/*
 * Part that defines a message reaction.
 *
 * @property string  $user_id
 * @property string  $message_id
 * @property Member  $member
 * @property Emoji   $emoji
 * @property string  $channel_id
 * @property string  $guild_id
 * @property Channel $channel
 * @property Guild   $guild
 * @property User    $user
 * @property Message $message
 */
 
//Load message info
$message = $message ?? $reaction->message;
$message_content = $message->content;
//$message->channel->sendMessage($message_content);

//Load guild info
$guild						= $reaction->guild;
$author_guild_id			= $reaction->guild_id; //echo "author_guild_id: $author_guild_id" . PHP_EOL;
$author_guild				= $discord->guilds->get('id', $author_guild_id);

if (is_object($message->author) && get_class($message->author) == "Discord\Parts\User\Member") { //Load author info
    $author_user = $message->author->user;
    $author_member = $message->author;
} else {
    $author_user = $author;
}
$author_channel 			= $message->channel;
$author_channel_id			= $author_channel->id; 												//echo "author_channel_id: " . $author_channel_id . PHP_EOL;

/*Disabling this so that the bot will automatically create the roles the first time they are added. They can be manually removed later.
if ("{$discord->id}" == $reaction->user->id)
    return true; //Don't process reactions made by this bot
*/

$is_dm = false;
if (is_object($message->author) && get_class($message->author) == "Discord\Parts\User\User") { //True if direct message
    echo '[MESSAGE REACT DM]' . PHP_EOL;
    $is_dm = true;
    return true; //Don't try and process direct messages
}

$author_username 			= $author_user->username; 											//echo "author_username: " . $author_username . PHP_EOL;
$author_discriminator 		= $author_user->discriminator;										//echo "author_discriminator: " . $author_discriminator . PHP_EOL;
$author_id 					= $author_user->id;													//echo "author_id: " . $author_id . PHP_EOL;
$author_avatar 				= $author_user->avatar;										//echo "author_avatar: " . $author_avatar . PHP_EOL;
$author_check 				= "$author_username#$author_discriminator"; 						//echo "author_check: " . $author_check . PHP_EOL;
$author_folder				= $author_guild_id."\\".$author_id;

//var_dump($reaction);
$respondent_user = $reaction->user;
//Load respondent info
$respondent_username 		= $respondent_user->username; 										//echo "author_username: " . $author_username . PHP_EOL;
$respondent_discriminator 	= $respondent_user->discriminator;									//echo "author_discriminator: " . $author_discriminator . PHP_EOL;
$respondent_id 				= $respondent_user->id;												//echo "author_id: " . $author_id . PHP_EOL;
$respondent_avatar 			= $respondent_user->avatar;									//echo "author_avatar: " . $author_avatar . PHP_EOL;
$respondent_check 			= "$respondent_username#$respondent_discriminator"; 				//echo "respondent_check: " . $respondent_check . PHP_EOL;
//$respondent_member 			= $author_guild->members->get('id', $respondent_id);
$respondent_member = $reaction->member;


/*
//
//
//
*/

echo "[messageReactionAdd]" . PHP_EOL;
$message_content_lower = strtolower($message_content);

//Create a folder for the guild if it doesn't exist already
$guild_folder = "\\guilds\\$author_guild_id";
CheckDir($guild_folder);
//Load config variables for the guild
$guild_config_path = __DIR__  . "$guild_folder\\guild_config.php"; //echo "guild_config_path: " . $guild_config_path . PHP_EOL;
include "$guild_config_path";

//Role picker stuff
$message_id	= $message->id;														//echo "message_id: " . $message_id . PHP_EOL;
global $species, $species2, $species3, $sexualities, $gender, $pronouns, $nsfwarray;
$guild_custom_roles_path = __DIR__  . "\\$guild_folder\\custom_roles.php";
if (CheckFile($guild_folder."/", 'custom_roles.php')){
	include "$guild_custom_roles_path"; //Overwrite default custom_roles
}else{
	global $customroles;
}

//Load emoji info
//guild, user
//animated, managed, requireColons
//createdTimestamp, createdAt
$emoji						= $reaction->emoji;
$emoji_id					= $emoji->id;			//echo "emoji_id: " . $emoji_id . PHP_EOL; //Unicode if null

$unicode					= false;
if ($emoji_id === null) {
    $unicode 	= true;
}					//echo "unicode: " . $unicode . PHP_EOL;
$emoji_name					= $emoji->name;			//echo "emoji_name: " . $emoji_name . PHP_EOL;
$emoji_identifier			= $emoji->id;			//echo "emoji_identifier: " . $emoji_identifier . PHP_EOL;

if ($unicode) {
    $response = "$emoji_name";
} else {
    $response = "<:$emoji_identifier>";
}
//$message->reply("Debug: $response");


//echo "$author_check's message was reacted to by $respondent_check" . PHP_EOL;

//Check rolepicker option
global $rolepicker_option, $species_option, $sexuality_option, $gender_option, $custom_option;
if (($rolepicker_id != "") || ($rolepicker_id != null)) {
    if (!CheckFile($guild_folder, "rolepicker_option.php")) {
        $rp0	= $rolepicker_option;
    }										//Species role picker
    else {
        $rp0	= VarLoad($guild_folder, "rolepicker_option.php");
    }
} else {
    $rp0 = false;
} //echo "rp0: $rp0" . PHP_EOL;



//echo $author_id.':'.$rolepicker_id.PHP_EOL;

if ($rp0) {
    if ($author_id == $rolepicker_id) {
        //Check options
        if (($species_message_id != "") || ($species_messagwe_id != null)) {
            if (!CheckFile($guild_folder, "species_option.php")) {
                $rp1	= $species_option;
            }										//Species role picker
            else {
                $rp1	= VarLoad($guild_folder, "species_option.php");
            }
        } else {
            $rp1 = false;
        }
        if (($sexuality_message_id != "") || ($sexuality_message_id != null)) {
            if (!CheckFile($guild_folder, "sexuality_option.php")) {
                $rp2	= $sexuality_option;
            }										//Sexuality role picker
            else {
                $rp2	= VarLoad($guild_folder, "sexuality_option.php");
            }
        } else {
            $rp2 = false;
        } //echo "rp2: $rp2" . PHP_EOL;
        if (($gender_message_id != "") || ($gender_message_id != null)) {
            if (!CheckFile($guild_folder, "gender_option.php")) {
                $rp3	= $gender_option;
            }										//Gender role picker
            else {
                $rp3	= VarLoad($guild_folder, "gender_option.php");
            }
        } else {
            $rp3 = false;
        }
		if (($pronouns_message_id != "") || ($pronouns_message_id != null)) {
            if (!CheckFile($guild_folder, "pronouns_option.php")) {
                $rp5	= $pronouns_option;
            }										//pronouns role picker
            else {
                $rp5	= VarLoad($guild_folder, "pronouns_option.php");
            }
        } else {
            $rp5 = false;
        }
		
        if (($customroles_message_id != "") || ($customroles_message_id != null)) {
            if (!CheckFile($guild_folder, "custom_option.php")) {
                $rp4	= $custom_option;
            }										//Custom role picker
            else {
                $rp4	= VarLoad($guild_folder, "custom_option.php");
            }
        } else {
            $rp4 = false;
        } //echo "rp4: $rp4" . PHP_EOL;
		if (($nsfw_message_id != "") || ($nsfw_message_id != null)) {
            if (!CheckFile($guild_folder, "nsfw_option.php")) {
                $nsfw	= $nsfw_option;
            }										//Custom role picker
            else {
                $nsfw	= VarLoad($guild_folder, "nsfw_option.php");
            }
        } else {
            $nsfw = false;
        } //echo "nsfw: $nsfw" . PHP_EOL;
		
    
        //Load guild roles info
        $guild_roles												= $guild->roles;
        $guild_roles_names 											= array();
        $guild_roles_ids 											= array();
        foreach ($guild_roles as $role) {
            $guild_roles_names[] 									= strtolower("{$role->name}"); 				//echo "role name: " . $role->name . PHP_EOL; //var_dump($role->name);
        $guild_roles_ids[]										= $role->id; 				//echo "role[$x] id: " . PHP_EOL; //var_dump($role->id);
        $guild_roles_role["{$role->id}"]						= $role;
        }
        //Load respondent roles info
        $respondent_member_role_collection 								= $respondent_member->roles;
        $respondent_member_roles_names 									= array();
        $respondent_member_roles_ids 									= array();
        foreach ($respondent_member_role_collection as $role) {
            $respondent_member_roles_names[] 						= strtolower("{$role->name}"); 	//echo "role[$x] name: " . PHP_EOL; //var_dump($role->name);
        $respondent_member_roles_ids[] 							= $role->id; 				//echo "role[$x] id: " . PHP_EOL; //var_dump($role->id);
        $respondent_member_roles_role["{$role->id}"]			= $role;
        }
    
        //Process the reaction to add a role
        $select_name = "";
        switch ($message_id) {
        case ($species_message_id):
            if ($rp1) {
                echo "species reaction" . PHP_EOL;
                foreach ($species as $var_name => $value) {
                    if (($value == $emoji_name) || ($value == $emoji_name)) {
                        $select_name = $var_name;
                        echo "select_name: " . $select_name . PHP_EOL;
                        if (!in_array(strtolower($select_name), $guild_roles_names)) {//Check to make sure the role exists in the guild
                            //Create the role
                            $new_role = $discord->factory(
                                Discord\Parts\Guild\Role::class,
                                [
                                    'name' => ucfirst($select_name),
                                    'permissions' => 0,
                                    'color' => 15158332,
                                    'hoist' => false,
                                    'mentionable' => false
                                ]
                            );
                            /*
                            $new_role = array(
                                'name' => ucfirst($select_name),
                                'permissions' => 0,
                                'color' => 15158332,
                                'hoist' => false,
                                'mentionable' => false
                            );
                            */
                            $author_guild->createRole($new_role->getUpdatableAttributes())->done(function ($role) use ($respondent_member) : void {
                                //echo '[ROLECREATE SUCCEED]' . PHP_EOL;
                                $respondent_member->addRole($role->id);
                            }, static function ($error) {
                                echo $e->getMessage() . PHP_EOL;
                            });
                            echo "[ROLE $select_name CREATED]" . PHP_EOL;
                        }
                        //Messages can have a max of 20 different reacts, but species has more than 20 options
                        //Clear reactions to avoid discord ratelimit
                        //$message->clearReactions();
                    }
                }
                //$message->clearReactions();
                /*foreach ($species as $var_name => $value){
                    //$message->react($value);
                }*/
            }
            break;
        case ($species2_message_id):
            if ($rp1) {
                echo "species2 reaction" . PHP_EOL;
                foreach ($species2 as $var_name => $value) {
                    if (($value == $emoji_name) || ($value == $emoji_name)) {
                        $select_name = $var_name;
                        echo "select_name: " . $select_name . PHP_EOL;
                        if (!in_array(strtolower($select_name), $guild_roles_names)) {//Check to make sure the role exists in the guild
                            //Create the role
                            /*
                            $new_role = array(
                                'name' => ucfirst($select_name),
                                'permissions' => 0,
                                'color' => 15158332,
                                'hoist' => false,
                                'mentionable' => false
                            );
                            */
                            $new_role = $discord->factory(
                                Discord\Parts\Guild\Role::class,
                                [
                            'name' => ucfirst($select_name),
                            'permissions' => 0,
                            'color' => 15158332,
                            'hoist' => false,
                            'mentionable' => false
                            ]
                            );
                            $author_guild->createRole($new_role->getUpdatableAttributes())->done(function ($role) use ($respondent_member) : void {
                                //echo '[ROLECREATE SUCCEED]' . PHP_EOL;
                                $respondent_member->addRole($role->id);
                            }, static function ($error) {
                                echo $e->getMessage() . PHP_EOL;
                            });
                            echo "[ROLE $select_name CREATED]" . PHP_EOL;
                        }
                        //Messages can have a max of 20 different reacts, but species has more than 20 options
                        //Clear reactions to avoid discord ratelimit
                        //$message->clearReactions();
                    }
                }
                //$message->clearReactions();
                /*foreach ($species2 as $var_name => $value){
                    //$message->react($value);
                }*/
            }
            break;
        case ($species3_message_id):
            if ($rp1) {
                echo "species3 reaction" . PHP_EOL;
                foreach ($species3 as $var_name => $value) {
                    if (($value == $emoji_name) || ($value == $emoji_name)) {
                        $select_name = $var_name;
                        echo "select_name: " . $select_name . PHP_EOL;
                        if (!in_array(strtolower($select_name), $guild_roles_names)) {//Check to make sure the role exists in the guild
                            //Create the role
                            /*
                            $new_role = array(
                                'name' => ucfirst($select_name),
                                'permissions' => 0,
                                'color' => 15158332,
                                'hoist' => false,
                                'mentionable' => false
                            );
                            */
                            $new_role = $discord->factory(
                                Discord\Parts\Guild\Role::class,
                                [
                                'name' => ucfirst($select_name),
                                'permissions' => 0,
                                'color' => 15158332,
                                'hoist' => false,
                                'mentionable' => false
                                ]
                            );
                            $author_guild->createRole($new_role->getUpdatableAttributes())->done(function ($role) use ($respondent_member) : void {
                                //echo '[ROLECREATE SUCCEED]' . PHP_EOL;
                                $respondent_member->addRole($role->id);
                            }, static function ($error) {
                                echo $e->getMessage() . PHP_EOL;
                            });
                            echo "[ROLE $select_name CREATED]" . PHP_EOL;
                        }
                        //Messages can have a max of 20 different reacts, but species has more than 20 options
                        //Clear reactions to avoid discord ratelimit
                        //$message->clearReactions();
                    }
                }
                //$message->clearReactions();
                /*foreach ($species3 as $var_name => $value){
                    //$message->react($value);
                }*/
            }
            break;
        case ($sexuality_message_id):
            if ($rp2) {
                echo "sexuality reaction" . PHP_EOL;
                foreach ($sexualities as $var_name => $value) {
                    if (($value == $emoji_name) || ($value == $emoji_name)) {
                        $select_name = $var_name;
                        if (!in_array(strtolower($select_name), $guild_roles_names)) {//Check to make sure the role exists in the guild
                            //Create the role
                            /*
                            $new_role = array(
                                'name' => ucfirst($select_name),
                                'permissions' => 0,
                                'color' => 7419530,
                                'hoist' => false,
                                'mentionable' => false
                            );
                            */
                            
                            $new_role = $discord->factory(
                                Discord\Parts\Guild\Role::class,
                                [
                                'name' => ucfirst($select_name),
                                'permissions' => 0,
                                'color' => 0x992d22,
                                'hoist' => false,
                                'mentionable' => false
                                ]
                            );
                            $author_guild->createRole($new_role->getUpdatableAttributes())->done(function ($role) use ($respondent_member) : void {
                                //echo '[ROLECREATE SUCCEED]' . PHP_EOL;
                                $respondent_member->addRole($role->id);
                            }, static function ($error) {
                                echo $e->getMessage() . PHP_EOL;
                            });
                            echo "[ROLE $select_name CREATED]" . PHP_EOL;
                        }
                    }
                }
                /*foreach ($sexualities as $var_name => $value){
                    //$message->react($value);
                }*/
            }
            break;
        case ($gender_message_id):
            if ($rp3) {
                echo "gender reaction" . PHP_EOL;
                foreach ($gender as $var_name => $value) {
                    if (($value == $emoji_name) || ($value == $emoji_name)) {
                        $select_name = $var_name;
                        if (!in_array(strtolower($select_name), $guild_roles_names)) {//Check to make sure the role exists in the guild
                            //Create the role
                            /*
                            $new_role = array(
                                'name' => ucfirst($select_name),
                                'permissions' => 0,
                                'color' => 3066993,
                                'hoist' => false,
                                'mentionable' => false
                            );
                            */
                            
                            $new_role = $discord->factory(
                                Discord\Parts\Guild\Role::class,
                                [
                                'name' => ucfirst($select_name),
                                'permissions' => 0,
                                'color' => 0x713678a,
                                'hoist' => false,
                                'mentionable' => false
                                ]
                            );
                            $author_guild->createRole($new_role->getUpdatableAttributes())->done(function ($role) use ($respondent_member) : void {
                                //echo '[ROLECREATE SUCCEED]' . PHP_EOL;
                                $respondent_member->addRole($role->id);
                            }, static function ($error) {
                                echo $e->getMessage() . PHP_EOL;
                            });
                            echo "[ROLE $select_name CREATED]" . PHP_EOL;
                        }
                    }
                }
                //$message->clearReactions();
                /*foreach ($gender as $var_name => $value){
                    //$message->react($value);
                }*/
            }
            break;
		case ($pronouns_message_id):
            if ($rp5) {
                echo "pronouns reaction" . PHP_EOL;
                foreach ($pronouns as $var_name => $value) {
                    if (($value == $emoji_name) || ($value == $emoji_name)) {
                        $select_name = $var_name;
                        if (!in_array(strtolower($select_name), $guild_roles_names)) {//Check to make sure the role exists in the guild
                            //Create the role
                            /*
                            $new_role = array(
                                'name' => ucfirst($select_name),
                                'permissions' => 0,
                                'color' => 3066993,
                                'hoist' => false,
                                'mentionable' => false
                            );
                            */
                            
                            $new_role = $discord->factory(
                                Discord\Parts\Guild\Role::class,
                                [
                                'name' => ucfirst($select_name),
                                'permissions' => 0,
                                'color' => 0x9b59b6,
                                'hoist' => false,
                                'mentionable' => false
                                ]
                            );
                            $author_guild->createRole($new_role->getUpdatableAttributes())->done(function ($role) use ($respondent_member) : void {
                                //echo '[ROLECREATE SUCCEED]' . PHP_EOL;
                                $respondent_member->addRole($role->id);
                            }, static function ($error) {
                                echo $e->getMessage() . PHP_EOL;
                            });
                            echo "[ROLE $select_name CREATED]" . PHP_EOL;
                        }
                    }
                }
                //$message->clearReactions();
                /*foreach ($pronouns as $var_name => $value){
                    //$message->react($value);
                }*/
            }
            break;
		
        case ($customroles_message_id):
            if ($rp4) {
                echo "Custom roles reaction" . PHP_EOL;
                //echo "emoji_name: $emoji_name" . PHP_EOL; //Should be unicode
                foreach ($customroles as $var_name => $value) {
                    if (($value == $emoji_name) || ($value == $emoji_name)) {
                        $select_name = $var_name;
                        echo "select_name: $select_name" . PHP_EOL;
                        if (!in_array(strtolower($select_name), $guild_roles_names)) {//Check to make sure the role exists in the guild
                            //Create the role
                            /*
                            $new_role = array(
                                'name' => ucfirst($select_name),
                                'permissions' => 0,
                                'color' => 3066993,
                                'hoist' => false,
                                'mentionable' => false
                            );
                            */
                            
                            $new_role = $discord->factory(
                                Discord\Parts\Guild\Role::class,
                                [
                                'name' => ucfirst($select_name),
                                'permissions' => 0,
                                'color' => 0x1abc9c,
                                'hoist' => false,
                                'mentionable' => false
                                ]
                            );
                            $author_guild->createRole($new_role->getUpdatableAttributes())->done(function ($role) use ($respondent_member) : void {
                                //echo '[ROLECREATE SUCCEED]' . PHP_EOL;
                                $respondent_member->addRole($role->id);
                            }, static function ($error) {
                                echo $e->getMessage() . PHP_EOL;
                            });
                            echo "[ROLE $select_name CREATED]" . PHP_EOL;
                        }
                    }
                }
                //$message->clearReactions();
                /*foreach ($customroles as $var_name => $value){
                    //$message->react($value);
                }*/
            }
            break;
		case ($nsfw_message_id):
			if ($nsfw) {
                echo "NSFW roles reaction" . PHP_EOL;
                //echo "emoji_name: $emoji_name" . PHP_EOL; //Should be unicode
                foreach ($nsfwarray as $var_name => $value) {
                    if (($value == $emoji_name) || ($value == $emoji_name)) {
                        $select_name = $var_name;
                        echo "select_name: $select_name" . PHP_EOL;
                        if (!in_array(strtolower($select_name), $guild_roles_names)) {//Check to make sure the role exists in the guild
                            //Create the role
                            $new_role = $discord->factory(
                                Discord\Parts\Guild\Role::class,
                                [
                                'name' => ucfirst($select_name),
                                'permissions' => 0,
                                'color' => 15158332,
                                'hoist' => false,
                                'mentionable' => false
                                ]
                            );
                            $author_guild->createRole($new_role->getUpdatableAttributes())->done(function ($role) use ($respondent_member) : void {
                                //echo '[ROLECREATE SUCCEED]' . PHP_EOL;
                                $respondent_member->addRole($role->id);
                            }, static function ($error) {
                                echo $e->getMessage() . PHP_EOL;
                            });
                            echo "[ROLE $select_name CREATED]" . PHP_EOL;
                        }
                    }
                }
                //$message->clearReactions();
                /*foreach ($nsfwarray as $var_name => $value){
                    //$message->react($value);
                }*/
            }
			break;
    }
        if ($select_name != "") { //A reaction role was found
            //Check if the member has a role of the same name
            if (in_array(strtolower($select_name), $respondent_member_roles_names)) {
                //Remove the role
                $role_index = array_search(strtolower($select_name), $guild_roles_names);
                $target_role_id = $guild_roles_ids[$role_index];
                echo "target_role_id: " . $target_role_id . PHP_EOL;
                $respondent_member->removeRole($guild_roles_role[$target_role_id]); //$target_role_id);
                echo "Role removed: $select_name" . PHP_EOL;
            } else {
                //echo "Respondent does not already have the role" . PHP_EOL;
            if (in_array(strtolower($select_name), $guild_roles_names)) {//Check to make sure the role exists in the guild
                //Add the role
                $role_index = array_search(strtolower($select_name), $guild_roles_names);
                $target_role_id = $guild_roles_ids[$role_index];
                $respondent_member->addRole($guild_roles_role[$target_role_id]); // $target_role_id);
                echo "Role added: $select_name" . PHP_EOL;
            } else {
                echo "Guild does not have this role" . PHP_EOL;
            }
            }
        }
    }
}
