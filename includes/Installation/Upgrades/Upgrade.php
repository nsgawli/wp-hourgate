<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Installation\Upgrades;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Version-gated migrations.
 */
class Upgrade {

	/**
	 * Run the upgrade process.
	 *
	 * Each version-gated step added here runs at most once per site: it only
	 * fires while the site's stored version is still behind the version
	 * that introduced it, and `Autoloader::set_installation_complete()`
	 * (called right after this) persists the new version so the same step
	 * won't run again on the next request. Example:
	 *
	 *     if ( version_compare( $current_version, '1.1.0', '<' ) ) {
	 *         self::add_some_column();
	 *     }
	 *
	 * Schema changes use guarded `ALTER TABLE` statements (check the column
	 * or index exists first) and backfills run in small `UPDATE ... LIMIT`
	 * batches.
	 *
	 * @param string $current_version Current version of the plugin.
	 * @version 1.0.0
	 * @return void
	 */
	public static function run( $current_version ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- used by future version gates.
	}
}
