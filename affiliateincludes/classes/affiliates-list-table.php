<?php
/**
 * Affiliates List Table class - based on WP Users list table.
 *q
 */

class Affiliates_List_Table extends WP_List_Table {

	var $site_id;

	var $_column_headers;

	var $filters = array();
	var $url_base = 'admin.php?page=affiliatesadminmanage';
	var $blog_id = 0;

	function __construct( $args = array() ) {

		parent::__construct( array(
			'singular' => 'affiliate',
			'plural'   => 'affiliates',
			'screen'   => 'affiliates',
		) );

		$this->check_table_filters();

	}

	function ajax_user_can() {

		return current_user_can( 'manage_options' );

	}

	function check_table_filters() {

		//echo "_POST<pre>"; print_r($_GET); echo "</pre>";
		
		if ( (isset($_REQUEST['blog'])) && (!empty($_REQUEST['blog'])) ) {
			$this->filters['blog_id'] = 0;			
 			$this->filters['blog'] = esc_attr($_REQUEST['blog']);
			
			if (intval($this->filters['blog']) != 0) {
				$this->filters['blog_id'] = intval($this->filters['blog']);
			} else {
				global $wpdb;
				
				$PHP_URL_SCHEME = parse_url($this->filters['blog'], PHP_URL_SCHEME);
				//echo "PHP_URL_SCHEME=[". $PHP_URL_SCHEME ."]<br />";
				if (!empty($PHP_URL_SCHEME)) {
					$this->filters['blog'] = str_replace($PHP_URL_SCHEME."://", '', $this->filters['blog']);
				}

				if (is_subdomain_install()) {
					//echo "blog[". $this->filters['blog'] ."]<br />";
					if (!empty($this->filters['blog'])) {
						// We first remove the domain from the user input. 
						$this->filters['blog'] = str_replace('.'. DOMAIN_CURRENT_SITE, '', $this->filters['blog']);
						
						$full_domain = $this->filters['blog'] .".". DOMAIN_CURRENT_SITE;
					} else {
						$full_domain = DOMAIN_CURRENT_SITE;
					}
					$sql_str = $wpdb->prepare("SELECT blog_id FROM $wpdb->blogs WHERE domain = %s LIMIT 1", $full_domain);
				} else {
					$snapshot_blog_id_search_path 		= untrailingslashit($this->filters['blog']);
					$sql_str = $wpdb->prepare("SELECT blog_id FROM $wpdb->blogs WHERE domain = %s AND path = %s LIMIT 1", 
						DOMAIN_CURRENT_SITE, "/".$snapshot_blog_id_search_path."/");
				}
				//echo "sql_str=[". $sql_str ."]<br />";
				$blog = $wpdb->get_row( $sql_str );
				//echo "blog<pre>"; print_r($blog); echo "</pre>";
				
				if ((isset($blog->blog_id)) && (intval($blog->blog_id) > 0)) {
					$this->filters['blog_id'] = intval($blog->blog_id);
				} else if (!$blog) {
					if ((function_exists('is_plugin_active')) && (is_plugin_active('domain-mapping/domain-mapping.php'))) {
						$sql_str = $wpdb->prepare("SELECT blog_id FROM ". $wpdb->prefix ."domain_mapping WHERE domain = %s LIMIT 1", 
							$this->filters['blog']);
						//echo "sql_str=[". $sql_str ."]<br />";
						$blog = $wpdb->get_row( $sql_str );
						if ((isset($blog->blog_id)) && (intval($blog->blog_id) > 0)) {
							$this->filters['blog_id'] = intval($blog->blog_id);
						}
					} 
				}
				if ($this->filters['blog_id'] > 0) {
					//echo "blog_id[". $this->filters['blog_id'] ."]<br />";
					$this->filters['blog_details'] = get_blog_details($this->filters['blog_id']);
				}
			}
						
		} else {
			$this->filters['blog'] = '';
		}
		
		if ( (isset($_REQUEST['s'])) && (!empty($_REQUEST['s'])) ) {
			$this->filters['search'] = esc_attr($_REQUEST['s']);
		} else {
			$this->filters['search'] = '';
		}
		
		//echo "filters<pre>"; print_r($this->filters); echo "</pre>";
		
	}
	
/*	
	function search_box( $text, $input_id ) {
		if ( empty( $_REQUEST['s'] ) && !$this->has_items() )
			return;

		$input_id = $input_id . '-search-input';

		if ( ! empty( $_REQUEST['blog'] ) )
			echo '<input type="hidden" name="blog" value="' . esc_attr( $_REQUEST['blog'] ) . '" />';
		if ( ! empty( $_REQUEST['orderby'] ) )
			echo '<input type="hidden" name="orderby" value="' . esc_attr( $_REQUEST['orderby'] ) . '" />';
		if ( ! empty( $_REQUEST['order'] ) )
			echo '<input type="hidden" name="order" value="' . esc_attr( $_REQUEST['order'] ) . '" />';
		if ( ! empty( $_REQUEST['post_mime_type'] ) )
			echo '<input type="hidden" name="post_mime_type" value="' . esc_attr( $_REQUEST['post_mime_type'] ) . '" />';
		if ( ! empty( $_REQUEST['detached'] ) )
			echo '<input type="hidden" name="detached" value="' . esc_attr( $_REQUEST['detached'] ) . '" />';
?>
<p class="search-box">
	<label class="screen-reader-text" for="<?php echo $input_id ?>"><?php echo $text; ?>:</label>
	<input type="search" id="<?php echo $input_id ?>" name="s" value="<?php _admin_search_query(); ?>" />
	<?php submit_button( $text, 'button', false, false, array('id' => 'search-submit') ); ?>
</p>
<?php
	}
*/
		
