<?php
/**
 * @brief		blogStatistics Widget
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Blog
 * @since		14 May 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\blog\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * blogStatistics Widget
 */
class _blogStatistics extends \IPS\Widget\StaticCache
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'blogStatistics';
	
	/**
	 * @brief	App
	 */
	public $app = 'blog';
		
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';

	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		$stats = array();
		
		$stats['total_blogs']	= \IPS\Db::i()->select( "COUNT(*)", 'blog_blogs' )->first();
		$stats['total_entries']	= \IPS\Db::i()->select( "COUNT(*)", 'blog_entries' )->first();
		
		return $this->output( $stats );
	}
}