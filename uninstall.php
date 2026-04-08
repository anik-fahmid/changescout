<?php
/**
 * Fired when the plugin is uninstalled.
 * Removes all plugin options and scheduled events.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove all plugin options.
$options = [
	'aics_changelog_urls',
	'aics_ai_provider',
	'aics_gemini_api_key',
	'aics_openai_api_key',
	'aics_claude_api_key',
	'aics_max_tokens',
	'aics_notification_email',
	'aics_email_from_name',
	'aics_email_from_address',
	'aics_email_frequency',
	'aics_email_day',
	'aics_email_time',
	'aics_smtp_enabled',
	'aics_smtp_host',
	'aics_smtp_port',
	'aics_smtp_encryption',
	'aics_smtp_username',
	'aics_smtp_password',
	'aics_changelog_summaries',
];

foreach ( $options as $option ) {
	delete_option( $option );
}

// Clear scheduled cron event.
wp_clear_scheduled_hook( 'aics_changelog_email' );
