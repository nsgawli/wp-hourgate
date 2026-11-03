<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Core\Schedule;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * An open period in UTC: `[start, end)`, Unix seconds. Immutable.
 */
final class Window {

	/**
	 * First open second.
	 *
	 * @var int
	 */
	private $start;

	/**
	 * First closed second after the window.
	 *
	 * @var int
	 */
	private $end;

	/**
	 * Create a window.
	 *
	 * @param int $start UTC timestamp, inclusive.
	 * @param int $end   UTC timestamp, exclusive; after `$start`.
	 * @throws \InvalidArgumentException When `$end <= $start`.
	 */
	public function __construct( int $start, int $end ) {

		if ( $end <= $start ) {
			throw new \InvalidArgumentException( 'A window must end after it starts.' );
		}

		$this->start = $start;
		$this->end   = $end;
	}

	/**
	 * First open second (UTC).
	 *
	 * @return int
	 */
	public function get_start() {
		return $this->start;
	}

	/**
	 * First closed second after the window (UTC).
	 *
	 * @return int
	 */
	public function get_end() {
		return $this->end;
	}

	/**
	 * Whether an instant is inside: `start <= ts < end`.
	 *
	 * @param int $ts UTC timestamp.
	 * @return bool
	 */
	public function contains( int $ts ) {
		return $this->start <= $ts && $ts < $this->end;
	}

	/**
	 * The window as an array.
	 *
	 * @return array{start: int, end: int}
	 */
	public function to_array() {
		return array(
			'start' => $this->start,
			'end'   => $this->end,
		);
	}
}
