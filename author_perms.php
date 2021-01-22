<?php
$author	= $message->author; //Member OR User object

if ( is_object($author) && get_class($author) == "Discord\Parts\User\Member") {
    $author_user = $author->user;
    $author_member = $author;
} else {
    $author_user = $author;
}
if ($author_member) {
    //Permissions granted by roles
    $user_perms = array(
        'priority_speaker' => false,
        'stream' => false,
        'connect' => false,
        'speak' => false,
        'mute_members' => false,
        'deafen_members' => false,
        'move_members' => false,
        'use_vad' => false,
        
        'add_reactions' => false,
        'send_messages' => false,
        'send_tts_messages' => false,
        'manage_messages' => false,
        'embed_links' => false,
        'attach_files' => false,
        'read_message_history' => false,
        'mention_everyone' => false,
        'use_external_emojis' => false,
        
        'kick_members' => false,
        'ban_members' => false,
        'administrator' => false,
        'manage_guild' => false,
        'view_audit_log' => false,
        'view_guild_insights' => false,
        'change_nickname' => false,
        'manage_nicknames' => false,
        'manage_emojis' => false,
        
        'bitwise' => 0,
        'create_instant_invite' => false,
        'manage_channels' => false,
        'view_channel' => false,
        'manage_roles' => false,
        'manage_webhooks' => false
    );
    
    $author_member_roles = $author_member->roles; 								//Role objects for the author);
    
    foreach ($author_member_roles as $role) { //Check all roles for guild and channel permissions
        $permissions = json_decode(json_encode($role->permissions), 1);
        foreach ($permissions as $id => $perm) {
            if ( ($perm === true) || ($member->guild->owner_id == $member->id) ) {
                $user_perms[$id] = true;
            }
            //echo "id: " . $id . PHP_EOL;
            //echo "perm: $perm" . PHP_EOL;
        }
        /*
        if ($permissions->priority_speaker === true) $priority_speaker = true;
        if ($permissions->stream === true) $stream = true;
        if ($permissions->connect === true) $connect = true;
        if ($permissions->speak === true) $speak = true;
        if ($permissions->mute_members === true) $mute_members = true;
        if ($permissions->deafen_members === true) $deafen_members = true;
        if ($permissions->move_members === true) $move_members = true;
        if ($permissions->use_vad === true) $use_vad = true;

        if ($permissions->add_reactions === true) $add_reactions = true;
        if ($permissions->send_messages === true) $send_messages = true;
        if ($permissions->send_tts_messages === true) $send_tts_messages = true;
        if ($permissions->manage_messages === true) $manage_messages = true;
        if ($permissions->embed_links === true) $embed_links = true;
        if ($permissions->attach_files === true) $attach_files = true;
        if ($permissions->read_message_history === true) $read_message_history = true;
        if ($permissions->mention_everyone === true) $mention_everyone = true;
        if ($permissions->use_external_emojis === true) $use_external_emojis = true;

        if ($permissions->kick_members === true) $kick_members = true;
        if ($permissions->ban_members === true) $ban_members = true;
        if ($permissions->administrator === true) $administrator = true;
        if ($permissions->manage_guild === true) $manage_guild = true;
        if ($permissions->view_audit_log === true) $view_audit_log = true;
        if ($permissions->view_guild_insights === true) $view_guild_insights = true;
        if ($permissions->change_nickname === true) $change_nickname = true;
        if ($permissions->manage_nicknames === true) $manage_nicknames = true;
        if ($permissions->manage_emojis === true) $manage_emojis = true;
        */
    }
    //echo "kick_members: " . $user_perms['kick_members'] . PHP_EOL;
    //var_dump($user_perms);
    //var_dump($author_member->getPermissions());
}
