<?php
/**
 * DailyMaximum
 * Copyright 2013 Ziloi.com (Ziloi) on PluginBIN.com (License: MIT)
 * Developed for Ziloi.com (Ziloi) on PluginBIN.com by Matthew Gross (http://mattgross.net)
 * http://www.pluginbin.com/author/ziloi
 */

if ( !defined( "IN_MYBB" ) ) {
    die( "You CANNOT Access this File Directly!" );
}

// Hooks
$plugins->add_hook( 'newreply_start', 'dailymaximum_start' );
$plugins->add_hook( 'newthread_start', 'dailymaximum_start' );
$plugins->add_hook( 'newreply_do_newreply_start', 'dailymaximum_start' );
$plugins->add_hook( 'newthread_do_newthread_start', 'dailymaximum_start' );
// Continue:

function dailymaximum_info()
{
    return array(
         "name" => "Daily Maximum",
        "description" => "A MyBB Plugin to limit the number of posts a user post in a specific forum per day.",
        "website" => "http://pluginbin.com/author/ziloidev",
        "author" => "Ziloi Plugin Development",
        "authorsite" => "http://www.pluginbin.com/author/ziloi",
        "version" => "1.2B",
        "guid" => "",
        "compatibility" => "*" 
    );
}

function dailymaximum_activate()
{
    global $db;
    
    $dailymaximum_group = array(
         'gid' => 'NULL',
        'name' => 'dailymaximum',
        'title' => 'DailyMaximum',
        'description' => "A MyBB Plugin to limit the number of posts a user post in a specific forum per day.",
        'disporder' => "13",
        'isdefault' => "no" 
    );
    
    $db->insert_query( 'settinggroups', $dailymaximum_group );
    $gid = $db->insert_id();
    
    $dailymaximum_setting_1 = array(
         "sid" => "NULL",
        "name" => "dailymaximum_1",
        "title" => "Enable/Disable Daily Maximum",
        "description" => "Toggles the Plugin ON or OFF",
        "optionscode" => "onoff",
        "value" => '1',
        "disporder" => 1,
        "gid" => intval( $gid ) 
    );
    
    $dailymaximum_setting_2 = array(
         "sid" => "NULL",
        "name" => "dailymaximum_2",
        "title" => "Toggle Feature For Specific Forums",
        "description" => "Which Forums (ID) would you like this feature to be running? 
            One per line with equals sign and then usergroup ids seperated by commas 
            followed by a colon and the amount of posts per set time followed by a 
            dash and the amount of days (24 hours) that the amount of posts will last for. 
            (Example: 1=2,4:10-2 Would mean the users in the usergroup 2 and 4 would only be
            able to post in the forum id 1, 10 times every 2 days). You can generate this at 
            http://ziloi.com/tools/dailymaximum/generate-config.php",
        "optionscode" => "textarea",
        "value" => "",
        "disporder" => 2,
        "gid" => intval( $gid ) 
    );
    
    $dailymaximum_setting_3 = array(
         "sid" => "NULL",
        "name" => "dailymaximum_3",
        "title" => "URL to Redirect to for user once they hit their daily post maximum (Optional - Leave blank for default).",
        "description" => "",
        "optionscode" => "text",
        "value" => "",
        "disporder" => 3,
        "gid" => intval( $gid ) 
    );
    
    $dailymaximum_setting_4 = array(
         "sid" => "NULL",
        "name" => "dailymaximum_4",
        "title" => "Option to utilize feature on Posts, Threads, or Both",
        'optionscode' => 'radio \n 1=only Posts \n 2=only Threads \n 3=Both Posts and Threads',
        "description" => "",
        "value" => "3",
        "disporder" => 4,
        "gid" => intval( $gid ) 
    );
    
    // Insert Queries
    $db->insert_query( "settings", $dailymaximum_setting_1 );
    $db->insert_query( "settings", $dailymaximum_setting_2 );
    $db->insert_query( "settings", $dailymaximum_setting_3 );
    $db->insert_query( "settings", $dailymaximum_setting_4 );
    // Rebuild
    rebuild_settings();
}

function dailymaximum_deactivate()
{
    global $db;
    $db->query( "DELETE FROM " . TABLE_PREFIX . "settinggroups WHERE name='dailymaximum'" );
    $db->query( "DELETE FROM " . TABLE_PREFIX . "settings WHERE name='dailymaximum_1'" );
    $db->query( "DELETE FROM " . TABLE_PREFIX . "settings WHERE name='dailymaximum_2'" );
    $db->query( "DELETE FROM " . TABLE_PREFIX . "settings WHERE name='dailymaximum_3'" );
}