	function prepare_items() {
		global $role, $usersearch, $blog_id;

		$usersearch = isset( $_REQUEST['s'] ) ? trim( $_REQUEST['s'] ) : '';

		$role = isset( $_REQUEST['role'] ) ? $_REQUEST['role'] : '';

		$per_page = 'users_per_page';
		$users_per_page = $this->get_items_per_page( $per_page );

		$paged = $this->get_pagenum();

		$args = array(
			'blog_id'	=>	$blog_id,
			'number' 	=> 	$users_per_page,
			'offset' 	=> 	( $paged-1 ) * $users_per_page,
			'role' 		=> 	$role,
			'search' 	=> 	$usersearch,
			'fields' 	=> 	'all_with_meta'
		);

		// Under Multisite on the Network the superadmin can filter users by blog. So check for filters. 
		if (is_multisite()) {
			if (is_main_site()) {
				$args['blog_id'] = 0;
			
				if ((isset($this->filters['blog_id'])) && ($this->filters['blog_id'] > 0)) {
					$args['blog_id'] = $this->filters['blog_id'];
				}
			} else {
				// This was an attempt to exclude Super Admins from showing in the sub-sites. But maybe later
				//$args = array(
				//	'blog_id'	=>	0,
				//	'number' 	=> 	0,
				//	'offset' 	=> 	0,
				//	'role' 		=> 	'Super Admin',
				//	'fields' 	=> 	array( 'ID' ),
				//);
				//echo "args<pre>"; print_r($args); echo "</pre.";
				//$wp_superadmin_search = new WP_User_Query( $args );
				//$results = $wp_superadmin_search->get_results();
				//echo "results<pre>"; print_r($results); echo "</pre>";
			}
		}
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
		//echo "args<pre>"; print_r($args); echo "</pre>";
		$wp_user_search = new WP_User_Query( $args );
		//echo "wp_user_search<pre>"; print_r($wp_user_search); echo "</pre>";
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

		if ((is_multisite()) && (is_network_admin())) {
			?><div class="alignleft actions"><?php
			$this->show_filters_blog();
			?></div><?php
			?><input id="post-query-submit" class="button-secondary" type="submit" value="Filter" name="chat-filter"><?php
		}
	}

	function show_filters_blog() {

		$placeholder_text = __('Blog ID', 'affiliate');
		if ((defined('SUBDOMAIN_INSTALL')) && (SUBDOMAIN_INSTALL == true)) {
			$placeholder_text .= ', '. __('Sub-Domain', 'affiliate');
		} else {
			$placeholder_text .= ', '. __('Domain Path', 'affiliate');
		}
		$placeholder_text .= __(' or Mapped Domain', 'affiliate');
		?><label for="affiliate-search-blog"><?php _e('Blog:', 'affiliate'); ?> <input size="40" type="text" id="affiliate-search-blog" name="blog" value="<?php echo $this->filters['blog']; ?>" placeholder="<?php echo $placeholder_text; ?>" /><?php
	}

	function current_action() {
		if ( isset($_REQUEST['changeit']) && !empty($_REQUEST['new_role']) )
			return 'promote';

		return parent::current_action();
	}

