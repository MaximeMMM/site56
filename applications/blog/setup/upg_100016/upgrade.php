<?php
/**
 * @brief		4.0.0 Upgrade Code
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Blog
 * @since		24 Feb 2015
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\blog\setup\upg_100016;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Convert RSS tags
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		foreach( \IPS\Db::i()->select( 'rss_id, rss_tags', 'blog_rss_import' ) as $feed )
		{
			$update = array();
			if( @unserialize( $feed['rss_tags'] ) !== FALSE )
			{
				$update['rss_tags']	= json_encode( unserialize( $feed['rss_tags'] ) );
				\IPS\Db::i()->update( 'blog_rss_import', $update, array( 'rss_id=?', $feed['rss_id'] ) );
			}
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Converting RSS feeds";
	}
}