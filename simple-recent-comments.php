<?php
/*
Plugin name: Simple Recent Comments
Version: 0.1
Description: Plugin for displaying recent commits in a widget.
Author: sean
Author URI:
*/

namespace WGOM;

class SimpleRecentComments extends \WP_Widget {

	/*
	private static $option_key = 'simple-recent-comments';
	private static $options = array(
		'number-comments' => 10,
		'maximum-length' => 120,
		'group-comments' => false,
		'comment-template' => "",
		'shortcodes' => [],
	);
	 */

	private static $cache_key = 'comment_cache';
	private static $cache_group = 'simple_recent_comments';
	// Automatically expire cache after 30 minutes.
	private static $cache_expiration = 1800;

	private static $menu_slug = 'simple-recent-comments';
	private static $comments_section = 'src_comments_section';

	private static $options = array(
		array(
			'id' => 'simple_recent_comments_number',
			'title' => "Show the most recent comments",
			'args' => array(
				'type' => 'integer',
				'default' => 10,
			),
		),
		array(
			'id' => 'simple_recent_comments_maximum_length',
			'title' => "Maximum comment length",
			'args' => array(
				'type' => 'integer',
				'default' => 100,
			),
		),
		array(
			'id' => 'simple_recent_comments_comment_template',
			'title' => "Comment template",
			'args' => array(
				'type' => 'string',
				'default' => '<li><a href="%comment_link" title="%post_title">%comment_author</a>: %comment_excerpt</li>',
			),
		),
		array(
			'id' => 'simple_recent_comments_group_by_post',
			'title' => "Group comments by post",
			'args' => array(
				'type' => 'boolean',
				'default' => false,
			),
		),
		array(
			'id' => 'simple_recent_comments_post_header_template',
			'title' => "Grouped post header template",
			'args' => array(
				'type' => 'string',
				'default' => '<li>In response to <a href="%post_link" title="%post_title">%post_title</a><ul>',
			),
		),
		array(
			'id' => 'simple_recent_comments_post_footer_template',
			'title' => "Grouped post footer template",
			'args' => array(
				'type' => 'string',
				'default' => '</ul></li>',
			),
		),
	);

	public function __construct() {
		$widget_ops = array(
			'classname' => 'Simple Recent Comments',
			'description' => 'Display recent comments'
		);
		parent::__construct('simple-recent-comments', 'Simple Recent Comments', $widget_ops);

		\add_action('admin_init', array(self::class, 'add_settings'));
		\add_action('admin_menu', array(self::class, 'menu_init'));
	}

	public function __destruct() {
	}

	public static function menu_init() {
		\add_options_page(
			'Simple Recent Comments',
			'Recent Comments',
			'manage_options',
			'simple-recent-comments',
			array(self::class, 'cb_options_page')
		);
	}

	public static function add_settings() {
		// Already on a new settings page so do not want a title or a
		// callback for the section. There is only a single setting so
		// adding extra output from the section only adds clutter.
		\add_settings_section(self::$comments_section, 'Simple Recent Comments', '', self::$menu_slug);

		foreach (self::$options as $_key => $opt) {
			\add_settings_field(
				$opt['id'],
				$opt['title'],
				array(self::class, 'cb_settings_field'),
				self::$menu_slug,
				self::$comments_section,
				$opt
			);
			\register_setting(self::$menu_slug, $opt['id'], $opt['args']);
		}
	}

	public static function cb_options_page() {
	?>
	<form method="post" action="options.php">
	<?php
		\settings_fields(self::$menu_slug);
		\do_settings_sections(self::$menu_slug);
		\submit_button();
	?>
	</form>
	<?php
	}

	public static function cb_settings_field($args) {
		$option_id = $args['id'];
		if (mb_strpos($option_id, 'template')) {
			self::cb_settings_template($args);
			return;
		}

		$option_value = get_option($args['id']);
		$args_type = $args['args']['type'];
		if ($args_type === 'boolean') {
			$checked = \checked('on', $option_value, false);
			echo "<input id='$option_id' name='$option_id' type='checkbox' $checked></input>";
		}
		elseif ($args_type === 'integer') {
			echo "<input id='$option_id' name='$option_id' size='3' value='$option_value'></input>";
		}
		elseif ($args_type === 'string') {
			?><textarea name="<?php echo $option_id ?>" id="<?php echo $option_id ?>" cols="72" rows="2"><?php echo $option_value ?></textarea><?php
		}
	}

	public static function cb_settings_template($args) {
		$option_id = $args['id'];
		$option_value = get_option($args['id']);
?>
<textarea name="<?php echo $option_id ?>" id="<?php echo $option_id ?>" cols="72" rows="2"><?php echo $option_value ?></textarea>
<details>
  <summary>Available tags to use in template.</summary>
  <div>
    <ul>
      <li><code>%comment_excerpt</code> - Shortened comment.</li>
      <li><code>%comment_link</code> - Link to the comment.</li>
      <li><code>%comment_author</code> - Name of the commenter.</li>
      <li><code>%comment_date</code> - Date of comment.</li>
      <li><code>%comment_time</code> - Time of comment.</li>
      <li><code>%post_title</code> - Title of the post.</li>
      <li><code>%post_link</code> - Link to the post.</li>
    </ul>
  </div>
</details>
<?php
	}

	public function form($instance) {
		// Nothing to configure on the widget page.
	}

