<?php
/*
Plugin name: Simple Recent Comments
Version: 0.3
Description: Plugin for displaying recent commits in a widget.
Author: sean
Author URI:
*/

namespace WGOM;

class SimpleRecentComments extends \WP_Widget {

	private static $cache_key = 'comment_cache';
	private static $cache_group = 'simple_recent_comments';
	// Automatically expire cache after 30 minutes.
	private static $cache_expiration = 1800;

	private static $menu_slug = 'simple-recent-comments';
	private static $comments_section = 'src_comments_section';

	private static $options = array(
		'simple_recent_comments_number' => array(
			'title' => "Show the most recent comments",
			'callback' => 'cb_settings_field_integer',
			'default' => 10,
		),
		'simple_recent_comments_maximum_length' => array(
			'title' => "Maximum comment length",
			'callback' => 'cb_settings_field_integer',
			'default' => 100,
		),
		'simple_recent_comments_comment_template' => array(
			'title' => "Comment template",
			'callback' => 'cb_settings_field_template',
			'default' => '<li><a href="%comment_link" title="%post_title">%comment_author</a>: %comment_excerpt</li>',
		),
		'simple_recent_comments_group_by_post' => array(
			'title' => "Group comments by post",
			'callback' => 'cb_settings_field_checkbox',
			'default' => false,
		),
		'simple_recent_comments_post_header_template' => array(
			'title' => "Grouped post header template",
			'callback' => 'cb_settings_field_template',
			'default' => '<li>In response to <a href="%post_link" title="%post_title">%post_title</a><ul>',
		),
		'simple_recent_comments_post_footer_template' => array(
			'title' => "Grouped post footer template",
			'callback' => 'cb_settings_field_template',
			'default' => '</ul></li>',
		),
		'simple_recent_comments_shorten_shortcode' => array(
			'title' => "Shortcodes to shorten",
			'callback' => 'cb_settings_field_shortcode',
			'default' => '',
		),
	);

