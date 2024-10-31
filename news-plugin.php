<?php

/**
 * Plugin Name: NewsPlugin
 * Plugin URI: http://newsplugin.com/
 * Description: Create custom newsfeeds for your website. Choose keywords, number of articles and  * other settings, put the feed wherever you want using widgets or shortcodes, and watch the fresh  * relevant news headlines appear on your pages (or approve and publish them manually).
 * Author: newsplugin.com
 * Text Domain: news_plugin
 * Domain Path: /languages
 * Version: 1.1.0
 * Author URI: http://newsplugin.com/
 *
 * @package    WordPress
 * @subpackage News Plugin
 * @since 1.0.0
 */

// Prevent ourselves from being run directly.
defined('ABSPATH') or die("No script kiddies please!");

// Include the fetch_feed functionality (to be replaced eventually).
include_once(ABSPATH . WPINC . '/feed.php');

require_once(plugin_dir_path(__FILE__) . 'news-plugin-widget.php');
require_once(plugin_dir_path(__FILE__) . 'news-plugin-utils.php');

/**
 * The NewsPlugin itself, for encapsulating the hooks.
 */
class News_Plugin
{

	/**
	 * Register plugin with WordPress.
	 */
	public function __construct()
	{

		add_action('init', [$this, 'localize']);

		// Widgets.
		add_action('widgets_init', [$this, 'widgets_init']);
		add_action('admin_init', [$this, 'admin_init']);
		add_action('admin_menu', [$this, 'admin_menu']);
		add_action('admin_init', [&$this, 'register_help_section']);
		add_action('admin_init', [&$this, 'register_activation_section']);
		add_action('admin_init', [&$this, 'register_shortcode_section']);
		add_action('admin_init', [&$this, 'register_style_section']);
		add_action('admin_init', [&$this, 'register_feed_section']);
		add_action('admin_init', [&$this, 'register_status_section']);
		add_action('admin_enqueue_scripts', [$this, 'register_admin_scripts']);
		add_action('wp_enqueue_scripts', [$this, 'register_styles']);

		add_action('admin_post_nopriv_news_plugin_save_style', [$this, 'handle_save_style']);
		add_action('admin_post_news_plugin_save_style', [$this, 'handle_save_style']);
		add_action('admin_post_nopriv_news_plugin_send_feedback', [$this, 'handle_send_feedback']);
		add_action('admin_post_news_plugin_send_feedback', [$this, 'handle_send_feedback']);
		add_action('admin_post_nopriv_news_plugin_update_system_info', [$this, 'handle_update_system_info']);
		add_action('admin_post_news_plugin_update_system_info', [$this, 'handle_update_system_info']);

		add_action('admin_init', [$this, 'refresh_plugin_version']);

		register_activation_hook(__FILE__, [$this, 'userSystemCheck_create']);
		register_deactivation_hook(__FILE__, [$this, 'userSystemCheck_deactivation']);
		$usc = get_option('newsPlugin_system_info');
		$api_key = get_option('news_plugin_api_key');
		if (
			!$usc ||
			!isset($usc['info_version']) || ($usc['info_version'] < News_Plugin_Utils::get_system_info_version()) ||
			!isset($usc['api_key']) || ($usc['api_key'] !== $api_key)
		) {
			update_option('newsPlugin_system_info', News_Plugin_Utils::get_system_info());
		}
	}

	/**
	 * Do on user system check creation
	 *
	 * @return void
	 */
	public function userSystemCheck_create()
	{
		add_option('newsPlugin_system_info', News_Plugin_Utils::get_system_info());
		add_option('news_plugin_url_method', false);
	}

	/**
	 * Do on system check deactivation
	 *
	 * @return void
	 */
	public function userSystemCheck_deactivation()
	{
		delete_option('newsPlugin_system_info');
	}

	/**
	 * Refresh plugin version
	 *
	 * @return void
	 */
	public function refresh_plugin_version()
	{
		if (function_exists('get_plugin_data')) {
			$xtime = get_option('news_plugin_version_taken');
			$mtime = filemtime(plugin_dir_path(__FILE__) . 'news-plugin.php');
			if ($mtime > $xtime) {
				News_Plugin_Utils::np_version_hard();
			}
		}
	}

	/**
	 * Load plugin textdomain
	 *
	 * @return void
	 */
	public function localize()
	{
		load_plugin_textdomain('news_plugin', false, basename(dirname(__FILE__)) . '/languages');
	}

	/**
	 * Register the plugin widget, widget areas and widget shorcodes.
	 */
	public function widgets_init()
	{
		register_widget('News_Plugin_Widget');
		for ($area = 1; $area <= 4; $area++) {
			register_sidebar([
				'name' => "NewsPlugin Widget Area {$area}",
				'id' => "newsplugin_widgets_{$area}",
				'description' => "Use the [newsplugin_widgets&nbsp;area={$area}] shortcode to show your newsfeed anywhere you want.",
				'before_widget' => '<div id="%1$s" class="widget %2$s">',
				'after_widget' => '</div>'
			]);
		}
		add_shortcode('newsplugin_widgets', [$this, 'widget_area_shortcode']);
		add_shortcode('newsplugin_feed', [$this, 'feed_shortcode']);
	}

	/**
	 * Process the widget area shortcode.
	 *
	 * @param array $attrs Attributes.
	 * @return string|false
	 */
	public function widget_area_shortcode($attrs)
	{
		$a = shortcode_atts(['area' => '1'], $attrs);
		$sidebar = "newsplugin_widgets_{$a['area']}";
		ob_start();
		if (is_active_sidebar($sidebar)) {
			echo '<div class="newsplugin_widget_area">';
			dynamic_sidebar($sidebar);
			echo '</div>';
		}
		return ob_get_clean();
	}


