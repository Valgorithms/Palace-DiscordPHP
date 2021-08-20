<?php
//Returns a random result from an array
function GetRandomArrayIndex($array)
{
    if ((count($array)) != 0) {
        $array_size = count($array)-1;
        $index = rand(0, $array_size);
        return $index;
    } else {
        return -1;
    } //Not an array
}

function getvar($array, $var)
{ //gamerbanner stuff
    if (array_key_exists($var, $array)) {
        return $array[$var];
    }
    return null;
}

//Removes a value from an array
function array_value_remove($value, $array)
{
    $remove_array = array($value);
    return array_diff($array, $remove_array);
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
    //if($GLOBALS['debug_echo']) echo "CheckDir" . PHP_EOL;
    include("constants.php");
    $path = getcwd().$foldername."/";
    $exist = false;
    //Create folder if it doesn't already exist
    if (!file_exists($path)) {
        mkdir($path, 0777, true);
        if($GLOBALS['debug_echo']) echo "NEW DIR CREATED: $path" . PHP_EOL;
    } else {
        $exist = true;
    }
    return $exist;
}

//Checks if a file exists
function CheckFile($foldername, $filename)
{
    if ($foldername !== null) {
        $folder_symbol = "/";
    }  else $folder_symbol = "";
    //if($GLOBALS['debug_echo']) echo "CheckDir" . PHP_EOL;
    include("constants.php");
    $path = getcwd().$foldername.$folder_symbol.$filename;
    //Create folder if it doesn't already exist
    if (file_exists($path)) {
        $exist = true;
    } else {
        $exist = false;
    }
    return $exist;
}

//Saves a variable to a file
//Target is a full path, IE getcwd().target.php
function VarSave($foldername, $filename, $variable)
{
    if ($foldername !== null) {
        $folder_symbol = "/";
    }  else $folder_symbol = "";
    //if($GLOBALS['debug_echo']) echo "VarSave" . PHP_EOL;
    include("constants.php");
    $path = getcwd().$foldername.$folder_symbol; //if($GLOBALS['debug_echo']) echo "PATH: $path" . PHP_EOL;
    //Create folder if it doesn't already exist
    if (!file_exists($path)) {
        mkdir($path, 0777, true);
        if($GLOBALS['debug_echo']) echo "NEW DIR CREATED: $path" . PHP_EOL;
    }
    //Save variable to a file
    $serialized_variable = serialize($variable);
    file_put_contents($path.$filename, $serialized_variable);
}

//Loads a variable from a file
//Target is a full path, IE getcwd().target.php
function VarLoad($foldername, $filename)
{
    if ($foldername !== null) {
        $folder_symbol = "/";
    }  else $folder_symbol = "";
    //if($GLOBALS['debug_echo']) echo "[VarLoad]" . PHP_EOL;
    include("constants.php");
    $path = getcwd().$foldername.$folder_symbol; //if($GLOBALS['debug_echo']) echo "PATH: $path" . PHP_EOL;
    //Make sure the file exists
    if (!file_exists($path.$filename)) {
        return null;
    }
    //Load a variable from a file
    $loadedvar = file_get_contents($path.$filename); //if($GLOBALS['debug_echo']) echo "FULL PATH: $path$filename" . PHP_EOL;
    $unserialized = unserialize($loadedvar);
    return $unserialized;
}