	function get_columns() {
		$columns = array();
		//$columns['cb']			= '<input type="checkbox" />';
		$columns['username'] 	= __( 'Username', 'affiliate' );

		$columns['name']     	= __( 'Name', 'affiliate' );

		if ((is_multisite()) && (is_network_admin())) {
			$columns['blog']	= __('Blog', 'affiliate');
		}

		if (aff_get_option('affiliateenableapproval', 'no') == 'yes') {
			$columns['approval'] = __('Approved', 'affiliate');
		}
			
		$columns['paypal']    	= __( 'PayPal account', 'affiliate' );
		$columns['reference']	= __( 'Reference', 'affiliate' );
		$columns['linked']		= __('URL','affiliate');
		
		return $columns;
	}

	function get_sortable_columns() {
		$c = array(
			'username' => 'login',
			'name'     => 'name'
		);

		if ((is_multisite()) && (is_network_admin())) {
			$c['blog'] = 'blog';
		}
		return $c;
	}


	function display() {
		extract( $this->_args );
		$this->display_tablenav( 'top' );
		?>
		<table class="wp-list-table <?php echo implode( ' ', $this->get_table_classes() ); ?>" cellspacing="0">
		<thead>
		<tr>
			<?php $this->print_column_headers(); ?>
		</tr>
		</thead>
		<tbody id="the-list"<?php if ( $singular ) echo " class='list:$singular'"; ?>>
			<?php $this->display_rows_or_placeholder(); ?>
		</tbody>
		<tfoot>
		<tr>
			<?php $this->print_column_headers( false ); ?>
		</tr>
		</tfoot>
		</table>
		<?php
		$this->display_tablenav( 'bottom' );
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

		//$url = 'admin.php?page=affiliatesadminmanage';

		$checkbox = '';
		// Check if the user for this row is editable
		if ( current_user_can( 'list_users' ) ) {

			// Set up the hover actions for this user
			$actions = array();

			$edit = '<strong><a href="'. $this->url_base .'&amp;subpage=summary&amp;id='. $user_object->ID .'">'. $user_object->user_login .'</a></strong>';
			$actions['edit'] = '<a title="'. __('Manage Affiliate','affiliate') .'" href="' . $this->url_base .'&amp;subpage=summary&amp;id='. $user_object->ID .'" class="edit">'. __('manage','affiliate') . '</a>';

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
				//case 'cb':
				//	$r .= "<th scope='row' class='check-column'>$checkbox</th>";
				//	break;
					
				case 'blog': 
					$blog_name = '';
					$primary_blog = get_user_meta( $user_object->ID, 'primary_blog', true);
					if (!empty($primary_blog)) {
						$blog_details = get_blog_details($primary_blog);
						//echo "blog_details<pre>"; print_r($blog_details); echo "</pre>";
						if (($blog_details) && (!empty($blog_details))) {
							$blog_name .= $blog_details->blogname;
							$actions = array();
							$filter_url = add_query_arg('blog', $blog_details->blog_id, $this->url_base);
							
							$search_s = isset($_REQUEST['s']) ? esc_attr( wp_unslash( $_REQUEST['s'] ) ) : '';
							if (!empty($search_s)) 
								$filter_url = add_query_arg('s', $this->filters['search'], $filter_url);
														
							$actions['affiliate-filter'] = '<a title="'. __('Filter listing by this Blog', 'affiliate') .'" href="'. $filter_url  .'" >'. __('filter', 'affiliate') .'</a>';
							$actions['affiliate-dashboard'] = '<a title="'. __('Visit Dashboard', 'affiliate') .'" href="'. get_admin_url($blog_details->blog_id) .'" >'. __('dashboard', 'affiliate') .'</a>';
							$actions['affiliate-dashboard-admin'] = '<a title="'. __('Visit Dashboard', 'affiliate') .'" href="'. add_query_arg('page', 'affiliatesadmin', get_admin_url($blog_details->blog_id)) .'" >'. __('affiliate', 'affiliate') .'</a>';
							
							
							$actions['affiliate-visit'] = '<a title="'. __('Visit Site', 'affiliate') .'" href="'. $blog_details->siteurl .'" >'. __('visit', 'affiliate') .'</a>';
							$blog_name .= $this->row_actions( $actions );
						}
					}					
					$r .= '<td '. $attributes .'>'. $blog_name .'</td>';

					break;
				case 'username':
				
					$r .= "<td $attributes>$avatar $edit</td>";
					break;
				case 'name':
					$r .= "<td $attributes>$user_object->first_name $user_object->last_name</td>";
					break;

				case 'approval':
					$app = get_user_meta( $user_object->ID, 'affiliateapproved', true );
					//echo "app[". $app ."]<br />";
					if(empty($app)) $app = 'no';
					$r .= "<td $attributes>". ucwords($app) ."</td>";
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