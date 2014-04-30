<?php

$action = $_REQUEST['action'];

add_filter( 'cron_schedules', 'virada_add_cron' );

function virada_add_cron( $schedules ) {

	$schedules['tsecs'] = array(
	'interval' => 30,
	'display' => __( 'A cada 30 segundos' )
	);
	return $schedules;
}


add_action( 'wp', 'virada_add_schedule' );

function virada_add_schedule() {
	if ( ! wp_next_scheduled( 'virada_cron' ) ) {
	wp_schedule_event( time(), 'tsecs', 'virada_cron');
	}
}


add_action( 'virada_cron', function() {
	//file_put_contents('/tmp/teste', "1\n", FILE_APPEND);
	virada_get_social_feeds();
} );

function virada_get_social_feeds() {

	//global $wpdb;

    include ('Simple-Database-PHP-Class/Db.php');
    include ('extra-db-config.php');
    $db = new Db('mysql',
        $db_config['virada_nas_redes']['host'],
        $db_config['virada_nas_redes']['name'],
        $db_config['virada_nas_redes']['user'],
        $db_config['virada_nas_redes']['pass']
    );

	/* Twitter */

	include('twitter-api-php/TwitterAPIExchange.php');
	$settings = array(
		'oauth_access_token' => "12317412-aYKqwGx9IASKJ7hMFCMHouaxRZ041cxwziZoxyTGV",
		'oauth_access_token_secret' => "5DJwNl6WMbupZr0i1WgqAPp54kqr82K6MVDIHcNxJZb50",
		'consumer_key' => "UI0s5HS9iVDNKfq8x3Dq4kADx",
		'consumer_secret' => "0Ay02qdCxEmROMOUyO4wCmwuYhzJNOWuXRkykvRJFES9iCAFxq"
	);

	$url = 'https://api.twitter.com/1.1/search/tweets.json';
	$requestMethod = 'GET';

	$getfield = '?q=#' . get_theme_option('hashtag');

	$twitter = new TwitterAPIExchange($settings);
	$response =  $twitter->setGetfield($getfield)
								  ->buildOauth($url, $requestMethod)
								  ->performRequest();
	//var_dump($response); die;
	$tweets = json_decode($response);

	function virada_twitterify($ret) {
		$ret = preg_replace("#(^|[\n ])([\w]+?://[\w]+[^ \"\n\r\t< ]*)#", "\\1<a href=\"\\2\" target=\"_blank\">\\2</a>", $ret);
		$ret = preg_replace("#(^|[\n ])((www|ftp)\.[^ \"\t\n\r< ]*)#", "\\1<a href=\"//\\2\" target=\"_blank\">\\2</a>", $ret);
		$ret = preg_replace("/@(\w+)/", "<a href=\"//twitter.com/\\1\" target=\"_blank\">@\\1</a>", $ret);
		$ret = preg_replace("/#(\w+)/", "<a href=\"//twitter.com/search?q=\\1\" target=\"_blank\">#\\1</a>", $ret);
		return $ret;
	}


	/* Instagram */

	function callInstagram($url) {
		$ch = curl_init();
		curl_setopt_array($ch, array(
		CURLOPT_URL => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_SSL_VERIFYHOST => 2
		));

		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
	}

	$tag = get_theme_option('hashtag');
	$client_id = "a3c10779238e4008ad7d47d3136ee8ce";
	$url = 'https://api.instagram.com/v1/tags/'.$tag.'/media/recent?client_id='.$client_id;

	$inst_stream = callInstagram($url);
	$instagram_fotos = json_decode($inst_stream, true);


	foreach($instagram_fotos['data'] as $item){
		$image_link = $item['images']['standard_resolution']['url'];
		$image_tag = '<img src="'.$image_link.'" />';


		// check if post exists
		//$exists = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(ID) FROM $wpdb->posts WHERE post_type = 'instagram_cpt' AND post_title = %s", $item['id']));
        $exists = $db->query( 'SELECT id FROM items WHERE type = "instagram_cpt" AND ref_id = :ref_id', array( 'ref_id' => $item['id'] ) )->fetch();

        if ($exists){
            //error_log('ALREADY EXISTS '.print_r($exists, true), 4);
			continue;
        }

        $db->create( 'items', array(
            'ref_id'            => $item['id'],
            'type'              => 'instagram_cpt',
            'content'           => $image_tag,
            'date'              => date('Y-m-d H:i:s', $item['created_time']),
            'author_username'   => $item['user']['username'],
            'author_fullname'   => $item['user']['full_name'],
            'text'              => $item['text'],
            'link'              => $item['link']
        ));

//		$post = array(
//			'post_content'   => $image_tag,
//			'post_title'     => $item['id'],
//			'post_status'    => 'publish',
//			'post_type'      => 'instagram_cpt',
//			//'post_author'    => [ <user ID> ] // The user ID number of the author. Default is the current user ID.
//			//'post_excerpt'   => [ <string> ] // For all your post excerpt needs.
//			'comment_status' => 'closed',
//			'post_date' => date('Y-m-d H:i:s', $item['created_time'])
//		);

		//echo date('Y-m-d H:i:s', $item['created_time']) . "\n\n";

		//echo date('Y-m-d H:i:s', $item['created_time']);

//		$newId = wp_insert_post($post);
//		var_dump($newId);
//
//		if (!is_wp_error($newId) && $newId) {
//			add_post_meta($newId, 'author_username', $item['user']['username']);
//		}
		//print_r($item);
		//break;
	}

	/* Twitter */

	foreach($tweets->statuses as $tweet) {

		// check if post exists
		//$exists = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(ID) FROM $wpdb->posts WHERE post_type = 'twitter_cpt' AND post_title = %s", $tweet->id));
        $exists = $db->query( 'SELECT id FROM items WHERE type = "twitter_cpt" AND ref_id = :ref_id', array( 'ref_id' => $tweet->id ) )->fetch();

        if ($exists){
            //error_log('ALREADY EXISTS '.print_r($exists, true), 4);
			continue;
        }
        $db->create( 'items', array(
            'ref_id'            => $tweet->id,
            'type'              => 'twitter_cpt',
            'content'           => virada_twitterify($tweet->text),
            'date'              => gmdate('Y-m-d H:i:s', strtotime($tweet->created_at)),
            'author_username'   => $tweet->user->screen_name,
            'author_fullname'   => $tweet->user->name
        ));

//		$post = array(
//			'post_content'   => virada_twitterify($tweet->text),
//			'post_title'     => $tweet->id,
//			'post_status'    => 'publish',
//			'post_type'      => 'twitter_cpt',
//			//'post_author'    => [ <user ID> ] // The user ID number of the author. Default is the current user ID.
//			//'post_excerpt'   => [ <string> ] // For all your post excerpt needs.
//			'comment_status' => 'closed',
//			//'post_date' => date('Y-m-d H:i:s', $item['created_time'])
//		);
//
//		$newId = wp_insert_post($post);
//		var_dump($newId);
//
//		if (!is_wp_error($newId) && $newId) {
//			add_post_meta($newId, 'author_username', $tweet->user->screen_name);
//		}

	}

}

?>
