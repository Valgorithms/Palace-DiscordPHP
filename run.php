<?php

//This file was written by Valithor#5947 <@116927250145869826>
//Special thanks to keira#7829 <@297969955356540929> for helping me get this behemoth working after converting from DiscordPHP

//DO NOT VAR_DUMP GETS, most objects like GuildMember have a guild property which references all members
//Use get_class($object) to verify the main object (usually a collection, check src/Models/)
//Use get_class($object->first())to verify you're getting the right kind of object. IE, $author_guildmember->roles should be Models\Role)
//If any of these methods resolve to a class of React\Promise\Promise you're probably passing an invalid parameter for the class
//Always subtract 1 when counting roles because everyone has an @everyone role
$vm = false; //Set this to true if using a VM that can be paused

include __DIR__ . '/vendor/autoload.php';
define('MAIN_INCLUDED', 1); //Token and SQL credential files are protected, this must be defined to access
ini_set('memory_limit', '-1'); //Unlimited memory usage

function execInBackground($cmd)
{
    if (substr(php_uname(), 0, 7) == "Windows") {
        //pclose(popen("start /B ". $cmd, "r"));
        pclose(popen("start ". $cmd, "r"));
    }//else exec($cmd . " > /dev/null &");
}

//Global variables
include 'config.php'; //Global config variables
include 'species.php'; //Used by the species role picker function
include 'sexualities.php'; //Used by the sexuality role picker function
include 'gender.php'; //Used by the gender role picker function
include 'custom_roles.php'; //Create your own roles with this template!

include 'blacklisted_owners.php'; //Array of guild owner user IDs that are not allowed to use this bot
include 'blacklisted_guilds.php'; //Array of Guilds that are not allowed to use this bot
include 'whitelisted_guilds.php'; //Only guilds in the $whitelisted_guilds array should be allowed to access the bot.

require '../token.php';
use Discord\Discord;
use Discord\Parts\Embed\Embed;
use Discord\Parts\User\User;
use Discord\Parts\Guild\Role;
use Carbon\Carbon;

$logger = new Monolog\Logger('HTTPLogger');
$logger->pushHandler(new Monolog\Handler\StreamHandler('php://stdout'));

$discord = new Discord([
    'token' => "$token",
    'loadAllMembers' => true,
    'storeMessages' => true,
	//'httpLogger' => $logger
]);

$loop = $discord->getLoop();

use RestCord\DiscordClient;

$restcord = new DiscordClient(['token' => "{$token}"]); // Token is required
//var_dump($restcord->guild->getGuild(['guild.id' => 116927365652807686]));

/*
set_exception_handler(function (Throwable $e) { //stops execution completely
    //
});
*/