	// [feed_shortcode title="" keywords="News" count="" age="" sources="" excluded_sources="" search_mode="" search_type="" sort_mode="" link_type="" show_date="" show_source="" show_abstract="" feed_mode=""]

	/**
	 * Process the newsfeed shortcode.
	 *
	 * @param array $attrs Attributes.
	 * @return string|false
	 */
	public function feed_shortcode($attrs)
	{
		$attrs = shortcode_atts([
			'id' => '',
			'title' => '',
			'keywords' => 'News',
			'count' => '',
			'age' => '',
			'sources' => '',
			'excluded_sources' => '',
			'search_mode' => '',
			'search_type' => '',
			'sort_mode' => '',
			'link_open_mode' => '',
			'link_follow' => '',
			'link_type' => '',
			'show_date' => '',
			'show_source' => '',
			'show_abstract' => '',
			'feed_mode' => '',
			'wp_uid' => ''
		], $attrs);
		$newswid = new News_Plugin_Widget();
		$a = $newswid->update($attrs, []);
		$a['id'] = $attrs['id'];
		ob_start();
		the_widget('News_Plugin_Widget', $a, []);
		return ob_get_clean();
	}

	/**
	 * Register the plugin CSS style.
	 */
	public function register_styles()
	{
		wp_register_style('news-plugin', plugin_dir_url(__FILE__) . 'assets/css/news-plugin.css', [], "0.1");
		wp_enqueue_style('news-plugin');
	}

	/**
	 * Register admin scripts
	 *
	 * @return void
	 */
	public function register_admin_scripts()
	{
		$assets_path = plugin_dir_url(__FILE__) . 'assets/';
		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_style('news-plugin', $assets_path . 'css/news-plugin.css');
		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion, WordPress.WP.EnqueuedResourceParameters.NoExplicitVersion
		wp_enqueue_script('news-plugin', $assets_path . 'js/jscolor.min.js', [], false, true);
	}

	/**
	 * Register the plugin options.
	 */
	public function admin_init()
	{
		add_settings_section(
			'default',
			null,
			null,
			'news-plugin-settings'
		);

		add_settings_field(
			'news_plugin_api_key',
			__('Activation Key:', 'news_plugin'),
			[$this, 'settings_api_key'],
			'news-plugin-settings',
			'default'
		);
		register_setting(
			'news-plugin-settings',
			'news_plugin_api_key',
			[$this, 'validate_api_key']
		);

		/*
		 Disable User Mode for now.
	   add_settings_field(
		   'news_plugin_user_mode',
		   __('Choose User Mode:','news_plugin'),
		   array( $this, 'settings_user_mode' ),
		   'news-plugin-settings',
		   'default'
	   );
	   register_setting(
		   'news-plugin-settings',
		   'news_plugin_user_mode',
		   array( $this, 'validate_user_mode' )
	   );
	   */
	}

	/**
	 * Register the plugin menu.
	 */
	public function admin_menu()
	{
		add_menu_page(
			__('NewsPlugin Settings', 'news_plugin'),
			__('NewsPlugin', 'news_plugin'),
			'manage_options',
			'news-plugin-settings',
			[$this, 'newsplugin_options_page'],
			'dashicons-megaphone',
			'3'
		);
		add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_action_links']);
	}

	/*
	 * For easier overriding I declared the keys
	 * here as well as our tabs array which is populated
	 * when registering settings
	 */
	/**
	 * Key - $status
	 *
	 * @var string
	 */
	 private $status_settings_key = 'newsplugin_status_settings';
	/**
	 * Key - feed
	 *
	 * @var string
	 */
	private $feed_settings_key = 'newsplugin_feed_settings';
	/**
	 * Key - style
	 *
	 * @var string
	 */
	private $style_settings_key = 'newsplugin_style_settings';
	/**
	 * Key - activation
	 *
	 * @var string
	 */
	private $activation_settings_key = 'newsplugin_activation_settings';
	/**
	 * Key - shortcode
	 *
	 * @var string
	 */
	private $shortcode_settings_key = 'newsplugin_shortcode_settings';
	/**
	 * Key - help
	 *
	 * @var string
	 */
	private $help_settings_key = 'newsplugin_help_settings';
	/**
	 * Key - key
	 *
	 * @var string
	 */
	private $plugin_options_key = 'news-plugin-settings';
	/**
	 * Key - tabs
	 *
	 * @var array
	 */
	private $plugin_settings_tabs = [];

	/**
	 * Registering the sections - status
	 *
	 * @return void
	 */
	public function register_status_section()
	{
		$this->plugin_settings_tabs[$this->status_settings_key] = 'Server Information';
	}
	/**
	 * Registering the sections - feed
	 *
	 * @return void
	 */
	public function register_feed_section()
	{
		$this->plugin_settings_tabs[$this->feed_settings_key] = 'Send Feedback';
	}
	/**
	 * Registering the sections - style
	 *
	 * @return void
	 */
	public function register_style_section()
	{
		$this->plugin_settings_tabs[$this->style_settings_key] = 'Customize Styles';
	}
	/**
	 * Registering the sections - activation
	 *
	 * @return void
	 */
	public function register_activation_section()
	{
		$this->plugin_settings_tabs[$this->activation_settings_key] = 'Activate NewsPlugin';
	}
	/**
	 * Registering the sections - shortcode
	 *
	 * @return void
	 */
	public function register_shortcode_section()
	{
		$this->plugin_settings_tabs[$this->shortcode_settings_key] = 'Generate Shortcode';
	}
	/**
	 * Registering the sections - help
	 *
	 * @return void
	 */
	public function register_help_section()
	{
		$this->plugin_settings_tabs[$this->help_settings_key] = 'Instructions!';
	}

