<?php
/**
 * @brief		Background Task
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Downloads
 * @since		23 Sep 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\downloads\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task
 */
class _Follow
{
	/**
	 * Run Background Task
	 *
	 * @param	mixed					$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int						$offset	Offset
	 * @return	int|null				New offset or NULL if complete
	 * @throws	\OutOfRangeException	Indicates offset doesn't exist and thus task is complete
	 */
	public function run( $data, $offset )
	{
		$category	= \IPS\downloads\Category::load( $data['category_id'] );
		$member		= \IPS\Member::load( $data['member_id'] );
		return \IPS\downloads\File::_sendNotificationsBatch( $category, $member, $offset );
	}
	
	/**
	 * Get Progress
	 *
	 * @param	mixed					$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int						$offset	Offset
	 * @return	array( 'text' => 'Doing something...', 'complete' => 50 )	Text explaining task and percentage complete
	 * @throws	\OutOfRangeException	Indicates offset doesn't exist and thus task is complete
	 */
	public function getProgress( $data, $offset )
	{
		$numberOfFollowers = \IPS\downloads\File::_notificationRecipients( \IPS\downloads\Category::load( $data['category_id']  ) )->count( TRUE );
		
		if ( $numberOfFollowers )
		{
			$complete = round( 100 / $numberOfFollowers * $offset, 2 );
		}
		else
		{
			$complete = 100;
		}
		
		$category = \IPS\downloads\Category::load( $data['category_id'] );
		
		$title = $category->_title;
		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('backgroundQueue_follow', FALSE, array( 'sprintf' => array( "<a href='{$category->url()}' target='_blank'>{$title}</a>" ) ) ), 'complete' => $complete );
	}		
}