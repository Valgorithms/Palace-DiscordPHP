<?php
//Returns a random result from an array
function GetRandomArrayIndex(Array $array)
{
    if ((count($array)) != 0) {
        return rand(0, count($array)-1);
    }
    return -1; //Not an array
}

function getvar(Array $array, $var)
{
    if (array_key_exists($var, $array)) {
        return $array[$var];
    }
    return null;
}

//Removes a value from an array using comparison
function array_value_remove($value, $array)
{    
    return array_diff($array, array($value));
}

//Checks if a directory contains any files
function is_dir_empty($dir)
{
    foreach (new DirectoryIterator($dir) as $fileInfo) {
        if ($fileInfo->isDot()) {
            continue;
        }
        return false;
    }
    return true;
}

//Checks if a folder exists and creates one if it doesn't
function CheckDir($foldername)
{
    include("constants.php");
    if (! file_exists(getcwd().$foldername."/")) { //Create folder if it doesn't already exist
        mkdir(getcwd().$foldername."/", 0777, true);
		return false;
    }
	return true;
}

//Checks if a file exists
function CheckFile($foldername, $filename)
{
	$folder_symbol = "";
    if ($foldername !== null) {
        $folder_symbol = "/";
    }
    //Create folder if it doesn't already exist
    if (file_exists(getcwd().$foldername.$folder_symbol.$filename)) {
		return true;
    }
	return false;
}

//Saves a variable to a file
//Target is a full path, IE getcwd().target.php
function VarSave($foldername, $filename, $variable)
{
    $folder_symbol = "";
	if ($foldername !== null) {
        $folder_symbol = "/";
    }
    //Create folder if it doesn't already exist
    if (! file_exists(getcwd().$foldername.$folder_symbol)) {
        mkdir(getcwd().$foldername.$folder_symbol, 0777, true);
    }
    //Save variable to a file
    $serialized_variable = serialize($variable);
    file_put_contents($path.$filename, $serialized_variable);
}

//Loads a variable from a file
//Target is a full path, IE getcwd().target.php
function VarLoad($foldername, $filename)
{
    $folder_symbol = "";
	if ($foldername !== null) {
        $folder_symbol = "/";
    }
    //Make sure the file exists
    if (! file_exists(getcwd().$foldername.$folder_symbol.$filename)) {
        return null;
    }
    //Load a variable from a file
    $loadedvar = file_get_contents($path.$filename);
    $unserialized = unserialize($loadedvar);
    return $unserialized;
}

function VarDelete($foldername, $filename)
{
	$folder_symbol = "";
    if ($foldername !== null) {
        $folder_symbol = "/";
    }    
    //Make sure the file exists first
    if (CheckFile($foldername, $filename)) {
        //Delete the file
        unlink(getcwd().$foldername.$folder_symbol.$filename);
        clearstatcache();
    }
}

/*
*********************
*********************
Timers and Cooldowns
*********************
*********************
*/

function TimeCompare($foldername, $filename)
{
    $now = new DateTime();
    $then = VarLoad($foldername, $filename); //instance of DateTime;
    //check if file exists
    if ($then) {
        $sincetime = date_diff($now, $then);
        $timecompare['y'] = $sinceYear 	= $sincetime->y;
    } else {
        //File not found, so return 0's
        $sincetime = date_diff($now, $now);
        $timecompare['y'] = $sinceYear 	= ($sincetime->y)+1; //Assume one year has passed, enough time to avoid any cooldown
    }
	$timecompare['m'] = $sinceMonth 	= $sincetime->m;
	$timecompare['d'] = $sinceDay 		= $sincetime->d;
	$timecompare['h'] = $sinceHour 		= $sincetime->h;
	$timecompare['i'] = $sinceMinute 	= $sincetime->i;
	$timecompare['s'] = $sinceSecond 	= $sincetime->s;
	return $timecompare;
}


function TimeCompareMem($author_id, $variable)
{
    $now = new DateTime();
    $varname = $author_id . $variable . "_cooldown"; //Check this
    $then = $GLOBALS["$varname"]; //instance of DateTime    
    //check if file exists
    if ($then) {
        $sincetime = date_diff($now, $then);
        $timecompare['y'] = $sinceYear 	= $sincetime->y;
    } else {
        //File not found, so return 0's
        $sincetime = date_diff($now, $now);
        $timecompare['y'] = $sinceYear 	= ($sincetime->y)+1; //Assume one year has passed, enough time to avoid any cooldown
    }
	$timecompare['m'] = $sinceMonth 	= $sincetime->m;
	$timecompare['d'] = $sinceDay 		= $sincetime->d;
	$timecompare['h'] = $sinceHour 		= $sincetime->h;
	$timecompare['i'] = $sinceMinute 	= $sincetime->i;
	$timecompare['s'] = $sinceSecond 	= $sincetime->s;
	return $timecompare;
}

