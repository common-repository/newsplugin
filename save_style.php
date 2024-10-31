<?php

/**
 * Save CSS styles
 *
 * @package    WordPress
 * @subpackage News Plugin
 * @since 1.0.0
 */

// Verify nonce.
$nonce = isset($_POST['news_plugin_save_style_field']) ? sanitize_key($_POST['news_plugin_save_style_field']) : null;
if (! $nonce || ! wp_verify_nonce($nonce, 'news_plugin_save_style')) {
	die(esc_html__('4 - Security check failed. Try to submit the form once again.', 'news_plugin'));
}

$user = wp_get_current_user();
$userID = $user->ID;
$default_Value = isset($_POST['default_values_style']) ? sanitize_key(wp_unslash($_POST['default_values_style'])) : null;
$styleDash = [
 'newsfeed_title' => [
	  'color'         =>  isset($_POST['title_color']) ? sanitize_key(wp_unslash($_POST['title_color'])) : null,
	 'size'          =>  isset($_POST['title_size']) ? sanitize_key(wp_unslash($_POST['title_size'])) : null,
	  'font_family'   =>  isset($_POST['title_font']) ? sanitize_key(wp_unslash($_POST['title_font'])) : null
  ],
 'article_headline' => [
		'color'         =>  isset($_POST['news_title_color']) ? sanitize_key(wp_unslash($_POST['news_title_color'])) : null,
		'size'          =>  isset($_POST['news_title_size']) ? sanitize_key(wp_unslash($_POST['news_title_size'])) : null,
	 'font_family'   =>  isset($_POST['news_title_family']) ? sanitize_key(wp_unslash($_POST['news_title_family'])) : null
 ],
 'article_abstract' => [
		'color'         =>  isset($_POST['abstract_font_color']) ? sanitize_key(wp_unslash($_POST['abstract_font_color'])) : null,
	 'size'          =>  isset($_POST['abstract_font_size']) ? sanitize_key(wp_unslash($_POST['abstract_font_size'])) : null,
	  'font_family'   =>  isset($_POST['abstract_font_family']) ? sanitize_key(wp_unslash($_POST['abstract_font_family'])) : null,
 ],
 'article_date' => [
		'color'         =>  isset($_POST['news_date_color']) ? sanitize_key(wp_unslash($_POST['news_date_color'])) : null,
	 'size'          =>  isset($_POST['news_date_size']) ? sanitize_key(wp_unslash($_POST['news_date_size'])) : null,
	  'font_family'   =>  isset($_POST['date_font']) ? sanitize_key(wp_unslash($_POST['date_font'])) : null,
 ],
 'article_sources' => [
	 'color'         =>  isset($_POST['source_color']) ? sanitize_key(wp_unslash($_POST['source_color'])) : null,
		'size'          =>  isset($_POST['source_size']) ? sanitize_key(wp_unslash($_POST['source_size'])) : null,
	 'font_family'   =>  isset($_POST['source_font']) ? sanitize_key(wp_unslash($_POST['source_font'])) : null,
 ]
];

if (isset($default_Value)) {
	$default_values = [
		'newsfeed_title' => [
			'color'         =>  '000000',
			'size'          =>  22,
	'font_family'   =>  'Times New Roman'
		],
		'article_headline' => [
			'color'         =>  '000000',
			'size'          =>  18,
			'font_family'   =>  'Times New Roman'
		],
		'article_abstract' => [
			'color'         =>  '000000',
			'size'          =>  14,
			'font_family'   =>  'Times New Roman'
		],
		'article_date' => [
			'color'         =>  '000000',
			'size'          =>  12,
			'font_family'   =>  'Times New Roman'
		],
		'article_sources' => [
			'color'         =>  '000000',
			'size'          =>  12,
			'font_family'   =>  'Times New Roman'
		]
	];
	update_user_meta($userID, 'news_style_dashbord_style', $default_values);
} else {
	update_user_meta($userID, 'news_style_dashbord_style', $styleDash);
}

$redirect = admin_url('admin.php') . '?page=news-plugin-settings&tab=newsplugin_style_settings';
wp_safe_redirect($redirect);
exit();
