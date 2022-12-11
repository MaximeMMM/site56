<?php
/**
 * @brief		Background Task
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Blogs
 * @since		12 Apr 2016
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\blog\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task
 */
class _RebuildEntryImages
{
	/**
	 * @brief   Number of entry images to rebuild per cycle
	 */
	public $rebuild	= 10;

	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		$data['count'] = (int) \IPS\Db::i()->select( 'count(*)', 'blog_entries', "entry_image<>''" )->first();

		if( $data['count'] == 0 )
		{
			return NULL;
		}

		return $data;
	}

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
		$select = \IPS\Db::i()->select( '*', 'blog_entries', "entry_image<>''", 'entry_id ASC', array( $offset, $this->rebuild ) );

		if ( !$select->count() )
		{
			throw new \OutOfRangeException;
		}

		foreach ( $select as $entry )
		{
			try
			{
				$filePath = \IPS\ROOT_PATH . '/uploads/' . $entry['entry_image'];

				$file = \IPS\File::create( 'core_Attachment', $entry['entry_image'], NULL, NULL, FALSE, $filePath );
				$attachment = $file->makeAttachment('');
				$fileName = htmlspecialchars( $attachment['attach_file'], \IPS\HTMLENTITIES, 'UTF-8', TRUE );

				$image = <<<IMAGE
<a href="<fileStore.core_Attachment>/{$file}" class="ipsAttachLink ipsAttachLink_image" style="float:left;"><img data-fileid="{$attachment['attach_id']}" src="<fileStore.core_Attachment>/{$file}" class="ipsImage ipsImage_thumbnailed" style="margin:10px;" alt="{$fileName}"></a>
IMAGE;
				
				\IPS\Db::i()->update( 'blog_entries', array( 'entry_content' => $image . $entry['entry_content'] ), array( 'entry_id=?', $entry['entry_id'] ) );

				$map	= array(
					'attachment_id'		=> $attachment['attach_id'],
					'location_key'		=> 'blog_Entries',
					'id1'				=> $entry['entry_id'],
					'id2'				=> NULL,
					'id3'				=> NULL,
					'temp'				=> NULL,
				);

				\IPS\Db::i()->replace( 'core_attachments_map', $map );
			}
			catch ( \Exception $e ) {}
		}

		return $offset + $this->rebuild;
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
		return array(
			'text'      => \IPS\Member::loggedIn()->language()->addToStack( 'rebuilding_blog_entry_images', FALSE ),
			'complete'  => ( isset( $data['count'] ) AND $data['count'] ) 
				? ( round( 100 / $data['count'] * $offset, 2 ) ) 
				: 100
		);
	}
}