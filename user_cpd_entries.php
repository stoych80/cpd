<?php
if(!class_exists('WP_List_Table')) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}
if(!is_admin()) {
	require_once( ABSPATH . 'wp-admin/includes/screen.php' );
	require_once( ABSPATH . 'wp-admin/includes/class-wp-screen.php' );
	require_once( ABSPATH . 'wp-admin/includes/template.php' );
}
class user_cpd_entries extends WP_List_Table {

	protected static $wp_user_id;
	protected static $dd_cpd_entry_fields;
	protected static $is_admin;
	protected static $dd_cpd_frontend_orderable_fields;
	
	public static $filters_where=[];
	protected static $filters_having=[];
	private static $points_or_hours_num_submitted=[];
	/** Class constructor */
	public function __construct($wp_user_id) {
		parent::__construct( array(
			'singular' => 'CPD entry', //singular name of the listed records
			'plural'   => 'CPD entries', //plural name of the listed records
			'ajax'     => false,
			'screen' => 'user_cpd_entries'
		));
		if (isset($_REQUEST['ddcpd_filter']) && is_array($_REQUEST['ddcpd_filter'])) {
			$dd_cpd_entry_fields = dd_cpd::get_dd_cpd_entry_fields();
			$dd_cpd_entry_fields = array_keys($dd_cpd_entry_fields);
			for ($i=0;$i<count($dd_cpd_entry_fields);$i++) {
				$dd_cpd_entry_fields[$i] = 'e.'.$dd_cpd_entry_fields[$i];
			}
			$is_points_or_is_hours = dd_cpd::get_is_points_or_is_hours();
			foreach ($_REQUEST['ddcpd_filter'] as $f=>$val) {
				if ((empty($val['value']) && !in_array($val['operator'], ['IS EMPTY','IS NOT EMPTY'])) || empty($val['operator']) || !in_array($f, array_merge(['e.cycle_id'],$dd_cpd_entry_fields)) || !in_array($val['operator'], ['=','<=','>=','<>','IN','NOT IN','CONTAINS','NOT CONTAINS','IS EMPTY','IS NOT EMPTY'])) continue;
				$val2 = null;
				if (is_string($val['value'])) {
					$val2=esc_sql(trim($val['value']));
				} else if (is_array($val['value'])) {
					$val2=array_map('esc_sql', array_map('trim', $val['value']));
				}
				if (empty($val2) && !in_array($val['operator'], ['IS EMPTY','IS NOT EMPTY'])) continue;
				switch($f) {
					case 'entries_num':
						self::$filters_having[]=$f . " " . $val['operator']." '".$val2."'";
					break;
					case 'points_or_hours_num':
						if ($is_points_or_is_hours == 'points') {
							self::$filters_having[]=$f . " " . $val['operator']." '".$val2."'";
						} else {
							self::$points_or_hours_num_submitted = ['operator'=>$val['operator'], 'val'=>trim($val['value'])];
						}
					break;
					default:
						if ($f == 'e.points_or_hours' && $is_points_or_is_hours == 'hours') {
							
						} else if ($f=='c.audit_status' && is_array($val2) && in_array('awaiting_request_a_review',$val2)) {
							self::$filters_where[]="($f " . $val['operator']." ('".implode("','",$val2)."')".($val['operator']=='IN' ? " OR $f IS NULL" : " AND $f IS NOT NULL").")";
						} else if ($f=='c.passes') {
							if ($val2=='yes') {
								self::$filters_where[]="$f " . $val['operator']."1";
							} else if ($val2=='no') {
								self::$filters_where[]="($f " . $val['operator']."0 OR $f IS NULL)";
							}
						} else {
							if (in_array($val['operator'], ['IS EMPTY','IS NOT EMPTY'])) {
								self::$filters_where[]=$val['operator']=='IS NOT EMPTY' ? "($f IS NOT NULL AND $f != '')" : "($f IS NULL OR $f = '')";
							} else {
								if (strpos($val['operator'], 'CONTAINS')!==false) {
									$val['operator'] = str_replace('CONTAINS', 'LIKE', $val['operator']);
									$val2 = '%'.$val2.'%';
								}
								self::$filters_where[]=$f . " " . $val['operator'].(is_array($val2) ? " ('".implode("','",$val2)."')" : " '".$val2."'");
							}
						}
					break;
				}
			}
		}
		self::$dd_cpd_frontend_orderable_fields = apply_filters('dd_cpd_frontend_orderable_fields',array('title','activity_type','points_or_hours','date'));
		self::$wp_user_id=absint($wp_user_id);
		//IF THE FIELDS ARE CHANGED YOU NEED TO EXTEND THIS CLASS AND SPECIFY THE FUNCTIONS FOR THE NEW FIELDS IN THE CHILD CLASS. Search for the hooks _frontend_cpd_entries_list_class_file & _frontend_cpd_entries_list_class_name WHERE YOU NEED TO SPECIFY THE CHILD CLASS
		self::$dd_cpd_entry_fields = dd_cpd::get_dd_cpd_entry_fields();
		self::$is_admin = is_admin(); 
if (!self::$is_admin) { ?>
<script type="text/javascript">
jQuery(function ($) {
	$('#cb-select-all-1, #cb-select-all-2').click(function () {
		var is_all_checked = $(this).is(':checked');
		$('#cb-select-all-1').prop('checked', is_all_checked);
		$('#cb-select-all-2').prop('checked', is_all_checked);
		$('input[name=bulk-ids\\[\\]]').each(function () {
			$(this).prop('checked', is_all_checked);
		});
	});
	$('div.dd_cpd_list_cycle').tooltip({
      show: {
        effect: "slideDown",
        delay: 250
      }
    });
});
</script>
<?php
}
	}