	/**
	 * Get value with default
	 *
	 * @param array  $arr Array.
	 * @param string $a First index.
	 * @param string $b Second index.
	 * @param mixed  $def Default.
	 * @return mixed
	 */
	public function get_with_default($arr, $a, $b, $def)
	{
 /* Grrr this should be language construct ... oh. It will be. PHP 7. https://wiki.php.net/rfc/isset_ternary */
		if (!is_array($arr)) {
			return $def;
		}
		if (!isset($arr[$a])) {
			return $def;
		}
		if (!isset($arr[$a][$b])) {
			return $def;
		}
		return $arr[$a][$b];
	}

	/**
	 * Plugin Options page rendering goes here, checks
	 * for active tab and replaces key with the related
	 * settings key. Uses the plugin_options_tabs method
	 * to render the tabs.
	 *
	 * @return void
	 */
	public function newsplugin_options_page()
	{

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset($_GET['tab']) ? sanitize_title_with_dashes(wp_unslash($_GET['tab'])) : $this->help_settings_key;
		?>
		<div class="wrap">
			<h2>NewsPlugin Settings</h2>
			<?php $this->newsplugin_options_tabs($tab); ?>
			<?php $key = get_option('news_plugin_api_key');
			if (empty($key)) { ?>
				<div class="error">
					<p><a href="<?php echo esc_url(admin_url('admin.php?page=news-plugin-settings&tab=newsplugin_activation_settings')); ?>">Add Activation Key</a> to the NewsPlugin. Otherwise, the generated shortcodes or NewsPlugin widgets will not work!</p>
				</div>
			<?php } ?>
			<?php if ($tab === $this->activation_settings_key) { ?>
				<form method="post" action="options.php">
					<?php wp_nonce_field('update-options'); ?>
					<?php settings_fields($this->plugin_options_key); ?>
					<?php do_settings_sections($this->plugin_options_key); ?>
					<?php submit_button(); ?>
				</form>
			<?php } elseif ($tab === $this->shortcode_settings_key && !empty($key)) { ?>
				<table id="shortcodeTable" class="form-table">
					<tr>
						<th scope="row">
							<label for="newsplugin_title">Newsfeed Title: </label>
						</th>
						<td>
							<input type="text" id="newsplugin_title" name="newsplugin_title" value="" class="regular-text" onclick="validationFocus('newsplugin_title')" onfocus="validationFocus('newsplugin_title')">
							<p class="description">Give your feed a good name. For example: Canada Solar Energy News</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="newsplugin_keywords">Keywords: </label>
						</th>
						<td>
							<input type="text" id="newsplugin_keywords" name="newsplugin_keywords" value="" class="regular-text" onclick="validationFocus('newsplugin_keywords')" onfocus="validationFocus('newsplugin_keywords')" onchange="validateKeyword()">
							<p class="description" id="keyword_suggestion"></p>
							<p class="description">Use keywords to find relevant news. Example: canada &amp; "solar energy"
								<br>
								Read the <a href="http://newsplugin.com/faq#keyword-tips">FAQ</a> for more examples and keywords tips.
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="newsplugin_articles">Number of Articles: </label>
						</th>
						<td>
							<input type="text" id="newsplugin_articles" name="newsplugin_articles" value="" class="regular-text" onclick="validationFocus('newsplugin_articles')" onfocus="validationFocus('newsplugin_articles')">
							<p class="description">Set how many headlines to show in your feed. Example: 10</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							More Information:
						</th>
						<td>
							<fieldset>
								<label for="newsplugin_more_dates">
									<input type="checkbox" id="newsplugin_more_dates" name="newsplugin_more_dates">
									Show Dates
								</label>
								<br>
								<label for="newsplugin_more_sources">
									<input type="checkbox" id="newsplugin_more_sources" name="newsplugin_more_sources">
									Show Sources
								</label>
								<br>
								<label for="newsplugin_more_abstracts">
									<input type="checkbox" id="newsplugin_more_abstracts" name="newsplugin_more_abstracts">
									Show Abstracts
								</label>
								<br>
								<p class="description">By default, your feed displays headlines only. You can add more information. Example: New Reports on Canada Solar Energy, 12 Feb 2015 (BBC)</p>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="newsplugin_search">Search Mode: </label>
						</th>
						<td>
							<select id="newsplugin_search" name="newsplugin_search">
								<option value="">Default</option>
								<option value="title">Headline Only</option>
								<option value="text">Headline &amp; Full Text</option>
							</select>
							<p class="description">Show news that has your keywords in a headline or anywhere in an article. Default is headlines and full text.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="newsplugin_link_open">Link-Open Mode: </label>
						</th>
						<td>
							<select id="newsplugin_link_open" name="newsplugin_link_open">
								<option value="_self">Same Window</option>
								<option value="_blank">New Tab</option>
							</select>
							<p class="description">Open link in same window or new tab. Default is same window.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="newsplugin_link_follow">Link-Follow Mode: </label>
						</th>
						<td>
							<select id="newsplugin_link_follow" name="newsplugin_link_follow">
								<option value="yes">Yes</option>
								<option value="no">No</option>
							</select>
							<p class="description">Instruct the search engines to follow the link. Default is to follow.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="newsplugin_sort">Sort Mode: </label>
						</th>
						<td>
							<select id="newsplugin_sort" name="newsplugin_sort">
								<option value="">Default</option>
								<option value="relevance">Relevance</option>
								<option value="date">Date</option>
							</select>
							<p class="description">Show feed sorted by date or relevance. Default is by relevance.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="newsplugin_age">News Age Limit (in hours): </label>
						</th>
						<td>
							<input type="text" id="newsplugin_age" name="newsplugin_age" value="0" class="regular-text">
							<p class="description">Don't show articles older than given period. 0 means no limit.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="newsplugin_publishing">Feed Publishing: </label>
						</th>
						<td>
							<select id="newsplugin_publishing" name="newsplugin_publishing">
								<option value="">Default</option>
								<option value="auto">Automatic</option>
								<option value="manual">Manual</option>
							</select>
							<p class="description">Your feed can be automatically updated with new headlines, or you can choose headlines and publish them manually using news buffering. Default is automatic.</p>
						</td>
					</tr>
				</table>
				<p class="submit">
					<?php add_thickbox(); ?>
					<div id="shortcode-generated" style="display:none;"></div>
					<input type="button" id="shortcode_button" value="Generate Shortcode" class="button button-primary" onclick="validateShortcode()">
				</p>
				<script type="text/javascript">
					function validationFocus(id) {
						document.getElementById(id).style.border = "1px solid #ddd";
						document.getElementById(id).style.boxShadow = "0 1px 2px rgba(0, 0, 0, 0.07) inset";
					}

					function validateKeyword() {
						var newsplugin_keywords = document.getElementById('newsplugin_keywords');
						var newsplugin_keywords_value = document.getElementById('newsplugin_keywords').value.toLowerCase();
						var keyword_suggestion = document.getElementById('keyword_suggestion');
						var or = newsplugin_keywords_value.indexOf(" or ");
						var and = newsplugin_keywords_value.indexOf(" and ");
						var comma = newsplugin_keywords_value.indexOf(",");
						var suggestion = '';
						if (or > 0 || and > 0 || comma > 0) {
							newsplugin_keywords.style.border = "1px solid #ff0000";
							newsplugin_keywords.style.boxShadow = "0 1px 2px rgba(255, 0, 0, 0.07) inset";
							suggestion = "<span style='color:red;'>You are using an invalid syntax.<br>Please consider using the suggestion below:</span><br>";
							var text = newsplugin_keywords_value.replace(/ or /g, " | ");
							text = text.replace(/ and /g, " & ");
							text = text.replace(/,/g, " | ");
							suggestion += "<span style='color:#000;font-weight:bold;font-style:normal;'>" + text + "</span>";
							suggestion += "<br><br><p style='font-style:normal;font-weight:bold;margin-top:10px'>Keyword Tips:</p>";
							suggestion += "<ul style='margin-top:5px;list-style: inside none disc;'><li><strong>Symbol | stands for OR</strong><br>Using the | symbol gives you articles for every keyword in your search string.</li><li><strong>Symbol &amp; stands for AND</strong><br>Using the &amp; symbol gives you only those articles that contain all keywords in your search string.</li><li><strong>Quotation marks</strong><br>Using quotation marks ' ' limits your search for exact phrases.</li><li><strong>Asterisk sign</strong><br>Using an asterisk sign * gives you variations of the root keyword. You cannot use it in phrases.</li><li><strong>Parenthesis</strong><br>You can use parenthesis ( ) to adjust the priority of your search phrase evaluation (as common math/boolean expressions).</li></ul><br><br>";
						}
						keyword_suggestion.innerHTML = suggestion;
					}

					function validateShortcode() {
						var newsplugin_title = document.getElementById('newsplugin_title');
						var newsplugin_keywords = document.getElementById('newsplugin_keywords');
						var newsplugin_articles = document.getElementById('newsplugin_articles');
						if (newsplugin_title.value == "" || /^\s*$/.test(newsplugin_title.value) || newsplugin_keywords.value == "" || /^\s*$/.test(newsplugin_keywords.value) || newsplugin_articles.value == "" || /^\s*$/.test(newsplugin_articles.value) || isNaN(newsplugin_articles.value) || parseInt(newsplugin_articles.value) <= 0) {
							if (newsplugin_title.value == "" || /^\s*$/.test(newsplugin_title.value)) {
								newsplugin_title.style.border = "1px solid #ff0000";
								newsplugin_title.style.boxShadow = "0 1px 2px rgba(255, 0, 0, 0.07) inset";
							}
							if (newsplugin_keywords.value == "" || /^\s*$/.test(newsplugin_keywords.value)) {
								newsplugin_keywords.style.border = "1px solid #ff0000";
								newsplugin_keywords.style.boxShadow = "0 1px 2px rgba(255, 0, 0, 0.07) inset";
							}
							if (newsplugin_articles.value == "" || /^\s*$/.test(newsplugin_articles.value) || isNaN(newsplugin_articles.value) || parseInt(newsplugin_articles.value) <= 0) {
								newsplugin_articles.style.border = "1px solid #ff0000";
								newsplugin_articles.style.boxShadow = "0 1px 2px rgba(255, 0, 0, 0.07) inset";
							}
							window.scrollTo(0, 0);
							if (!jQuery(".error").length) {
								jQuery("<div class='error'><p>Fill the required fields properly.</p></div>").insertBefore("#shortcodeTable");
							}
						} else {
							window.scrollTo(0, 0);
							generateShortcode();
							jQuery(".error").hide();
						}
					}

					function generateShortcode() {
						var shortcode_params = "";
						var owns = Object.prototype.hasOwnProperty;
						var key;
						var str_opts = new Object({
							newsplugin_title: 'title',
							newsplugin_keywords: 'keywords',
							newsplugin_search: 'search_mode',
							newsplugin_sort: 'sort_mode',
							newsplugin_link_open: 'link_open_mode',
							newsplugin_link_follow: 'link_follow',
							newsplugin_publishing: 'feed_mode'
						});
						for (key in str_opts) {
							if (owns.call(str_opts, key)) {
								var value = document.getElementById(key).value;
								if (value != "") {
									shortcode_params += " " + str_opts[key] + "='" + value + "'";
								}
							}
						}
						var bool_opts = new Object({
							newsplugin_more_dates: 'show_date',
							newsplugin_more_sources: 'show_source',
							newsplugin_more_abstracts: 'show_abstract'
						});
						for (key in bool_opts) {
							if (owns.call(bool_opts, key)) {
								var value = document.getElementById(key).checked;
								if (value != "") {
									shortcode_params += " " + bool_opts[key] + "='true'";
								}
							}
						}
						var newsplugin_articles = Math.abs(parseInt(document.getElementById('newsplugin_articles').value));
						if (newsplugin_articles != "" && !isNaN(newsplugin_articles)) {
							shortcode_params += " count='" + newsplugin_articles + "'";
						}
						var newsplugin_age = Math.abs(parseInt(document.getElementById('newsplugin_age').value));
						if (newsplugin_age != "" && !isNaN(newsplugin_age)) {
							shortcode_params += " age='" + newsplugin_age + "'";
						}
						shortcode_params += " wp_uid='<?php echo esc_attr(get_current_user_id()); ?>'";
						var html = "<p>Press Ctrl+C to copy to clipboard and paste it in your posts or pages.</p>";
						html += "<p><textarea id='shortcode-field' onfocus='this.select()' onclick='this.select()' readonly='readonly' style='width:400px; height:200px; max-width:400px; max-height:200px; min-width:400px; min-height:200px;'>[newsplugin_feed id='" + new Date().valueOf() + "'" + shortcode_params + "]</textarea></p>";
						document.getElementById('shortcode-generated').innerHTML = html;
						tb_show("NewsPlugin Shortcode Generated!", "#TB_inline?width=410&height=305&inlineId=shortcode-generated");
						document.getElementById('shortcode-field').focus();
						return false;
					}
				</script>
			<?php } elseif ($tab === $this->help_settings_key) { ?>
				<h3>Instructions</h3>
				<p>Please read the instructions below carefully to easily setup and use the NewsPlugin.</p>
				<p><strong>1. Enter Activation Key:</strong><br>First of all, enter your Activation Key in the <a href="<?php echo esc_url(admin_url('admin.php?page=news-plugin-settings&tab=' . $this->activation_settings_key)); ?>">Activate</a> tab.</p>
				<p><strong>2. Create Newsfeeds:</strong><br>Create your newsfeed by generating a shortcode from <a href="<?php echo esc_url(admin_url('admin.php?page=news-plugin-settings&tab=' . $this->shortcode_settings_key)); ?>">Generate Shortcode</a> tab. Put that shortcode in posts or pages where you want to display your newsfeed.<br>OR<br>create your newsfeed from <a href="<?php echo esc_url(admin_url('widgets.php')); ?>">Appearance &gt; Widgets</a>. From the widgets panel drag the "NewsPlugin" widget to the desired sidebar or widget area where you want to show your newsfeed. Edit the widget features to create/edit your newsfeed. Choose the name, number of headlines, keywords and other settings.</p>
				<p><strong>3. Edit Headlines (if you want to):</strong><br>You can remove unwanted headlines or star the good ones right from your site. Note that you must be logged in to WordPress as an administrator or an editor to see the 'Edit Newsfeed Mode' link on your page (next to your newsfeed).</p>
				<h3>Support</h3>
				<p>For more information about this plugin, please visit <a href="http://newsplugin.com" target="_blank">NewsPlugin.com</a> and the <a href="http://newsplugin.com/faq" target="_blank">FAQ</a>. Thanks for using the NewsPlugin, and we hope you like it.</p>
				<p><a href="http://newsplugin.com/contact" target="_blank">Contact us!</a></p>
			<?php } elseif ($tab === $this->style_settings_key) {
				$user = wp_get_current_user();
				$userID = (isset($user->ID) ? (int) $user->ID : 0);
				$style_news = get_user_meta($userID, 'news_style_dashbord_style', 'true');

				$font_family = [];
				$font_family = ["Arial", "Cambria", "Algerian", "Copperplate", "Lucida Console", "Times New Roman", "Impact", "Monaco", "Georgia", "Optima"];
				?>
				<h3>Style news plugin widgets created by user <?php echo esc_html($user->display_name); ?></h3>
				<div class="news-row-style">
					<div class="style_left">
						<form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
							<?php wp_nonce_field('news_plugin_save_style', 'news_plugin_save_style_field'); ?>
							<input type="hidden" name="action" value="news_plugin_save_style">
							<h3>Newsfeed Title</h3>
							<h4>Color</h4>
							<?php
							echo '<input class="jscolor" name="title_color" id="title_color" type="text" value="' . esc_attr($this->get_with_default($style_news, 'newsfeed_title', 'color', '')) . '" /><br>';
							echo '<h4>Size</h4>';
							echo '<select name="title_size" id="title_size">';
							$v = $this->get_with_default($style_news, 'newsfeed_title', 'size', '');
							echo '<option value="' . esc_attr($v) . '">' . esc_html($v) . '</option>';
							for ($i = 10; $i <= 50; $i++) {
								if ($i === $v) {
								} else {
									echo '<option value="' . esc_attr($i) . '">' . esc_html($i) . '</option>';
								}
							}
							echo '</select>';
							echo '<h4>Font Family</h4>';
							echo '<select name="title_font" id="title_font">';
							$v = $this->get_with_default($style_news, 'newsfeed_title', 'font_family', '');
							echo '<option value="' . esc_attr($v) . '">' . esc_html($v) . '</option>';
							if ($v) {
								echo '<option value="">Unchanged (theme default)</option>';
							}
							foreach ($font_family as $fonts) {
								if ($fonts === $v) {
								} else {
									echo '<option value="' . esc_attr($fonts) . '">' . esc_html($fonts) . '</option>';
								}
							}
							echo '</select>';
							echo '<h3>Article Headline</h3>';
							echo '<h4>Color</h4>';
							echo '<input class="jscolor" name="news_title_color" id="news_title_color" type="text" value="' . esc_attr($this->get_with_default($style_news, 'article_headline', 'color', '')) . '" /><br>';
							echo '<h4>Size</h4>';
							echo '<select name="news_title_size" id="news_title_size">';
							$v = $this->get_with_default($style_news, 'article_headline', 'size', '');
							echo '<option value="' . esc_attr($v) . '">' . esc_html($v) . '</option>';
							for ($i = 10; $i <= 50; $i++) {
								if ($i === $v) {
								} else {
									echo '<option value="' . esc_attr($i) . '">' . esc_html($i) . '</option>';
								}
							}
							echo '</select>';
							echo '<h4>Font Family</h4>';
							echo '<select name="news_title_family" id="news_title_family">';
							$v = $this->get_with_default($style_news, 'article_headline', 'font_family', '');
							echo '<option value="' . esc_attr($v) . '">' . esc_html($v) . '</option>';
							if ($v) {
								echo '<option value="">Unchanged (theme default)</option>';
							}
							foreach ($font_family as $fonts) {
								if ($fonts === $v) {
								} else {
									echo '<option value="' . esc_attr($fonts) . '">' . esc_html($fonts) . '</option>';
								}
							}
							echo '</select>';
							echo '<h3>Article Abstract</h3>';
							echo '<h4>Color</h4>';
							echo '<input class="jscolor" name="abstract_font_color" id="abstract_font_color" type="text" value="' . esc_attr($this->get_with_default($style_news, 'article_abstract', 'color', '')) . '" /><br>';
							echo '<h4>Size</h4>';
							echo '<select name="abstract_font_size" id="abstract_font_size">';
							$v = $this->get_with_default($style_news, 'article_abstract', 'size', '');
							echo '<option value="' . esc_attr($v) . '">' . esc_html($v) . '</option>';
							for ($i = 10; $i <= 50; $i++) {
								if ($i === $v) {
								} else {
									echo '<option value="' . esc_attr($i) . '">' . esc_html($i) . '</option>';
								}
							}
							echo '</select>';
							echo '<h4>Font Family</h4>';
							echo '<select name="abstract_font_family" id="abstract_font_family">';
							$v = $this->get_with_default($style_news, 'article_abstract', 'font_family', '');
							echo '<option value="' . esc_attr($v) . '">' . esc_html($v) . '</option>';
							if ($v) {
								echo '<option value="">Unchanged (theme default)</option>';
							}
							foreach ($font_family as $fonts) {
								if ($fonts === $v) {
								} else {
									echo '<option value="' . esc_attr($fonts) . '">' . esc_html($fonts) . '</option>';
								}
							}
							echo '</select>';
							echo '</div>';
							echo '<div class="style_right">';
							echo '<h3>Article Date</h3>';

							echo '<h4>Color</h4>';

							echo '<input class="jscolor" name="news_date_color" id="news_date_color" type="text" value="' . esc_attr($this->get_with_default($style_news, 'article_date', 'color', '')) . '" /><br>';

							echo '<h4>Size</h4>';

							echo '<select name="news_date_size" id="news_date_size">';

							$v = $this->get_with_default($style_news, 'article_date', 'size', '');
							echo '<option value="' . esc_attr($v) . '">' . esc_html($v) . '</option>';

							for ($i = 10; $i <= 50; $i++) {
								if ($i === $v) {
								} else {
									echo '<option value="' . esc_attr($i) . '">' . esc_html($i) . '</option>';
								}
							}
							echo '</select>';

							echo '<h4>Font Family</h4>';
							echo '<select name="date_font" id="date_font">';
							$v = $this->get_with_default($style_news, 'article_date', 'font_family', '');
							echo '<option value="' . esc_attr($v) . '">' . esc_html($v) . '</option>';
							if ($v) {
								echo '<option value="">Unchanged (theme default)</option>';
							}
							foreach ($font_family as $fonts) {
								if ($fonts === $v) {
								} else {
									echo '<option value="' . esc_attr($fonts) . '">' . esc_html($fonts) . '</option>';
								}
							}
							echo '</select>';

							echo '<h3>Article Sources</h3>';

							echo '<h4>Color</h4>';

							echo '<input class="jscolor" name="source_color" id="source_color" type="text" value="' . esc_attr($this->get_with_default($style_news, 'article_sources', 'color', '')) . '" /><br>';

							echo '<h4>Size</h4>';

							echo '<select name="source_size" id="source_size">';

							$v = $this->get_with_default($style_news, 'article_sources', 'size', '');
							echo '<option value="' . esc_attr($v) . '">' . esc_html($v) . '</option>';

							for ($i = 10; $i <= 50; $i++) {
								if ($i === $v) {
								} else {
									echo '<option value="' . esc_attr($i) . '">' . esc_html($i) . '</option>';
								}
							}
							echo '</select>';

							echo '<h4>Font Family</h4>';
							echo '<select name="source_font" id="source_font">';
							$v = $this->get_with_default($style_news, 'article_sources', 'font_family', '');
							echo '<option value="' . esc_attr($v) . '">' . esc_html($v) . '</option>';
							if ($v) {
								echo '<option value="">Unchanged (theme default)</option>';
							}
							foreach ($font_family as $fonts) {
								if ($fonts === $v) {
								} else {
									echo '<option value="' . esc_attr($fonts) . '">' . esc_html($fonts) . '</option>';
								}
							}
							echo '</select>';
							echo '<br><br>';
							echo '<div>';
							echo '<h3><input type="checkbox" name="default_values_style" value="1"><span>Reset all style changes</span></h3>';
							echo '<p>(This will change all styles to default format.)</p>';
							echo '</div>';

							submit_button();
							echo '</div>';
							echo '</form>';
							echo '</div>';
							?>

			<?php } elseif ($tab === $this->feed_settings_key) {
						echo '<div class="feeds-row-style">';
						echo '<div>';
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if (isset($_GET['status'])) {
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended
					if (intval($_GET['status']) === 1) {
						echo '<span><h3>Your message has been sent.<br/>Thank you.</h3></span>';
					} else {
						echo '<span><h3>Error sending message. Please use the form at <a href="https://www.newsplugin.com/contact/">https://www.newsplugin.com/contact/</a>, don' . "'" . 't forget to include the server informations and mention that the plugin feedback page failed.</span></h3>';
					}
				} ?>
					</div>
					<form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" id="feed_form">
						<?php wp_nonce_field('news_plugin_send_feedback', 'news_plugin_send_feedback_field'); ?>
						<input type="hidden" name="action" value="news_plugin_send_feedback">
						<div class="feed_left">
							<h3><?php esc_html_e('Email', 'news_plugin'); ?></h3><?php
							$user_info = News_Plugin_Utils::get_user_info();
							if ($user_info) {
								$email = $user_info->email;
							} else {
								$email = '';
							}
											echo '<input class="text notsobig" name="feed_from" id="feed_from" type="email" size="64" value="' . esc_attr($email) . '"/><br>';
											echo '<h3>Subject</h3>';
											echo '<input class="text notsobig" name="feed_subject" id="feed_subject" type="text" size="64" /><br>';
											echo '<h3>Description</h3>';
											echo '<textarea form="feed_form" class="notsobig" name="feed_desc" id="taid" cols="64" rows="10">';
											echo '</textarea><br>';
											echo '<p class="submit">';
											echo '<input type="submit" name="send" id="send" class="button button-primary" value="Submit">';
											echo '</p>';
											echo '</div>';

											echo '<div class="feed_right">';
											echo '<h2><input type="checkbox" id="feed_system_div" onclick="showDiv(this)" checked="checked"/> Also include system environment details</h2>';
											echo '<div class="feed_system_preview" id="system_enmt">';
											echo '<table cellspacing="0" id="feed_status" class="feeds_status_table wideStatus">';
											echo '<thead>';
											echo '<tr>';
											echo '<th data-export-label="System Environment" colspan="3">';
											echo '<h2 style="float:left;">System Environment</h2>';
											echo '</th>';
											echo '</tr>';
											echo '</thead>';
											echo '<tbody>';
											echo '<input type="hidden" id="sys_table_input" value="" name="sys_table_input">';

											$results = get_option('newsPlugin_system_info');
							foreach ($results['wordpress_env'] as $key => $value) {
								$key_Name = str_replace('_', ' ', $key);
								echo '<tr>
							<td data-export-label="' . esc_attr($key) . '">' . esc_html(strtoupper($key_Name)) . ' :</td>
							<td>' . esc_html($value) . '</td>
						</tr>';
							}
							foreach ($results['system_env'] as $key => $value) {
								$key_Name = str_replace('_', ' ', $key);
								echo '<tr>
							<td data-export-label="' . esc_attr($key) . '">' . esc_html(strtoupper($key_Name)) . ' :</td>
							<td>' . esc_html($value) . '</td>
						</tr>';
							}
							foreach ($results['newsplugin_env'] as $key => $value) {
								$key_Name = str_replace('_', ' ', $key);
								echo '<tr>
							<td data-export-label="' . esc_attr($key) . '">' . esc_html(strtoupper($key_Name)) . ' :</td>
							<td>' . esc_html($value) . '</td>
						</tr>';
							}

											echo '</tbody>';
											echo '</table>';
											echo '</div>';
											echo '<div class="log_div">';
											$myfilename = plugin_dir_url(__FILE__) . "logs/plugin-logs.txt";
											// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
											$content = file_exists($myfilename) ? file_get_contents($myfilename) : false;
											$v = ($content === false) ? '' : ' checked="checked"';
											echo '<h2><input type="checkbox" id="errors_div" onclick="showError(this)"' . esc_html($v) . '/> Include Custom Logs</h2>';
											echo '</div>';
											echo '<div class="feed_system_preview" id="error_show_div">';
											echo '<textarea id="errors_logs" name="noLog_errors" form="feed_form" style="display:none;">"' . esc_html($content) . '"</textarea>';
							if ($content !== false) {
								echo '<p><strong>"' . esc_html($content) . '"</strong></p>';
							}
											echo '</div>';
											echo '</div>';
											echo '</form>';

											echo '</div>';
							?>
							<script>
								function showDiv(box) {
									if (box.checked) {
										document.getElementById("system_enmt").style.display = 'block';
										var field = document.getElementById("sys_table_input");
										field.id = "insert_sys_info";
										field.setAttribute("name", "insert_sys_info");
									} else {
										document.getElementById("system_enmt").style.display = "none";
										var field = document.getElementById("insert_sys_info");
										field.id = "sys_table_input";
										field.setAttribute("name", "sys_table_input");
									}
								}

								function showError(error) {
									if (error.checked) {
										var field = document.getElementById("errors_logs");
										field.setAttribute("name", "errors_logs");
									} else {
										var field = document.getElementById("errors_logs");
										field.setAttribute("name", "noLog_errors");
									}
								}
							</script>

			<?php } elseif ($tab === $this->status_settings_key) { ?>
							<form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
								<?php wp_nonce_field('news_plugin_update_system_info'); ?>
								<input type="hidden" name="action" value="news_plugin_update_system_info">
								<p class="submit">
									<input type="submit" name="update_system_info" id="update_system_info" class="button button-primary" value="Refresh">
								</p>
							</form>
							<table cellspacing="0" id="status" class="news_status_table wideStatus">
								<tbody>
									<?php
									$results = get_option('newsPlugin_system_info');
									echo '<tr><td class="back_td_clr" colspan="2"><h3>WordPress Environment</h3></td></tr>';
									foreach ($results['wordpress_env'] as $key => $value) {
										$key_Name = str_replace('_', ' ', $key);
										echo '<tr>
							<td data-export-label="' . esc_attr($key) . '">' . esc_html(strtoupper($key_Name)) . ' :</td>
							<td>' . esc_html($value) . '</td>
						</tr>';
									}
									echo '<tr><td class="back_td_clr" colspan="2"><h3>System Environment</h3></td></tr>';
									foreach ($results['system_env'] as $key => $value) {
										$key_Name = str_replace('_', ' ', $key);
										echo '<tr>
							<td data-export-label="' . esc_attr($key) . '">' . esc_html(strtoupper($key_Name)) . ' :</td>
							<td>' . esc_html($value) . '</td>
						</tr>';
									}
									echo '<tr><td class="back_td_clr" colspan="2"><h3>NewsPlugin Environment</h3></td></tr>';
									foreach ($results['newsplugin_env'] as $key => $value) {
										$key_Name = str_replace('_', ' ', $key);
										echo '<tr>
							<td data-export-label="' . esc_attr($key) . '">' . esc_html(strtoupper($key_Name)) . ' :</td>
							<td>' . esc_html($value) . '</td>
						</tr>';
									}
									echo '</tbody>';
									echo '</table>';

									?>
			<?php } ?>
						</div>
				<?php
	}

