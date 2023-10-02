<?php
/**
 * Class LP_Order_DB
 *
 * @author tungnx
 * @since 4.1.4
 */

defined( 'ABSPATH' ) || exit();

class LP_Order_DB extends LP_Database {
	private static $_instance;

	protected function __construct() {
		parent::__construct();
	}

	public static function getInstance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Get the latest LP Order id by user_id and course_id
	 *
	 * @param int|string $user_id LP_User is int, LP_User_Guest is string
	 * @param int $course_id
	 *
	 * @return null|string
	 * @since 4.1.4
	 * @author tungnx
	 * @version 1.0.1
	 */
	public function get_last_lp_order_id_of_user_course( $user_id, int $course_id ) {
		$key_cache = "lp/order/id/last/$user_id/$course_id";
		$order_id  = LP_Cache::cache_load_first( 'get', $key_cache );
		if ( false !== $order_id ) {
			return $order_id;
		}

		if ( ! $user_id || ! $course_id ) {
			return null;
		}

		$user_id_str = $this->wpdb->prepare( '%"%d"%', $user_id );

		$query = $this->wpdb->prepare(
			"SELECT p.ID FROM {$this->tb_posts} as p
			INNER join {$this->tb_postmeta} pm on p.ID = pm.post_id
			INNER join {$this->tb_lp_order_items} as oi on p.ID = oi.order_id
			INNER join {$this->tb_lp_order_itemmeta} as oim on oim.learnpress_order_item_id = oi.order_item_id
			WHERE post_type = %s
			AND pm.meta_key = %s
			AND (pm.meta_value = %s OR pm.meta_value LIKE '%s')
			AND oim.meta_key = %s
			AND oim.meta_value = %d
			ORDER BY p.ID DESC
			LIMIT 1
			",
			LP_ORDER_CPT,
			'_user_id',
			$user_id,
			$user_id_str,
			'_course_id',
			$course_id
		);

		$order_id = $this->wpdb->get_var( $query );

		LP_Cache::cache_load_first( 'set', $key_cache, $order_id );

		return $order_id;
	}

	/**
	 * Get order_item_ids by order_id
	 *
	 * @param int $order_id
	 * @author tungnx
	 * @since 4.1.4
	 * @version 1.0.0
	 * @return array
	 */
	public function get_order_item_ids( int $order_id ): array {
		$query = $this->wpdb->prepare(
			"SELECT order_item_id FROM $this->tb_lp_order_items
			WHERE order_id = %d
			",
			$order_id
		);

		return $this->wpdb->get_col( $query );
	}

	/**
	 * Delete row IN order_item_ids
	 *
	 * @param LP_Order_Filter $filter
	 * @author tungnx
	 * @since 4.1.4
	 * @version 1.0.0
	 * @return bool|int
	 * @throws Exception
	 */
	public function delete_order_item( LP_Order_Filter $filter ) {
		// Check valid user.
		if ( ! current_user_can( 'administrator' ) ) {
			throw new Exception( __( 'Invalid user!', 'learnpress' ) );
		}

		if ( empty( $filter->order_item_ids ) ) {
			return 1;
		}

		$where = 'WHERE 1=1 ';

		$where .= $this->wpdb->prepare(
			'AND order_item_id IN(' . LP_Helper::db_format_array( $filter->order_item_ids, '%d' ) . ')',
			$filter->order_item_ids
		);

		return $this->wpdb->query(
			"DELETE FROM {$this->tb_lp_order_items}
			{$where}
			"
		);
	}

	/**
	 * Delete row IN order_item_ids
	 *
	 * @param LP_Order_Filter $filter
	 * @author tungnx
	 * @since 4.1.4
	 * @version 1.0.0
	 * @return bool|int
	 * @throws Exception
	 */
	public function delete_order_itemmeta( LP_Order_Filter $filter ) {
		// Check valid user.
		if ( ! current_user_can( 'administrator' ) ) {
			throw new Exception( __( 'Invalid user!', 'learnpress' ) );
		}

		if ( empty( $filter->order_item_ids ) ) {
			return 1;
		}

		$where = 'WHERE 1=1 ';

		$where .= $this->wpdb->prepare(
			'AND learnpress_order_item_id IN(' . LP_Helper::db_format_array( $filter->order_item_ids, '%d' ) . ')',
			$filter->order_item_ids
		);

		return $this->wpdb->query(
			"DELETE FROM {$this->tb_lp_order_itemmeta}
			{$where}
			"
		);
	}

