<?php
/**
 * Provide a standard interface for MediaWiki LDAP lookups
 *
 * Copyright (C) 2017  Mark A. Hershberger
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace LDAP;

use ConfigFactory;
use GlobalVarConfig;
use MWException;

class Connection {
	protected static $ldap;
	protected static $conn;
	protected $param;

	/**
	 * ye olde constructor
	 * @param array $param of parameters
	 */
	public function __construct( $param ) {
		wfDebug( __METHOD__ );
		$this->param = $param;
		self::$conn = ldap_connect( $this->param['server'] );
		if ( !self::$conn ) {
			throw new MWException( "Error Connecting to LDAP server.!" );
		}

		ldap_set_option( self::$conn, LDAP_OPT_REFERRALS, 0 );
		ldap_set_option( self::$conn, LDAP_OPT_PROTOCOL_VERSION, 3 );
		if ( !ldap_bind( self::$conn, $this->param['user'],
						 $this->param['pass'] )
		) {
			throw new MWException( "Couldn't bind to LDAP server: " .
								   ldap_error( self::$conn ) );
		}
	}

	/**
	 * Connect using information in the given INI file
	 * @param string $iniFile full path to file
	 * @return LDAP::Connection
	 */
	public static function newFromIniFile( $iniFile = null ) {
		if ( !is_readable( $iniFile ) ) {
			if ( substr( $iniFile, 0, 1 ) !== "/" &&
				 !is_readable( __DIR__ . '/../' . $iniFile ) ) {
				throw new MWException( "Can't read '$iniFile'" );
			}
			$iniFile = __DIR__ . '/../' . $iniFile;
		}
		$data = parse_ini_file( $iniFile );
		if ( $data === false ) {
			$err = error_get_last();
			throw new MWException( "Error reading '$iniFile': " . $err['message'] );
		}

		self::$ldap = new Connection( $data );
		return self::$ldap;
	}

	/**
	 * Get the config for this LDAP\Connection
	 * @return GlobalVarConfig
	 */
	public static function makeConfig() {
		return new GlobalVarConfig( 'LDAP' );
	}

	/**
	 * Get the connection that is in the configuration file
	 * @return LDAP\Connection
	 */
	public static function get() {
		if ( !isset( self::$ldap ) ) {
			$conf = new GlobalVarConfig( 'LDAP' );

			self::newFromIniFile( $conf->get( "IniFile" ) );
		}
		return self::$ldap;
	}

	/**
	 * Perform an LDAP search
	 * @param string $match desired in ldap search format
	 * @param array $attrs list of attributes to get, default to '*'
	 * @return array
	 */
	public function search( $match, $attrs = [ "*" ] ) {
		wfProfileIn( __METHOD__ . " - LDAP Search" );
		$runTime = -microtime( true );
		if ( !isset( $this->param['basedn'] ) ) {
			throw new MWException( "No basedn set!" );
		}
		$res = ldap_search( self::$conn, $this->param['basedn'],
							$match, $attrs );
		if ( !$res ) {
			wfProfileOut( __METHOD__ . " - LDAP Search" );
			throw new MWException( "Error in LDAP search: " .
								   ldap_error( self::$conn ) );
		}

		$entry = ldap_get_entries( self::$conn, $res );
		$runTime += microtime( true );
		wfProfileOut( __METHOD__ . " - LDAP Search" );
		wfDebugLog( __CLASS__, "Ran LDAP search for '$match' in $runTime seconds.\n" );
		return $entry;
	}

}
