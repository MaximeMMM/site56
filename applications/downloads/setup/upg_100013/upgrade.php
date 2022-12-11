<?php
/**
 * @brief		4.0.0 RC 1 Upgrade Code
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @subpackage	Downloads
 * @since		9 Feb 2015
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\downloads\setup\upg_100013;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 RC 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Step 1
	 * Fixing custom fields
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		foreach( \IPS\Db::i()->select( '*', 'downloads_cfields' ) as $field )
		{
			if( $field['cf_type'] == 'Select' )
			{
				try
				{
					\IPS\Db::i()->dropIndex( 'downloads_ccontent', "field_" . $field['cf_id'] );
					\IPS\Db::i()->dropColumn( 'downloads_ccontent', "field_" . $field['cf_id'] );
				} 
				catch ( \IPS\Db\Exception $e )
				{

				}

				\IPS\Db::i()->addColumn( 'downloads_ccontent', array( 'name' => "field_" . $field['cf_id'], 'type' => 'TEXT' ) );
				\IPS\Db::i()->addIndex( 'downloads_ccontent', array( 'type' => 'key', 'name' => "field_" . $field['cf_id'], 'columns' => array( "field_" . $field['cf_id'] ) ) );
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
		return "Fixing downloads custom fields";
	}
}