	public function date_chart_analytics( LP_Order_Filter $filter, string $date ) {
		$filter->only_fields[] = 'count( p.ID) as count_order';
		$filter->only_fields[] = 'HOUR(p.post_date) as order_time';
		$filter->where[]       = $this->wpdb->prepare( 'AND cast( p.post_date as DATE)= cast(%s as DATE)', $date );
		$filter->group_by      = 'order_time';
		// $filter->run_query_count = false;

		return $filter;
	}

	public function privious_days_analytics( LP_Order_Filter $filter, int $day ) {
		if ( $day < 2 ) {
			throw new Exception( 'Day must be greater than 2 days.', 'learnpress' );
		}
		$filter->only_fields[] = 'count( p.ID) as count_order';
		$filter->only_fields[] = 'DAY(p.post_date) as order_time';
		$filter->where[]       = $this->wpdb->prepare( 'AND p.post_date >= DATE_ADD(CURDATE(), INTERVAL -%d DAY)', $day );
		$filter->group_by      = 'order_time';

		return $filter;
	}

	public function month_chart_analytics( LP_Order_Filter $filter, string $date ) {
		$filter->only_fields[] = 'count( p.ID) as count_order';
		$filter->only_fields[] = 'DAY(p.post_date) as order_time';
		$filter->where[]       = $this->wpdb->prepare( 'AND EXTRACT(YEAR_MONTH FROM p.post_date)= EXTRACT(YEAR_MONTH FROM %s)', $date );
		$filter->group_by      = 'order_time';
		// $filter->run_query_count = false;

		return $filter;
	}

	public function year_chart_analytics( LP_Order_Filter $filter, string $date ) {
		$filter->only_fields[] = 'count( p.ID) as count_order';
		$filter->only_fields[] = 'MONTH(p.post_date) as order_time';
		$filter->where[]       = $this->wpdb->prepare( 'AND YEAR(p.post_date)= YEAR(%s)', $date );
		$filter->group_by      = 'order_time';
		// $filter->run_query_count = false;
		return $filter;
	}

	public function order_analytics_execute( $type = '', $value = '' ) {
		if ( ! $date ) {
			$date = current_time( 'mysql' );
		}
		$date                     = date( 'Y-m-d', strtotime( $date ) );
		$filter                   = new LP_Order_Filter();
		$filter->collection       = $this->tb_posts;
		$filter->collection_alias = 'p';
		$oi_table                 = $this->tb_lp_order_items;
		$oim_table                = $this->tb_lp_order_itemmeta;
		$filter->only_fields      = array(
			'p.ID',
			'p.post_date as order_date',
			// 'oi.item_id as course_id',

			// $this->wpdb->prepare( 'REPLACE(p.post_status, %s, %s) as order_status', 'lp-', '' ),
			// 'oim.meta_value as sale_price',
		);
		// $filter->join             = [
		// 	"INNER JOIN $oi_table AS oi ON p.ID = oi.order_id",
		// 	"INNER JOIN $oim_table AS oim ON oi.order_item_id = oim.learnpress_order_item_id",
		// ];
		$filter->limit = -1;
		$filter->where = [
			$this->wpdb->prepare( 'AND p.post_type=%s', $filter->post_type ),
			// $this->wpdb->prepare( 'AND p.post_status=%s', LP_ORDER_COMPLETED_DB ),
			// $this->wpdb->prepare( 'AND oim.meta_key=%s', '_total' ),
		];
		$filter->order_by = 'p.post_date';
		$filter->order    = 'asc';
		// $filter->group_by        = 'post_hour';
		$filter->query_count = false;
		$result              = $this->execute( $filter );
		return $result;
	}

	public function filter_order_count_statics( LP_Order_Filter $filter ) {
		// $filter->query_count = true;
		$filter->only_fields[]   = 'count( p.ID) as count_order';
		$filter->only_fields[]   = 'p.post_status';
		$filter->group_by        = 'p.post_status';
		$filter->run_query_count = false;

		return $filter;
	}

	public function get_chart_data( string $type, string $date ) {
		if ( ! $date ) {
			$date = current_time( 'mysql' );
		}
		$date                     = date( 'Y-m-d', strtotime( $date ) );
		$filter                   = new LP_Order_Filter();
		$filter->collection       = $this->tb_posts;
		$filter->collection_alias = 'p';

		$filter->where[] = $this->wpdb->prepare( 'AND p.post_status=%s', LP_ORDER_COMPLETED_DB );
		switch ( $type ) {
			case 'date':
				$filter = $this->date_chart_analytics( $filter, $date );
				break;
			case 'month':
				$filter = $this->month_chart_analytics( $filter, $date );
				break;
			case 'year':
				$filter = $this->year_chart_analytics( $filter, $date );
				break;
			default:
				// code...
				break;
		}
		$filter->run_query_count = false;
	}

	// public function
}