	/**
	 * Renders our tabs in the plugin options page,
	 * walks through the object's tabs array and prints
	 * them one by one. Provides the heading for the
	 * plugin_options_page method.
	 *
	 * @param string $current_tab Current tab ID.
	 * @return void
	 */
	public function newsplugin_options_tabs($current_tab)
	{
		echo '<h2 class="nav-tab-wrapper">';
		foreach ($this->plugin_settings_tabs as $tab_key => $tab_caption) {
			$active = $current_tab === $tab_key ? 'nav-tab-active' : '';
			echo '<a class="nav-tab ' . esc_attr($active) . '" href="?page=' . esc_attr($this->plugin_options_key) . '&tab=' . esc_attr($tab_key) . '">' . esc_html($tab_caption) . '</a>';
		}
		echo '</h2>';
	}

	/**
	 * Add link to the options page to the plugin action links.
	 *
	 * @param array $default_links Default links.
	 * @return array
	 */
	public function add_action_links($default_links)
	{
		$links = [
			'<a href="' . admin_url('admin.php?page=news-plugin-settings') . '">Settings</a>',
		];
		return array_merge($links, $default_links);
	}

	/**
	 * Render the API key settings.
	 */
	public function settings_api_key()
	{
		$v = get_option('news_plugin_api_key');
		echo '<input class="regular-text" name="news_plugin_api_key" id="news_plugin_api_key" type="text" size="64" value="' . esc_attr($v) . '" />';
		echo '<p class="description">';
		echo 'You can get it at <a href="http://my.newsplugin.com/register" target="_blank">http://my.newsplugin.com/register</a>.';
		echo '</p>';
	}

