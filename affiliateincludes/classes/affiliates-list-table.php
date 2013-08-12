<?php
/**
 * Affiliates List Table class - based on WP Users list table.
 *
 */

class Affiliates_List_Table extends WP_List_Table {

	var $site_id;

	var $_column_headers;

	function __construct( $args = array() ) {

		parent::__construct( array(
			'singular' => 'affiliate',
			'plural'   => 'affiliates',
			'screen'   => 'affiliates',
		) );

	}

	function ajax_user_can() {

		return current_user_can( 'manage_options' );

	}

	function prepare_items() {
		global $role, $usersearch;

		$usersearch = isset( $_REQUEST['s'] ) ? trim( $_REQUEST['s'] ) : '';

		$role = isset( $_REQUEST['role'] ) ? $_REQUEST['role'] : '';

		$per_page = 'users_per_page';
		$users_per_page = $this->get_items_per_page( $per_page );

		$paged = $this->get_pagenum();

		$args = array(
			'number' => $users_per_page,
			'offset' => ( $paged-1 ) * $users_per_page,
			'role' => $role,
			'search' => $usersearch,
			'fields' => 'all_with_meta'
		);

		if ( '' !== $args['search'] )
			$args['search'] = '*' . $args['search'] . '*';

		if ( isset( $_REQUEST['orderby'] ) )
			$args['orderby'] = $_REQUEST['orderby'];

		if ( isset( $_REQUEST['order'] ) )
			$args['order'] = $_REQUEST['order'];

		// Add the affiliate args
		$args['meta_key'] = 'enable_affiliate';
		$args['meta_value'] = 'yes';

		// Query the user IDs for this page
		$wp_user_search = new WP_User_Query( $args );

		$this->items = $wp_user_search->get_results();

		$this->set_pagination_args( array(
			'total_items' => $wp_user_search->get_total(),
			'per_page' => $users_per_page,
		) );
	}

	function no_items() {
		_e( 'No matching users were found.' );
	}

	function get_views() {
		global $wp_roles, $role;

		$role_links = array();

		return $role_links;
	}

	function get_bulk_actions() {
		$actions = array();


		return $actions;
	}

	function extra_tablenav( $which ) {
		if ( 'top' != $which )
			return;

	}

	function current_action() {
		if ( isset($_REQUEST['changeit']) && !empty($_REQUEST['new_role']) )
			return 'promote';

		return parent::current_action();
	}

	function get_columns() {
		$c = array(
			'cb'       => '<input type="checkbox" />',
			'username' => __( 'Username', 'affiliate' ),
			'name'     => __( 'Name', 'affiliate' ),
			'paypal'    => __( 'PayPal account', 'affiliate' ),
			'reference'     => __( 'Reference', 'affiliate' ),
			'linked'	=> __('URL','affiliate')
		);

		return $c;
	}

	function get_sortable_columns() {
		$c = array(
			'username' => 'login',
			'name'     => 'name'
		);


		return $c;
	}

	function display_rows() {

		$style = '';
		foreach ( $this->items as $userid => $user_object ) {

			$style = ( ' class="alternate"' == $style ) ? '' : ' class="alternate"';
			echo "\n\t" . $this->single_row( $user_object, $style );

		}
	}

	/**
	 * Generate HTML for a single row on the users.php admin panel.
	 *
	 * @since 2.1.0
	 *
	 * @param object $user_object
	 * @param string $style Optional. Attributes added to the TR element. Must be sanitized.
	 * @param string $role Key for the $wp_roles array.
	 * @param int $numposts Optional. Post count to display for this user. Defaults to zero, as in, a new user has made zero posts.
	 * @return string
	 */
	function single_row( $user_object, $style = '' ) {
		global $wp_roles;

		if ( !( is_object( $user_object ) && is_a( $user_object, 'WP_User' ) ) )
			$user_object = get_userdata( (int) $user_object );
		$user_object->filter = 'display';
		$email = $user_object->user_email;

		$url = 'admin.php?page=affiliatesadminmanage';

		$checkbox = '';
		// Check if the user for this row is editable
		if ( current_user_can( 'list_users' ) ) {

			// Set up the hover actions for this user
			$actions = array();

			$edit = "<strong><a href=\"" . $url . "&amp;subpage=users&amp;id=". $user_object->ID . "\">$user_object->user_login</a></strong><br />";
			$actions['edit'] = "<a href='" . $url . "&amp;subpage=users&amp;id=". $user_object->ID . "' class='edit'>" . __('Manage Affiliate','affiliate') . "</a>";

			$edit .= $this->row_actions( $actions );


		} else {
			$edit = '<strong>' . $user_object->user_login . '</strong>';
		}

		$avatar = get_avatar( $user_object->ID, 32 );

		$r = "<tr id='user-$user_object->ID'$style>";

		list( $columns, $hidden ) = $this->get_column_info();

		foreach ( $columns as $column_name => $column_display_name ) {
			$class = "class=\"$column_name column-$column_name\"";

			$style = '';
			if ( in_array( $column_name, $hidden ) )
				$style = ' style="display:none;"';

			$attributes = "$class$style";

			switch ( $column_name ) {
				case 'cb':
					$r .= "<th scope='row' class='check-column'>$checkbox</th>";
					break;
				case 'username':
					$r .= "<td $attributes>$avatar $edit</td>";
					break;
				case 'name':
					$r .= "<td $attributes>$user_object->first_name $user_object->last_name</td>";
					break;
				case 'paypal':
					$r .= "<td $attributes>" . get_user_meta($user_object->ID, 'affiliate_paypal', true) . "</td>";
					break;
				case 'reference':
					$r .= "<td $attributes>" . get_user_meta($user_object->ID, 'affiliate_reference', true) . "</td>";
					break;
				case 'linked':
					$referrer = get_user_meta($user_object->ID, 'affiliate_referrer', true);
					if(!empty($referrer)) {
						$r .= "<td $attributes><a href='http://{$referrer}'>" . $referrer . "</a></td>";
					} else {
						$r .= "<td $attributes></td>";
					}

					break;
				default:
					$r .= "<td $attributes>";
					$r .= apply_filters( 'manage_users_custom_column', '', $column_name, $user_object->ID );
					$r .= "</td>";
			}
		}
		$r .= '</tr>';

		return $r;
	}
}