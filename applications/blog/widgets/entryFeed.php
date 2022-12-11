<?php
/**
 * @brief		Blog Entry Feed Widget
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Blog
 * @since		22 Jun 2015
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
 * Blog Entry Feed Widget
 */
class _entryFeed extends \IPS\Content\Widget
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'entryFeed';
	
	/**
	 * @brief	App
	 */
	public $app = 'blog';
		
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';
	
	/**
	 * Class
	 */
	protected static $class = 'IPS\blog\Entry';
	
	/**
	 * Get where clause
	 *
	 * @return	array
	 */
	protected function buildWhere()
	{
		$where = parent::buildWhere();
		$where[] = array( 'entry_status!=?', 'draft' );
		return $where;
	}
}