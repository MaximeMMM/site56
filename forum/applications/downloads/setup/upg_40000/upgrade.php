<?php
/**
 * @brief		4.0.0 Upgrade Code
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Downloads
 * @since		19 Nov 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\downloads\setup\upg_40000;

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
	 * Upgrade categories
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$perCycle	= 100;
		$did		= 0;
		$limit		= intval( \IPS\Request::i()->extra );
		
		/* Try to prevent timeouts to the extent possible */
		$cutOff		= \IPS\core\Setup\Upgrade::determineCutoff();
		
		foreach( \IPS\Db::i()->select( '*', 'downloads_categories', null, 'downloads_categories.cid ASC', array( $limit, $perCycle ) )->join( 'core_permission_index', array( 'app=? and perm_type=? and perm_type_id=downloads_categories.cid', 'downloads', 'cat' ) ) as $category )
		{
			if ( $cutOff !== NULL AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}
			
			$did++;
			
			$options	= unserialize( $category['coptions'] );
			/* We make a guess as to whether approval should be required since it is no longer per-permission mask */
			$approval	= ( !$category['perm_7'] or $category['perm_7'] == ',4,' ) ? 1 : 0;
			$cid		= $category['cid'];
			$name		= $category['cname'];
			$desc		= $category['cdesc'];

			$category	= \IPS\downloads\Category::constructFromData( $category );

			$category->bitoptions['allowss']				= ( isset( $options['opt_allowss'] ) ) ? $options['opt_allowss'] : 0;
			$category->bitoptions['reqss']					= ( isset( $options['opt_reqss'] ) ) ? $options['opt_reqss'] : 0;
			$category->bitoptions['comments']				= ( isset( $options['opt_comments'] ) ) ? $options['opt_comments'] : 0;
			$category->bitoptions['moderation']				= $approval;
			$category->bitoptions['comment_moderation']		= \IPS\Settings::i()->idm_comment_approval;
			$category->bitoptions['moderation_edits']		= !\IPS\Settings::i()->idm_allow_autoedit;
			$category->bitoptions['submitter_log']			= \IPS\Settings::i()->submitter_view_dl;
			$category->bitoptions['reviews_download']		= \IPS\Settings::i()->must_dl_rate;
			$category->bitoptions['topic_delete']			= ( isset( $options['opt_topicd'] ) ) ? $options['opt_topicd'] : 0;
			$category->bitoptions['topic_screenshot']		= ( isset( $options['opt_topicss'] ) ) ? $options['opt_topicss'] : 0;

			\IPS\Lang::saveCustom( 'downloads', "downloads_category_{$cid}", trim( $name ) );
			\IPS\Lang::saveCustom( 'downloads', "downloads_category_{$cid}_npv", $options['opt_noperm_view'] ? \IPS\Text\Parser::parseStatic( $options['opt_noperm_view'], TRUE, NULL, NULL, TRUE, TRUE, TRUE ) : '' );
			\IPS\Lang::saveCustom( 'downloads', "downloads_category_{$cid}_npd", $options['opt_noperm_dl'] ? \IPS\Text\Parser::parseStatic( $options['opt_noperm_dl'], TRUE, NULL, NULL, TRUE, TRUE, TRUE ) : '' );
			\IPS\Lang::saveCustom( 'downloads', "downloads_category_{$cid}_desc", \IPS\Text\Parser::parseStatic( $desc, TRUE, NULL, NULL, TRUE, TRUE, TRUE ) );

			$category->types			= '';
			$category->sortorder		= 'file_' . \str_ireplace( array( ' DESC', ' ASC' ), '', $options['opt_sortorder'] );
			$category->maxfile			= $options['opt_maxfile'] ?: null;
			$category->maxss			= $options['opt_maxss'] ?: null;
			$category->maxdims			= ( $options['opt_thumb_x'] ) ? $options['opt_thumb_x'] . 'x' . $options['opt_thumb_x'] : NULL;
			$category->versioning		= \IPS\Settings::i()->idm_versioning ? NULL : 0;
			$category->log				= \IPS\Settings::i()->idm_logalldownloads ? ( \IPS\Settings::i()->idm_log_prune ?: NULL ) : 0;
			$category->forum_id			= ( isset( $options['opt_topicf'] ) ) ? $options['opt_topicf'] : 0;
			$category->topic_prefix		= ( isset( $options['opt_topicp'] ) ) ? $options['opt_topicp'] : 0;
			$category->topic_suffix		= ( isset( $options['opt_topics'] ) ) ? $options['opt_topics'] : 0;

			try
			{
				$latestFile	= \IPS\Db::i()->select( '*', 'downloads_files', array( 'file_open=? and file_cat=?', 1, $cid ), 'file_submitted DESC', array( 0, 1 ) )->first();

				$category->last_file_id		= $latestFile['file_id'];
				$category->last_file_date	= $latestFile['file_submitted'];
			}
			catch( \UnderflowException $e )
			{
				$category->last_file_id		= 0;
				$category->last_file_date	= 0;
			}

			$category->save();
		}
		
		if ( $did )
		{
			return ( $limit + $did );
		}
		else
		{
			\IPS\Db::i()->update( 'core_permission_index', array( 'perm_type' => 'category' ), array( 'app=?', 'downloads' ) );
			\IPS\Db::i()->dropColumn( 'downloads_categories', array( 'coptions', 'cname' ) );
			
			unset( $_SESSION['_step1Count_40000_downloads'] );
			
			return true;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		
		if ( !isset( $_SESSION['_step1Count_40000_downloads'] ) )
		{
			$_SESSION['_step1Count_40000_downloads'] = \IPS\Db::i()->select( 'COUNT(*)', 'downloads_categories' )->first();
		}
		
		return "Upgrading download manager categories (Upgraded so far: " . ( ( $limit > $_SESSION['_step1Count_40000_downloads'] ) ? $_SESSION['_step1Count_40000_downloads'] : $limit ) . ' out of ' . $_SESSION['_step1Count_40000_downloads'] . ")";
	}

	/**
	 * Custom field translations
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		foreach( \IPS\Db::i()->select( '*', 'downloads_cfields' ) as $field )
		{
			\IPS\Lang::saveCustom( 'core', "downloads_field_{$field['cf_id']}", $field['cf_title'] );
			\IPS\Lang::saveCustom( 'core', "downloads_field_{$field['cf_id']}_desc", $field['cf_desc'] );

			$update	= array();

			switch( $field['cf_type'] )
			{
				case 'input':
					$update['cf_type']	= 'Text';
				break;

				case 'textarea':
					$update['cf_type']	= 'TextArea';
				break;

				case 'drop':
					$update['cf_type']	= 'Select';
				break;

				case 'radio':
					$update['cf_type']	= 'Radio';
				break;

				case 'cbox':
					$update['cf_type']		= 'Select';
					$update['cf_multiple']	= TRUE;
				break;

				default:
					$update['cf_type']	= 'Text';
				break;
			}

			if( $field['cf_type'] === 'Textarea' )
			{
				$update['cf_type']	= 'TextArea';
			}
			
			/* Fix custom input format */
			if ( $field['cf_input_format'] )
			{
				$regex = '/' . preg_quote( $field['cf_input_format'] ) . '/';
				$regex = str_replace( 'n', '\d', $regex );
				$regex = str_replace( 'a', '\w', $regex );
				
				$update['cf_input_format'] = $regex;
			}
			
			/* Fix the options */
			if( $field['cf_content'] )
			{
				$options	= explode( '|', $field['cf_content'] );
				$newOptions	= array();

				foreach( $options as $option )
				{
					list( $k, $v )	= explode( '=', $option );
					$newOptions[]	= $v;
				}

				if( !isset( $_SESSION['pfieldsd'] ) )
				{
					$_SESSION['pfieldsd']	= array();
				}

				$_SESSION['pfieldsd'][ $field['cf_id'] ]	= $field['cf_id'];
				$update['cf_content']	= json_encode( $newOptions );
			}

			if( count( $update ) )
			{
				\IPS\Db::i()->update( 'downloads_cfields', $update, 'cf_id=' . $field['cf_id'] );
			}

			/* Indexes are needed, otherwise you get an error if you try to edit the custom field later */
			\IPS\Db::i()->addIndex( 'downloads_ccontent', array( 'name' => 'field_' . $field['cf_id'], 'type' => 'key', 'columns' => array( 'field_' . $field['cf_id'] ), 'length' => array( 255 ) ) );
		}

		\IPS\Db::i()->dropColumn( 'downloads_cfields', array( 'cf_title', 'cf_desc' ) );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Upgrading download manager custom fields";
	}

	/**
	 * Fix watermark path
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		if( !isset( $_SESSION['pfieldsd'] ) )
		{
			return true;
		}

		$update	= array();

		foreach( $_SESSION['pfieldsd'] as $fieldId )
		{
			$update[]	= "field_{$fieldId}=TRIM( BOTH ',' FROM field_{$fieldId} )";
		}

		if( count( $update ) )
		{
			\IPS\Db::i()->update( 'downloads_ccontent', implode( ", ", $update ) );
		}

		unset( $_SESSION['pfieldsd'] );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step3CustomTitle()
	{
		if( !isset( $_SESSION['pfieldsd'] ) )
		{
			return "No custom field data needs to be updated";
		}

		return "Updating custom file fields";
	}

	/**
	 * Fix watermark path
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step4()
	{
		$setting = \IPS\Db::i()->select( '*', 'core_sys_conf_settings', "conf_key='idm_watermarkpath'" )->first();

		if( $setting['conf_value'] )
		{
			$pathBits	= explode( '/', $setting['conf_value'] );
			$fileName	= array_pop( $pathBits );

			try
			{
				$url = \IPS\File::create( 'downloads_Screenshots', $fileName, file_get_contents( $setting['conf_value'] ), NULL, TRUE, NULL, FALSE );

				\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => $url ), array( 'conf_key=?', 'idm_watermarkpath' ) );
			}
			catch( \Exception $e )
			{
				\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => '' ), array( 'conf_key=?', 'idm_watermarkpath' ) );
			}
		}

		\IPS\Db::i()->delete( 'core_sys_conf_settings', "conf_key IN ( 'idm_screenshot_url', 'idm_filestorage', 'idm_localfilepath', 'idm_localsspath', 'idm_remoteport', 'idm_remotessurl', 
			'idm_remotefileurl', 'idm_filestorage', 'idm_remoteurl', 'idm_remoteuser', 'idm_remotepass', 'idm_remotesspath', 'idm_remotefilepath' )" );

		unset( \IPS\Data\Store::i()->settings );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step4CustomTitle()
	{
		return "Fixing watermark configuration";
	}
	
	/**
	 * Finish - This is run after all apps have been upgraded
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 * @note	We opted not to let users run this immediately during the upgrade because of potential issues (it taking a long time and users stopping it or getting frustrated) but we can revisit later
	 */
	public function finish()
    {
	   \IPS\Task::queue( 'core', 'RebuildPosts', array( 'class' => 'IPS\downloads\File' ), 2 );
	   \IPS\Task::queue( 'core', 'RebuildPosts', array( 'class' => 'IPS\downloads\File\Comment' ), 2 );
	   \IPS\Task::queue( 'core', 'RebuildPosts', array( 'class' => 'IPS\downloads\File\Review' ), 2 );
	   \IPS\Task::queue( 'core', 'RebuildNonContentPosts', array( 'extension' => 'downloads_Categories' ), 2 );

        return TRUE;
    }
}