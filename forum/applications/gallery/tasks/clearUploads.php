<?php
/**
 * @brief		Clear temporary uploads task
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Gallery
 * @since		18 Mar 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\gallery\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Clear temporary uploads task
 */
class _clearUploads extends \IPS\Task
{
	/**
	 * Execute
	 *
	 * If ran successfully, should return anything worth logging. Only log something
	 * worth mentioning (don't log "task ran successfully"). Return NULL (actual NULL, not '' or 0) to not log (which will be most cases).
	 * If an error occurs which means the task could not finish running, throw an \IPS\Task\Exception - do not log an error as a normal log.
	 * Tasks should execute within the time of a normal HTTP request.
	 *
	 * @return	mixed	Message to log or NULL
	 * @throws	\IPS\Task\Exception
	 */
	public function execute()
	{
		$cutoff		= \IPS\DateTime::create()->sub( new \DateInterval( 'P1D' ) )->getTimestamp();
		$sessions	= array();

		foreach( \IPS\Db::i()->select( '*', 'gallery_images_uploads', array( 'upload_date < ?', $cutoff ) ) as $upload )
		{
			$file	= \IPS\File::get( 'gallery_Images', $upload['upload_location'] )->delete();
			
			$sessions[ $upload['upload_session'] ]	= $upload['upload_session'];
		}

		if( count( $sessions ) )
		{
			\IPS\Db::i()->delete( 'gallery_images_uploads', array( array( "upload_session IN('" . implode( "','", $sessions ) . "')" ) ) );
		}

		return NULL;
	}
	
	/**
	 * Cleanup
	 *
	 * If your task takes longer than 15 minutes to run, this method
	 * will be called before execute(). Use it to clean up anything which
	 * may not have been done
	 *
	 * @return	void
	 */
	public function cleanup()
	{
		
	}
}