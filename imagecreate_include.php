<?php
//This code is dynamically driven by run.php and cannot be encapsulated into a function
if (php_sapi_name() == "cli") {
    //if($GLOBALS['debug_echo']) echo "CLI" . PHP_EOL;
} else { //Browser
    return true;
}

//Create the image
if($GLOBALS['debug_echo']) echo "CREATE CACHED IMAGE" . PHP_EOL;
$my_img = imagecreate(300, 80);
imagesetthickness($my_img, 1);

$background 	= imagecolorallocate($my_img, 47, 49, 54); //First call to this function is always the background
$text_colour 	= imagecolorallocate($my_img, 255, 255, 0);
$line_colour 	= imagecolorallocate($my_img, 167, 197, 253);
imagestring($my_img, 4, 30, 25, $author_username, $text_colour);
imageline($my_img, 30, 45, 260, 45, $line_colour);

/*
// Create image instances
$dest = imagecreatefromgif('php.gif');
$src = imagecreatefromgif('php.gif');

// Copy and merge
imagecopymerge($dest, $src, 10, 10, 0, 0, 100, 47, 75);

// Output and free from memory
header('Content-Type: image/gif');
imagegif($dest);

imagedestroy($dest);
imagedestroy($src);
*/

//Save the image

$img_rand = rand(0, 99999999999); //Some big number to make the URLs unique because Discord caches image links
$img_dir_path = getcwd() . "\\";
$cache_folder = "cache\\" . $author_id . "\\";
CheckDir($cache_folder);
$full_folder_path = $img_dir_path . $cache_folder; //if($GLOBALS['debug_echo']) echo "full_folder_path: " . $full_folder_path . PHP_EOL;

//Delete old images before creating the new one
//if($GLOBALS['debug_echo']) echo "DELETING OLD IMAGES" . PHP_EOL;
$files = glob($full_folder_path . "*"); //Get all file names
foreach ($files as $file) { //Iterate files
  if (is_file($file)) {
      unlink($file);
  } //Delete file
}
clearstatcache();

//Path given to embed
$img_output_path =  $cache_folder . $img_rand . "cachedimage.png";

//Save the file
//if($GLOBALS['debug_echo']) echo "SAVING NEW IMAGE" . PHP_EOL;
imagepng($my_img, $img_dir_path . $img_output_path);


// \CharlotteDunois\Yasmin\Utils\DataHelpers::makeBase64URI($file),
