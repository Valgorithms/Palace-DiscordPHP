<?php
//if($GLOBALS['debug_echo']) echo '[SLASH INIT]' . PHP_EOL;
// creates clobal commands, needs to be saved only once
/*
$discord->application->commands->freshen()->done(
	function ($commands) use ($discord) {
		$command = new Discord\Parts\Interactions\Command\Command($discord, [
				'name' => 'invite',
				'description' => 'Bot invite link'
		]);
		if (! $commands->get('name', 'invite')) {
			$commands->save($command);
		}
	}
);

// creates guild specific commands, needs to be saved only once
$discord->guilds['468979034571931648']->commands->freshen()->done(
	function ($commands) use ($discord) {
        //if ($command = $commands->get('name', 'players')) $commands->delete($command->id);
		if (! $commands->get('name', 'players')) {
			$command = new Discord\Parts\Interactions\Command\Command($discord, [
				'name' => 'players',
				'description' => 'Show Civ13 and Blue Colony server information'
			]);
			$commands->save($command);
		}
	}
);

$discord->guilds['807759102624792576']->commands->freshen()->done(
	function ($commands) use ($discord) {
        //if ($command = $commands->get('name', 'players')) $commands->delete($command->id);
		if (! $commands->get('name', 'players')) {
			$command = new Discord\Parts\Interactions\Command\Command($discord, [
				'name' => 'players',
				'description' => 'Show Civ13 and Blue Colony server information'
			]);
			$commands->save($command);
		}
	}
);
*/

$discord->listenCommand('invite', function ($interaction) use ($discord) {
	$interaction->respondWithMessage(Discord\Builders\MessageBuilder::new()->setContent($discord->application->getInviteURLAttribute('8')));
});

