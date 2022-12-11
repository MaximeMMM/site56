<?php
/**
 * @brief		4.1.8 Upgrade Code
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		11 Jan 2016
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\downloads\setup\upg_101024;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.8 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Download category sort options are stored with database prefix incorrectly.
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		foreach( \IPS\Db::i()->select( '*', 'downloads_categories' ) as $cat )
		{
			if ( mb_substr( $cat['csortorder'], 0, 5 ) === 'file_' )
			{
				\IPS\Db::i()->update( 'downloads_categories', array( 'csortorder' => mb_substr( $cat['csortorder'], 5 ) ), array( 'cid=?', $cat['cid'] ) );
			}
		}
		
		return TRUE;
	}
}