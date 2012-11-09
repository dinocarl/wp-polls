<?php
/*
+----------------------------------------------------------------+
|																							|
|	WordPress Plugin: WP-Polls										|
|	Copyright (c) 2012 Lester "GaMerZ" Chan									|
|																							|
|	File Written By:																	|
|	- Lester "GaMerZ" Chan															|
|	- http://lesterchan.net															|
|																							|
|	File Information:																	|
|	- Polls Logs																			|
|	- wp-content/plugins/wp-polls/polls-logs.php								|
|																							|
+----------------------------------------------------------------+
*/


### Check Whether User Can Manage Polls
if(!current_user_can('manage_polls')) {
	die('Access Denied');
}

/**
 * Functions
 */

/**
 * set_sql_limit()
 * 
 * @param int $page - The current page number
 * @return string - the SQL LIMIT
 */
function set_sql_limit($page) {
	
	global $results_per_page;
	
	$row = 0;
	
	for($i=1; $i<$page; $i++) {
		
		$row = $row + $results_per_page;
	}
	
	return ' LIMIT ' . $row . ',' . $results_per_page;
}

/**
 * return_page_link() -- Returns the page link target given the page number.
 * 
 * @param int $page_num
 * @return string - the page link URL
 */
function return_page_link($page_num) {
	
	global $prev_page;

	$current_url = (! empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	
	if(isset($_GET['pg']) || $prev_page) {
		
		unset($_GET['pg']);
		
		$url_parts = parse_url($current_url);
		
		//Reassemble query string
		$qs = '?';
		
		foreach($_GET as $key=>$value) {
			
			$qs .= $key.'='.$value.'&';
		}
		
		$qs .= 'pg=' . $page_num;
		
		return wp_nonce_url($url_parts['scheme'] . '://' . $url_parts['host'] . ((isset($url_parts['port'])) ? ':'.$url_parts['port'] : null) .$url_parts['path'] . $qs . return_filter_params_qs(), 'wp-polls_logs');
		
	}
	
		return wp_nonce_url($current_url . '&pg=' . $page_num . return_filter_params_qs(), 'wp-polls_logs');
}

/**
 * showing_results() -- Returns the showing results text
 * 
 * @param int $page_num
 * @return string - Showing results text
 */
function showing_results($page_num) {
	
	global $results_per_page, $poll_ips, $poll_logs_count;
	
	$omega = $page_num * $results_per_page;
	$begin = ($page_num != 1) ? (($omega - $results_per_page) + 1) : 1;
	$end = ($page_num != 1) ? (($begin + count($poll_ips)) - 1) : count($poll_ips);
	
	return ($begin != $end) ? 'Showing records ' . $begin . ' - ' . $end : 'Showing record ' . $begin;
}

/**
 * return_filter_params_qs() -- Returns querystring params used on filter. Adds POST vars to querystring.
 * May not need to use this at all.
 * 
 * @param void
 * @return string|null
 */
function return_filter_params_qs() {
	
	if(isset($_POST['do']) && $_POST['do'] == 'Filter') {
		
		return '&do=Filter&users_voted_for='. $_POST['users_voted_for'] . '&filter=' . $_POST['filter'];
	}
	
	return null;
}


//Pagination
$page = (isset($_GET['pg'])) ? $_GET['pg'] : 1;
$results_per_page = 200;
$limit = set_sql_limit($page);

### Variables

$pollip_answers = array();

//Basic Poll Data
$poll_question_data = $wpdb->get_row("SELECT pollq_multiple, pollq_question, pollq_totalvoters FROM $wpdb->pollsq WHERE pollq_id = $poll_id");
$poll_question = stripslashes($poll_question_data->pollq_question);
$poll_totalvoters = intval($poll_question_data->pollq_totalvoters);
$poll_multiple = intval($poll_question_data->pollq_multiple);

//Number of answers submitted by registered users
$poll_registered = $wpdb->get_var("SELECT COUNT(pollip_userid) FROM $wpdb->pollsip WHERE pollip_qid = $poll_id AND pollip_userid > 0");

//Number of answers submitted from comment authors
$poll_comments =  $wpdb->get_var("SELECT COUNT(pollip_user) FROM $wpdb->pollsip WHERE pollip_qid = $poll_id AND pollip_user != '".__('Guest', 'wp-polls')."' AND pollip_userid = 0");

//Number of polls submitted by guests
$poll_guest = $wpdb->get_var("SELECT COUNT(pollip_user) FROM $wpdb->pollsip WHERE pollip_qid = $poll_id AND pollip_user = '".__('Guest', 'wp-polls')."'");

//Total answers submitted by registered, comment authors, and guests
$poll_totalrecorded = ($poll_registered+$poll_comments+$poll_guest);

//Get poll answers configured for this poll
$poll_answers_data = $wpdb->get_results("SELECT polla_aid, polla_answers FROM $wpdb->pollsa WHERE polla_qid = $poll_id ORDER BY ".get_option('poll_ans_sortby').' '.get_option('poll_ans_sortorder'));

//Get list of users who submitted answers for this poll
//$poll_voters = $wpdb->get_col("SELECT DISTINCT pollip_user FROM $wpdb->pollsip WHERE pollip_qid = $poll_id AND pollip_user != '".__('Guest', 'wp-polls')."' ORDER BY pollip_user ASC");

//Total number of answers submitted for this poll
$poll_logs_count = $wpdb->get_var("SELECT COUNT(pollip_id) FROM $wpdb->pollsip WHERE pollip_qid = $poll_id");


### Process Filters
if(!empty($_REQUEST['do'])) {
	
	
	if(! wp_verify_nonce($_REQUEST['_wpnonce'], 'wp-polls_logs')) die('You do not have permission to perform this action');
	
	$comment_sql = '';
	$guest_sql = '';
	$users_voted_for_sql = '';
	$what_user_voted_sql = '';
	$num_choices_sql = '';
	$num_choices_sign_sql = '';
	$order_by = '';
	switch(intval($_REQUEST['filter'])) {
		case 1:
			$users_voted_for = intval($_REQUEST['users_voted_for']);
			
			$users_voted_for_sql = "AND pollip_aid = $users_voted_for";
			
			$order_by = 'pollip_timestamp DESC';
			break;
		case 2:
			$exclude_registered_2 = intval($_POST['exclude_registered_2']);
			$exclude_comment_2 = intval($_POST['exclude_comment_2']);
			$num_choices = intval($_POST['num_choices']);
			$num_choices_sign = addslashes($_POST['num_choices_sign']);
			switch($num_choices_sign) {
				case 'more':
					$num_choices_sign_sql = '>';
					break;
				case 'more_exactly':
					$num_choices_sign_sql = '>=';
					break;
				case 'exactly':
					$num_choices_sign_sql = '=';
					break;
				case 'less_exactly'://var_dump($poll_logs_count);
					$num_choices_sign_sql = '<=';
					break;
				case 'less':
					$num_choices_sign_sql = '<';
					break;
			}
			if($exclude_registered_2) {
				$registered_sql = 'AND pollip_userid = 0';
			}
			if($exclude_comment_2) {
				if(!$exclude_registered_2) {
					$comment_sql = 'AND pollip_userid > 0';
				} else {
					$comment_sql = 'AND pollip_user = \''.__('Guest', 'wp-polls').'\'';
				}
			}
			$guest_sql  = 'AND pollip_user != \''.__('Guest', 'wp-polls').'\'';
			$num_choices_query = $wpdb->get_col("SELECT pollip_user, COUNT(pollip_ip) AS num_choices FROM $wpdb->pollsip WHERE pollip_qid = $poll_id GROUP BY pollip_ip, pollip_user HAVING num_choices $num_choices_sign_sql $num_choices");
			$num_choices_sql = 'AND pollip_user IN (\''.implode('\',\'',$num_choices_query).'\')';
			$order_by = 'pollip_user, pollip_ip';
			break;
		case 3;
			$what_user_voted = addslashes($_POST['what_user_voted']);
			$what_user_voted_sql = "AND pollip_user = '$what_user_voted'";
			$order_by = 'pollip_user, pollip_ip';
			break;
	}
	
	$poll_logs_count = $wpdb->get_var("SELECT COUNT(pollip_aid) FROM $wpdb->pollsip WHERE pollip_qid = $poll_id $users_voted_for_sql $comment_sql $guest_sql $what_user_voted_sql $num_choices_sql ORDER BY $order_by");
	$poll_ips = $wpdb->get_results("SELECT $wpdb->pollsip.*, $wpdb->users.user_email FROM $wpdb->pollsip INNER JOIN $wpdb->users ON $wpdb->pollsip.pollip_userid = $wpdb->users.ID WHERE pollip_qid = $poll_id $users_voted_for_sql $comment_sql $guest_sql $what_user_voted_sql $num_choices_sql ORDER BY $order_by $limit");
} else {
	
	$poll_ips = $wpdb->get_results("SELECT p.pollip_aid, p.pollip_ip, p.pollip_host, p.pollip_timestamp, p.pollip_user, u.user_email FROM $wpdb->pollsip p INNER JOIN $wpdb->users u ON p.pollip_userid = u.ID WHERE p.pollip_qid = $poll_id ORDER BY pollip_aid ASC, pollip_user ASC $limit");
}
?>

<?php if(!empty($text)) { echo '<!-- Last Action --><div id="message" class="updated fade">'.stripslashes($text).'</div>'; } else { echo '<div id="message" class="updated" style="display: none;"></div>'; } ?>
<div class="wrap">
	<div id="icon-wp-polls" class="icon32"><br /></div>
	<h2><?php _e('Poll\'s Logs', 'wp-polls'); ?></h2>
	<h3><?php echo $poll_question; ?></h3>
	<p>
		<?php printf(_n('There are a total of <strong>%s</strong> recorded vote for this poll.', 'There are a total of <strong>%s</strong> recorded votes for this poll.', $poll_totalrecorded, 'wp-polls'), number_format_i18n($poll_totalrecorded)); ?><br />
	</p>
</div>
<?php if($poll_totalrecorded > 0):?>
<div class="wrap">
	<h3><?php _e('Filter Poll\'s Logs', 'wp-polls') ?></h3>
	<table width="100%"  border="0" cellspacing="0" cellpadding="0">
		<tr>
			<td width="50%">
				<form method="post" action="<?php echo admin_url('admin.php?page='.$base_name.'&amp;mode=logs&amp;id='.$poll_id); ?>">
				
				<input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('wp-polls_logs')?>" />
				
				<p style="display: none;"><input type="hidden" name="filter" value="1" /></p>
				<table class="form-table">
					<tr>
						<th scope="row" valign="top"><?php _e('Display All Users That Voted For', 'wp-polls'); ?></th>
						<td>
							<select name="users_voted_for" size="1">
								<?php
									if($poll_answers_data) {
										foreach($poll_answers_data as $data) {
											$polla_id = intval($data->polla_aid);
											$polla_answers = stripslashes(strip_tags(htmlspecialchars($data->polla_answers)));
											if($polla_id  == $users_voted_for) {
												echo '<option value="'.$polla_id .'" selected="selected">'.$polla_answers.'</option>';
											} else {
												echo '<option value="'.$polla_id .'">'.$polla_answers.'</option>';
											}
											$pollip_answers[$polla_id] = $polla_answers;
										}
									}
								?>
							</select>
							<input type="submit" name="do" value="<?php _e('Filter', 'wp-polls'); ?>" class="button" />
						</td>
						
						<tr><td colspan="2"><input type="button" value="<?php _e('Clear Filter', 'wp-polls'); ?>" onclick="self.location.href = '<?php echo htmlspecialchars($base_page); ?>&amp;mode=logs&amp;id=<?php echo $poll_id; ?>';" class="button" /></td></tr>
				</table>
				</form>
			</td>
		</tr>
	</table>
</div>
<p>&nbsp;</p>
<?php endif;?>

<div class="wrap">
	<h3><?php _e('Poll Logs', 'wp-polls'); ?></h3>
	<div id="poll_logs_display">
		<?php
			if($poll_ips) {
				
				if(empty($_REQUEST['do'])) {
					echo '<p>'.__('There are  <strong>'. $poll_logs_count .'</strong> records for this poll.', 'wp-polls') .'</p>';
				} else {
					echo '<p>' . __('There are  <strong>'. $poll_logs_count .'</strong> filtered records for this poll.', 'wp-polls') . '</p>';
				}
				echo '<table class="widefat">'."\n";
				$k = 1;
				$j = 0;
				$poll_last_aid = -1;
				
				if(isset($_REQUEST['filter']) && (intval($_REQUEST['filter']) > 1)) {
					
					echo "<tr class=\"thead\">\n";
					echo "<th>".__('Answer', 'wp-polls')."</th>\n";
					echo "<th>".__('IP', 'wp-polls')."</th>\n";
					echo "<th>".__('Host', 'wp-polls')."</th>\n";
					echo "<th>".__('E-mail', 'wp-polls')."</th>\n";
					echo "<th>".__('Date', 'wp-polls')."</th>\n";
					echo "</tr>\n";
					
					foreach($poll_ips as $poll_ip) {
						$pollip_aid = intval($poll_ip->pollip_aid);
						$pollip_user = stripslashes($poll_ip->pollip_user);
						$pollip_email = $poll_ip->user_email;
						$pollip_ip = $poll_ip->pollip_ip;
						$pollip_host = $poll_ip->pollip_host;
						$pollip_date = mysql2date(sprintf(__('%s @ %s', 'wp-polls'), get_option('date_format'), get_option('time_format')), gmdate('Y-m-d H:i:s', $poll_ip->pollip_timestamp));
						if($i%2 == 0) {
							$style = '';
						}  else {
							$style = 'class="alternate"';
						}
						if($pollip_user != $temp_pollip_user) {
							echo '<tr class="highlight">'."\n";
							echo "<td colspan=\"5\"><strong>".__('User', 'wp-polls')." ".number_format_i18n($k).": $pollip_user</strong></td>\n";
							echo '</tr>';
							$k++;
						}		
						echo "<tr $style>\n";
						echo "<td>{$pollip_answers[$pollip_aid]}</td>\n";
						echo "<td><a href=\"http://ws.arin.net/cgi-bin/whois.pl?queryinput=$pollip_ip\" title=\"$pollip_ip\">$pollip_ip</a></td>\n";
						echo "<td>$pollip_host</td>\n";
						echo "<td>$pollip_email</td>\n";
						echo "<td>$pollip_date</td>\n";
						echo "</tr>\n";
						$temp_pollip_user = $pollip_user;				
						$i++;
						$j++;
					}
				} else {
					foreach($poll_ips as $poll_ip) {
						
						$pollip_aid = intval($poll_ip->pollip_aid);
						$pollip_user = stripslashes($poll_ip->pollip_user);
						$pollip_email = $poll_ip->user_email;
						$pollip_ip = $poll_ip->pollip_ip;
						$pollip_host = $poll_ip->pollip_host;
						$pollip_date = mysql2date(sprintf(__('%s @ %s', 'wp-polls'), get_option('date_format'), get_option('time_format')), gmdate('Y-m-d H:i:s', $poll_ip->pollip_timestamp)); 
						if($pollip_aid != $poll_last_aid) {
							if($pollip_aid == 0) {
								echo "<tr class=\"highlight\">\n<td colspan=\"5\"><strong>$pollip_answers[$pollip_aid]</strong></td>\n</tr>\n";
							} else {
								echo "<tr class=\"highlight\">\n<td colspan=\"5\"><strong>".__('Answer', 'wp-polls')." ".number_format_i18n($k).": $pollip_answers[$pollip_aid]</strong></td>\n</tr>\n";
								$k++;
							}
							echo "<tr class=\"thead\">\n";
							echo "<th>".__('No.', 'wp-polls')."</th>\n";
							echo "<th>".__('User', 'wp-polls')."</th>\n";
							echo "<th>".__('E-mail', 'wp-polls')."</th>\n";
							echo "<th>".__('IP/Host', 'wp-polls')."</th>\n";
							echo "<th>".__('Date', 'wp-polls')."</th>\n";
							echo "</tr>\n";
							$i = 1;
						}
						if($i%2 == 0) {
							$style = '';
						}  else {
							$style = 'class="alternate"';
						}
						echo "<tr $style>\n";
						echo "<td>".number_format_i18n($i)."</td>\n";
						echo "<td>$pollip_user</td>\n";
						echo "<td>$pollip_email</td>\n";
						echo "<td><a href=\"http://ws.arin.net/cgi-bin/whois.pl?queryinput=$pollip_ip\" title=\"$pollip_ip\">$pollip_ip</a> / $pollip_host</td>\n";
						echo "<td>$pollip_date</td>\n";
						echo "</tr>\n";
						$poll_last_aid = $pollip_aid;
						$i++;
						$j++;
					}
				}
				
				//Pagination prev & next page vars
				$prev_page = ($poll_logs_count > $results_per_page && $page > 1) ? ($page - 1) : false;
				$next_page = ($poll_logs_count > $results_per_page && (($results_per_page * $page) < $poll_logs_count)) ? ($page + 1) : false;
				
				echo "<tr class=\"highlight\">\n";
				echo "<td colspan=\"3\">". showing_results($page)."</td>";
				echo "<td colspan=\"2\" align=\"right\">";
				
					if($prev_page) {
						
						echo "<a href='" . return_page_link($prev_page) . "'>&lt;&lt; Previous</a> &nbsp;&nbsp;";
					} 
					
					if($next_page) {
						
						echo "<a href='" . return_page_link($next_page) . "'>Next &gt;&gt;</a>";
					}
					
				echo "</td>";
				echo "</tr>\n";
				echo '</table>'."\n";
			}
		?>
	</div>
	<?php if(!empty($_POST['do'])):?>
		<br class="clear" /><div id="poll_logs_display_none" style="text-align: center; display: <?php if(!$poll_ips) { echo 'block'; } else { echo 'none'; } ?>;" ><?php _e('No poll logs matches the filter.', 'wp-polls'); ?></div>
	<?php else:?>
		<br class="clear" /><div id="poll_logs_display_none" style="text-align: center; display: <?php if(!$poll_logs_count) { echo 'block'; } else { echo 'none'; } ?>;" ><?php _e('No poll logs available for this poll.', 'wp-polls'); ?></div>
	<?php endif; ?>
</div>
<p>&nbsp;</p>
