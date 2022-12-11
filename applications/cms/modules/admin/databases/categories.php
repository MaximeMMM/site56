<?php
/**
 * @brief		Fields Model
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Content
 * @since		8 April 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\cms\modules\admin\databases;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * categories
 */
class _categories extends \IPS\Node\Controller
{
	/**
	 * Node Class
	 */
	protected $nodeClass = '\IPS\cms\Categories';
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		$this->url = $this->url->setQueryString( array( 'database_id' => \IPS\Request::i()->database_id ) );
		
		/* Assign the correct nodeClass so contentItem is specified */
		$this->nodeClass = '\IPS\cms\Categories' . \IPS\Request::i()->database_id;
		
		\IPS\Dispatcher::i()->checkAcpPermission( 'categories_manage' );
		
		$nodeClass = $this->nodeClass;

		$childLang = \IPS\Member::loggedIn()->language()->addToStack( $nodeClass::$nodeTitle . '_add_child' );
		$nodeClass::$nodeTitle = \IPS\Member::loggedIn()->language()->addToStack('content_cat_db_title', FALSE, array( 'sprintf' => array( \IPS\cms\Databases::load( \IPS\Request::i()->database_id)->_title ) ) );
		\IPS\Member::loggedIn()->language()->words[ $nodeClass::$nodeTitle . '_add_child' ] = $childLang;
		parent::execute();
	}
	
	/**
	 * Get Root Rows
	 *
	 * @return	array
	 */
	public function _getRoots()
	{
		$nodeClass = $this->nodeClass;
		$rows = array();
	
		foreach( $nodeClass::roots( NULL ) as $node )
		{
			if ( $node->database_id == \IPS\Request::i()->database_id )
			{
				$rows[ $node->_id ] = $this->_getRow( $node );
			}
		}
	
		return $rows;
	}
}