<?php

namespace LDAP;

use GlobalVarConfig;
use ConfigFactory;
use MWException;
use User;

class Group {

	$this->setupGroupMap();


	static public function makeConfig() {
		return new GlobalVarConfig( 'LDAPGroup' );
	}

	protected function setGroupRestrictions( $groupMap = [] ) {
		global $wgGroupPermissions, $wgAddGroups, $wgRemoveGroups;
		foreach( $groupMap as $name => $DNs ) {
			if ( !isset( $wgGroupPermissions[$name] ) ) {
				$wgGroupPermissions[$name] = $wgGroupPermissions['user'];
			}
		}

		$groups = array_keys( $groupMap );
		$nonLDAPGroups = array_diff( array_keys( $wgGroupPermissions ),
									 $groups );

		// Restrict the ability of users to change these rights
		foreach (
			array_unique( array_keys( $wgGroupPermissions ) ) as $group )
		{
			if ( isset( $wgGroupPermissions[$group]['userrights'] ) &&
				 $wgGroupPermissions[$group]['userrights'] ) {
				$wgGroupPermissions[$group]['userrights'] = false;
				if ( !isset( $wgAddGroups[$group] ) ) {
					$wgAddGroups[$group] = $nonLDAPGroups;
				}
				if ( !isset( $wgRemoveGroups[$group] ) ) {
					$wgRemoveGroups[$group] = $nonLDAPGroups;
				}
			}
		}
	}

	protected function doGroupMapUsingChain( $userDN ) {
		list($cn, $rest) = explode( ",", $userDN );

		foreach( $this->ldapGroupMap as $groupDN => $group ) {
			$entry = $this->doLDAPSearch(
				"(&(objectClass=user)($cn)" .
				"(memberOf:1.2.840.113556.1.4.1941:=$groupDN))" );
			if( $entry[ 'count' ] === 1 ) {
				$this->ldapData'memberof'][] = $groupDN;
			}
		}
	}

	protected function setupGroupMap() {
		$config
			= ConfigFactory::getDefaultInstance()->makeConfig( 'LdapGroups' );
		$groupMap = $config->get("Map");

		foreach( $groupMap as $name => $DNs ) {
			foreach ($DNs as $key) {
				$lowLDAP = strtolower( $key );
				$this->mwGroupMap[ $name ][] = $lowLDAP;
				$this->ldapGroupMap[ $lowLDAP ] = $name;
			}
		}
		$this->setGroupRestrictions( $groupMap );
	}

	public function fetchLDAPData( User $user ) {
		$email = $user->getEmail();

		if( !$email ) {
			// Fail early
			throw new MWException( "No email found for $user" );
		}
		wfDebug( __METHOD__ . ": Fetching user data for $user from LDAP\n" );
		$entry = $this->doLDAPSearch( $this->param['searchattr'] .
									"=" . $user->getEmail() );

		if ( $entry['count'] === 0 ) {
			wfProfileOut( __METHOD__ );
			throw new MWException( "No user found with the ID: " .
								   $user->getEmail() );
		}
		if ( $entry['count'] !== 1 ) {
			wfProfileOut( __METHOD__ );
			throw new MWException( "More than one user found " .
								   "with the ID: $user" );
		}

		$this->ldapData = $entry[0];
		$config
			= ConfigFactory::getDefaultInstance()->makeConfig( 'LdapGroups' );
		if ( $config->get( "UseMatchingRuleInChainQuery" ) ) {
			$this->doGroupMapUsingChain( $this->ldapData['dn'] );
		}

		return $this->ldapData;
	}

	public function mapGroups( User $user ) {
		# Create a list of LDAP groups this person is a member of
		$memberOf = [];
		if ( isset( $this->ldapData['memberof'] ) ) {
			wfDebugLog( __METHOD__, "memberof: " .var_export( $this->ldapData['memberof'], true ) );
			$tmp = array_map( 'strtolower',$this->ldapData['memberof'] );
			unset( $tmp['count'] );
			$memberOf = array_flip( $tmp );
		}

		wfDebugLog( "In Groups: ", implode( ", ", $user->getGroups() ) );
		# This is a list of LDAP groups that map to MW groups we already have
		$hasControlledGroups = array_intersect( $this->ldapGroupMap,
												$user->getGroups() );

		# This is a list of groups that map to MW groups we do NOT already have
		$notControlledGroups = array_diff( $this->ldapGroupMap,
										   $user->getGroups() );

		# LDAP-mapped MW Groups that should be added because they aren't
		# in the user's list of MW groups
		$addThese = array_keys(
			array_flip( array_intersect_key( $notControlledGroups,
											 $memberOf ) ) );

		# MW Groups that should be removed because the user doesn't have any
		# of LDAP groups
		foreach ( array_keys( $this->mwGroupMap ) as $checkGroup ) {
			$matched = array_intersect( $this->mwGroupMap[$checkGroup],
										array_flip( $memberOf ) );
			if( count( $matched ) === 0 ) {
				wfDebugLog( __METHOD__, "removing: $checkGroup" );
				$user->removeGroup( $checkGroup );
			}
		}

		foreach ( $addThese as $group ) {
			$user->addGroup( $group );
			wfDebugLog( __METHOD__, "Adding: $group" );
		}
		// saving now causes problems.
		#$user->saveSettings();
	}

	// This hook is probably not the right place.
	static public function loadUser( $user, $email ) {
		$here->fetchLDAPData( $user, $email );

		// Make sure user is in the right groups;
		$here->mapGroups( $user );
	}
}