	/**
	 * Retrieve users data from the database
	 *
	 * @param int $per_page
	 * @param int $page_number
	 *
	 * @return mixed
	 */
	public static function get_items( $per_page =10, $page_number = 1 ) {
		global $wpdb;
		$sql = "SELECT e.id,e.cycle_id,e.`".implode('`,e.`', array_keys(self::$dd_cpd_entry_fields))."`,o.option_title FROM `dd_cpd_user_entries` e LEFT JOIN dd_cpd_field_options o ON o.id=e.activity_type WHERE e.wp_user_id=".self::$wp_user_id;
		if (self::$filters_where) {
			$sql .= ' AND '.  implode(' AND ', self::$filters_where);
		}
		$sql .= ' GROUP BY e.id';
		if (self::$filters_having) {
			$sql .= ' HAVING '.  implode(' AND ', self::$filters_having);
		}
		if (!empty($_REQUEST['orderby']) && (in_array($_REQUEST['orderby'], self::$dd_cpd_frontend_orderable_fields) || $_REQUEST['orderby']=='option_title')) {
			$sql .= ' ORDER BY ' . esc_sql( $_REQUEST['orderby'] );
			$sql .= !empty($_REQUEST['order']) ? ' ' . esc_sql($_REQUEST['order']) : ' ASC';
		} else {
			$sql .= ' ORDER BY ' . apply_filters('dd_cpd_frontend_initial_order_field','date DESC');
		}

		$sql .= " LIMIT $per_page";
		$sql .= ' OFFSET ' . ( $page_number - 1 ) * $per_page;
		$result = $wpdb->get_results( $sql, ARRAY_A);
		return $result;
	}

	/** Text displayed when no user data is available */
	public function no_items() {
		echo 'No CPD entries';
	}

	/**
	 * Render a column when no column specific method exist.
	 *
	 * @param array $item
	 * @param string $column_name
	 *
	 * @return mixed
	 */
	public function column_default( $item, $column_name ) {
		/*switch ( $column_name ) {
			case 'address':
			case 'city':
				return $item[ $column_name ];
			default:
				return print_r( $item, true ); //Show the whole array for troubleshooting purposes
		}*/
	}

