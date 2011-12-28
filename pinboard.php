<?php
/*
Plugin Name: alexking.org Pinboard Posting
Version: 1.0
Description: Create posts from Pinboard bookmarks.
*/

@define('AKV3_LINK_CAT', '49');
@define('AKV3_PINBOARD_DRYRUN', false);
@define('AKV3_PINBOARD_USERNAME', 'username');
@define('AKV3_PINBOARD_PASSWORD', 'password');

function akv3_pinboard_tags() {
	return array(
		'atw',
	);
}

function akv3_pinboard_cron() {
	$tags = akv3_pinboard_tags();
	if (is_array($tags) && count($tags)) {
		foreach ($tags as $tag) {
			akv3_pinboard_process_tag($tag);
		}
	}
}
add_action('social_cron_15', 'akv3_pinboard_cron');

function akv3_pinboard_guid($hash) {
	return 'http://pinboard-'.$hash;
}

function akv3_pinboard_markdown($content) {
// stolen from Mark Jaquith's excellent Markdown on Save plugin
// http://wordpress.org/extend/plugins/markdown-on-save/
	if (function_exists('Markdown')) {
		$content = preg_replace("#<p>(.*?)</p>(\n|$)#", '$1$2', Markdown($content));
	}
	return $content;
}

function akv3_pinboard_process_tag($tag) {
	set_time_limit(0);
	$tz = date_default_timezone_get();
	date_default_timezone_set('America/Denver');
	global $wpdb;

	if (!class_exists('DeliciousBrownies')) {
		include('lib/DeliciousBrownies.php');
	}
	$db = new DeliciousBrownies;
	$db->setUsername(AKV3_PINBOARD_USERNAME);
	$db->setPassword(AKV3_PINBOARD_PASSWORD);
	
	$time_window = strtotime('-2 days GMT');

	$items = $db->getRecentPosts($tag, 30);
	$bookmarks = array();

	if (count($items)) {
		foreach ($items as $item) {
			$bookmarks[akv3_pinboard_guid($item['hash'])] = $item;
		}
// grab item hashes, look for existing items (use GUID)
		$guids = array_keys($bookmarks);
		$existing = $wpdb->get_col("
			SELECT guid
			FROM $wpdb->posts
			WHERE guid IN ('".implode("', '", array_map(array($wpdb, 'escape'), $guids))."')
		");
		if (!AKV3_PINBOARD_DRYRUN) {
			foreach ($existing as $guid) {
				unset($bookmarks[$guid]);
			}
		}
	}
// create new posts
	if (count($bookmarks)) {
		$bookmarks = array_reverse($bookmarks);
		foreach ($bookmarks as $guid => $bookmark) {
			$bookmark_time = strtotime($bookmark['time']);
			if ($bookmark_time < $time_window) {
				continue;
			}
			if (in_array('md', explode(' ', $bookmark['tag']))) {
				$bookmark['extended'] = akv3_pinboard_markdown($bookmark['extended']);
			}
			if (AKV3_PINBOARD_DRYRUN) {
				echo '<pre>';
				print_r(array(
					'post_status' => (in_array('draft', explode(' ', $bookmark['tag'])) ? 'draft' : 'publish'),
					'post_author' => 1,
					'post_category' => array(AKV3_LINK_CAT),
					'post_title' => strip_tags($bookmark['description']),
					'post_name' => sanitize_title(strip_tags($bookmark['description'])),
					'post_content' => $bookmark['extended'],
					'post_date' => date('Y-m-d H:i:s', $bookmark_time),
					'guid' => $guid,
					'url' => $bookmark['href'],
					'pinboard_tags' => $bookmark['tag'],
				));
				echo '</pre>';
				continue;
			}
			$post_id = wp_insert_post(array(
				'post_status' => 'draft',
				'post_author' => 1,
				'post_category' => array(AKV3_LINK_CAT),
				'post_title' => strip_tags($bookmark['description']),
				'post_name' => sanitize_title(strip_tags($bookmark['description'])),
				'post_content' => $bookmark['extended'],
				'post_date' => date('Y-m-d H:i:s', $bookmark_time),
				'guid' => $guid,
			));
			set_post_format($post_id, 'link');
			update_post_meta($post_id, '_format_link_url', $bookmark['href']);
// set tags, as post meta for now
			update_post_meta($post_id, '_pinboard_tags', $bookmark['tag']);
			if (!in_array('draft', explode(' ', $bookmark['tag']))) {
				wp_update_post(array(
					'ID' => $post_id,
					'post_status' => 'publish',
				));
			}
		}
	}
	date_default_timezone_set($tz);
	if (AKV3_PINBOARD_DRYRUN) {
		die();
	}
}

// test run
if ($_GET['ak_action'] == 'pinboard') {
 	add_action('admin_init', 'akv3_pinboard_cron');
}