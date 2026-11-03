<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\RestApi;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use HourGate\RestApi\OrderingStatus;
use HourGate\RestApi\Pause;
use HourGate\RestApi\Schedule\Preview;
use HourGate\RestApi\Settings\Behaviour;
use HourGate\RestApi\Settings\Closures;
use HourGate\RestApi\Settings\General;
use HourGate\RestApi\Settings\Schedule;
use HourGate\RestApi\Status;

/**
 * REST API Loader.
 */
class Autoloader {

	/**
	 * Initialize REST API controllers.
	 *
	 * @return void
	 */
	public static function init() {

		General::init();
		Schedule::init();
		Closures::init();
		Preview::init();
		Behaviour::init();
		Pause::init();
		OrderingStatus::init();
		Status::init();
	}
}
