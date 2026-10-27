<?php
/**
 * Odinstalowanie wtyczki.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia;

defined( 'ABSPATH' ) || exit;

/**
 * Usuwa dane modułów, gdy właściciel zaznaczył to w ustawieniach.
 */
final class Uninstaller {

	/**
	 * Sprząta dane albo zostawia je w bazie.
	 *
	 * @param string $modules_dir Folder z modułami.
	 */
	public static function run( string $modules_dir ): void {
		if ( 'yes' !== get_option( Plugin::OPTION_DELETE_DATA ) ) {
			return;
		}

		$registry = new Registry( $modules_dir );
		$registry->discover();
		$registry->uninstall_all();

		delete_option( Plugin::OPTION_DELETE_DATA );
	}
}
