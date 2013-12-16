<?php

add_action( 'admin_menu', 'bp_tools_admin_menu' );
add_action( 'load-tools_page_bp-repair', 'bp_admin_repair_handler' );

function bp_tools_admin_menu() {
	add_management_page(
		'BuddyPress',
		'BuddyPress',
		'manage_options',
		'bp-repair',
		'bp_admin_repair'
	);
}

function bp_admin_repair() {
	?>
	<div class="wrap">

		<?php screen_icon( 'tools' ); ?>

		<h2 class="nav-tab-wrapper">Repair BuddyPress</h2>

		<p><?php esc_html_e( 'BuddyPress keeps track of relationships between users. Occasionally these relationships become out of sync, most often after an import or migration. Use the tools below to manually recalculate these relationships.', 'buddypress' ); ?></p>
		<p class="description"><?php esc_html_e( 'Some of these tools create substantial database overhead. Avoid running more than 1 repair job at a time.', 'buddypress' ); ?></p>

		<form class="settings" method="post" action="">
			<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Relationships to Repair:', 'buddypress' ) ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><span><?php esc_html_e( 'Repair', 'buddypress' ) ?></span></legend>

								<?php foreach ( bp_admin_repair_list() as $item ) : ?>

									<label><input type="checkbox" class="checkbox" name="<?php echo esc_attr( $item[0] ) . '" id="' . esc_attr( str_replace( '_', '-', $item[0] ) ); ?>" value="1" /> <?php echo esc_html( $item[1] ); ?></label><br />

								<?php endforeach; ?>

							</fieldset>
						</td>
					</tr>
				</tbody>
			</table>

			<fieldset class="submit">
				<input class="button-primary" type="submit" name="submit" value="<?php esc_attr_e( 'Repair Items', 'buddypress' ); ?>" />
				<?php wp_nonce_field( 'bp-do-counts' ); ?>
			</fieldset>
		</form>
	</div>
	<?php
}

function bp_admin_repair_handler() {
	if ( ! bp_is_post_request() ) {
		return;
	}

	check_admin_referer( 'bp-do-counts' );

	// Stores messages
	$messages = array();

	wp_cache_flush();

	foreach ( (array) bp_admin_repair_list() as $item ) {
		if ( isset( $item[2] ) && isset( $_POST[$item[0]] ) && 1 === absint( $_POST[$item[0]] ) && is_callable( $item[2] ) ) {
			$messages[] = call_user_func( $item[2] );
		}
	}

	if ( count( $messages ) ) {
		foreach ( $messages as $message ) {
			bp_admin_tools_feedback( $message[1] );
		}
	}
}


/**
 * Get the array of the repair list
 *
 * @return array
 */
function bp_admin_repair_list() {
	$repair_list = array(
		0  => array( 'bp-user-friends',              __( 'Count friends for each user',                       'buddypress' ), 'bp_admin_repair_friend_count'               ),
	);
	ksort( $repair_list );

	return (array) apply_filters( 'bp_repair_list', $repair_list );
}

/**
 * Recounts all the friends for each user
 */
function bp_admin_repair_friend_count() {
	global $wpdb;

	$statement = __( 'Counting the number of friends for each user&hellip; %s', 'buddypress' );
	$result    = __( 'Failed!', 'buddypress' );

	$sql_delete = "DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ( 'total_friend_count' );";
	if ( is_wp_error( $wpdb->query( $sql_delete ) ) )
		return array( 1, sprintf( $statement, $result ) );

	$total_users = $wpdb->get_row( "SELECT count(ID) as c FROM {$wpdb->users}" )->c;

	if ( $total_users > 0 ) {
		$per_query = 500;
		$offset = 0;
		while( $offset < $total_users ) {
			$users = get_users( array( 'offset' => $offset, 'number' => $per_query ) );

			foreach ( $users as $user ) {
				$friend_count = BP_Friends_Friendship::total_friend_count( $user->ID );
				update_user_meta( $user->ID, 'total_friend_count', $friend_count );
			}

			$offset += $per_query;
		}
	} else {
		return array( 2, sprintf( $statement, $result ) );
	}

	return array( 0, sprintf( $statement, __( 'Complete!', 'buddypress' ) ) );
}

function bp_admin_tools_feedback( $message, $class = false ) {
	if ( is_string( $message ) ) {
		$message = '<p>' . $message . '</p>';
		$class = $class ? $class : 'updated';
	} elseif ( is_wp_error( $message ) ) {
		$errors = $message->get_error_messages();

		switch ( count( $errors ) ) {
			case 0:
				return false;
				break;

			case 1:
				$message = '<p>' . $errors[0] . '</p>';
				break;

			default:
				$message = '<ul>' . "\n\t" . '<li>' . implode( '</li>' . "\n\t" . '<li>', $errors ) . '</li>' . "\n" . '</ul>';
				break;
		}

		$class = $class ? $class : 'error';
	} else {
		return false;
	}

	$message = '<div id="message" class="' . esc_attr( $class ) . '">' . $message . '</div>';
	$message = str_replace( "'", "\'", $message );
	$lambda  = create_function( '', "echo '$message';" );

	add_action( 'admin_notices', $lambda );

	return $lambda;
}