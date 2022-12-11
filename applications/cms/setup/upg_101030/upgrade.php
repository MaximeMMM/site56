<?php
/**
 * @brief		4.1.12 Upgrade Code
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Content
 * @since		08 Apr 2016
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\cms\setup\upg_101030;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.12 Upgrade Code
 */
class _Upgrade
{
	/**
	 * ...
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/**
		 *
		 * We need to reproduce the entire delete process, since trying to load an orphaned category as an object will fail due to the CMS autoloader.
		 *
		 */
		$databases = iterator_to_array( \IPS\Db::i()->select( 'database_id', 'cms_databases' ) );
		foreach( \IPS\Db::i()->select( '*', 'cms_database_categories', array( \IPS\Db::i()->in( 'category_database_id', $databases, TRUE ) ) ) as $row )
		{
			/* Delete any associated data */
			$aap = md5( 'content;categories;' . $row['category_id'] );

			\IPS\Db::i()->delete( 'core_tags', array( 'tag_aap_lookup=?', $aap ) );
			\IPS\Db::i()->delete( 'core_tags_perms', array( 'tag_perm_aap_lookup=?', $aap ) );
			\IPS\Db::i()->delete( 'core_follow', array( "follow_app=? AND follow_area=? AND follow_rel_id=?", 'cms', 'categories' . $row['category_database_id'], $row['category_id'] ) );
			\IPS\Db::i()->delete( 'core_permission_index', array( "app=? AND perm_type=? AND perm_type_id=?", 'cms', 'categories', $row['category_id'] ) );

			\IPS\Lang::deleteCustom( 'cms', 'content_cat_name_' . $row['category_id'] );
			\IPS\Lang::deleteCustom( 'cms', 'content_cat_name_' . $row['category_id'] . '_desc' );

			/* Delete the category */
			\IPS\Db::i()->delete( 'cms_database_categories', array( "category_id=?", $row['category_id'] ) );
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
		return "Removing orphaned categories";
	}

	/**
	 * ...
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		/* An incorrect fixed field was added due to a bug in the install process in earlier versions. We need to clean this out */
		try
		{
			$articlesDB = \IPS\cms\Databases::load( 1 );

			if ( is_array( $articlesDB->get_fixed_field_perms() ) )
			{
				$fixedFields = $articlesDB->get_fixed_field_perms();
				if( isset( $fixedFields['record'] ) )
				{
					unset( $fixedFields['record'] );
				}

				$articlesDB->fixed_field_perms = json_encode( $fixedFields );
				$articlesDB->save();
			}
		}
		catch( \Exception $e ) {}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Removing broken fixed field permission";
	}
}