<?php
	// Migration task
function knawat_migration_task() {
	global $wpdb;
	$old_version     = $wpdb->get_var( "SELECT `meta_value` FROM {$wpdb->postmeta} WHERE `meta_key` = 'knawat-old-version'" );
	$current_version = $wpdb->get_var( "SELECT `meta_value` FROM {$wpdb->postmeta} WHERE `meta_key` = 'knawat-current-version'" );
	$migration_done  = $wpdb->get_var( "SELECT `meta_value` FROM {$wpdb->postmeta} WHERE `meta_key` = 'migration_task_1'" );
	// check the first migration task if it's done or not
	if ( strcasecmp( $current_version, $old_version ) >= 1 && empty( $migration_done ) ) {
		$wpdb->update(
			$wpdb->posts,
			array( 'post_type' => 'product' ),
			array(
				'post_parent' => 0,
				'post_type'   => 'product_variation',
			)
		);
		$wpdb->replace(
			$wpdb->postmeta,
			array(
				'meta_id'    => '1000002',
				'meta_key'   => 'migration_task_1',
				'meta_value' => true,
			)
		);
	}
}

