<?php

/*
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
4 */

namespace LDAP;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false
		  ? getenv( 'MW_INSTALL_PATH' )
		  : __DIR__ . '/../../..';
$maintphp = $basePath . '/maintenance/Maintenance.php';
if ( !is_readable( $maintphp ) ) {
	echo "Please set the environment variable MW_INSTALL_PATH to the MediaWiki path.\n";
	exit;
}
require_once $maintphp;

class ListAllObjects extends \Maintenance {
	/**
	 * Yet another constructor
	 */
	public function __construct() {
		parent::__construct();
		$this->addDescription( "A short script to show everything in a remote LDAP " .
							   "repository" );
	}

	/**
	 * Basic executioner
	 */
	public function execute() {
		$ldap = Connection::get();
		$res = $ldap->search( "objectclass=*" );
		$this->output( "Got {$res['count']} object(s).\n" );
		foreach ( range( 0, $res['count'] - 1 ) as $index ) {
			$this->output( "$index: " );
			$this->outputObject( $res[$index] );
		}
	}

	/**
	 * Output one Object returned by LDAP
	 * @param array $obj the object information
	 * @param string $indent what the indention should be.
	 */
	protected function outputObject( array $obj, $indent = "  " ) {
		$dName = $obj['dn'];
		$count = $obj['count'];
		$this->output( "$dName\n" );
		foreach ( range( 0, $count - 1 ) as $index ) {
			$type = isset( $obj[$index] ) ? $obj[$index] : null;
			if ( $type ) {
				$this->output( $indent );
				$this->output( "$type:\n" );
				foreach ( range( 0, $obj[$type]["count"] - 1 ) as $jndex ) {
					$this->output( $indent . $indent . $obj[$type][$jndex] . "\n" );
				}
				$this->outputMembership( $dName );
			}
		}
	}

	/**
	 * Print the memberships for an object
	 * @param string $dName distinguished name for object
	 */
	protected function outputMembership( $dName ) {
		return;
		$this->output( "-----\n\n{$indent}Membership:\n" );
	}
}

$maintClass = 'LDAP\ListAllObjects';

require_once RUN_MAINTENANCE_IF_MAIN;
