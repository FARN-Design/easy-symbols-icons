<?php

namespace Farn\EasySymbolsIcons\database;

use EasyIcon;
use wpdb;

class Settings
{
	private static wpdb $wpdb;
	private static string $tableName;

	public static function setup(): void {
		global $wpdb;
		self::$wpdb = $wpdb;

		self::$tableName = $wpdb->prefix . EasyIcon::$prefix . str_replace(".php", "", basename(__FILE__));

		self::createDatabaseTable();
		self::setupDefaults();
    }

	/**
	 * Creates the database table for the storage of plugin specific settings.
	 * Creates the database table when the table not already exists.
	 *
	 * @return void
	 */
	private static function createDatabaseTable(): void {
		$charset_collate = self::$wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS ". self::$tableName . " (
	  		id mediumint(9) NOT NULL AUTO_INCREMENT,
	    	Setting varchar(255) NOT NULL UNIQUE,
	    	Value text NOT NULL,
	    	PRIMARY KEY (id)
	  	) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}


	/**
	 * Updates new Settings inside the table corresponding to the Settings Array.
	 *
	 * @return void
	 */
	private static function setupDefaults(): void {

		/**
		 * Use this Array to define Settings (and its default value) in the Database.
		 * If a Setting in removed here it will not be removed inside the database table.
		 * If a Setting is added it will be automatically added to the database table.
		 *
		 * @var array|string[] $defaultSettings Setting => SettingValue
		 */
		$defaultSettings = [
			'load_more_button_text' => 'Load More',
			'no_entries_found_text' => 'No Entries Found',
			'google_api_key' => '',
			'loaded_fonts' => json_encode([]),
		];

		$prepared_query = self::$wpdb->prepare("SELECT Setting FROM %i",array(self::$tableName));
		$results = self::$wpdb->get_results($prepared_query, ARRAY_N);
		$results = array_column($results,0);

		foreach ($defaultSettings as $key => $value) {
			if(!in_array($key, $results)){
				self::$wpdb->insert(self::$tableName,
					array(
						'Setting' => $key,
						'Value' => $value )
				);
			}
		}
	}

	/**
	 * Stores a new value for a given setting in the database.
	 *
	 * @param string $setting the setting (same name as in the DB) from which the value should change.
	 * @param string $settingsValue the new value of the setting.
	 *
	 * @return void
	 */
	public static function saveSettingInDB( string $setting, string $settingsValue): void {
		$sql = "UPDATE ".self::$tableName . " SET Value = %s WHERE Setting = %s;";
		$preparedStatement = self::$wpdb->prepare($sql, array($settingsValue,$setting));
		self::$wpdb->query( $preparedStatement );
	}

	/**
	 * This function gets a setting value from the database.
	 *
	 * @param string $setting Setting as String.
	 *
	 * @return mixed
	 */
	public static function getSettingFromDB( string $setting) {
		$sql = "SELECT Value FROM %i WHERE Setting = %s;";
		$prepared_query = self::$wpdb->prepare($sql, array(self::$tableName, $setting));
		$result = self::$wpdb->get_results($prepared_query, ARRAY_N);
		if (sizeof($result) == 0){
			wp_send_json_error("Database Setting not Found in Database: ".$setting);
		}

		return $result[0][0];
	}
}