	/**
	 * Render the bulk edit checkbox
	 *
	 * @param array $item
	 *
	 * @return string
	 */
	function column_cb( $item ) {
		return sprintf('<input type="checkbox" name="bulk-ids[]" value="%d" />', $item['id']);
	}

	
	/**
	 * Methods for the columns. 
	 * IF THE FIELDS ARE CHANGED YOU NEED TO EXTEND THIS CLASS AND SPECIFY THE FUNCTIONS FOR THE NEW FIELDS IN THE CHILD CLASS. Search for the hooks _frontend_cpd_entries_list_class_file & _frontend_cpd_entries_list_class_name WHERE YOU NEED TO SPECIFY THE CHILD CLASS
	 *
	 * @param array $item an array of DB data
	 * @return string
	 */
	public function column_title( $item ) {
		return '<strong>'.$item['title'].'</strong>';
	}
	public function column_option_title($item) {
		return $item['option_title'];
	}
	public function column_points_or_hours($item) {
		return $item['points_or_hours'];
	}
	public function column_date($item) {
		return date('d/m/Y',  strtotime($item['date']));
	}
	public function column_learning_outcome($item) {
		return $item['learning_outcome'];
	}
	public function column_work_completed($item) {
		return $item['work_completed'];
	}
	public function column_actions($item) {
		global $wpdb;
		$cycle_html = '';
		if ($item['cycle_id']) {
			$cycle = $wpdb->get_row('SELECT title,from_date,to_date FROM `dd_cpd_cycles` WHERE cycle_id='.$item['cycle_id']);
			$cycle_html ='<div class="dd_cpd_list_cycle" style="cursor:help;margin-top:8px;" title="From '.date('d/m/Y', strtotime($cycle->from_date)).' - '.date('d/m/Y', strtotime($cycle->to_date)).'">cycle: <b>'.esc_html($cycle->title).'</b></div>';
		}
		$is_cpd_entry_locked = dd_cpd::isCpdEntryLocked($item['id']);
		$current_attachments_html = $is_cpd_entry_locked ? '<span style="color:red;">Locked</span>' : '';
		$results = $wpdb->get_var('SELECT COUNT(a.id) FROM dd_cpd_user_entries_attachments a JOIN dd_cpd_user_entries_attachments_link al On al.attachment_id=a.id WHERE al.user_entry_id='.$item['id']);
		if ($results) {
			$current_attachments_html .= '<div class="dd_cpd_attachments-wrapper">';
			$current_attachments_html .= '<span class="dd_cpd_attachments-label">'.apply_filters('dd_cpd_attachments_label','Attachments').':</span> <strong>'.$results.'</strong>';
			/*$current_attachments_html .= '<ul>';
			foreach ($results as $result) {
				$current_attachments_html .= '<li><strong>'.$result->title.'</strong><br>'.($result->filename ? 'File: <a href="'.strtok($_SERVER['REQUEST_URI'], '?').'?dd_cpd_view_attachment='.$result->id.'" target="_blank"><span class="dd_cpd_attachments-link">'.$result->filename.'</span></a><br>' : '').($result->description ? 'Description: '.$result->description.'<br>' : '').($result->url ? 'External url: '.$result->url.'<br>' : '').'</li>';
			}
			$current_attachments_html .= '</ul>';*/
			$current_attachments_html .= '</div>';
		}
		$request_uri = strtok($_SERVER['REQUEST_URI'], '?');
		return (!self::$is_admin && !$is_cpd_entry_locked ? '<a href="'.$request_uri.'?action=view&ID='.$item['id'].'" style="text-decoration:underline;">View</a> | <a href="'.$request_uri.'?action=edit&ID='.$item['id'].'" style="text-decoration:underline;">Edit</a> | <a href="'.$request_uri.'" theid="'.$item['id'].'" thetitle="'.esc_attr($item['title']).'" thehash="'.hash('sha256','***'.$item['id'].self::$wp_user_id.date('Y-m-d')).'" title="Delete this cpd entry" class="dd_cpd_delete_entry" style="text-decoration:underline;">Delete</a>' : '').$current_attachments_html.$cycle_html;
	}