function TimeLimitCheck($time = null, int $y = 0, int $m = 0, int $d = 0, int $h = 0, int $i = 0, int $s = 0)
{
    if (! $time) return true; //Nothing to check, assume true
    //Calculate total number of seconds needed to continue.
    $required_time =
    ($s) +
    ($i * 60) +
    ($h * 3600) +
    ($d * 86400) +
    ($m * 2629746) +
    ($y * 31556952);
    //Calculate total number of seconds passed.
    $passed_time =
    ($time['s']) +
    ($time['i'] * 60) +
    ($time['h'] * 3600) +
    ($time['d'] * 86400) +
    ($time['m'] * 2629746) +
    ($time['y'] * 31556952);
    $return_array = array();
	$return_array[0] = false;
    if ($passed_time > $required_time) {
        $return_array[0] = true;
    }
    $return_array[1] = $passed_time;
    return $return_array;
}

function PassedTimeCheck(int $y = 0, int $m = 0, int $d = 0, int $h = 0, int $i = 0, int $s = 0)
{
    //Calculate total number of seconds passed.
    $passed_time =
    ($s) +
    ($i * 60) +
    ($h * 3600) +
    ($d * 86400) +
    ($m * 2629746) +
    ($y * 31556952);
    if ($passed_time) return $passed_time;
	return 31556952; //Assume one year has passed, enough time to avoid any cooldown
	
}

function CheckCooldown($foldername, $filename, $limit_array)
{
    $TimeCompare = TimeCompare($foldername, $filename);
    if ($TimeCompare) {
        $TimeLimitCheck = TimeLimitCheck($TimeCompare, $limit_array['year'], $limit_array['month'], $limit_array['day'], $limit_array['hour'], $limit_array['min'], $limit_array['sec']);
    } else { //File was not found, so assume the check passes because they haven't used it before
        $TimeLimitCheck = array();
        $TimeLimitCheck[] = true;
        $TimeLimitCheck[] = 0;
    }
	return $TimeLimitCheck;
}

function CheckCooldownMem($author_id, $variable, $limit_array)
{
    $TimeCompare = TimeCompareMem($author_id, $variable);
    if ($TimeCompare) {
        $TimeLimitCheck = TimeLimitCheck($TimeCompare, $limit_array['year'], $limit_array['month'], $limit_array['day'], $limit_array['hour'], $limit_array['min'], $limit_array['sec']);
    } else { //File was not found, so assume the check passes because they haven't used it before
        $TimeLimitCheck = array();
        $TimeLimitCheck[] = true;
        $TimeLimitCheck[] = 0;
    }
	return $TimeLimitCheck;
}

function SetCooldown($foldername, $filename)
{
    $folder_symbol = "";
	if ($foldername !== null) {
        $folder_symbol = "/";
    }
    VarSave($foldername, $filename, new DateTime());
}

function SetCooldownMem($author_id, $variable)
{
    $GLOBALS[$author_id . $variable . "_cooldown"] = $now = new DateTime();
}

function FormatTime($seconds)
{
    //compare time
    $dtF = new \DateTime('@0');
    $dtT = new \DateTime("@$seconds");
    //ymdhis
    $formatted = $dtF->diff($dtT)->format(' %y years, %m months, %d days, %h hours, %i minutes and %s seconds');
    //remove 0 values
    $formatted = str_replace(" 0 years,", "", $formatted);
    $formatted = str_replace(" 0 months,", "", $formatted);
    $formatted = str_replace(" 0 days,", "", $formatted);
    $formatted = str_replace(" 0 hours,", "", $formatted);
    $formatted = str_replace(" 0 minutes and", "", $formatted);
    $formatted = str_replace(" 0 seconds,", "", $formatted);
    $formatted = trim($formatted);
    return $formatted;
}

function TimeArrayToSeconds(Array $array)
{
    $y = $array['year'];
    $m = $array['month'];
    $d = $array['day'];
    $h = $array['hour'];
    $i = $array['min'];
    $s = $array['sec'];
    $seconds =
    ($s) +
    ($i * 60) +
    ($h * 3600) +
    ($d * 86400) +
    ($m * 2629746) +
    ($y * 31556952);
    return $seconds;
}

