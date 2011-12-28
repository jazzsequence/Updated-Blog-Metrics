<?php
/*
Plugin Name: Updated Blog Metrics
Version: 1.3
Plugin URI: https://github.com/jazzsequence/updated-blog-metrics
Description: Based on Joost de Valk's <a href="http://yoast.com/wordpress/blog-metrics/" target="_blank">Blog Metrics</a> plugin, provides author metrics over the past year rather than just the past 30 days.
Author: jazzsequence
Author URI: http://www.arcanepalette.com/

Copyright 2007-2009 Chris Reynolds  (email : chris@jazzsequence.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

if ( ! class_exists( 'BM_Admin' ) ) {

	class BM_Admin {

		function add_config_page() {
			global $wpdb;
			if ( function_exists('add_submenu_page') ) {
				add_options_page('Blog Metrics Configuration', 'Blog Metrics', 5, basename(__FILE__), array('BM_Admin','config_page'));
				add_filter( 'plugin_action_links', array( 'BM_Admin', 'filter_plugin_actions'), 10, 2 );
				add_filter( 'ozh_adminmenu_icon', array( 'BM_Admin', 'add_ozh_adminmenu_icon' ) );
			}
		}

		function add_ozh_adminmenu_icon( $hook ) {
			static $bmicon;
			if (!$bmicon) {
				$bmicon = WP_CONTENT_URL . '/plugins/' . plugin_basename(dirname(__FILE__)). '/chart_bar.png';
			}
			if ($hook == 'blog_metrics.php') return $bmicon;
			return $hook;
		}

		function filter_plugin_actions( $links, $file ){
			//Static so we don't call plugin_basename on every plugin row.
			static $this_plugin;
			if ( ! $this_plugin ) $this_plugin = plugin_basename(__FILE__);

			if ( $file == $this_plugin ){
				$settings_link = '<a href="options-general.php?page=blog_metrics.php">' . __('Settings') . '</a>';
				array_unshift( $links, $settings_link ); // before other links
			}
			return $links;
		}

		function config_page() {
			if ( isset($_POST['submit']) ) {
				if (!current_user_can('manage_options')) die(__('You cannot edit the Blog Metrics options.'));
				check_admin_referer('blogmetrics-config');

				foreach (array('stddev', 'fullstats') as $option_name) {
					if (isset($_POST[$option_name])) {
						$options[$option_name] = true;
					} else {
						$options[$option_name] = false;
					}
				}

				$options['everset'] = true;

				update_option('BlogMetricsOptions', $options);
			}

			$options = get_option('BlogMetricsOptions');
			?>
			<div class="wrap">
				<h2>Blog Metrics options</h2>
				<form action="" method="post" id="blogmetrics-conf" style="width: 35em; ">
					<table class="form-table">
						<?php
						if ( function_exists('wp_nonce_field') )
							wp_nonce_field('blogmetrics-config');
						?>
						<tr>
							<td>
								<input type="checkbox" id="stddev" name="stddev" <?php if ($options['stddev']) echo ' checked="checked" '; ?>/>
							</td>
							<td>
								<label for="stddev">Show the standard deviation</label>
							</td>
						</tr>
						<tr>
							<td>
								<input type="checkbox" id="fullstats" name="fullstats" <?php if ($options['fullstats']) echo ' checked="checked" '; ?>/>
							</td>
							<td>
								<label for="fullstats">Show full stats</label>
							</td>
						</tr>
					</table>
					<div class="submit"><input type="submit" name="submit" value="Update Settings &raquo;" /></div>
				</form>
			</div>
			<h2>Like this plugin?</h2>
			<p>Why not do any of the following:</p>
			<ul class="pluginmenu">
				<li>Link to it so other folks can find out about it.</li>
				<li><a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=AWM2TG3D4HYQ6">Donate</a> a token of your appreciation!</li>
			</ul>

<?php		}

	}

}

$options = get_option('BlogMetricsOptions');

if (!$options['everset']) {
	$options['fullstats'] = true;
	$options['stddev'] = false;
	$options['everset'] = true;

	update_option('BlogMetricsOptions', $options);
}

function bm_wordcount($statement, $attribute, $countAttribute, $avg = 0) {
	global $wpdb;
	$result=0;

	$countStatement = "SELECT COUNT(".$countAttribute.") " .$statement;
	$counter = $wpdb->get_var($countStatement);
	$startLimit = 0;

	$rows_at_Once=$counter;

	$incrementStatement = "SELECT ".$attribute." ".$statement;

	$intermedcount = 0;

	while( $startLimit < $counter) {
		$query = $incrementStatement." LIMIT ".$startLimit.", ".$rows_at_Once;
		$results = $wpdb->get_col($query);
		//count the words for each statement
		$intermedcount += count($results);
		for ($i=0; $i<count($results); $i++) {
			$sum = str_word_count($results[$i]);
			if ($avg == 0) {
				$result += $sum;
			} else {
				$intermed += ($sum*$sum);
			}
		}
		$startLimit+=$rows_at_Once;
	}
	if ($avg != 0) {
		$result = sqrt($intermed/$intermedcount);
	}
	return $result;
}

function bm_get_stats($period="alltime",$authorid=0) {
	global $wpdb;
	$options = get_option('BlogMetricsOptions');

	$periodquery = "";
	$authorquery = "";

	if ($period == "year") {
		$periodquery = " AND p.post_date > date_sub(now(),interval 1 year)";
	}
	if ($authorid != 0) {
		$authorquery = " AND p.post_author = $authorid";
	}

	$authorsquery = "SELECT COUNT(DISTINCT post_author) FROM $wpdb->posts p WHERE p.post_type = 'post'".$periodquery;

	// Override query if an authorid is set, to return display name for author
	if ($authorid != 0) {
		$authorsquery = "SELECT u.display_name FROM $wpdb->users u WHERE u.ID = $authorid";
	}

	$postsquery = "SELECT COUNT(ID) FROM $wpdb->posts p WHERE p.post_type = 'post' AND p.post_status='publish'".$periodquery.$authorquery;

	$firstpostquery = "SELECT p.post_date FROM $wpdb->posts p WHERE p.post_status = 'publish'$authorquery ORDER BY p.post_date LIMIT 1";

	$commentfromwhere 	="FROM $wpdb->comments c, $wpdb->posts p, $wpdb->users u "
						."WHERE c.comment_approved = '1'"
						." AND c.comment_author_email != u.user_email"
						." AND c.comment_post_ID = p.ID"
						." AND c.comment_type = ''"
						." AND p.post_type = 'post'"
						." AND p.post_author = u.ID"
						.$periodquery.$authorquery;

	$commentsquery 		= "SELECT COUNT(c.comment_ID) ".$commentfromwhere;
	$commentwordsquery 	= $commentfromwhere;

	$trackbackquery = str_replace("c.comment_type = ''","c.comment_type != ''",$commentsquery);

	$postwordsquery = "FROM $wpdb->posts p WHERE p.post_status = 'publish' AND p.post_type = 'post'".$periodquery.$authorquery;

	$stats['authors'] 		= $wpdb->get_var($authorsquery);
	$stats['posts'] 		= $wpdb->get_var($postsquery);
	$stats['comments'] 		= $wpdb->get_var($commentsquery);
	$stats['trackbacks']	= $wpdb->get_var($trackbackquery);
	$stats['postwords'] 	= bm_wordcount($postwordsquery,"post_content","ID");
	$stats['commentwords'] 	= bm_wordcount($commentwordsquery,"comment_content","comment_ID");
	if ($period == "alltime") {
		$stats['firstpost'] = $wpdb->get_var($firstpostquery);
		$stats['bloggingyears'] 	= floor( ( time() - strtotime($stats['firstpost']) ) / 2628000);
		if ($stats['bloggingyears'] == 0) {
			$stats['bloggingyears'] = 1;
		}
	} else if ($period == "year") {
		$stats['bloggingyears']	= 1;
	}
	if ($stats['posts'] > 0) {
		$stats['avgposts'] 		= round($stats['posts'] / $stats['bloggingyears'],1);
	}

	if ($stats['comments'] > 0 && $stats['posts'] > 0) {
		$stats['avgcomments'] = round(($stats['comments'] / $stats['posts']),1);
	} else {
		$stats['avgcomments'] = 0;
	}
	if ($stats['avgcomments'] > 1 && $options['stddev']) {
		 $commentstddevquery = "SELECT (COUNT(c.comment_ID)-".$stats['avgcomments'].")*(COUNT(c.comment_ID)-".$stats['avgcomments'].") AS commentdiff2 ".$commentfromwhere." GROUP BY c.comment_post_ID";
		$results = $wpdb->get_results($commentstddevquery);
		$totaldev = 0;
		foreach($results as $result) {
			$totaldev += $result->commentdiff2;
		}
		$stats['stddevcomments'] = round(sqrt($totaldev / $stats['posts']),1);
	}
	if ($stats['trackbacks'] > 0) {
		$stats['avgtrackbacks'] = round($stats['trackbacks'] / $stats['posts'],1);
	} else {
		$stats['avgtrackbacks'] = 0;
	}
	if ($stats['avgtrackbacks'] > 1 && $options['stddev']) {
		$trackbacksstddevquery = str_replace("c.comment_type = ''","c.comment_type != ''",$commentstddevquery);
		$results = $wpdb->get_results($trackbacksstddevquery);
		$totaldev = 0;
		if ($results) {
			foreach($results as $result) {
				$totaldev += $result->commentdiff2;
			}
			$stats['stddevtrackbacks'] = round(sqrt($totaldev / $stats['posts']),1);
		} else {
			$stats['stddevtrackbacks'] = 0;
		}
	}
	if ($stats['postwords'] > 0 && $options['stddev'] && $stats['posts'] > 1) {
		$stats['stddevpostwords'] 	= bm_wordcount($postwordsquery,"post_content","ID",($stats['postwords'] / $stats['posts']));
	}

	$stats['period'] = $period;
	return $stats;
}

function bm_print_stats($stats) {
	$options = get_option('BlogMetricsOptions');

	if ($stats['period'] == "alltime") {
		$per = "per";
	} else if ($stats['period'] == "year") {
		$per = "this";
	}
	echo '<td style="vertical-align:text-top;width:220px;">';
	if ( !is_numeric($stats['authors']) ) {
		echo '<h3>'.$stats['authors'].'</h3>';
	}
	echo '<h4 style="margin-bottom:2px;">Raw Author Contribution</h4>';

	if ($stats['avgposts'] == 1) {
		echo $stats['avgposts']." post $per year<br/>\n";
	} else {
		echo $stats['avgposts']." posts $per year<br/>\n";
	}
	if ($stats['posts'] > 0) {
		echo 'Avg: '.round($stats['postwords'] / $stats['posts'])." words per post<br/>\n";
	}
	if ($stats['stddevpostwords']) {
		echo 'Std dev: '.round($stats['stddevpostwords']).' words'."<br/>\n";
	}
	echo '<h4 style="margin-bottom:2px;">Conversation Rate Per Post</h4>';
	echo '<table style="border-collapse:collapse;">';
	echo '<tr><td>Avg: &nbsp;</td><td>'.$stats['avgcomments'].' comments'."</td></tr>\n";
	if ($stats['stddevcomments']) {
		echo '<tr><td>Std dev: &nbsp;</td><td>'.$stats['stddevcomments'].' comments'."</td></tr>\n";
	}
	if ($stats['commentwords'] > 0 && $stats['posts'] > 0) {
		echo '<tr><td>Avg:</td><td>'.floor($stats['commentwords'] / $stats['posts']).' words in comments'."</td></tr>\n";
	}
	echo '<tr><td>Avg:</td><td>'.$stats['avgtrackbacks'].' trackbacks'."</td></tr>\n";
	if ($stats['stddevtrackbacks']) {
		echo '<tr><td>Std dev:</td><td>'.$stats['stddevtrackbacks'].' trackbacks'."</td></tr>\n";
	}
	echo '</table>'."\n\n";

	if ($options['fullstats']) {
		echo '<h4 style="margin-bottom:2px;">Full Stats</h4>';
		echo '<table style="border-collapse:collapse;">';
		if ( is_numeric($stats['authors']) ) {
			echo '<tr><td>Author(s):</td><td>'.$stats['authors']."</td></tr>";
		}
		if ($stats['period'] == "alltime") {
			echo '<tr><td>Posts:</td><td>'.$stats['posts']."</td></tr>";
		}
		echo '<tr><td>Words in posts:</td><td>'.$stats['postwords']."</td></tr>";
		echo '<tr><td>Comments:</td><td>'.$stats['comments']."</td></tr>";
		echo '<tr><td>Words in comments:</td><td>'.$stats['commentwords']."</td></tr>";
		echo '<tr><td>Trackbacks:</td><td>'.$stats['trackbacks']."</td></tr>";
		if ($stats['period'] == "alltime") {
			echo '<tr><td>years blogging: &nbsp;</td><td>'.$stats['bloggingyears']."</td></tr>";
		}
		echo '</table>';
	}
	echo '</td>';
}

function bm_author_stats($period) {
	global $wpdb;
	echo '<table style="border-collapse:collapse;vertical-align:text-top;">';
	$authorquery = "SELECT DISTINCT p.post_author, count(ID) AS posts FROM $wpdb->posts p WHERE p.post_type = 'post'";
	if ($period == "year") {
		$authorquery .= " AND p.post_date > date_sub(now(),interval 1 year)";
	}
	$authorquery .= "  GROUP BY p.post_author ORDER BY posts DESC";

	$authors = $wpdb->get_results($authorquery);
	echo '<tr>';
	$i = 0;
	foreach ($authors as $author) {
		if ($i == 4) {
			echo '</tr><tr>';
			$i = 0;
		}
		bm_print_stats(bm_get_stats($period,$author->post_author));
		$i++;
	}
	echo '</tr>';
	echo '</table>';
}

function bm_dashboard() {
	global $wpdb;
	echo '<div class="wrap">';
	echo '<h2>Blog Metrics</h2>';
	echo '<table>';
	echo '<tr>';
	echo '<td style="width:220px;"><h3 style="margin-bottom:0;">Full Stats</h3></td>';
	echo '<td style="width:220px;"><h3 style="margin-bottom:0;">Last year</h3></td>';
	echo '</tr>';
	echo '<tr>';
	bm_print_stats(bm_get_stats());
	bm_print_stats(bm_get_stats("year"));
	echo '</tr>';
	echo '</table>';
	$numauthorquery = "SELECT COUNT(DISTINCT p.post_author) FROM $wpdb->posts p";
	$numauthors = $wpdb->get_var($numauthorquery);
	if ($numauthors > 1) {
		echo '<h2 style="margin-top:20px;">Author stats for the last year</h2>';
		bm_author_stats("year");
		echo '<h2 style="margin-top:20px;">Author stats </h2>';
		bm_author_stats("alltime");
	}
	echo '</div>';
}

function bm_admin_menu() {
	add_submenu_page('index.php', 'Blog Metrics', 'Blog Metrics', 2,basename( __FILE__), 'bm_dashboard');
}

//This function creates a backend option panel for the plugin.  It stores the options using the wordpress get_option function.
function widget_blog_metrics_control() {
	$options = get_option('blogmetricswidget');

	if ( !is_array($options) ) {
	// Defaults
		$options = array(
			'title'				=> 'Updated Blog Metrics');

	}
		if ( $_POST['blogmetricswidget-submit'] ) {
			$options['title'] 				= strip_tags(stripslashes($_POST['blogmetricswidget-title']));

			update_option('blogmetricswidget', $options);
		}

		$title = htmlspecialchars($options['title'], ENT_QUOTES);

		//You need one of these for each option/parameter.  You can use input boxes, radio buttons, checkboxes, etc.
	?>
		<table>
			<tr>
				<th scope="row" style="text-align: right;">Title</th>
				<td><input style="width: 150px;" id="blogmetricswidget-title" name="blogmetricswidget-title" type="text" value="<?php echo $title; ?>" /></td>
			</tr>
			<tr><td>&nbsp;<input type="hidden" id="blogmetricswidget-submit" name="blogmetricswidget-submit" value="1" /></td></tr>
		</table>
	<?php
}
function widget_blog_metrics_init() {
	if (!function_exists('register_sidebar_widget'))
		return;

	function widget_blog_metrics($args) {
		extract($args);

		$options	= get_option('BlogMetricsWidget');
		$title		= $options['title'];

		echo $before_widget;
		echo $before_title . $title . $after_title;

		$stats = bm_get_stats();
		if ($stats['period'] == "alltime") {
			$per = "per";
		} else if ($stats['period'] == "year") {
			$per = "this";
		}

		echo '<ul>';
		echo '<li><strong>Blog stats</strong></li>';
		echo '<li><ul>';
		echo '<li>'.$stats['posts']." posts</li>\n";
		echo '<li>'.$stats['comments']." comments</li>\n";
		echo '<li>'.$stats['trackbacks']." trackbacks</li>\n";
		echo '</ul></li>';
		echo '<li><br/><strong>Raw Author Contribution </strong></li>';
		echo '<li><ul>';
		if ($stats['avgposts'] == 1) {
			echo '<li>'.$stats['avgposts']." post $per year</li>\n";
		} else {
			echo '<li>'.$stats['avgposts']." posts $per year</li>\n";
		}
		echo '<li>'.''.round($stats['postwords'] / $stats['posts'])." words per post</li>\n";
		echo '</ul></li>';
		echo '<li><br/><strong>Conversation Rate</strong></li>';
		echo '<li><ul>';
		echo '<li>'.$stats['avgcomments'].' comments per post'."</li>\n";
		if ($stats['commentwords'] > 0) {
			echo '<li>'.floor($stats['commentwords'] / $stats['posts'])." words in comments</li>\n";
		}
		echo '<li>'.$stats['avgtrackbacks']." trackbacks per post</li>\n";
		echo "</ul></li>\n";

		echo "</ul>\n";
		echo $after_widget;
	}

	register_sidebar_widget('Blog Metrics Widget', 'widget_blog_metrics');
	register_widget_control('Blog Metrics Widget', 'widget_blog_metrics_control', 350);
}

add_action('plugins_loaded', 'widget_blog_metrics_init');
add_action('admin_menu', 'bm_admin_menu');
add_action('admin_menu', array('BM_Admin','add_config_page'));

?>
