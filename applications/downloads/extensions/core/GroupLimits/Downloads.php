<?php
/**
 * @brief		Group Limits
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Downloads
 * @since		21 Nov 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\downloads\extensions\core\GroupLimits;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Group Limits
 *
 * This extension is used to define which limit values "win" when a user has secondary groups defined
 */
class _Downloads
{
	/**
	 * Get group limits by priority
	 *
	 * @return	array
	 */
	public function getLimits()
	{
		return array (
			'exclude' 		=> array(),
			'lessIsMore'	=> array( 'idm_throttling', 'idm_wait_period' ),
			'neg1IsBest'	=> array(),
			'zeroIsBest'	=> array(),
			'callback'		=> array( 'idm_restrictions' => function( $a, $b, $k )
			{
				// Decode
				if ( isset( $a[ $k ] ) AND $a[ $k ] )
				{
					$a = json_decode( $a[ $k ], TRUE );
				}
				else
				{
					if( !isset( $b[ $k ] ) )
					{
						return null;
					}

					return $b[ $k ];
				}
				if ( isset( $b[ $k ] ) AND $b[ $k ] )
				{
					$b = json_decode( $b[ $k ], TRUE );
				}
				else
				{
					if( !isset( $a[ $k ] ) )
					{
						return null;
					}

					return json_encode( $a[ $k ] );
				}
				$return = array();
				
				// Lower is better
				foreach ( array( 'limit_sim', 'min_posts' ) as $k )
				{
					$return[ $k ] = ( $a[ $k ] < $b[ $k ] ) ? $a[ $k ] : $b[ $k ];
				}
				
				// Higher is better
				foreach ( array( 'daily_bw', 'weekly_bw', 'monthly_bw', 'daily_dl', 'weekly_dl', 'monthly_dl' ) as $k )
				{
					$return[ $k ] = ( $a[ $k ] > $b[ $k ] ) ? $a[ $k ] : $b[ $k ];
				}
				
				// Encode
				return json_encode( $return );
			} )
		);
	}
}