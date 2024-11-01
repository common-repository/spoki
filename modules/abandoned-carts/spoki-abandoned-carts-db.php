<?php

/**
 * Spoki Abandoned Carts Db class.
 */
class Spoki_Abandoned_Carts_Db
{
	protected static $_instance = null;

	public static function instance()
	{
		if (is_null(self::$_instance)) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 *  Create tables
	 */
	public function create_tables()
	{
		$this->create_cart_abandonment_table();
		$this->create_spoki_setting_table();
	}

	/**
	 *  Create Plugin Setting meta table.
	 */
	public function create_spoki_setting_table()
	{
		global $wpdb;

		$spoki_setting_tb = $wpdb->prefix . SPOKI_SETTING_TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		$query = $wpdb->prepare('SHOW TABLES LIKE %s', $spoki_setting_tb);

		if (!$wpdb->get_var($query) == $spoki_setting_tb) {
			$sql = "CREATE TABLE $spoki_setting_tb (
				`id` BIGINT(20) NOT NULL AUTO_INCREMENT,
				`meta_key` varchar(255) NOT NULL,
				`meta_value` longtext NOT NULL,
				PRIMARY KEY (`id`)
				) $charset_collate;\n";
			include_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta($sql);
		}
	}

	/**
	 *  Create tables for analytics.
	 */
	public function create_cart_abandonment_table()
	{

		global $wpdb;

		$cart_abandonment_db = $wpdb->prefix . SPOKI_ABANDONMENT_TABLE;
		$charset_collate = $wpdb->get_charset_collate();
		$query = $wpdb->prepare('SHOW TABLES LIKE %s', $cart_abandonment_db);
		if (!$wpdb->get_var($query) == $cart_abandonment_db) {
			// Cart abandonment tracking db sql command.
			$sql = "CREATE TABLE $cart_abandonment_db (
				id BIGINT(20) NOT NULL AUTO_INCREMENT,
				checkout_id int(11),
				order_id int(11),
				email VARCHAR(100),
				phone VARCHAR(100),
				cart_contents LONGTEXT,
				cart_total DECIMAL(10,2),
				session_id VARCHAR(60),
				other_fields LONGTEXT,
				order_status ENUM( 'normal','abandoned','completed','lost') DEFAULT 'normal',
				unsubscribed  boolean DEFAULT 0,
				contacted  boolean DEFAULT 0,
				recontacted  boolean DEFAULT 0,
				coupon_code VARCHAR(50),
				time DATETIME DEFAULT NULL,
				PRIMARY KEY  (`id`),
				UNIQUE KEY `session_id_UNIQUE` (`session_id`)
			) $charset_collate;\n";

			include_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta($sql);
		} else {
			// add "recontacted" column for version <= 2.7.4
			$check_column = (array)$wpdb->get_results("SELECT count(COLUMN_NAME) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME = '{$cart_abandonment_db}' AND COLUMN_NAME = 'recontacted'")[0];
			$check_column = (int)array_shift($check_column);
			if ($check_column == 0) {
				$wpdb->query("ALTER TABLE $cart_abandonment_db ADD COLUMN `recontacted` boolean DEFAULT 0");
			}

			// add "order_id" column for version <= 2.9.2
			$check_column = (array)$wpdb->get_results("SELECT count(COLUMN_NAME) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME = '{$cart_abandonment_db}' AND COLUMN_NAME = 'order_id'")[0];
			$check_column = (int)array_shift($check_column);
			if ($check_column == 0) {
				$wpdb->query("ALTER TABLE $cart_abandonment_db ADD COLUMN `order_id` int(11) DEFAULT null");
			}
		}
	}

	/**
	 *  Insert initial data.
	 */
	public function init_tables()
	{
		global $wpdb;
		$spoki_setting_tb = $wpdb->prefix . SPOKI_SETTING_TABLE;

		if ($wpdb->get_var("SHOW TABLES LIKE '$spoki_setting_tb'") !== $spoki_setting_tb) {
			error_log('Error: Table does not exist: ' . $spoki_setting_tb);
			return;
		}

		$meta_count = $wpdb->get_var("SELECT COUNT(*) FROM $spoki_setting_tb");
		if ((!$meta_count)) {
			$env_file_path = SPOKI_DIR . '/.env';

			if (file_exists($env_file_path)) {
				$meta_data = parse_ini_file($env_file_path);
				if ($meta_data === false) {
					error_log('Error: Failed to parse .env file at ' . $env_file_path);
					return;
				}
				$meta_data["access_token"] = md5(uniqid(wp_rand(), true));
				foreach ($meta_data as $meta_key => $meta_value) {
					$wpdb->insert(
						$spoki_setting_tb,
						array('meta_key' => $meta_key, 'meta_value' => $meta_value),
						array('%s', '%s')
					);
				}
			} else {
				error_log('Warning: .env file not found at ' . $env_file_path);
			}
		}
	}
}