function snowflake_timestamp($snowflake)
{ //used by Restcord to get account age
    if (\PHP_INT_SIZE === 4) { //x86
        $binary = \str_pad(\base_convert($snowflake, 10, 2), 64, 0, \STR_PAD_LEFT);
        $time = \base_convert(\substr($binary, 0, 42), 2, 10);
        $timestamp = (float) ((((int) \substr($time, 0, -3)) + 1420070400).'.'.\substr($time, -3));
        $workerID = (int) \base_convert(\substr($binary, 42, 5), 2, 10);
        $processID = (int) \base_convert(\substr($binary, 47, 5), 2, 10);
        $increment = (int) \base_convert(\substr($binary, 52, 12), 2, 10);
    } else { //x64
        $snowflake = (int) $snowflake;
        $time = (string) ($snowflake >> 22);
        $timestamp = (float) ((((int) \substr($time, 0, -3)) + 1420070400).'.'.\substr($time, -3));
        $workerID = ($snowflake & 0x3E0000) >> 17;
        $processID = ($snowflake & 0x1F000) >> 12;
        $increment = ($snowflake & 0xFFF);
    }
    if ($timestamp < 1420070400 || $workerID < 0 || $workerID >= 32 || $processID < 0 || $processID >= 32 || $increment < 0 || $increment >= 4096) {
        return null;
    }
    return $timestamp;
}

function GetMention(array $array = [])
{
    $size = count($array);
    //Exit conditions
    if ( is_object($array[0]) && get_class($array[0]) != "Discord\Parts\Guild\Guild") {
        if (is_numeric($array[0])) {
            //Try to get the guild by ID
            return false; //Not yet implemented
        } else {
            if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "No guild variable passed!" . PHP_EOL;
            return false; //Check if guild variable was passed
        }
    }
    $guild = $array[0];
    
    $option = null;
    $filter = null;
    $value = $array[1];
    switch ($size) {
        case 5:
            //Check if an instance of restcord
            if ( is_object($array[4]) && get_class($array[4]) == "RestCord\DiscordClient") {
                $restcord = &$array[4];
            } else {
                if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "Parameter isn't a valid instance of Restcord!" . PHP_EOL;
            }
            // no break
        case 4:
            if (is_numeric($array[3])) {
                $option = $array[3];
            }
            // no break
        case 3:
            $filter = $array[2];
            if ($filter) {
                $value = str_replace($filter, "", $array[1]);
            }
            // no break
        case 2:
            break;
        case 0:
        case 1:
        default:
            return false;
    }
    
    //Explode the string into an array
    $value = str_replace("<@!", "", $value);
    $value = str_replace("<@", "", $value);
    $value = str_replace(">", "", $value);
    $linesplit = explode(" ", $value);
    //Check each part of the string for a mention
    $id_array = array();
    foreach ($linesplit as $line) {
        if (is_numeric($line)) { //Add each ID to an array
            if (!(in_array($line, $id_array))) { //Don't add duplicates
                $id_array[] = $line;
            }
        }
    }
    
    if (empty($id_array)) {
        if(isset($GLOBALS['debug_echo']) && $GLOBALS['debug_echo']) echo "ID array empty!" . PHP_EOL;
        return false;
    }
    
    $return_array = array();
    foreach ($id_array as $id) {
        $value = str_replace($id, "", $value);
        switch ($option) { //What info do we care about getting back?
            case 1:
                $mention_member	= $guild->members->offsetGet($id);
                $mention_user = $mention_member->user;
                $return_array[$id]['mention_member'] = $mention_member;
                $return_array[$id]['mention_user'] = $mention_user;
                $return_array[$id]['restcord_user'] = $restcord_user ?? false;
                $return_array[$id]['restcord_user_found'] = $restcord_user_found ?? false;
                break;
            case 2:
            case 3:
            case null: //Grab all that apply
            default:
                $mention_member	= $guild->members->offsetGet($id);
                $mention_user = $mention_member->user;
                $return_array[$id]['mention_member'] = $mention_member;
                $return_array[$id]['mention_user'] = $mention_user;
                $return_array[$id]['restcord_user'] = $restcord_user ?? false;
                $return_array[$id]['restcord_user_found'] = $restcord_user ?? false;
                break;
        }
    }
    $return_array['string']['string'] = trim($value) ?? false;
    return $return_array;
}


function appendImages($array)
{
    if (! is_array($array) || empty($array)) {
        return false;
    }
    
    /* Create new imagick object */
    $img = new Imagick();
    foreach ($array as $url) {
        /* retrieve image content */
        $webimage = file_get_contents($url);
        $img->readImageBlob($webimage);
    }
    /* Append the images into one */
    $img->resetIterator();
    $combined = $img->appendImages(true);
    /* Output the image */
    $combined->setImageFormat("png");
    
    /* Define pathing */
    $cache_folder = "C:/WinNMP/WWW/vzg.project/cache/";
    $img_rand = rand(0, 99999999999) . "cachedimage.png"; //Some big number to make the URLs unique because Discord caches image links
    $path =  $cache_folder . $img_rand;
    
    /* Delete old images before creating the new one */
    $files = glob($cache_folder . "*"); //Get all file names
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        } //Delete file
    }
    clearstatcache();
        
    /* Save the file */
    $combined->writeImage($path);
    //imagepng($combined, $path); //Only works for resources, but imagick is an object
    
    /* Return the URL where the image can be accessed by Discord */
    return "https://www.valzargaming.com/cache/" . $img_rand;
}