	public function __construct() {
		$widget_ops = array(
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

		foreach (self::$options as $id => $opt) {
			\add_settings_field(
				$id,
				$opt['title'],
				array(self::class, $opt['callback']),
				self::$menu_slug,
				self::$comments_section,
				array(
					'id' => $id,
				)
			);
			\register_setting(self::$menu_slug, $id, array('default' => $opt['default']));
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

	public static function cb_settings_field_checkbox($args) {
		$option_id = $args['id'];
		$option_value = \get_option($option_id);
		$checked = \checked('on', $option_value, false);
		echo "<input id='$option_id' name='$option_id' type='checkbox' $checked></input>";
	}

	public static function cb_settings_field_integer($args) {
		$option_id = $args['id'];
		$option_value = \get_option($option_id);
		echo "<input id='$option_id' name='$option_id' size='3' value='$option_value'></input>";
	}

	public static function cb_settings_field_template($args) {
		$option_id = $args['id'];
		$option_value = \get_option($args['id']);
?>
<textarea name="<?php echo $option_id ?>" id="<?php echo $option_id ?>" cols="72" rows="2"><?php echo $option_value ?></textarea>
<?php
		if (strpos($option_id, '_comment_') !== false) {
?>
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
		elseif (strpos($option_id, '_post_') !== false) {
?>
<details>
  <summary>Available tags to use in template.</summary>
  <div>
    <ul>
      <li><code>%post_title</code> - Title of the post.</li>
      <li><code>%post_link</code> - Link to the post.</li>
    </ul>
  </div>
</details>
<?php
		}
	}

	public static function cb_settings_field_shortcode($args) {
		$option_id = $args['id'];
		$option_value = \get_option($args['id']);
		?>
<textarea name="<?php echo $option_id ?>" id="<?php echo $option_id ?>" cols="72" rows="2"><?php echo $option_value ?></textarea>
<details>
  <summary>How to use</summary>
  <p>Include each shortcode that should be completely shortened on a separate line. Each shortcode block will be replaced with the shortcode in uppercase.</p>
  <p>For example, add "spoiler" on its own line to display "[spoiler]something spoilery[/spoiler]" in a comment as "SPOILER" in the widget.</p>
  </details>
<?php
	}

	public function form($instance) {
		$defaults = array(
			'title' => 'Recent Comments',
		);

		$instance = wp_parse_args((array) $instance, $defaults);
?>
<p>
    <label for="<?php echo $this->get_field_id('title'); ?>">Title</label>
    <input id="<?php echo $this->get_field_id('title'); ?>" class="widefat" name="<?php echo $this->get_field_name('title'); ?>" value="<?php echo $instance['title']; ?>" />
</p>
<?php
	}

	public function update($new_instance, $old_instance) {
		return $new_instance;
	}

	public function widget($args, $instance) {
		$title = apply_filters('widget_title', $instance['title']);
		$content = \wp_cache_get(self::$cache_key, self::$cache_group);
		if (!$content) {
			$cached = "UNCACHED";
			$content = $this->generate($instance);
			\wp_cache_set(self::$cache_key, $content, self::$cache_group, self::$cache_expiration);
		}
		else {
			$cached = "CACHED";
		}

		extract($args, EXTR_SKIP);
		echo $before_widget;
		if ($title) {
			echo $before_title . $title . $after_title;
		}
		echo "<!-- $cached -->\n" . $content;
		echo $after_widget;
	}

	// Simple wrapper to provide the default value.
	private function get_option($option_id) {
		return \get_option($option_id, self::$options[$option_id]['default']);
	}

	private function generate($instance) {
		$comment_number = $this->get_option('simple_recent_comments_number');
		$maximum_length = $this->get_option('simple_recent_comments_maximum_length');
		$comment_template = $this->get_option('simple_recent_comments_comment_template');
		$group_comments = $this->get_option('simple_recent_comments_group_by_post');
		$post_header_template = $this->get_option('simple_recent_comments_post_header_template');
		$post_footer_template = $this->get_option('simple_recent_comments_post_footer_template');
		$shortcodes = $this->process_shortcode_list();

		$date_format = \get_option('date_format');
		$time_format = \get_option('time_format');

		$html = "<ul>";
		$output = "";
		$results = $this->fetch_comments($comment_number, $group_comments);
		$grouped = $this->group_by_post($results, $group_comments);
		$posts = $grouped['posts'];
		$group_order = $grouped['order'];
		$groups = $grouped['groups'];
		foreach ($group_order as $group_id) {
			$post_patterns = array();
			$post_replacements = array();
			if ($group_comments) {
				$post_id = $groups[$group_id][0]->post_ID;
				$post_title = $posts[$post_id]['title'];
				$post_link = $posts[$post_id]['link'];
				$post_patterns = array(
					'/%post_link/',
					'/%post_title/',
				);

				$post_replacements = array(
					$post_link,
					$post_title,
				);
				$html .= preg_replace($post_patterns, $post_replacements, $post_header_template);
			}

			foreach ($groups[$group_id] as $comment) {
				$excerpt = $this->comment_excerpt(
					$comment->comment_content, $maximum_length, $shortcodes
				);

				$patterns = array(
					'/%comment_link/',
					'/%comment_author/',
					'/%comment_date/',
					'/%comment_time/',
					'/%post_link/',
					'/%post_title/',
					// Keep excerpt last so the comment
					// content will not have anything
					// replaced.
					'/%comment_excerpt/',
				) + $post_patterns;

				$post_id = $comment->post_ID;
				$post_title = $posts[$post_id]['title'];
				$post_link = $posts[$post_id]['link'];
				$comment_link = "";
				if ($post_link) {
					$comment_link = $post_link . "#comment-{$comment->comment_ID}";
				}
				$replacements = array(
					$comment_link,
					$comment->comment_author,
					\mysql2date($date_format, $comment->comment_date),
					\mysql2date($time_format, $comment->comment_date),
					$post_link,
					$post_title,
					$excerpt,
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
		//echo "<!-- query: {$query} -->";
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
				$post_id = "0";
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

	private function comment_excerpt($comment_content, $maximum_length, $shortcodes) {
		$excerpt = \wp_strip_all_tags($comment_content);

		// Convert consecutive newlines into a single line for the excerpt.
		$excerpt = preg_replace('/\R/', " ", $excerpt);

		foreach ($shortcodes as $shortcode) {
			$sc_pattern = $shortcode['re'];
			$sc_replacement = $shortcode['code'];
			$excerpt = preg_replace($sc_pattern, $sc_replacement, $excerpt);
		}

		// HTML will merge multiple whitespace characters together.
		// Convert them all into a single space for the purposes of the
		// widget.
		$excerpt = preg_replace('/\s{2,}/', " ", $excerpt);

		// Allow three extra characters because the excerpt will have
		// an ellipsis appended to it.
		if (mb_strlen($excerpt) > ($maximum_length + 3)) {
			$excerpt = mb_substr($excerpt, 0, $maximum_length) . "...";
		}

		return $excerpt;
	}

	private function process_shortcode_list() {
		$shortcode_option = $this->get_option('simple_recent_comments_shorten_shortcode');
		$shortcodes = array();
		if (!$shortcode_option) {
			return $shortcodes;
		}

		$shortcode_list = preg_split('/\R/', $shortcode_option);
		if ($shortcode_list === false) {
			return $shortcodes;
		}

		foreach ($shortcode_list as $shortcode) {
			$shortcode = trim($shortcode);
			$search = array(
				're' => '/\[(' . $shortcode . ')\b(.*?)(?:(\/))?\](?:(.+?)\[\/\1\])?/',
				'code' => mb_strtoupper($shortcode),
			);
			$shortcodes[] = $search;
		}

		return $shortcodes;
	}
}

\add_action('widgets_init', function () { \register_widget("WGOM\\SimpleRecentComments"); });

?>