	public function update($new_instance, $old_instance) {
		// Nothing to update on the widget page.
	}

	public function widget($args, $instance) {
		$content = \wp_cache_get(self::$cache_key, self::$cache_group);
		if (!$content) {
			$content = $this->generate($instance);
			\wp_cache_set(self::$cache_key, $content, self::$cache_group, self::$cache_expiration);
		}

		extract($args, EXTR_SKIP);
		echo $before_widget;
		echo $content;
		echo $after_widget;
	}

	private function generate($instance) {
		$comment_number = \get_option('simple_recent_comments_number');
		$maximum_length = \get_option('simple_recent_comments_maximum_length');
		$comment_template = \get_option('simple_recent_comments_comment_template');
		$group_comments = \get_option('simple_recent_comments_group_by_post');
		$post_header_template = \get_option('simple_recent_comments_post_header_template');
		$post_footer_template = \get_option('simple_recent_comments_post_footer_template');

		$date_format = \get_option('date_format');
		$time_format = \get_option('time_format');

		$html = "<ul>";
		$output = "";
		$results = $this->fetch_comments($comment_number, $group_comments);
		//$html .= '<pre>' . var_export($results, true) . '</pre>';
		$grouped = $this->group_by_post($results, $group_comments);
		//$html .=  '<pre>' . var_export($grouped, true) . '</pre>';
		$posts = $grouped['posts'];
		$group_order = $grouped['order'];
		$groups = $grouped['groups'];
		foreach ($group_order as $group_id) {
			$post_id = $groups[$group_id][0]->post_ID;
			$post_title = $posts[$group_id]['title'];
			$post_link = $posts[$group_id]['link'];
			$post_patterns = array(
				'/%post_link/',
				'/%post_title/',
			);

			$post_replacements = array(
				$post_link,
				$post_title,
			);

			if ($group_comments) {
				$html .= preg_replace($post_patterns, $post_replacements, $post_header_template);
			}

			foreach ($groups[$group_id] as $comment) {
				$excerpt = $this->comment_excerpt($comment->comment_content, $maximum_length);

				$patterns = array(
					'/%comment_excerpt/',
					'/%comment_link/',
					'/%comment_author/',
					'/%comment_date/',
					'/%comment_time/',
				) + $post_patterns;

				$post_id = $comment->post_ID;
				$post_link = $posts[$group_id]['link'];
				$comment_link = "";
				if ($post_link) {
					$comment_link = $post_link . "#comment-{$comment->comment_ID}";
				}
				$replacements = array(
					$excerpt,
					$comment_link,
					$comment->comment_author,
					\mysql2date($date_format, $comment->comment_date),
					\mysql2date($time_format, $comment->comment_date),
				) + $post_replacements;

				$rendered = \preg_replace($patterns, $replacements, $comment_template);
				$html .= $rendered;
			}

			if ($group_comments) {
				$html .= preg_replace($post_patterns, $post_replacements, $post_footer_template);
			}
		}

		$html .= "</ul>";
		return $html;
	}

	private function fetch_comments($comment_number, $group_comments) {
		global $wpdb;
		$query = $wpdb->prepare(
			"SELECT comments.comment_ID, comments.comment_author, comments.comment_date, comments.comment_content, posts.ID as post_ID, posts.post_title as post_title " .
			"FROM $wpdb->comments comments LEFT JOIN $wpdb->posts posts " .
			"ON comments.comment_post_ID = posts.ID " .
			"WHERE comments.comment_approved = '1' AND comments.comment_type = 'comment' " .
			"AND posts.post_password = '' " .
			"ORDER BY comment_date_gmt DESC " .
			"LIMIT $comment_number"
		);
		echo "<!-- query: {$query} -->";
		$results = $wpdb->get_results($query);
		return $results;
	}

	/*
	 * Group comments by post if that option is enabled. If not, group
	 * every comment under a dummy post to keep the code consistent.
	 */
	private function group_by_post($comments, $group_comments) {
		$grouped = array();
		$posts = array();
		$order = array();
		$seen_posts = array();

		foreach ($comments as $comment) {
			$post_id = $comment->post_ID;

			// Always store post ID and post title. The ID is
			// needed to get the permalink to the post.
			if (!array_key_exists($post_id, $posts)) {
				$posts[$post_id] = array(
					'title' => $comment->post_title,
					'link' => \get_permalink($post_id),
				);
			}

			// Force every comment to be under post "0" if not grouped.
			if (!$group_comments) {
				$post_id = 0;
			}
			if (!array_key_exists($post_id, $grouped)) {
				$grouped[$post_id] = array();
			}
			$grouped[$post_id][] = $comment;

			if (!array_key_exists($post_id, $seen_posts)) {
				$order[] = $post_id;
				$seen_posts[$post_id] = true;
			}
		}

		$results = array(
			'groups' => $grouped,
			'posts' => $posts,
			'order' => $order,
		);
		return $results;
	}

	private function comment_excerpt($comment_content, $maximum_length) {
		$excerpt = \wp_strip_all_tags($comment_content);
		// Allow three extra characters because the excerpt will have
		// an ellipsis appended to it.
		if (mb_strlen($excerpt) > ($maximum_length + 3)) {
			$excerpt = mb_substr($excerpt, 0, $maximum_length) . "...";
		}

		return $excerpt;
	}
}

\add_action('widgets_init', function () { \register_widget("WGOM\\SimpleRecentComments"); });

?>