	/**
	 * Validate the API key settings.
	 *
	 * @param string $input API key.
	 * @return string
	 */
	public function validate_api_key($input)
	{
		return sanitize_text_field($input);
	}

	/**
	 * Render the user mode settings.
	 */
	public function settings_user_mode()
	{
		$v = get_option('news_plugin_user_mode');
		echo '<p>';
		echo '<input type="radio" name="news_plugin_user_mode" id="news_plugin_user_mode_0" value="0"', ($v === 0 ? ' checked="checked"' : ''), '>';
		echo '<label for="news_plugin_user_mode_0">Basic - Simple &amp; easy way to start with.</label>';
		echo '<br>';
		echo '<input type="radio" name="news_plugin_user_mode" id="news_plugin_user_mode_1" value="1"', ($v === 1 ? ' checked="checked"' : ''), '>';
		echo '<label for="news_plugin_user_mode_1">Advanced - More features for advanced users.</label>';
		echo '<br>';
		echo '<input type="radio" name="news_plugin_user_mode" id="news_plugin_user_mode_2" value="2"', ($v === 2 ? ' checked="checked"' : ''), '>';
		echo '<label for="news_plugin_user_mode_2">Expert - Manual publishing mode for professionals.</label>';
		echo '</p>';
	}

	/**
	 * Validate the user mode settings.
	 *
	 * @param int $input User mode ID (?).
	 * @return int
	 */
	public function validate_user_mode($input)
	{
		$v = absint($input);
		return ($v < 3 ? $v : 0);
	}

	/**
	 * Update stystem info
	 *
	 * @return void
	 */
	public function handle_update_system_info()
	{
		update_option('newsPlugin_system_info', News_Plugin_Utils::get_system_info());
		$redirect = admin_url('admin.php') . '?page=news-plugin-settings&tab=newsplugin_status_settings';
		wp_safe_redirect($redirect);
	}

	/**
	 * Save CSS styles
	 *
	 * @return void
	 */
	public function handle_save_style()
	{
		include(plugin_dir_path(__FILE__) . 'save_style.php');
	}

	/**
	 * Send feedback
	 *
	 * @return void
	 */
	public function handle_send_feedback()
	{
		include(plugin_dir_path(__FILE__) . 'send_feedback.php');
	}
}

		// Hook ourselves into the WordPress.
		new News_Plugin();

?>