include_once "custom_functions.php";
$rescue = VarLoad("_globals", "RESCUE.php"); //Check if recovering from a fatal crash
$GLOBALS['presenceupdate'] = false;
if ($rescue == true) { //Attempt to restore crashed session
    echo "[RESCUE START]" . PHP_EOL;
    $rescue_dir = __DIR__ . '/_globals';
    $rescue_vars = scandir($rescue_dir);
    foreach ($rescue_vars as $var) {
        $backup_var = VarLoad("_globals", "$var");
                
        $filter = ".php";
        $value = str_replace($filter, "", $var);
        $GLOBALS["$value"] = $backup_var;
        
        $target_dir = $rescue_dir . "/" . $var;
        echo $target_dir . PHP_EOL;
        unlink($target_dir);
    }
    VarSave("_globals", "rescue.php", false);
    echo "[RESCUE DONE]" . PHP_EOL;
}
$dt = new DateTime("now"); // convert UNIX timestamp to PHP DateTime
echo "[LOGIN] " . $dt->format('d-m-Y H:i:s') . PHP_EOL; // output = 2017-01-01 00:00:00
try {
    $discord->on('error', function ($error) { //Handling of thrown errors
        echo "[ERROR] $error" . PHP_EOL;
        $exception = null;
        try {
            echo '[ERROR]' . $error->getMessage() . " in file " . $error->getFile() . " on line " . $error->getLine() . PHP_EOL;
        } catch (Throwable $exception) {
        } catch (Exception $e) {
            echo '[ERROR]' . $e->getMessage() . " in file " . $e->getFile() . " on line " . $e->getLine() . PHP_EOL;
        }
        if ($exception) {
            throw $exception;
        }
    });

    $discord->once('ready', function ($discord) use ($loop, $token, $restcord) {	// Listen for events here
        //$line_count = COUNT(FILE(basename($_SERVER['PHP_SELF']))); //No longer relevant due to includes
        //$version = "RC V1.4.1";
        /*
        $discord->user->setPresence( //Discord status
            array(
                'since' => null, //unix time (in milliseconds) of when the client went idle, or null if the client is not idle
                'game' => array(
                    //'name' => "$line_count lines of code! $version",
                    'name' => $version,
                    'type' => 3, //0, 1, 2, 3, 4 | Game/Playing, Streaming, Listening, Watching, Custom Status
                    'url' => null //stream url, is validated when type is 1, only Youtube and Twitch allowed

                    //Bots are only able to send name, type, and optionally url.
                    //As bots cannot send states or emojis, they can't make effective use of custom statuses.
                    //The header for a "Custom Status" may show up on their profile, but there is no actual custom status, because those fields are ignored.

                ),
                'status' => 'dnd', //online, dnd, idle, invisible, offline
                'afk' => false
            )
        );
        */
        $tag = $discord->user->username . "#" . $discord->user->discriminator;
        echo "[READY] Logged in as $tag (" . $discord->id . ')' . PHP_EOL;
        $dt = new DateTime("now"); // convert UNIX timestamp to PHP DateTime
        echo "[READY TIMESTAMP] " . $dt->format('d-m-Y H:i:s') . PHP_EOL; // output = 2017-01-01 00:00:00
        
        $discord->on('message', function ($message, $discord) use ($loop, $token, $restcord) { //Handling of a message
            include "author_perms.php";
            include "message-include.php";
        });
            
        $discord->on('GUILD_MEMBER_ADD', function ($guildmember) use ($discord) { //Handling of a member joining the guild
            include "guildmemberadd-include.php";
        });
        
        $discord->on('GUILD_MEMBER_REMOVE', function ($guildmember) use ($discord) { //Handling of a user leaving the guild
            include 'guildmemberremove-include.php';
        });
        
        $discord->on('GUILD_MEMBER_UPDATE', function ($member, $discord, $member_old)/* use ($discord) */{ //Handling of a member getting updated
            include "guildmemberupdate-include.php";
        });
            
        $discord->on('GUILD_BAN_ADD', function ($ban) use ($discord) { //Handling of a user getting banned
            include "guildbanadd-include.php";
        });
        
        $discord->on('GUILD_BAN_REMOVE', function ($ban) use ($discord) { //Handling of a user getting unbanned
            include "guildbanremove-include.php";
        });
        
        $discord->on('MESSAGE_UPDATE', function ($message_new, $message_old) use ($discord) { //Handling of a message being changed
            include "messageupdate-include.php";
        });
        
        $discord->on('messageUpdateRaw', function ($channel, $data_array) use ($discord) { //Handling of an old/uncached message being changed
            include "messageupdateraw-include.php";
        });
        
        $discord->on('MESSAGE_DELETE', function ($message) use ($discord) { //Handling of a message being deleted
            include "messagedelete-include.php";
        });
        
        $discord->on('messageDeleteRaw', function ($channel, $message_id) use ($discord) { //Handling of an old/uncached message being deleted
            include "messagedeleteraw-include.php";
        });
        
        $discord->on('MESSAGE_DELETE_BULK', function ($messages) use ($discord) { //Handling of multiple messages being deleted
            echo "[messageDeleteBulk]" . PHP_EOL;
        });
        
        $discord->on('messageDeleteBulkRaw', function ($messages) use ($discord) { //Handling of multiple old/uncached messages being deleted
            echo "[messageDeleteBulkRaw]" . PHP_EOL;
        });
        
        $discord->on('MESSAGE_REACTION_ADD', function ($reaction) use ($discord) { //Handling of a message being reacted to
            include "messagereactionadd-include.php";
        });
        
        $discord->on('MESSAGE_REACTION_REMOVE', function ($reaction) use ($discord) { //Handling of a message reaction being removed
            include "messagereactionremove-include.php";
        });
        
        $discord->on('MESSAGE_REACTION_REMOVE_ALL', function ($message) use ($discord) { //Handling of all reactions being removed from a message
            //$message_content = $message->content;
            echo "[messageReactionRemoveAll]" . PHP_EOL;
        });
        
        $discord->on('CHANNEL_CREATE', function ($channel) use ($discord) { //Handling of a channel being created
            echo "[channelCreate]" . PHP_EOL;
        });
        
        $discord->on('CHANNEL_DELETE', function ($channel) use ($discord) { //Handling of a channel being deleted
            echo "[channelDelete]" . PHP_EOL;
        });
        
        $discord->on('CHANNEL_UPDATE', function ($channel) use ($discord) { //Handling of a channel being changed
            echo "[channelUpdate]" . PHP_EOL;
        });
            
        $discord->on('userUpdate', function ($user_new, $user_old) use ($discord) { //Handling of a user changing their username/avatar/etc
            include "userupdate-include.php";
        });
            
        $discord->on('GUILD_ROLE_CREATE', function ($role) use ($discord) { //Handling of a role being created
            echo "[roleCreate]" . PHP_EOL;
        });
        
        $discord->on('GUILD_ROLE_DELETE', function ($role) use ($discord) { //Handling of a role being deleted
            echo "[roleDelete]" . PHP_EOL;
        });
        
        $discord->on('GUILD_ROLE_UPDATE', function ($role_new, $role_old) use ($discord) { //Handling of a role being changed
            echo "[roleUpdate]" . PHP_EOL;
        });
        
        $discord->on('voiceStateUpdate', function ($member_new, $member_old) use ($discord) { //Handling of a member's voice state changing (leaves/joins/etc.)
            echo "[voiceStateUpdate]" . PHP_EOL;
        });
        
        $discord->on("error", function (\Throwable $e) {
            echo '[ERROR]' . $e->getMessage() . " in file " . $e->getFile() . " on line " . $e->getLine() . PHP_EOL;
            return true;
        });
        
        /*
        $discord->wsmanager()->on('debug', function ($debug) {
            switch ($debug){
                case "Shard 0 received WS packet with OP code 0": //Spammy
                case "Shard 0 handling WS event PRESENCE_UPDATE": //Spammy
                    break;
                default:
                    echo "[WS DEBUG] $debug" . PHP_EOL;
            }
        });
        */
        
        $discord->on('debug', function ($debug) {
            echo '[DEBUG]' . PHP_EOL;
            $dt = new DateTime("now"); // convert UNIX timestamp to PHP DateTime
            switch ($debug) {
                case 0:
                default:
                    echo "[CLIENT DEBUG] {".$dt->format('d-m-Y H:i:s')."} $debug" . PHP_EOL;
            }
        });
    }); //end main function ready

    $discord->on('disconnect', function ($erMsg, $code) use ($discord, $token, $restcord, $vm) {
        //Restart the bot if it disconnects
        //This is almost always going to be caused by error code 1006, meaning the bot did not get heartbeat from Discord
        include "disconnect-include.php";
    });

    set_error_handler(function (int $number, string $message, string $filename, int $fileline) {
        /*if ($message != "Undefined variable: suggestion_pending_channel") //Expected to be null
        if ($message != "Trying to access array offset on value of type null") //Expected to be null, part of ;validate*/
        $warn = false;
        $skip_array = array();
        $skip_array[] = "Undefined variable";
        $skip_array[] = "Trying to access array offset on value of type null"; //Expected to be null, part of ;validate
        foreach ($skip_array as $value) {
            if (strpos($value, $message) !== false) {
                $warn = true;
            }
        }
        if ($warn) {
            echo PHP_EOL . PHP_EOL . "Handler captured error $number: '$message' in $filename on line $fileline" . PHP_EOL . PHP_EOL;
        }
        //die();
    });
    
    $discord->run();
} catch (Throwable $e) { //Restart the bo
    echo "Captured Throwable: " . $e->getMessage() . " in file " . $e->getFile() . " on line " . $e->getLine(). PHP_EOL;

    //Rescue global variables
    $GLOBALS["RESCUE"] = true;
    $blacklist_globals = array(
        "GLOBALS",
        "loop",
        "discord",
        "restcord",
        "MachiKoro_Games"
    );
    echo "Skipped: ";
    foreach ($GLOBALS as $key => $value) {
        $temp = array($value);
        if (!in_array($key, $blacklist_globals)) {
            try {
                VarSave("_globals", "$key.php", $value);
            } catch (Throwable $e) { //This will probably crash the bot
                echo "$key, ";
            }
        } else {
            echo "$key, ";
        }
    }
    echo PHP_EOL;
  
    //sleep(5);
    
    echo "RESTARTING BOT" . PHP_EOL;
    $discord->destroy();
    $restart_cmd = 'cmd /c "'. __DIR__ . '\run.bat"'; //echo $restart_cmd . PHP_EOL;
    //system($restart_cmd);
    execInBackground($restart_cmd);
    die();
}
?> 