function VarDelete($foldername, $filename)
{
    if ($foldername !== null) {
        $folder_symbol = "/";
    }  else $folder_symbol = "";
    if($GLOBALS['debug_echo']) echo "VarDelete" . PHP_EOL;
    include("constants.php");
    $path = getcwd().$foldername.$folder_symbol.$filename; //if($GLOBALS['debug_echo']) echo "PATH: $path" . PHP_EOL;
    //Make sure the file exists first
    if (CheckFile($foldername, $filename)) {
        //Delete the file
        unlink($path);
        clearstatcache();
    } else {
        if($GLOBALS['debug_echo']) echo "NO FILE TO DELETE" . PHP_EOL;
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
{ //if($GLOBALS['debug_echo']) echo "foldername, filename: $foldername, $filename" . PHP_EOL;
    include("constants.php");
    $then = VarLoad($foldername, $filename); //instance of now;
    //if($GLOBALS['debug_echo']) echo "then: " . PHP_EOL; var_dump ($then) . PHP_EOL;
    //check if file exists
    if ($then) {
        $sincetime = date_diff($now, $then);
        $timecompare['y'] = $sinceYear 		= $sincetime->y;
        $timecompare['m'] = $sinceMonth 	= $sincetime->m;
        $timecompare['d'] = $sinceDay 		= $sincetime->d;
        $timecompare['h'] = $sinceHour 		= $sincetime->h;
        $timecompare['i'] = $sinceMinute 	= $sincetime->i;
        $timecompare['s'] = $sinceSecond 	= $sincetime->s;
        if($GLOBALS['debug_echo']) echo 'Timer found to compare!' . PHP_EOL;
        return $timecompare;
    } else {
        //File not found, so return 0's
        $sincetime = date_diff($now, $now);
        $timecompare['y'] = $sinceYear 		= ($sincetime->y)+1; //Assume one year has passed, enough time to avoid any cooldown
        $timecompare['m'] = $sinceMonth 	= $sincetime->m;
        $timecompare['d'] = $sinceDay 		= $sincetime->d;
        $timecompare['h'] = $sinceHour 		= $sincetime->h;
        $timecompare['i'] = $sinceMinute 	= $sincetime->i;
        $timecompare['s'] = $sinceSecond 	= $sincetime->s;
        if($GLOBALS['debug_echo']) echo 'Timer not found to compare!' . PHP_EOL;
        //if($GLOBALS['debug_echo']) echo "timecompare: " . PHP_EOL; var_dump($timecompare) . PHP_EOL;
        return $timecompare;
    }
    //if($GLOBALS['debug_echo']) echo 'Timer not found to compare!' . PHP_EOL;
}


function TimeCompareMem($author_id, $variable)
{ //if($GLOBALS['debug_echo']) echo "foldername, filename: $foldername, $filename" . PHP_EOL;
    include("constants.php");
    //$then = VarLoad($foldername, $filename); //instance of now;
    $varname = $author_id . $variable . "_cooldown"; //Check this
    $then = $GLOBALS["$varname"];
    //if($GLOBALS['debug_echo']) echo "then: " . PHP_EOL; var_dump ($then) . PHP_EOL;
    //check if file exists
    if ($then) {
        $sincetime = date_diff($now, $then);
        $timecompare['y'] = $sinceYear 		= $sincetime->y;
        $timecompare['m'] = $sinceMonth 	= $sincetime->m;
        $timecompare['d'] = $sinceDay 		= $sincetime->d;
        $timecompare['h'] = $sinceHour 		= $sincetime->h;
        $timecompare['i'] = $sinceMinute 	= $sincetime->i;
        $timecompare['s'] = $sinceSecond 	= $sincetime->s;
        if($GLOBALS['debug_echo']) echo 'Timer found to compare!' . PHP_EOL;
        return $timecompare;
    } else {
        //File not found, so return 0's
        $sincetime = date_diff($now, $now);
        $timecompare['y'] = $sinceYear 		= ($sincetime->y)+1; //Assume one year has passed, enough time to avoid any cooldown
        $timecompare['m'] = $sinceMonth 	= $sincetime->m;
        $timecompare['d'] = $sinceDay 		= $sincetime->d;
        $timecompare['h'] = $sinceHour 		= $sincetime->h;
        $timecompare['i'] = $sinceMinute 	= $sincetime->i;
        $timecompare['s'] = $sinceSecond 	= $sincetime->s;
        if($GLOBALS['debug_echo']) echo 'Timer not found to compare!' . PHP_EOL;
        //if($GLOBALS['debug_echo']) echo "timecompare: " . PHP_EOL; var_dump($timecompare) . PHP_EOL;
        return $timecompare;
    }
    //if($GLOBALS['debug_echo']) echo 'Timer not found to compare!' . PHP_EOL;
}

function TimeLimitCheck($time, $y, $m, $d, $h, $i, $s)
{
    //if($GLOBALS['debug_echo']) echo "time['s']: " . $time['s'] . PHP_EOL;
    if (!$time) {
        return true;
    } //Nothing to check, assume true
    if (!$y) {
        $y = 0;
    }//if($GLOBALS['debug_echo']) echo '$y: ' . $s . PHP_EOL;
    if (!$m) {
        $m = 0;
    }//if($GLOBALS['debug_echo']) echo '$m: ' . $s . PHP_EOL;
    if (!$d) {
        $d = 0;
    }//if($GLOBALS['debug_echo']) echo '$d: ' . $s . PHP_EOL;
    if (!$h) {
        $h = 0;
    }//if($GLOBALS['debug_echo']) echo '$h: ' . $s . PHP_EOL;
    if (!$i) {
        $i = 0;
    }//if($GLOBALS['debug_echo']) echo '$i: ' . $s . PHP_EOL;
    if (!$s) {
        $s = 0;
    }//if($GLOBALS['debug_echo']) echo '$s: ' . $s . PHP_EOL;
    //if($GLOBALS['debug_echo']) echo "time['y'] " . $time['y'] . PHP_EOL;
    //if($GLOBALS['debug_echo']) echo "time['m'] " . $time['m'] . PHP_EOL;
    //if($GLOBALS['debug_echo']) echo "time['d'] " . $time['d'] . PHP_EOL;
    //if($GLOBALS['debug_echo']) echo "time['h'] " . $time['h'] . PHP_EOL;
    //if($GLOBALS['debug_echo']) echo "time['i'] " . $time['i'] . PHP_EOL;
    //if($GLOBALS['debug_echo']) echo "time['s'] " . $time['s'] . PHP_EOL;
    //Calculate total number of seconds needed to continue.
    $required_time =
    ($s) +
    ($i * 60) +
    ($h * 3600) +
    ($d * 86400) +
    ($m * 2629746) +
    ($y * 31556952);
    //if($GLOBALS['debug_echo']) echo 'required_time: ' . $required_time . PHP_EOL;
    //Calculate total number of seconds passed.
    $passed_time =
    ($time['s']) +
    ($time['i'] * 60) +
    ($time['h'] * 3600) +
    ($time['d'] * 86400) +
    ($time['m'] * 2629746) +
    ($time['y'] * 31556952);
    //if($GLOBALS['debug_echo']) echo 'passed_time: ' . $passed_time . PHP_EOL;
    $return_array = array();
    if ($passed_time > $required_time) {
        $return_array[0] = true;
    } else {
        $return_array[0] = false;
    }
    $return_array[1] = $passed_time;
    return $return_array;
}

function PassedTimeCheck($y, $m, $d, $h, $i, $s)
{
    if (!$y) {
        $y = 0;
    }//if($GLOBALS['debug_echo']) echo '$y: ' . $s . PHP_EOL;
    if (!$m) {
        $m = 0;
    }//if($GLOBALS['debug_echo']) echo '$m: ' . $s . PHP_EOL;
    if (!$d) {
        $d = 0;
    }//if($GLOBALS['debug_echo']) echo '$d: ' . $s . PHP_EOL;
    if (!$h) {
        $h = 0;
    }//if($GLOBALS['debug_echo']) echo '$h: ' . $s . PHP_EOL;
    if (!$i) {
        $i = 0;
    }//if($GLOBALS['debug_echo']) echo '$i: ' . $s . PHP_EOL;
    if (!$s) {
        $s = 0;
    }//if($GLOBALS['debug_echo']) echo '$s: ' . $s . PHP_EOL;
    //Calculate total number of seconds passed.
    $passed_time =
    ($s) +
    ($i * 60) +
    ($h * 3600) +
    ($d * 86400) +
    ($m * 2629746) +
    ($y * 31556952);
    //if($GLOBALS['debug_echo']) echo 'passed_time: ' . $passed_time . PHP_EOL;
    if ($passed_time != 0) {
        return $passed_time;
    }
}

function CheckCooldown($foldername, $filename, $limit_array)
{
    if($GLOBALS['debug_echo']) echo "CHECK COOLDOWN" . PHP_EOL;
    //if($GLOBALS['debug_echo']) echo "limit_array: " . PHP_EOL; var_dump ($limit_array) . PHP_EOL;
    $TimeCompare = TimeCompare($foldername, $filename);
    //if($GLOBALS['debug_echo']) echo "TimeCompare: " . PHP_EOL; var_dump ($TimeCompare) . PHP_EOL;
    //$timetopass = $timelimitcheck[0]; //True/False, whether enough time has passed
    //$timetopass = $timelimitcheck[1]; //total # of seconds
    if ($TimeCompare) {
        $TimeLimitCheck = TimeLimitCheck($TimeCompare, $limit_array['year'], $limit_array['month'], $limit_array['day'], $limit_array['hour'], $limit_array['min'], $limit_array['sec']);
        //if($GLOBALS['debug_echo']) echo "TimeLimitCheck: " . PHP_EOL; var_dump ($TimeLimitCheck) . PHP_EOL;
        return $TimeLimitCheck;
    } else { //File was not found, so assume the check passes because they haven't used it before
        $TimeLimitCheck = array();
        $TimeLimitCheck[] = true;
        $TimeLimitCheck[] = 0;
        return $TimeLimitCheck;
    }
}

function CheckCooldownMem($author_id, $variable, $limit_array)
{
    if($GLOBALS['debug_echo']) echo "[CHECK COOLDOWN]" . PHP_EOL;
    //if($GLOBALS['debug_echo']) echo "limit_array: " . PHP_EOL; var_dump ($limit_array) . PHP_EOL;
    $TimeCompare = TimeCompareMem($author_id, $variable);
    //if($GLOBALS['debug_echo']) echo "TimeCompare: " . PHP_EOL; var_dump ($TimeCompare) . PHP_EOL;
    //$timetopass = $timelimitcheck[0]; //True/False, whether enough time has passed
    //$timetopass = $timelimitcheck[1]; //total # of seconds
    if ($TimeCompare) {
        $TimeLimitCheck = TimeLimitCheck($TimeCompare, $limit_array['year'], $limit_array['month'], $limit_array['day'], $limit_array['hour'], $limit_array['min'], $limit_array['sec']);
        //if($GLOBALS['debug_echo']) echo "TimeLimitCheck: " . PHP_EOL; var_dump ($TimeLimitCheck) . PHP_EOL;
        return $TimeLimitCheck;
    } else { //File was not found, so assume the check passes because they haven't used it before
        $TimeLimitCheck = array();
        $TimeLimitCheck[] = true;
        $TimeLimitCheck[] = 0;
        return $TimeLimitCheck;
    }
}

function SetCooldown($foldername, $filename)
{
    if($GLOBALS['debug_echo']) echo "SET COOLDOWN" . PHP_EOL;
    if ($foldername !== null) {
        $folder_symbol = "/";
    }  else $folder_symbol = "";
    include("constants.php");
    $path = getcwd().$foldername.$folder_symbol; //if($GLOBALS['debug_echo']) echo "PATH: $path" . PHP_EOL;
    $now = new DateTime();
    VarSave($foldername, $filename, $now);
}

function SetCooldownMem($author_id, $variable)
{
    if($GLOBALS['debug_echo']) echo "[SET COOLDOWN]" . PHP_EOL;
    $now = new DateTime();
    $varname = $author_id . $variable . "_cooldown";
    $GLOBALS["$varname"] = $now;
}

function FormatTime($seconds)
{
    //compare time
    $dtF = new \DateTime('@0');
    $dtT = new \DateTime("@$seconds");
    //ymdhis
    $formatted = $dtF->diff($dtT)->format(' %y years, %m months, %d days, %h hours, %i minutes and %s seconds');
    //if($GLOBALS['debug_echo']) echo "formatted: " . $formatted . PHP_EOL;
    //remove 0 values
    $formatted = str_replace(" 0 years,", "", $formatted);
    $formatted = str_replace(" 0 months,", "", $formatted);
    $formatted = str_replace(" 0 days,", "", $formatted);
    $formatted = str_replace(" 0 hours,", "", $formatted);
    $formatted = str_replace(" 0 minutes and", "", $formatted);
    $formatted = str_replace(" 0 seconds,", "", $formatted);
    $formatted = trim($formatted);
    //if($GLOBALS['debug_echo']) echo "new formatted: " . $formatted . PHP_EOL;
    return $formatted;
}

function TimeArrayToSeconds($array)
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
    //if($GLOBALS['debug_echo']) echo "array[1] = " . $array[1] . PHP_EOL;
    //if($GLOBALS['debug_echo']) echo "array[2] = " . $array[2] . PHP_EOL;
    $size = count($array); //if($GLOBALS['debug_echo']) echo "Array size $size" . PHP_EOL;
    //Exit conditions
    if ( is_object($array[0]) && get_class($array[0]) != "Discord\Parts\Guild\Guild") {
        //if($GLOBALS['debug_echo']) echo "No guild passed!" . PHP_EOL;
        if (is_numeric($array[0])) {
            //Try to get the guild by ID
            //if($GLOBALS['debug_echo']) echo "Guild not found when searching by ID!" . PHP_EOL;
            return false; //Not yet implemented
        } else {
            if($GLOBALS['debug_echo']) echo "No guild variable passed!" . PHP_EOL;
            return false; //Check if guild variable was passed
        }
    }
    $guild = $array[0];
    //if($GLOBALS['debug_echo']) echo "Guild was passed!" . PHP_EOL;
    
    $option = null;
    $filter = null;
    $value = $array[1];
    switch ($size) {
        case 5:
            //Check if an instance of restcord
            if ( is_object($array[4]) && get_class($array[4]) == "RestCord\DiscordClient") {
                //if($GLOBALS['debug_echo']) echo "Restcord included!" . PHP_EOL;
                $restcord = &$array[4];
            } else {
                if($GLOBALS['debug_echo']) echo "Parameter isn't a valid instance of Restcord!" . PHP_EOL;
            }
            // no break
        case 4:
            if (is_numeric($array[3])) {
                $option = $array[3]; //if($GLOBALS['debug_echo']) echo "Option included!" . PHP_EOL;
            }
            // no break
        case 3:
            $filter = $array[2];
            if ($filter) {
                //if($GLOBALS['debug_echo']) echo "Filter included!" . PHP_EOL;
                $value = str_replace($filter, "", $array[1]); //if($GLOBALS['debug_echo']) echo "value: $value" . PHP_EOL;
            }
            // no break
        case 2:
            break;
        case 0:
        case 1:
        default:
            if($GLOBALS['debug_echo']) echo "Unexpected amount of parameters!" . PHP_EOL;
            return false;
    }
    
    //Explode the string into an array
    
    $value = str_replace("<@!", "", $value);
    $value = str_replace("<@", "", $value); // if($GLOBALS['debug_echo']) echo "line: $line" . PHP_EOL;
    $value = str_replace(">", "", $value); //if($GLOBALS['debug_echo']) echo "line: $line" . PHP_EOL;
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
        if($GLOBALS['debug_echo']) echo "ID array empty!" . PHP_EOL;
        return false;
    }
    
    $return_array = array();
    foreach ($id_array as $id) {
        $value = str_replace($id, "", $value);
        //if($GLOBALS['debug_echo']) echo "Option $option" . PHP_EOL;
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
                //if($GLOBALS['debug_echo']) echo "Built return_array!" . PHP_EOL;
                break;
        }
    }
    $return_array['string']['string'] = trim($value) ?? false;
    return $return_array;
}


function appendImages($array)
{
    if (!(is_array($array))) {
        return false;
    }
    if (empty($array)) {
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
    $webpath = "https://www.valzargaming.com/cache/" . $img_rand;
    return $webpath;
}
