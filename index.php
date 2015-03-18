<?php

include "config.php";

/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* ArrestDB 1.16 (github.com/ilausuch/ArrestDB/)
* Copyright (c) 2014 Alix Axel <alix.axel@gmail.com>
* Changes since 2015, Ivan Lausuch <ilausuch@gmail.com>
**/

// Allow from any origin
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');    // cache for 1 day
    }

    // Access-Control headers are received during OPTIONS requests
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
            header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE");

        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
            header("Access-Control-Allow-Headers:        {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

        exit(0);
    }

if (strcmp(PHP_SAPI, 'cli') === 0)
{
	exit('ArrestDB should not be run from CLI.' . PHP_EOL);
}

if ((empty($clients) !== true) && (in_array($_SERVER['REMOTE_ADDR'], (array) $clients) !== true))
{
	exit(ArrestDB::Reply(ArrestDB::$HTTP[403]));
}

else if (ArrestDB::Query($dsn) === false)
{
	exit(ArrestDB::Reply(ArrestDB::$HTTP[503]));
}

if (array_key_exists('_method', $_GET) === true)
{
	$_SERVER['REQUEST_METHOD'] = strtoupper(trim($_GET['_method']));
}

else if (array_key_exists('HTTP_X_HTTP_METHOD_OVERRIDE', $_SERVER) === true)
{
	$_SERVER['REQUEST_METHOD'] = strtoupper(trim($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']));
}

ArrestDB::Serve('GET', '/(#any)/(#any)/(#any)', function ($table, $id, $data)
{
	if (function_exists("ArrestDB_auth") && !ArrestDB_auth())
		exit(ArrestDB::Reply(ArrestDB::$HTTP[403]));
	
	if (function_exists("ArrestDB_tableAlias"))
		$tableBase=ArrestDB_tableAlias($table);
	else
		$tableBase=$table;
		
	if (function_exists("ArrestDB_obfuscate_id"))
		if ($id!=null && $id!="")
			$id=ArrestDB_obfuscate_id($tableBase,$id,true);
	
	if (function_exists("ArrestDB_allow"))
		if (!ArrestDB_allow("GET",$table,$id))
			return ArrestDB::Reply(ArrestDB::$HTTP[403]);
			
	$query = [];
	$query["SELECT"]="*";
	$query["TABLE"]=$tableBase;
	
	$query["WHERE"]=[
		sprintf('"%s" %s ?', $id, (ctype_digit($data) === true) ? '=' : 'LIKE')
	];

	if (isset($_GET['by']) === true){
		if (isset($_GET['order']) !== true)
			$_GET['order'] = 'ASC';

		$query["ORDER BY"]=$_GET['by']." ".$_GET['order'];
	}

	if (isset($_GET['limit']) === true){
		$query["LIMIT"]=$_GET['limit'];
		
		if (isset($_GET['offset']) === true)
			$query["OFFSET"]=$_GET['offset'];
	}
	
	if (function_exists("ArrestDB_modify_query"))
		$query=ArrestDB_modify_query("GET",$table,$id,$query);
		
	$query=ArrestDB::PrepareQueryGET($query);

	$result = ArrestDB::Query($query, $data);

	if ($result === false)
		return ArrestDB::Reply(ArrestDB::$HTTP[404]);

	else if (empty($result) === true)
		return ArrestDB::Reply(ArrestDB::$HTTP[204]);

	if (isset($result[0]))
		foreach ($result as $k=>$object)
			$result[$k]["__table"]=$tableBase;
	else
		$result["__table"]=$table;
	
	if (isset($_GET['extends']) === true || isset($_GET['$extends']) === true){
		if (isset($_GET['extends']))
			$extends=$_GET['extends'];
		
		if (isset($_GET['$extends']))
			$extends=$_GET['$extends'];
			
		$extends=explode(",", $extends);
		try{
			$result=ArrestDB::Extend($result,$extends);
		}catch(Exception $e){
			$result = ArrestDB::$HTTP[$e->getCode()];
			$result["error"]["detail"]=$e->getMessage();
			return ArrestDB::Reply($result);
		}
	}
	
	if (function_exists("ArrestDB_postProcess"))
		$result=ArrestDB_postProcessGET($table,$id,$result);
	
	$result=ArrestDB::ObfuscateId($result);
		
	return ArrestDB::Reply($result);
});



ArrestDB::Serve('GET', ['/(#any)/(#num)','/(#any)/','/(#any)'],function ($table, $id = null){
	if (function_exists("ArrestDB_auth") && !ArrestDB_auth())
		exit(ArrestDB::Reply(ArrestDB::$HTTP[403]));
	
	if (preg_match("/(?P<table>[^\(]+)\((?P<id>[^\)]+)\)/",$table,$matches)){
		$table=$matches["table"];
		$id=$matches["id"];
	}
	
	if (function_exists("ArrestDB_tableAlias"))
		$tableBase=ArrestDB_tableAlias($table);
	else
		$tableBase=$table;
		
		
	if (function_exists("ArrestDB_obfuscate_id"))
		if ($id!=null && $id!="")
			$id=ArrestDB_obfuscate_id($tableBase,$id,true);
		
	if (function_exists("ArrestDB_allow"))
		if (!ArrestDB_allow("GET",$table,$id))
			return ArrestDB::Reply(ArrestDB::$HTTP[403]);
	
	$query = [];
	$query["SELECT"]="*";
	$query["TABLE"]=$tableBase;
	$query["WHERE"]=[];
	
	if (isset($id) === true){
		$query["WHERE"][]='"id"=?';
		$query["LIMIT"]=1;
	}
	else{
		if (isset($_GET['by']) === true){
			if (isset($_GET['order']) !== true)
				$_GET['order'] = 'ASC';

			$query["ORDER BY"]=$_GET['by']." ".$_GET['order'];
		}

		if (isset($_GET['limit']) === true){
			$query["LIMIT"]=$_GET['limit'];
			
			if (isset($_GET['offset']) === true)
				$query["OFFSET"]=$_GET['offset'];
		}
	}
	
	if (function_exists("ArrestDB_modify_query"))
		$query=ArrestDB_modify_query("GET",$table,$id,$query);

	
	$query=ArrestDB::PrepareQueryGET($query);

	$result = (isset($id) === true) ? ArrestDB::Query($query, $id) : ArrestDB::Query($query);


	if ($result === false)
		return ArrestDB::Reply(ArrestDB::$HTTP[404]);
		
	else if (empty($result) === true)
		return ArrestDB::Reply(ArrestDB::$HTTP[204]);

	else if (isset($id) === true)
		$result = array_shift($result);
	
	
	if (isset($result[0]))
		foreach ($result as $k=>$object)
			$result[$k]["__table"]=$tableBase;
	else
		$result["__table"]=$tableBase;
	
	if (isset($_GET['extends']) === true || isset($_GET['$extends']) === true){
		if (isset($_GET['extends']))
			$extends=$_GET['extends'];
		
		if (isset($_GET['$extends']))
			$extends=$_GET['$extends'];
			
		$extends=explode(",", $extends);
		try{
			$result=ArrestDB::Extend($result,$extends);
		}catch(Exception $e){
			$result = ArrestDB::$HTTP[$e->getCode()];
			$result["error"]["detail"]=$e->getMessage();
			return ArrestDB::Reply($result);
		}
	}
	
	if (function_exists("ArrestDB_postProcess"))
		$result=ArrestDB_postProcess("GET",$table,$id,$result);
	
	$result=ArrestDB::ObfuscateId($result);
		
	return ArrestDB::Reply($result);
});


ArrestDB::Serve('DELETE', '/(#any)/(#num)', function ($table, $id)
{
	if (function_exists("ArrestDB_auth") && !ArrestDB_auth())
		exit(ArrestDB::Reply(ArrestDB::$HTTP[403]));
	
	if (preg_match("/(?P<table>[^\(]+)\((?P<id>[^\)]+)\)/",$table,$matches)){
		$table=$matches["table"];
		$id=$matches["id"];
	}
	
	if (function_exists("ArrestDB_obfuscate_id"))
		if ($id!=null && $id!="")
			$id=ArrestDB_obfuscate_id($table,$id,true);
		
	if (function_exists("ArrestDB_allow"))
		if (!ArrestDB_allow("DELETE",$table,$id)){
			$result = ArrestDB::$HTTP[403];
			return ArrestDB::Reply($result);
		}
		
	$query = array
	(
		sprintf('DELETE FROM "%s" WHERE "%s" = ?', $table, 'id'),
	);

	$query = sprintf('%s;', implode(' ', $query));
	$result = ArrestDB::Query($query, $id);

	if ($result === false)
	{
		$result = ArrestDB::$HTTP[404];
	}

	else if (empty($result) === true)
	{
		$result = ArrestDB::$HTTP[204];
	}

	else
	{
		$result = ArrestDB::$HTTP[200];
	}

	return ArrestDB::Reply($result);
});

if (in_array($http = strtoupper($_SERVER['REQUEST_METHOD']), ['POST', 'PUT']) === true)
{
	if (preg_match('~^\x78[\x01\x5E\x9C\xDA]~', $data = file_get_contents('php://input')) > 0)
	{
		$data = gzuncompress($data);
	}

	if ((array_key_exists('CONTENT_TYPE', $_SERVER) === true) && (empty($data) !== true))
	{
		if (strncasecmp($_SERVER['CONTENT_TYPE'], 'application/json', 16) === 0)
		{
			$GLOBALS['_' . $http] = json_decode($data, true);
		}

		else if ((strncasecmp($_SERVER['CONTENT_TYPE'], 'application/x-www-form-urlencoded', 33) === 0) && (strncasecmp($_SERVER['REQUEST_METHOD'], 'PUT', 3) === 0))
		{
			parse_str($data, $GLOBALS['_' . $http]);
		}
	}

	if ((isset($GLOBALS['_' . $http]) !== true) || (is_array($GLOBALS['_' . $http]) !== true))
	{
		$GLOBALS['_' . $http] = [];
	}

	unset($data);
}

ArrestDB::Serve('POST', '/(#any)', function ($table){
	
	
	if (function_exists("ArrestDB_auth") && !ArrestDB_auth())
		exit(ArrestDB::Reply(ArrestDB::$HTTP[403]));
				
	if (function_exists("ArrestDB_allow"))
		if (!ArrestDB_allow("POST",$table,0)){
			$result = ArrestDB::$HTTP[403];
			return ArrestDB::Reply($result);
		}
		
	if (empty($_POST) === true)
	{
		$result = ArrestDB::$HTTP[204];
	}

	else if (is_array($_POST) === true)
	{
		$queries = [];

		if (count($_POST) == count($_POST, COUNT_RECURSIVE))
		{
			$_POST = [$_POST];
		}

		foreach ($_POST as $row)
		{
			$query = [];
			$query["TABLE"]=$table;
			$query["VALUES"]=[];
			
			foreach ($row as $key => $value)
				$query["VALUES"][$key]=$value;
			
			if (function_exists("ArrestDB_modify_query"))
				$query=ArrestDB_modify_query("POST",$table,0,$query);
			
			
			$query=ArrestDB::PrepareQueryPOST($query);
			
			$queries[]=$query;
		}
		
		if (count($queries) > 1)
		{
			ArrestDB::Query()->beginTransaction();

			while (is_null($query = array_shift($queries)) !== true)
			{
				if (($result = ArrestDB::Query($query[0], $query[1])) === false)
				{
					ArrestDB::Query()->rollBack(); break;
				}
			}

			if (($result !== false) && (ArrestDB::Query()->inTransaction() === true))
			{
				$result = ArrestDB::Query()->commit();
			}
		}

		else if (is_null($query = array_shift($queries)) !== true)
		{
			$result = ArrestDB::Query($query[0], $query[1]);
		}
		
		$ids=$result;
		
		if ($result === false)
		{
			$result = ArrestDB::$HTTP[409];
		}

		else
		{
			$result = ArrestDB::$HTTP[201];
			$result["success"]["Ids"]=$ids;
		}
	}

	return ArrestDB::Reply($result);
});

ArrestDB::Serve('PUT', '/(#any)/(#num)', function ($table, $id)
{
	if (function_exists("ArrestDB_auth") && !ArrestDB_auth())
		exit(ArrestDB::Reply(ArrestDB::$HTTP[403]));
	
	if (preg_match("/(?P<table>[^\(]+)\((?P<id>[^\)]+)\)/",$table,$matches)){
		$table=$matches["table"];
		$id=$matches["id"];
	}
	
	if (function_exists("ArrestDB_obfuscate_id"))
		if ($id!=null && $id!="")
			$id=ArrestDB_obfuscate_id($table,$id,true);
				
	if (function_exists("ArrestDB_allow"))
		if (!ArrestDB_allow("PUT",$table,$id)){
			$result = ArrestDB::$HTTP[403];
			return ArrestDB::Reply($result);
		}
	
	if (empty($GLOBALS['_PUT']) === true)
	{
		$result = ArrestDB::$HTTP[204];
	}

	else if (is_array($GLOBALS['_PUT']) === true)
	{
		$data = [];

		foreach ($GLOBALS['_PUT'] as $key => $value)
		{
			$data[$key] = sprintf('"%s" = ?', $key);
		}

		$query = array
		(
			sprintf('UPDATE "%s" SET %s WHERE "%s" = ?', $table, implode(', ', $data), 'id'),
		);

		$query = sprintf('%s;', implode(' ', $query));
		$result = ArrestDB::Query($query, $GLOBALS['_PUT'], $id);

		if ($result === false)
		{
			$result = ArrestDB::$HTTP[409];
		}

		else
		{
			$result = ArrestDB::$HTTP[200];
		}
	}

	return ArrestDB::Reply($result);
});

exit(ArrestDB::Reply(ArrestDB::$HTTP[400]));


class ArrestDB
{
	public static $HTTP = [
		200 => [
			'success' => [
				'code' => 200,
				'status' => 'OK',
			],
		],
		201 => [
			'success' => [
				'code' => 201,
				'status' => 'Created',
			],
		],
		204 => [
			'error' => [
				'code' => 204,
				'status' => 'No Content',
			],
		],
		400 => [
			'error' => [
				'code' => 400,
				'status' => 'Bad Request',
			],
		],
		403 => [
			'error' => [
				'code' => 403,
				'status' => 'Forbidden',
			],
		],
		404 => [
			'error' => [
				'code' => 404,
				'status' => 'Not Found',
			],
		],
		409 => [
			'error' => [
				'code' => 409,
				'status' => 'Conflict',
			],
		],
		503 => [
			'error' => [
				'code' => 503,
				'status' => 'Service Unavailable',
			],
		],
	];

	public static function Query($query = null)
	{
		static $db = null;
		static $result = [];

		try
		{
			if (isset($db, $query) === true)
			{
				if (strncasecmp($db->getAttribute(\PDO::ATTR_DRIVER_NAME), 'mysql', 5) === 0)
				{
					$query = strtr($query, '"', '`');
				}

				if (empty($result[$hash = crc32($query)]) === true)
				{
					$result[$hash] = $db->prepare($query);
				}

				$data = array_slice(func_get_args(), 1);

				if (count($data, COUNT_RECURSIVE) > count($data))
				{
					$data = iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveArrayIterator($data)), false);
				}

				if ($result[$hash]->execute($data) === true)
				{
					$sequence = null;

					if ((strncmp($db->getAttribute(\PDO::ATTR_DRIVER_NAME), 'pgsql', 5) === 0) && (sscanf($query, 'INSERT INTO %s', $sequence) > 0))
					{
						$sequence = sprintf('%s_id_seq', trim($sequence, '"'));
					}

					switch (strstr($query, ' ', true))
					{
						case 'INSERT':
						case 'REPLACE':
							return $db->lastInsertId($sequence);

						case 'UPDATE':
						case 'DELETE':
							return $result[$hash]->rowCount();

						case 'SELECT':
						case 'EXPLAIN':
						case 'PRAGMA':
						case 'SHOW':
							return $result[$hash]->fetchAll();
					}

					return true;
				}

				return false;
			}

			else if (isset($query) === true)
			{
				$options = array
				(
					\PDO::ATTR_CASE => \PDO::CASE_NATURAL,
					\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
					\PDO::ATTR_EMULATE_PREPARES => false,
					\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
					\PDO::ATTR_ORACLE_NULLS => \PDO::NULL_NATURAL,
					\PDO::ATTR_STRINGIFY_FETCHES => false,
				);

				if (preg_match('~^sqlite://([[:print:]]++)$~i', $query, $dsn) > 0)
				{
					$options += array
					(
						\PDO::ATTR_TIMEOUT => 3,
					);

					$db = new \PDO(sprintf('sqlite:%s', $dsn[1]), null, null, $options);
					$pragmas = array
					(
						'automatic_index' => 'ON',
						'cache_size' => '8192',
						'foreign_keys' => 'ON',
						'journal_size_limit' => '67110000',
						'locking_mode' => 'NORMAL',
						'page_size' => '4096',
						'recursive_triggers' => 'ON',
						'secure_delete' => 'ON',
						'synchronous' => 'NORMAL',
						'temp_store' => 'MEMORY',
						'journal_mode' => 'WAL',
						'wal_autocheckpoint' => '4096',
					);

					if (strncasecmp(PHP_OS, 'WIN', 3) !== 0)
					{
						$memory = 131072;

						if (($page = intval(shell_exec('getconf PAGESIZE'))) > 0)
						{
							$pragmas['page_size'] = $page;
						}

						if (is_readable('/proc/meminfo') === true)
						{
							if (is_resource($handle = fopen('/proc/meminfo', 'rb')) === true)
							{
								while (($line = fgets($handle, 1024)) !== false)
								{
									if (sscanf($line, 'MemTotal: %d kB', $memory) == 1)
									{
										$memory = round($memory / 131072) * 131072; break;
									}
								}

								fclose($handle);
							}
						}

						$pragmas['cache_size'] = intval($memory * 0.25 / ($pragmas['page_size'] / 1024));
						$pragmas['wal_autocheckpoint'] = $pragmas['cache_size'] / 2;
					}

					foreach ($pragmas as $key => $value)
					{
						$db->exec(sprintf('PRAGMA %s=%s;', $key, $value));
					}
				}

				else if (preg_match('~^(mysql|pgsql)://(?:(.+?)(?::(.+?))?@)?([^/:@]++)(?::(\d++))?/(\w++)/?$~i', $query, $dsn) > 0)
				{
					if (strncasecmp($query, 'mysql', 5) === 0)
					{
						$options += array
						(
							\PDO::ATTR_AUTOCOMMIT => true,
							\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES "utf8" COLLATE "utf8_general_ci", time_zone = "+00:00";',
							\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
						);
					}

					$db = new \PDO(sprintf('%s:host=%s;port=%s;dbname=%s', $dsn[1], $dsn[4], $dsn[5], $dsn[6]), $dsn[2], $dsn[3], $options);
				}
			}
		}

		catch (\Exception $exception)
		{
			return false;
		}

		return (isset($db) === true) ? $db : false;
	}

	public static function Reply($data)
	{
		$bitmask = 0;
		$options = ['UNESCAPED_SLASHES', 'UNESCAPED_UNICODE'];

		if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) === true)
		{
			$options[] = 'PRETTY_PRINT';
		}

		foreach ($options as $option)
		{
			$bitmask |= (defined('JSON_' . $option) === true) ? constant('JSON_' . $option) : 0;
		}

		if (($result = json_encode($data, $bitmask)) !== false)
		{
			$callback = null;

			if (array_key_exists('callback', $_GET) === true)
			{
				$callback = trim(preg_replace('~[^[:alnum:]\[\]_.]~', '', $_GET['callback']));

				if (empty($callback) !== true)
				{
					$result = sprintf('%s(%s);', $callback, $result);
				}
			}

			if (headers_sent() !== true)
			{
				header(sprintf('Content-Type: application/%s; charset=utf-8', (empty($callback) === true) ? 'json' : 'javascript'));
			}
		}

		return $result;
	}

	public static function Serve($on = null, $route = null, $callback = null)
	{
		static $root = null;
		global $prefix;
		
		if (!isset($prefix))
			$prefix="";

		if (isset($_SERVER['REQUEST_METHOD']) !== true)
		{
			$_SERVER['REQUEST_METHOD'] = 'CLI';
		}
		
		
		if ((empty($on) === true) || (strcasecmp($_SERVER['REQUEST_METHOD'], $on) === 0))
		{
			if (is_null($root) === true)
			{
				if (isset($_SERVER["SERVER_SOFTWARE"]) && substr($_SERVER["SERVER_SOFTWARE"],0,strlen("nginx"))=="nginx"){
					$root=substr($_SERVER["REQUEST_URI"],strlen($prefix));
				}
				else{
					$path=substr($_SERVER['PHP_SELF'], strlen($_SERVER['SCRIPT_NAME']));
					$path=substr($path,strlen($prefix));
					$root = preg_replace('~/++~', '/',  $path. '/');
				}
			}
			
			$e=explode("?",$root);
			$root=$e[0];

			
			if (is_array($route))
				$routeList=$route;
			else
				$routeList=[$route];
				
			
			foreach($routeList as $route)
				if (preg_match('~^' . str_replace(['#any', '#num'], ['[^/]++', '[^/]++'], $route) . '~i', $root, $parts) > 0)
				{
					return (empty($callback) === true) ? true : exit(call_user_func_array($callback, array_slice($parts, 1)));
				}
		}
		
		return false;
	}
	
	public static function Extend($data,$extends){
		if (isset($data[0])){
			$result=array();
			foreach ($data as $object)
				$result[]=ArrestDB::Extend($object,$extends);
				
			return $result;
		}
		else{
			$object=$data;
			foreach ($extends as $extend){
				$path=explode("/",$extend);
				ArrestDB::ExtendComplete($object,$path);
			}
			
			return $object;	
		}
	}
	
	public static function ExtendComplete(&$object,$path){
		global $relations;
			
		$first=$path[0];
		
		if (!isset($object[$first])){
			if ($relations==null)
				throw new Exception("Relations not defined in config",400);
	
			if (!isset($relations[$object["__table"]]))
				throw new Exception("{$object["__table"]} not defined in relations",400);
	
			if (!isset($relations[$object["__table"]][$first]))
				throw new Exception("{$first} not defined in relations of {$object["__table"]}",400);
			
			$relation=$relations[$object["__table"]][$first];
			
			if(!isset($relation["type"])||!isset($relation["ftable"]))
				throw new Exception("Invalid configuration in {$first} of {$object["__table"]}. Requisites (type,ftable)",400);
	
			if (!isset($relation["key"]))
				$relation["key"]="id";
				
			if (!isset($relation["fkey"]))
				$relation["fkey"]="id";
			
			$id=$object[$relation["key"]];
				
			if (function_exists("ArrestDB_allow")){
				if ($relation["type"]=="object"){
					if (!ArrestDB_allow("GET_INTERNAL",$relation["ftable"],$id))
						throw new Exception("Cannot load {$relation["ftable"]} with id $id",403);
				}else
					if (!ArrestDB_allow("GET_INTERNAL",$relation["ftable"],""))
						throw new Exception("Cannot load {$relation["ftable"]} with id $id",403);
			}
			
			$query = [];
			$query["SELECT"]="*";
			$query["TABLE"]=$relation["ftable"];
			$query["WHERE"]=["{$relation["fkey"]}={$id}"];
			
			if (function_exists("ArrestDB_modify_query"))
				$query=ArrestDB_modify_query("GET_INTERNAL",$relation["ftable"],$id,$query);
				
			$query=ArrestDB::PrepareQueryGET($query);
			
			$result=ArrestDB::Query($query);
			
			if ($result === false){
				$result = ArrestDB::$HTTP[404];
				return $result;
			}
			
			if (function_exists("ArrestDB_postProcess"))
				$result=ArrestDB_postProcess("GET_INTERNAL",$relation["ftable"],$id,$result);
			
			foreach ($result as $k=>$item)
				$result[$k]["__table"]=$relation["ftable"];
		}
		else
			if (isset($object[$first][0]))
				$result=$object[$first];
			else
				$result=[$object[$first]];
			
		$path2=$path;
		array_shift($path2);

		if (count($path2)>0)
			foreach ($result as $k=>$item)
				ArrestDB::ExtendComplete($result[$k],$path2);
		
		if ($relation["type"]=="object"){
			if ($result!=null)
				$result=$result[0];
		}
		else{
			if ($result==null)
				$result=[];
		}
			
		
		$object[$path[0]]=$result;
			
	}
	
	public static function PrepareQueryGET($query){
		
		if (isset($query["SELECT"]))
			$result= "SELECT {$query["SELECT"]} ";
		else
			$result= "SELECT * ";
			
		$result.="FROM \"{$query["TABLE"]}\" ";

		if (is_array($query)){
			if (count($query["WHERE"])>0){
				$result.=" WHERE {$query["WHERE"][0]} ";
				
				unset($query["WHERE"][0]);
				foreach ($query["WHERE"] as $w)
					$result.=" AND {$w} ";
			}
		}
		else
			$result.=" WHERE {$query["WHERE"]} ";
		
		if (isset($query["ORDER BY"]))
			$result.=" ORDER BY {$query["ORDER BY"]} ";
		
		if (isset($query["LIMIT"])){
			$result.=" LIMIT {$query["LIMIT"]} ";
			if (isset($query["OFFSET"]))
				$result.=" OFFSET {$query["OFFSET"]}";
		}
		
		return $result;
	}
	
	public static function PrepareQueryPOST($query){
		$keys=[];
		$values=[];
		$questions=[];
		
		foreach($query["VALUES"] as $k=>$v){
			$keys[]="\"$k\"";
			$values[]="$v";
			$questions[]="?";
		}
			
		$keys=implode(', ', $keys);
		
		return [
			"INSERT INTO \"{$query["TABLE"]}\" ($keys) VALUES (".implode(', ',$questions).")",
			$values
			];
	}
	
	public static function ObfuscateId($data){
		if (function_exists("ArrestDB_obfuscate_id")){
			if (isset($data[0])){
				foreach($data as $k=>$object)
					$data[$k]=ArrestDB::ObfuscateId($object);
				
				return $data;
			}
			else{
				$data["id"]=ArrestDB_obfuscate_id($data["__table"],$data["id"],false);
				foreach($data as $k=>$value)
					if (is_array($value))
						$data[$k]=ArrestDB::ObfuscateId($value);
						
				return $data;
			}
		}else
			return $data;
	}
}