function dailymaximum_start()
{
    global $db, $dailymaximum, $mybb, $lang;
    switch ( $mybb->settings['dailymaximum_4'] ) {
        case "3":
            $lang->load( "newreply" );
            $lang->load( "newthread" );
            break;
        case "2":
            $lang->load( "newthread" );
            break;
        case "1":
            $lang->load( "newreply" );
            break;
    }
    if ( $mybb->settings['dailymaximum_1'] == 1 ) {
        // Bind settings to variables
        $dailymaximum_2              = $mybb->settings['dailymaximum_2'];
        $dailymaximum_3              = $mybb->settings['dailymaximum_3'];
        $dailymaximum_settings_array = array();
        if ( isset( $dailymaximum_2 ) && !empty( $dailymaximum_2 ) ) {
            $dailymaximum_regex = "(.*?)\=(.*?)\:(.*?)\-(.*?)$";
            foreach ( explode( "\n", $dailymaximum_2 ) as $dailymaximum_line ) {
                if ( $c = preg_match_all( "/" . $dailymaximum_regex . "/is", $dailymaximum_line, $dailymaximum_matches ) ) {
                    $dailymaximum_forumid                  = $dailymaximum_matches[1][0];
                    $dailymaximum_usergroups               = explode( ",", $dailymaximum_matches[2][0] );
                    $dailymaximum_postamount               = $dailymaximum_matches[3][0];
                    $dailymaximum_daycount                 = $dailymaximum_matches[4][0];
                    $dailymaximum_settings_array[$forumid] = array(
                         "usergroups" => $dailymaximum_usergroups,
                        "postamount" => $dailymaximum_postamount,
                        "daycount" => $dailymaximum_daycount 
                    );
                }
            }
        }
        if ( count( $dailymaximum_settings_array ) > 0 ) {
            // not empty
            if ( array_key_exists( $thread['fid'], $dailymaximum_settings_array ) ) {
                $fid                     = $thread['fid'];
                // Posting in Restricted forum.. Check how many posts in the last daycount they have posted, and make sure its below the postamount:
                $dailymaximum_daycut     = ( ( TIME_NOW - 60 * 60 * 24 ) * $dailymaximum_settings_array[$thread['fid']]["daycount"] );
                $dailymaximum_post_count = null;
                $currentPage             = substr( $_SERVER["SCRIPT_NAME"], strrpos( $_SERVER["SCRIPT_NAME"], "/" ) + 1 );
                // Final IF Statement Logic
                if ( $currentPage == "newreply.php" && $mybb->settings['dailymaximum_4'] == 1 || $mybb->settings['dailymaximum_4'] == 3 ) {
                    $dailymaximum_query_1      = $db->simple_select( "posts", "COUNT(*) AS posts_from_daycount_1", "uid='{$mybb->user['uid']}' AND visible='1' AND dateline>{$dailymaximum_daycut}" );
                    $dailymaximum_post_count_1 = $db->fetch_field( $dailymaximum_query_1, "posts_from_daycount_1" );
                    $dailymaximum_post_count_2 = 0; // default
                    if ( $mybb->settings['dailymaximum_4'] == 3 ) {
                        $dailymaximum_query_2      = $db->simple_select( "threads", "COUNT(*) AS posts_from_daycount_2", "uid='{$mybb->user['uid']}' AND visible='1' AND dateline>{$dailymaximum_daycut}" );
                        $dailymaximum_post_count_2 = $db->fetch_field( $dailymaximum_query_2, "posts_from_daycount_2" );
                    }
                    $dailymaximum_post_count = $dailymaximum_post_count_1 + $dailymaximum_post_count_2;
                }
                if ( $currentPage == "newthread.php" && $mybb->settings['dailymaximum_4'] == 2 || $mybb->settings['dailymaximum_4'] == 3 ) {
                    $dailymaximum_query_1      = $db->simple_select( "threads", "COUNT(*) AS posts_from_daycount_1", "uid='{$mybb->user['uid']}' AND visible='1' AND dateline>{$dailymaximum_daycut} AND fid='{$fid}'" );
                    $dailymaximum_post_count_1 = $db->fetch_field( $dailymaximum_query_1, "posts_from_daycount_1" );
                    $dailymaximum_post_count_2 = 0; // default
                    if ( $mybb->settings['dailymaximum_4'] == 3 ) {
                        $dailymaximum_query_2      = $db->simple_select( "posts", "COUNT(*) AS posts_from_daycount_2", "uid='{$mybb->user['uid']}' AND visible='1' AND dateline>{$dailymaximum_daycut} AND fid='{$fid}'" );
                        $dailymaximum_post_count_2 = $db->fetch_field( $dailymaximum_query_2, "posts_from_daycount_2" );
                    }
                    $dailymaximum_post_count = $dailymaximum_post_count_1 + $dailymaximum_post_count_2;
                }
                // Final if (Last check and print error, or redirect!)
                if ( $dailymaximum_post_count >= $dailymaximum_settings_array[$thread['fid']]["postamount"] ) {
                    if ( isset( $dailymaximum_3 ) && !empty( $dailymaximum_3 ) ) {
                        // Redirect URL is set
                        header( 'Location: ' . $dailymaximum_3 );
                    } else {
                        // return built in maxposts error page
                        $lang->error_maxposts = $lang->sprintf( $lang->error_maxposts, $dailymaximum_settings_array[$thread['fid']]["postamount"] );
                        error( $lang->error_maxposts );
                    }
                }
            }
        }
    }
}

?>