	/**
	 *  Associative array of columns
	 * @return array
	 */
	public function get_columns() {
		$columns = array('cb' => '<input type="checkbox" />');
		$fields_to_be_filled_in_order_points_or_hours_to_count_against_the_current_cycle_completion = dd_cpd::get_dd_cpd_fields_to_be_filled_in_order_points_or_hours_to_count_against_the_current_cycle_completion();
		$dd_cpd_user_cpd_entries_grid_only_show_help_text_foreach_field = apply_filters('dd_cpd_user_cpd_entries_grid_only_show_help_text_foreach_field', false);
		foreach (self::$dd_cpd_entry_fields as $f_name => $details) {
			$help_text = '';
			if ($dd_cpd_user_cpd_entries_grid_only_show_help_text_foreach_field) {
				if (isset($details['help_text'])) $help_text = $details['help_text'];
				if (in_array($f_name, $fields_to_be_filled_in_order_points_or_hours_to_count_against_the_current_cycle_completion)) {
					$help_text .= $help_text ? ". " : '';
					$help_text .= 'This field must have a value in order to get the '.dd_cpd::get_dd_cpd_entry_field_label('points_or_hours').' of the corresponding CPD entry.';
				}
			}
			if ($f_name == 'activity_type') {
				$f_name = 'option_title';
			}
			$columns[$f_name]=$details['title'].($help_text ? dd_cpd::show_icon_information($help_text, 'return') : '');
		}
		return apply_filters('dd_cpd_user_cpd_entries_grid_only_columns', $columns) + ['actions'=>!self::$is_admin ? 'Actions' : 'Details'];
	}


	/**
	 * Columns to make sortable.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		$sortable_columns=[];
		foreach (self::$dd_cpd_frontend_orderable_fields as $f_name) {
			$sortable_columns[$f_name] = array($f_name, true);
		}
		$sortable_columns['option_title'] = array('option_title', true);
		return $sortable_columns;
	}
	
	public function get_bulk_actions() {
		$actions = array(
			'dd-cpd-bulk-frontend-export-selected-pdf' => 'Export selected to PDF',
			'dd-cpd-bulk-frontend-export-selected-csv' => 'Export selected to CSV',
			'dd-cpd-bulk-frontend-export-all-pdf' => 'Export all to PDF',
			'dd-cpd-bulk-frontend-export-all-csv' => 'Export all to CSV'
		);
		return $actions;
	}

	/**
	 * Handles data query and filter, sorting, and pagination.
	 */
	public function prepare_items() {

		$this->_column_headers = $this->get_column_info();

		/** Process bulk action */
//		$this->process_bulk_action(); // done in wp_loaded of wp-content/plugins/dd_cpd/dd_cpd.php

		$per_page     = 20;// $this->get_items_per_page( 'events_per_page', 20 );
		$current_page = $this->get_pagenum();
		$total_items  = $this->record_count();

		$this->set_pagination_args(array(
			'total_items' => $total_items, //WE have to calculate the total number of items
			'per_page'    => $per_page //WE have to determine how many items to show on a page
		));

		$this->items = self::get_items( $per_page, $current_page );
	}
	public function record_count() {
		global $wpdb;
		$sql = "SELECT COUNT(e.id) FROM `dd_cpd_user_entries` e WHERE e.wp_user_id=".self::$wp_user_id;
		if (self::$filters_where) {
			$sql .= ' AND '.  implode(' AND ', self::$filters_where);
		}
		if (self::$filters_having) {
			$sql .= ' HAVING '.  implode(' AND ', self::$filters_having);
		}
		return $wpdb->get_var($sql);
	}
	
	/*public function process_bulk_action() {
		
	}*/
}