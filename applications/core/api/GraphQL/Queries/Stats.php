<?php
/**
 * @brief		GraphQL: Stats query
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		21 Sept 2020
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\api\GraphQL\Queries;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Stats query for GraphQL API
 */
class _Stats
{
	/*
	 * @brief 	Query description
	 */
	public static $description = "Returns community stats";

	/*
	 * Query arguments
	 */
	public function args()
	{
		return array();
	}

	/**
	 * Return the query return type
	 */
	public function type() 
	{
		return \IPS\core\api\GraphQL\TypeRegistry::stats();
	}

	/**
	 * Resolves this query
	 *
	 * @param 	mixed 	Value passed into this resolver
	 * @param 	array 	Arguments
	 * @param 	array 	Context values
	 * @return	\IPS\core\Stream
	 */
	public function resolve($val, $args, $context)
	{
		return $val;
	}
}
