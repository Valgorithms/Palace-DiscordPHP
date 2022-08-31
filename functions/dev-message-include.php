<?php
if ($message_content_lower == 'permissions') { //;permissions
	ob_start();
	$botmember = $author_guild->members->get('id', $discord->id);
	var_dump($botmember);
	$debug_output = ob_get_contents();
	ob_end_clean(); //here, output is cleaned. You may want to flush it with ob_end_flush()
	file_put_contents('botmember.txt', $debug_output);
	ob_end_flush();
	
	ob_start();
	$botperms = $botmember->getPermissions();
	var_dump($botperms);
	$debug_output = ob_get_contents();
	ob_end_clean(); //here, output is cleaned. You may want to flush it with ob_end_flush()
	file_put_contents('botperms.txt', $debug_output);
	ob_end_flush();
}
if ($message_content_lower == 'debug') { //;debug
	if($GLOBALS['debug_echo']) echo '[DEBUG]' . PHP_EOL;
	ob_start();
	
	//if($GLOBALS['debug_echo']) echo print_r(get_defined_vars(), true); //REALLY REALLY BAD IDEA
	print_r(get_defined_constants(true));
	
	$debug_output = ob_get_contents();
	ob_end_clean(); //here, output is cleaned. You may want to flush it with ob_end_flush()
	file_put_contents('debug.txt', $debug_output);
	ob_end_flush();
}
if ($message_content_lower == 'builder') { //;button
	/* Discord\Builders\Components\*
	addComponent($component)	adds a component to action row. must be a button component.
	removeComponent($component)	removes the given component from the action row.
	getComponents(): Component[]	returns all the action row components in an array.
	*/
	$builder = Discord\Builders\MessageBuilder::new();
	
	$row = Discord\Builders\Components\ActionRow::new();
	$button = Discord\Builders\Components\Button::new(Discord\Builders\Components\Button::STYLE_SUCCESS);
	$button->setLabel('Push me!');
	$button->setListener(function (Discord\Parts\Interactions\Interaction $interaction) {
		$interaction->respondWithMessage(Discord\Builders\MessageBuilder::new()
			->setContent("Why'd u push me?"));
	}, $discord);
	$row->addComponent($button);
	$builder->addComponent($row);
	//promise for delay
	/*
	$button->setListener(function (Discord\Parts\Interactions\Interaction $interaction) use ($discord) {
		return someFunctionWhichWillReturnAPromise()->then(function ($returnValueFromFunction) use ($interaction) {
			$interaction->respondWithMessage(Discord\Builders\MessageBuilder::new()
				->setContent($returnValueFromFunction));
		});
	}, $discord);
	*/
	
	$select = Discord\Builders\Components\SelectMenu::new()
		->addOption(Discord\Builders\Components\Option::new('me?'))
		->addOption(Discord\Builders\Components\Option::new('or me?'));
	
	$select->setListener(function (Discord\Parts\Interactions\Interaction $interaction, Discord\Helpers\Collection $options) {
		foreach ($options as $option) {
			echo $option->getValue().PHP_EOL;
		}

		$interaction->respondWithMessage(Discord\Builders\MessageBuilder::new()->setContent('Thanks!'));
	}, $discord);
	$builder->addComponent($select);
	
	$builder->setContent('Hello, world!');
	$builder->setReplyTo($message);
	$message->channel->sendMessage($builder);
}
if ($message_content_lower == 'stats') { //;stats
	$stats->handle($message);
}
if ($message_content_lower == 'jit') { //;jit
	var_dump(opcache_get_status()['jit']);
}
if ($message_content_lower == 'debug invite') { //;debuginvite
	$author_channel->createInvite([
		'max_age' => 60, // 1 minute
		'max_uses' => 5, // 5 uses
	])->done(function ($invite) use ($author_user, $author_channel) {
		$url = 'https://discord.gg/' . $invite->code;
		$author_user->sendMessage("Invite URL: $url");
		$author_channel->sendMessage("Invite URL: $url");
	});
}
if ($message_content_lower == 'debug guild names') { //;debug all invites
	$guildstring = "";
	foreach($discord->guilds as $guild) {
		$guildstring .= "[{$guild->name} ({$guild->id}) :man::".count($guild->members)." <@{$guild->owner_id}>] \n";
	}
	foreach (str_split($guildstring, 2000) as $piece) {
		$message->channel->sendMessage($piece);
	}
}
if (str_starts_with ($message_content_lower, 'debug guild invites ')) { //;debug all invites
	$filter = 'debug guild invites ';
	$value = str_replace($filter, '', $message_content_lower);
	if (! is_numeric($value)) return $message->reply("`$value` is not a valid Guild ID!");
	if (! $guild = $discord->guilds->get('id', "$value")) return $message->reply("Unable to locate Guild with ID `$value`!");
	$find_invite = function ($guild, $message, $channels) use (&$find_invite) {
		if ($channels) {
			$find_invite_channels = function (&$channels, $message) use (&$find_invite_channels) {
				if (! $channel = array_pop($channels)) return $message->react("👎");
				$channel->getInvites()->done(function ($invites) use ($find_invite_channels, &$channels, $message) {
					if (count($invites) == 0) {
						return $find_invite_channels($channels, $message);
					} else foreach ($invites as $invite) {
						if ($invite->code) {
							$url = 'https://discord.gg/' . $invite->code;
							return $message->reply("{$guild->name} ({$guild->id}) $url");
						} else return $find_invite_channels($channels, $message);
					}
				});
			};
			return $find_invite_channels($channels, $message);
		} else $guild->getInvites()->done(function ($invites) use ($find_invite, $guild, $message) {
			if (count($invites) == 0) {
				return $find_invite($guild, $message, array_chunk($guild->channels, 550));
			}
			foreach ($invites as $invite) {
				if ($invite->code) {
					$url = 'https://discord.gg/' . $invite->code;
					return $message->reply("{$guild->name} ({$guild->id}) $url");
				}
			}
		});
	};
	return $find_invite($guild, $message, null);
}
if (str_starts_with($message_content_lower, 'debug guild invite ')) { //;debug guild invite guildid
	$filter = "debug guild invite ";
	$value = str_replace($filter, "", $message_content_lower);
	if($GLOBALS['debug_echo']) echo "[DEBUG GUILD INVITE] `$value`" . PHP_EOL;
	if ($guild = $discord->guilds->get('id', $value)) {
		if ($guild->vanity_url_code) {
			if($GLOBALS['debug_echo']) echo "[VANITY INVITE EXISTS] `$value`" . PHP_EOL;
			$message->react("👍");
			$url = 'https://discord.gg/' . $guild->vanity_url_code;
			$message->channel->sendMessage("{$guild->name} ({$guild->id}) $url");
			return;
		}
		if ( ($bot_member = $guild->members->get('id', $discord->id)) && ($bot_perms = $bot_member->getPermissions()) && $bot_perms['manage_guild']) {
			foreach ($guild->invites as $invite) {
				if ($invite->code) {
					$url = 'https://discord.gg/' . $invite->code;
					$message->channel->sendMessage("{$guild->name} ({$guild->id}) $url");
					return;
				}
			}
		}
		foreach($guild->channels as $channel) {
			if($channel->type != 4) {
				$channel->createInvite([
					'max_age' => 60, // 1 minute
					'max_uses' => 1, // 1 use
				])->then(
					function ($invite) use ($message, $guild) {
						$message->react("👍");
						$url = 'https://discord.gg/' . $invite->code;
						$message->channel->sendMessage("{$guild->name} ({$guild->id}) $url");
					},
					function ($error) use ($message, $guild) {
						$message->react("👎");
						$message->channel->sendMessage("Unable to create guild invite for guild ID {$guild->id}!");
					}
				);
				break;
			}
		}
	} else $message->react('❌'); //Guild is not in repository
	return;
}
if ($message_content_lower == 'guildcount') {
	$message->channel->sendMessage(count($discord->guilds));
}
if (str_starts_with($message_content_lower, 'debug guild leave ')) { //;debug guild leave guildid
	$filter = "debug guild leave ";
	$value = str_replace($filter, "", $message_content_lower);
	$discord->guilds->leave($value)->done(
		function ($result) use ($message) {
			$message->react("👍");
		},
		function ($error) use ($message) {
			$message->react("👎");
		}
	);
}		
if (str_starts_with($message_content_lower, 'debug guild create')) { //;debug guild create
	return; //Only works for bots that are in less than 10 guilds
	if($GLOBALS['debug_echo']) echo '[DEBUG GUILD CREATE]' . PHP_EOL;
	/*
	$guild = $discord->factory(\Discord\Parts\Guild\Guild::class);
	$guild->name = 'Doll House';
	*/
	$guild_temp = $discord->guilds->create([
		'name' => 'Test Server',
	]);
	$discord->guilds->save($guild_temp)->then( //Fails
		function ($new_guild) use ($author_user) {
			$new_guild->channels->freshen()->then(
				function () use ($author_user, $channel, $new_guild) {
					foreach($new_guild->channels as $channel_new) {
						$channel_new->createInvite([
							'max_age' => 60, // 1 minute
							'max_uses' => 5, // 5 uses
						])->then(
							function ($invite) use ($author_user, $channel) {
								$url = 'https://discord.gg/' . $invite->code;
								$author_user->sendMessage("Invite URL: $url");
								$channel->sendMessage("Invite URL: $url");
							},
							function ($error) {
								ob_flush();
								ob_start();
								var_dump($error);
								file_put_contents("error_invite.txt", ob_get_flush());
							}
						);
					}
				},
				function ($error) {
					ob_flush();
					ob_start();
					var_dump($error);
					file_put_contents("error_guild_create_2.txt", ob_get_flush());
				}
			);
		},
		function ($error) {
			ob_flush();
			ob_start();
			var_dump($error);
			file_put_contents("error_guild_create_1.txt", ob_get_flush());
		}
	);
}
if ($message_content_lower == 'debug react') { //;debug react
	$message->react("👍");
	$message->react("👍");
	$message->react("👍");
	$message->react("👍");
	$message->react("👍");
	$message->react("👍");
	$message->react("👍");
	$message->react("👍");
}		
if ($message_content_lower == 'debug ping') { //;debug ping
	$message->channel->sendMessage("Pong!");
	$message->channel->sendMessage("Pong!");
	$message->channel->sendMessage("Pong!");
	$message->channel->sendMessage("Pong!");
	$message->channel->sendMessage("Pong!");
	$message->channel->sendMessage("Pong!");
	$message->channel->sendMessage("Pong!");
	$message->channel->sendMessage("Pong!");
	$message->channel->sendMessage("Pong!");
	$message->channel->sendMessage("Pong!");
}
if (str_starts_with($message_content_lower, 'mention')) { //;mention
	//Get an array of people mentioned
	$GetMentionResult = GetMention([&$author_guild, substr($message_content_lower, 8, strlen($message_content_lower)), null, 1, &$restcord]);
	if (!$GetMentionResult) return $message->reply("Invalid input! Please enter a valid ID or @mention the user");
	
	$output_string = "Mentions IDs: ";
	$keys = array_keys($GetMentionResult);
	for ($i = 0; $i < count($GetMentionResult); $i++) {
		if (is_numeric($keys[$i])) {
			$output_string = $output_string . " " . $keys[$i];
		} else {
			foreach ($GetMentionResult[$keys[$i]] as $key => $value) {
				$clean_string = $value;
			}
		}
	}
	$output_string = $output_string  . PHP_EOL . "Clean string: " . $clean_string;
	$author_channel->sendMessage($output_string);
}
if ($message_content_lower == 'genimage') {
	include "imagecreate_include.php"; //Generates $img_output_path
	$image_path = "http://www.valzargaming.com/discord%20-%20palace/" . $img_output_path;
	//if($GLOBALS['debug_echo']) echo "image_path: " . $image_path . PHP_EOL;
	//	Build the embed message
	$embed = $discord->factory(\Discord\Parts\Embed\Embed::class);
	$embed
//		->setTitle("$author_check")																// Set a title
		->setColor(0xe1452d)																	// Set a color (the thing on the left side)
		->setDescription("$author_guild_name")									// Set a description (below title, above fields)
//		->addFieldValues("⠀", "$documentation")														// New line after this
		
		->setThumbnail("$author_avatar")														// Set a thumbnail (the image in the top right corner)
//		->setImage('https://avatars1.githubusercontent.com/u/4529744?s=460&v=4')			 	// Set an image (below everything except footer)
		->setImage("$image_path")			 													// Set an image (below everything except footer)
		->setTimestamp()																	 	// Set a timestamp (gets shown next to footer)
		->setAuthor("$author_check", "$author_guild_avatar")  									// Set an author with icon
		->setFooter("Palace Bot by Valithor#5947")							 					// Set a footer without icon
		->setURL("");							 												// Set the URL
//		Open a DM channel then send the rich embed message
	/*
	$author_user->getPrivateChannel()->done(function($author_dmchannel) use ($message, $embed) {	//Promise
		if($GLOBALS['debug_echo']) echo 'SEND GENIMAGE EMBED' . PHP_EOL;
		$author_dmchannel->sendEmbed($embed);
	});
	*/
	return $author_channel->sendEmbed($embed);
}
if ($message_content_lower == 'promote') { //;promote
	$author_member->addRole($role_dev_id)->done(
		null,
		function ($error) { //if($GLOBALS['debug_echo']) echo "role_admin_id: $role_admin_id" . PHP_EOL;
			var_dump($error->getMessage());
		}
	);
}
if ($message_content_lower == 'demote') { //;demote
	$author_member->removeRole($role_dev_id)->done(
		null, //if($GLOBALS['debug_echo']) echo "role_admin_id: $role_admin_id" . PHP_EOL;
		function ($error) {
			var_dump($error->getMessage());
		}
	);
}
if ($message_content_lower == 'processmessages') {
	//$verifylog_channel																				//TextChannel				//if($GLOBALS['debug_echo']) echo "channel_messages class: " . get_class($verifylog_channel) . PHP_EOL;
	//$author_messages = $verifylog_channel->fetchMessages(); 											//Promise
	//if($GLOBALS['debug_echo']) echo "author_messages class: " . get_class($author_messages) . PHP_EOL; 							//Promise
	$verifylog_channel->getMessageHistory()->done(function ($message_collection) use ($verifylog_channel) {	//Resolve the promise
		//$verifylog_channel and the new $message_collection can be used here
		//if($GLOBALS['debug_echo']) echo "message_collection class: " . get_class($message_collection) . PHP_EOL; 				//Collection messages
		//foreach ($message_collection as $message) {														//Model/Message				//if($GLOBALS['debug_echo']) echo "message_collection message class:" . get_class($message) . PHP_EOL;
			//DO STUFF HERE TO MESSAGES
		//}
	});
	return;
}
if ($message_content_lower == 'restart') {
	if($GLOBALS['debug_echo']) echo "[RESTART LOOP]" . PHP_EOL;
	$dt = new DateTime("now");  // convert UNIX timestamp to PHP DateTime
	if($GLOBALS['debug_echo']) echo "[TIME] " . $dt->format('d-m-Y H:i:s') . PHP_EOL; // output = 2017-01-01 00:00:00
	//$loop->stop();
	$discord_class = get_class($discord);
	//$discord->destroy();
	eval ('$discord = new '.$discord_class.'(array(), $loop);');
	$discord->login($token)->done(
		null,
		function ($error) {
			if($GLOBALS['debug_echo']) echo "[LOGIN ERROR] $error".PHP_EOL; //if($GLOBALS['debug_echo']) echo any errors
		}
	);
	//$loop->run();
	if($GLOBALS['debug_echo']) echo "[LOOP RESTARTED]" . PHP_EOL;
}
if (str_starts_with($message_content_lower, 'timer ')) { //;timer
	if($GLOBALS['debug_echo']) echo "[TIMER]" . PHP_EOL;
	$message_content_lower = substr($message_content_lower, 6);
	if (is_numeric($value)) {
		$discord->getLoop()->addTimer($value, function () use ($author_channel) {
			return $author_channel->sendMessage("Timer");
		});
	} else return $message->reply("Invalid input! Please enter a valid number");
	return;
}
if (str_starts_with($message_content_lower, 'resolveid ')) { //;timer
	if($GLOBALS['debug_echo']) echo "[RESOLVEID]" . PHP_EOL;
	$message_content_lower = substr($message_content_lower, 10);
	if (is_numeric($value)) {
		$user = $discord->users->fetch("$value");
		var_dump($user);
	}
}
if ($message_content_lower == 'xml') {
	include "xml.php";
}
if ($message_content_lower == 'backup') { //;backup
	if($GLOBALS['debug_echo']) echo "[SAVEGLOBAL]" . PHP_EOL;
	$GLOBALS["RESCUE"] = true;
	$blacklist_globals = array(
		"GLOBALS",
		"loop",
		"discord",
	);
	if($GLOBALS['debug_echo']) echo "Skipped: ";
	foreach ($GLOBALS as $key => $value) {
		$temp = array($value);
		if (!in_array($key, $blacklist_globals)) {
			try {
				VarSave("_globals", "$key.php", $value);
			} catch (Throwable $e) { //This will probably crash the bot
				if($GLOBALS['debug_echo']) echo "$key, ";
			}
		} else {
			if($GLOBALS['debug_echo']) echo "$key, ";
		}
	}
	if($GLOBALS['debug_echo']) echo PHP_EOL;
}
if ($message_content_lower == 'rescue') { //;rescue
	if($GLOBALS['debug_echo']) echo "[RESCUE]" . PHP_EOL;
	include_once "custom_functions.php";
	$rescue = VarLoad("_globals", "RESCUE.php"); //Check if recovering from a fatal crash
	if ($rescue) { //Attempt to restore crashed session
		if($GLOBALS['debug_echo']) echo "[RESCUE START]" . PHP_EOL;
		$rescue_dir = getcwd() . '/_globals';
		$rescue_vars = scandir($rescue_dir);
		foreach ($rescue_vars as $var) {
			$backup_var = VarLoad("_globals", "$var");
			
			$filter = ".php";
			$value = str_replace($filter, "", $var);
			$GLOBALS["$value"] = $backup_var;
			
			$target_dir = $rescue_dir . "/" . $var;
			if($GLOBALS['debug_echo']) echo $target_dir . PHP_EOL;
			unlink($target_dir);
		}
		VarSave("_globals", "rescue.php", false);
		if($GLOBALS['debug_echo']) echo "[RESCUE DONE]" . PHP_EOL;
	}
}
if ($message_content_lower == 'get unregistered') { //;get unregistered
	echo "[GET UNREGISTERED START]" . PHP_EOL;
	$GLOBALS["UNREGISTERED"] = null;
	foreach ($author_guild->members as $target_member) { //GuildMember
		$target_skip = false;
		//get roles of member
		$target_guildmember_role_collection = $target_member->roles;
		foreach ($target_guildmember_role_collection as $role) {
			if ($role->name == "Peasant") {
				$target_skip = true;
			}
			if ($role->name == "Bots") {
				$target_skip = true;
			}
		}
		if (!$target_skip) {
			//Query SQL for ss13 where discord =
			$target_id = $target_member->id; //if($GLOBALS['debug_echo']) echo "target_id: " . $target_id . PHP_EOL;
			include "../connect.php";
			$sqlgettargetinfo = "
				SELECT
					`ss13`
				FROM
					`users`
				WHERE
					`discord` = '$target_id'";
			if ($resultsqlgettargetinfo = mysqli_query($con, $sqlgettargetinfo)) {
				$rowselect = mysqli_fetch_array($resultsqlgettargetinfo);
				if (!$ckey = $rowselect['ss13']) {
					//if($GLOBALS['debug_echo']) echo "$target_id: No ckey found" . PHP_EOL;
					$GLOBALS["UNREGISTERED"][] = $target_id;
				} else {
					//if($GLOBALS['debug_echo']) echo "$target_id: $ckey" . PHP_EOL;
				}
			} else {
				//if($GLOBALS['debug_echo']) echo "$target_id: No registration found" . PHP_EOL;
				$GLOBALS["UNREGISTERED"][] = $target_id;
			}
		}
	}
	echo count($GLOBALS["UNREGISTERED"]) . " UNREGISTERED ACCOUNTS" . PHP_EOL;
	echo "[GET UNREGISTERED DONE]" . PHP_EOL;
	return $message->react("👍");
}
if ($message_content_lower == 'fix unverified') { //;fix unverified
	echo "[FIX UNVERIFIED]" . PHP_EOL;
	$string = "";
	foreach ($author_guild->members as $target_member) {
		$has_role = false;
		foreach($target_member->roles as $role)
			if(!is_null($role->id)) $has_role = true;
		if (!$has_role) {
			$string = $string . '<@'.$target_member->id.'> ';
			if ($author_guild->id == "468979034571931648") { //Civ13
				$target_member->addRole("469312086766518272");
			}else if($author_guild->id == "807759102624792576") { //World
				//$target_member->addRole("469312086766518272"); //This server is hopefully not the big dumb and doesn't have a "Peasent" role
			}
		}
	}
	if($string) $message->channel->sendMessage($string);
	else $message->react("👎");
	return;
}
if ($message_content_lower == 'unverify unregistered') { //;unverify unregistered
	echo "[UNVERIFY UNREGISTERED START]" . PHP_EOL;
	if ($GLOBALS["UNREGISTERED"]) {
		echo "UNREGISTERED 0: " . $GLOBALS["UNREGISTERED"][0] . PHP_EOL;
		$GLOBALS["UNREGISTERED_COUNT"] = count($GLOBALS["UNREGISTERED"]);
		echo "UNREGISTERED_COUNT: " . $GLOBALS["UNREGISTERED_COUNT"] . PHP_EOL;
		$GLOBALS["UNREGISTERED_X"] = 0;
		$GLOBALS['UNREGISTERED_TIMER'] = $loop->addPeriodicTimer(5, function () use ($discord, $loop, $author_guild_id) {
			//FIX THIS
			if ($GLOBALS["UNREGISTERED_X"] < $GLOBALS["UNREGISTERED_COUNT"]) {
				$target_id = $GLOBALS["UNREGISTERED"][$GLOBALS["UNREGISTERED_X"]]; //GuildMember
				//if($GLOBALS['debug_echo']) echo "UNREGISTERED ID: $target_id" . PHP_EOL;
				if ($target_id) {
					echo "UNVERIFYING $target_id" . PHP_EOL;
					
					$target_guild = $discord->guilds->get('id', $author_guild_id); //if($GLOBALS['debug_echo']) echo "target_guild: " . get_class($target_guild) . PHP_EOL;
					$target_member = $target_guild->members->get('id', $target_id); //if($GLOBALS['debug_echo']) echo "target_member: " . get_class($target_member) . PHP_EOL;
					$x = 0;
					switch ($author_guild_id) {
						case '468979034571931648':
							//$remove($removed_roles);
							$target_member->removeRole("468982790772228127")->done( function ($result) use ($target_member) {
								$target_member->removeRole("468983261708681216")->done( function ($result) use ($target_member) {
									$target_member->addRole("469312086766518272");
								});
							});
							break;
						case '807759102624792576':
							$target_member->removeRole("816839199906070561");
						default:
							break;
					}
					$GLOBALS["UNREGISTERED_X"] = $GLOBALS["UNREGISTERED_X"] + 1;
					return;
				} else {
					$loop->cancelTimer($GLOBALS['UNREGISTERED_TIMER']);
					$GLOBALS["UNREGISTERED_COUNT"] = null;
					$GLOBALS['UNREGISTERED_X'] = null;
					$GLOBALS['UNREGISTERED_TIMER'] = null;
					echo "[UNREGISTERED TIMER DONE]";
					return;
				}
			}
		});
		$message->react("👍");
	} else $message->react("👎");
	echo "[CHECK UNREGISTERED DONE]" . PHP_EOL;
	return;
}
if ($message_content_lower == 'get unverified') { //;get unverified
	echo "[GET UNVERIFIED START]" . PHP_EOL;
	$GLOBALS["UNVERIFIED"] = null;
	if ($author_guild->id == '468979034571931648') { //Civ13
		$author_guild->members->freshen()->done(
			function ($members) use ($message, $author_guild) {
				//$members = $fetched_guild->members->all(); //array
				foreach ($members as $target_member) { //GuildMember
					$target_skip = false;
					//get roles of member
					$target_guildmember_role_collection = $target_member->roles;
					foreach ($target_guildmember_role_collection as $role) {
						if ($role->name == "Peasant") {
							$target_get = true;
						}
						if ($role->name == "Footman") {
							$target_skip = true;
						}
						if ($role->name == "Brother At Arms") {
							$target_skip = true;
						}
						if ($role->name == "Bots") {
							$target_skip = true;
						}
						if ($role->name == "BANNED") {
							$target_skip = true;
						}
					}
					if (!$target_skip && $target_get) {
						$mention_id = $target_member->id; //if($GLOBALS['debug_echo']) echo "mention_id: " . $mention_id . PHP_EOL;
						$GLOBALS["UNVERIFIED"][] = $mention_id;
					}
				}
				$message->react("👍");
				echo count($GLOBALS["UNVERIFIED"]) . " UNVERIFIED ACCOUNTS" . PHP_EOL;
				echo "[GET UNVERIFIED DONE]" . PHP_EOL;
			}
		);
	}
	elseif ($author_guild->id == '807759102624792576') { //Blue Colony
		$author_guild->members->freshen()->done(
			function ($members) use ($message, $author_guild) {
				//$members = $fetched_guild->members->all(); //array
				foreach ($members as $target_member) { //GuildMember
					$target_skip = false;
					//get roles of member
					$target_guildmember_role_collection = $target_member->roles;
					foreach ($target_guildmember_role_collection as $role) {
						if ($role->name == "Verified") {
							$target_skip = true;
						}
						if ($role->name == "Promoted") {
							$target_skip = true;
						}
						if ($role->name == "Banned") {
							$target_skip = true;
						}
						if ($role->name == "Muted") {
							$target_skip = true;
						}
						if ($role->name == "Bots") {
							$target_skip = true;
						}
					}
					if (!$target_skip && $target_get) {
						$mention_id = $target_member->id; //if($GLOBALS['debug_echo']) echo "mention_id: " . $mention_id . PHP_EOL;
						$GLOBALS["UNVERIFIED"][] = $mention_id;
					}
				}
				$message->react("👍");
				echo count($GLOBALS["UNVERIFIED"]) . " UNVERIFIED ACCOUNTS" . PHP_EOL;
				echo "[GET UNVERIFIED DONE]" . PHP_EOL;
			}
		);
	}
	elseif ($author_guild->id == '589291350609100818') { //Links Gaming Corner
		//$members = $fetched_guild->members->all(); //array
		foreach ($author_guild->members as $target_member) { //GuildMember
			$target_skip = false;
			//get roles of member
			$target_guildmember_role_collection = $target_member->roles;
			foreach ($target_guildmember_role_collection as $role) {
				if ($role->name == "Verified") {
					$target_skip = true;
				}
			}
			if(!$target_skip) $GLOBALS["UNVERIFIED"][] = $target_member->id;
		}
		$message->react("👍");
		echo count($GLOBALS["UNVERIFIED"]) . " UNVERIFIED ACCOUNTS" . PHP_EOL;
		echo "[GET UNVERIFIED DONE]" . PHP_EOL;
	}
	return;
}
if ($message_content_lower == 'verify unverified') { //;verify unverified
	echo "[PURGE UNVERIFIED START]" . PHP_EOL;
	if ($GLOBALS["UNVERIFIED"]) {
		echo "UNVERIFIED 0: " . $GLOBALS["UNVERIFIED"][0] . PHP_EOL;
		$GLOBALS["UNVERIFIED_COUNT"] = count($GLOBALS["UNVERIFIED"]);
		echo "UNVERIFIED_COUNT: " . $GLOBALS["UNVERIFIED_COUNT"] . PHP_EOL;
		$GLOBALS["UNVERIFIED_X"] = 0;
		$GLOBALS['UNVERIFIED_TIMER'] = $loop->addPeriodicTimer(3, function () use ($discord, $loop, $author_guild_id, $role_verified_id) {
			//FIX THIS
			if ($GLOBALS["UNVERIFIED_X"] < $GLOBALS["UNVERIFIED_COUNT"]) {
				$target_id = $GLOBALS["UNVERIFIED"][$GLOBALS["UNVERIFIED_X"]]; //GuildMember
				//if($GLOBALS['debug_echo']) echo "author_guild_id: " . $author_guild_id;
				//if($GLOBALS['debug_echo']) echo "UNVERIFIED ID: $target_id" . PHP_EOL;
				if ($target_id) {
					echo "PURGING $target_id" . PHP_EOL;
					$target_guild = $discord->guilds->get('id', $author_guild_id);
					if($role_verified_id && $target_member = $target_guild->members->get('id', $target_id)) //if($GLOBALS['debug_echo']) echo "target_member: " . get_class($target_member) . PHP_EOL;
					$target_member->addrole($role_verified_id);
					$GLOBALS["UNVERIFIED_X"] = $GLOBALS["UNVERIFIED_X"] + 1;
					return;
				} else {
					$loop->cancelTimer($GLOBALS['UNVERIFIED_TIMER']);
					$GLOBALS["UNVERIFIED_COUNT"] = null;
					$GLOBALS['UNVERIFIED_X'] = null;
					$GLOBALS['UNVERIFIED_TIMER'] = null;
					echo "[PURGE UNVERIFIED TIMER DONE]" . PHP_EOL;
					return;
				}
			}
		});
		if ($react) $message->react("👍");
	} elseif ($react) $message->react("👎");
	echo "[PURGE UNVERIFIED DONE]" . PHP_EOL;
	return;
}
if ($message_content_lower == 'purge unverified') { //;purge unverified
	if($GLOBALS['debug_echo']) echo "[PURGE UNVERIFIED START]" . PHP_EOL;
	if ($GLOBALS["UNVERIFIED"]) {
		if($GLOBALS['debug_echo']) echo "UNVERIFIED 0: " . $GLOBALS["UNVERIFIED"][0] . PHP_EOL;
		$GLOBALS["UNVERIFIED_COUNT"] = count($GLOBALS["UNVERIFIED"]);
		if($GLOBALS['debug_echo']) echo "UNVERIFIED_COUNT: " . $GLOBALS["UNVERIFIED_COUNT"] . PHP_EOL;
		$GLOBALS["UNVERIFIED_X"] = 0;
		$GLOBALS['UNVERIFIED_TIMER'] = $loop->addPeriodicTimer(3, function () use ($discord, $loop, $author_guild_id) {
			//FIX THIS
			if ($GLOBALS["UNVERIFIED_X"] < $GLOBALS["UNVERIFIED_COUNT"]) {
				$target_id = $GLOBALS["UNVERIFIED"][$GLOBALS["UNVERIFIED_X"]]; //GuildMember
				//if($GLOBALS['debug_echo']) echo "author_guild_id: " . $author_guild_id;
				//if($GLOBALS['debug_echo']) echo "UNVERIFIED ID: $target_id" . PHP_EOL;
				if ($target_id) {
					if($GLOBALS['debug_echo']) echo "PURGING $target_id" . PHP_EOL;
					$target_guild = $discord->guilds->get('id', $author_guild_id);
					if($target_member = $target_guild->members->get('id', $target_id)) //if($GLOBALS['debug_echo']) echo "target_member: " . get_class($target_member) . PHP_EOL;
					$target_guild->members->kick($target_member); //$target_member->kick("unverified purge");
					$GLOBALS["UNVERIFIED_X"] = $GLOBALS["UNVERIFIED_X"] + 1;
					return;
				} else {
					$loop->cancelTimer($GLOBALS['UNVERIFIED_TIMER']);
					$GLOBALS["UNVERIFIED_COUNT"] = null;
					$GLOBALS['UNVERIFIED_X'] = null;
					$GLOBALS['UNVERIFIED_TIMER'] = null;
					if($GLOBALS['debug_echo']) echo "[PURGE UNVERIFIED TIMER DONE]" . PHP_EOL;
					return;
				}
			}
		});
		if ($react) $message->react("👍");
	} elseif ($react) $message->react("👎");
	if($GLOBALS['debug_echo']) echo "[PURGE UNVERIFIED DONE]" . PHP_EOL;
	return;
}