// register guild command `/players`
$discord->listenCommand('players', function ($interaction) use ($discord, $browser) {
	$browser->get('http://192.168.1.175:8080/servers/serverinfo_get.php')->done( //Hosted on the website, NOT the bot's server
		function ($response) use ($interaction, $discord) {
			//if($GLOBALS['debug_echo']) echo '[RESPONSE]' . PHP_EOL;
			include "../servers/serverinfo.php"; //$servers[1]["key"] = address / alias / port / servername
			//if($GLOBALS['debug_echo']) echo '[RESPONSE SERVERINFO INCLUDED]' . PHP_EOL;
			$string = var_export((string)$response->getBody(), true);
			
			$data_json = json_decode($response->getBody());
			$desc_string_array = array();
			$desc_string = "";
			$server_state = array();
			foreach ($data_json as $varname => $varvalue){ //individual servers
				$varvalue = json_encode($varvalue);
				////if($GLOBALS['debug_echo']) echo "varname: " . $varname . PHP_EOL; //Index
				////if($GLOBALS['debug_echo']) echo "varvalue: " . $varvalue . PHP_EOL; //Json
				$server_state["$varname"] = $varvalue;
				
				$desc_string = $desc_string . $varname . ": " . urldecode($varvalue) . "\n";
				////if($GLOBALS['debug_echo']) echo "desc_string length: " . strlen($desc_string) . PHP_EOL;
				////if($GLOBALS['debug_echo']) echo "desc_string: " . $desc_string . PHP_EOL;
				$desc_string_array[] = $desc_string ?? "null";
				$desc_string = "";
			}
			
			$server_index[0] = "TDM" . PHP_EOL;
			$server_url[0] = "byond://51.254.161.128:1714";
			$server_index[1] = "Nomads" . PHP_EOL;
			$server_url[1] = "byond://51.254.161.128:1715";
			$server_index[2] = "Persistence" . PHP_EOL;
			$server_url[2] = "byond://69.140.47.22:1717";
			$server_index[3] = "Blue Colony" . PHP_EOL;
			$server_url[3] = "byond://69.140.47.22:7777";
			$server_state_dump = array(); // new assoc array for use with the embed
			
			$embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
			foreach ($server_index as $index => $servername){
				//if($GLOBALS['debug_echo']) echo "server_index key: $index";
				$assocArray = json_decode($server_state[$index], true);
				foreach ($assocArray as $key => $value){
					$value = urldecode($value);
					////if($GLOBALS['debug_echo']) echo "$key:$value" . PHP_EOL;
					$playerlist = "";
					if($key/* && $value && ($value != "unknown")*/)
						switch($key){
							case "version": //First key if online
								//$server_state_dump[$index]["Status"] = "Online";
								$server_state_dump[$index]["Server"] = "<" . $server_url[$index] . "> " . PHP_EOL . $server_index[$index]/* . " **(Online)**"*/;
								break;
							case "ERROR": //First key if offline
								//$server_state_dump[$index]["Status"] = "Offline";
								$server_state_dump[$index]["Server"] = "" . $server_url[$index] . " " . PHP_EOL . $server_index[$index] . " (Offline)"; //Don't show offline
								break;
							case "host":
								if( ($value == NULL) || ($value == "") ){
									$server_state_dump[$index]["Host"] = "Taislin";
								}elseif (strpos($value, 'Guest')!==false) { //Byond wasn't logged in at server start
									$server_state_dump[$index]["Host"] = "ValZarGaming";
								}else $server_state_dump[$index]["Host"] = $value;
								break;
							/*case "players":
								$server_state_dump[$index]["Player Count"] = $value;
								break;*/
							case "age":
								//"Epoch", urldecode($serverinfo[0]["Epoch"])
								$server_state_dump[$index]["Epoch"] = $value;
								break;
							case "season":
								//"Season", urldecode($serverinfo[0]["Season"])
								$server_state_dump[$index]["Season"] = $value;
								break;
							case "map":
								//"Map", urldecode($serverinfo[0]["Map"]);
								$server_state_dump[$index]["Map"] = $value;
								break;
							case "roundduration":
								$rd = explode (":", $value);
								$remainder = ($rd[0] % 24);
								$rd[0] = floor($rd[0] / 24);
								if( ($rd[0] != 0) || ($remainder != 0) || ($rd[1] != 0) ){ //Round is starting
									$rt = $rd[0] . "d " . $remainder . "h " . $rd[1] . "m";
								}else{
									$rt = null; //"STARTING";
								}
								$server_state_dump[$index]["Round Time"] = $rt;
								//
								break;
							case "stationtime":
								$rd = explode (":", $value);
								$remainder = ($rd[0] % 24);
								$rd[0] = floor($rd[0] / 24);
								if( ($rd[0] != 0) || ($remainder != 0) || ($rd[1] != 0) ){ //Round is starting
									$rt = $rd[0] . "d " . $remainder . "h " . $rd[1] . "m";
								}else{
									$rt = null; //"STARTING";
								}
								//$server_state_dump[$index]["Station Time"] = $rt;
								break;
							case "cachetime":
								$server_state_dump[$index]["Cache Time"] = gmdate("F j, Y, g:i a", $value) . " GMT";
							default:
								if ((substr($key, 0, 6) == "player") && ($key != "players") ){
									$server_state_dump[$index]["Players"][] = $value;
									//$playerlist = $playerlist . "$varvalue, ";
									//"Players", urldecode($serverinfo[0]["players"])
								}
								break;
						}
				}
			}
			//Build the embed message
			////if($GLOBALS['debug_echo']) echo "server_state_dump count:" . count($server_state_dump) . PHP_EOL;
			foreach ($server_index as $x => $temp){
				////if($GLOBALS['debug_echo']) echo "x: " . $x . PHP_EOL;
				if(is_array($server_state_dump[$x]))
				foreach ($server_state_dump[$x] as $key => $value){ //Status / Byond / Host / Player Count / Epoch / Season / Map / Round Time / Station Time / Players
					if($key && $value)
					if(is_array($value)){
						$output_string = implode(', ', $value);
						$embed->addFieldValues($key . " (" . count($value) . ")", $output_string, true);
					}elseif($key == "Host"){
						if(strpos($value, "(Offline") == false)
						$embed->addFieldValues($key, $value, true);
					}elseif($key == "Cache Time"){
						//$embed->addFieldValues($key, $value, true);
					}elseif($key == "Server"){
						$embed->addFieldValues($key, $value, false);
					}else{
						$embed->addFieldValues($key, $value, true);
					}
				}
			}
			//if($GLOBALS['debug_echo']) echo '[RESPONSE FOR LOOP DONE]' . PHP_EOL;
			//Finalize the embed
			$embed
				->setColor(0xe1452d)
				->setTimestamp()
				->setFooter("Palace Bot by Valithor#5947")
				->setURL("");
			
			//if($GLOBALS['debug_echo']) echo '[SEND EMBED]' . PHP_EOL;
			$message = Discord\Builders\MessageBuilder::new()
				->setContent('Players')
				->addEmbed($embed);
			$interaction->respondWithMessage($message)->done(
			function ($success){
			}, function ($error) {
				var_dump($error);
			});
		}, function ($error) use ($interaction, $discord) {
			//if($GLOBALS['debug_echo']) echo '[INTERACTION FAILED]' . PHP_EOL;
			$discord->getChannel('315259546308444160')->sendMessage('<@116927250145869826>, Webserver is down! <#' . $interaction->channel->id . '>' ); //Alert Valithor
			//$interaction->acknowledge(); // acknowledges the message and show source message
		},
    );
});

/*
// register guild command `/palace-test`
$discord->listenCommand('palace-test', function ($interaction) {
	$choices = $interaction->data->options;
	//if($GLOBALS['debug_echo']) echo 'Interactions: ' . PHP_EOL;
	var_dump($interaction);
	//if($GLOBALS['debug_echo']) echo PHP_EOL;
	
	//if($GLOBALS['debug_echo']) echo 'Choices: ' . PHP_EOL;
	var_dump($choices);
	//if($GLOBALS['debug_echo']) echo PHP_EOL;	
	$guild = $interaction->guild;
    $channel = $interaction->channel;
    $member = $interaction->member;
    // do some cool stuff here
    // good idea to var_dump interaction and choices to see what they contain
	
	

    // once finished, you MUST either acknowledge or reply to a message
    //$interaction->acknowledge(); // acknowledges the message and shows source message
    $interaction->respondWithMessage(Discord\Builders\MessageBuilder::new()->setContent('Hello, world!')); // replies to the message and shows the source message
});
*/