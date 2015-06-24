<?php

/*
	---------------------------------------
	METHODS
	---------------------------------------
	- GET: Get data from a table or execute a function
	- GET_INTERNAL: Access to data in extends operation
	- POST: Insert operation
	- PUT: Modify operation
	- DELETE: Delete operation
	
	---------------------------------------
	FILTERS (used in following operations)
	---------------------------------------
	
	Filters is an associative array that define when a auth, modifyQuery, postProcess and allow operations will execute
	
	Options:
	- method (string or array) : what method match [GET, GET_INTERNAL (in extends use), POST, PUT, DELETE
	- no_method (string or array) : inverted method filter
	
	- table (string or array) : what table must match
	- no_table (string or array) : inverted table filter
	
	- id_defined ('yes' or 'no') : if table identifier is defined or not in http query
	
	- id (string or array) :  if table identifier is in list
	- no_id (string or array) : inverted table filter
	
	For instance, execute when table is User or Category when use POST method
	[
		"table"=>["User","Category"],
		"method"=>"POST"
	]	
	
	---------------------------------------
	QUERIES
	---------------------------------------
	
	TABLE
	WHERE
	VALUES
*/


/*
	Configure DB access
	Example MYSQL: mysql://user:pass@localhost/dbname/	
	
    SQLite: $dsn = 'sqlite://./path/to/database.sqlite';
    MySQL: $dsn = 'mysql://[user[:pass]@]host[:port]/db/;
    PostgreSQL: $dsn = 'pgsql://[user[:pass]@]host[:port]/db/;
    
    With bad configuration any operation returns:
    
    {
	    "error": 
	    {
	        "code": 503,
	        "status": "Service Unavailable"
	    }
	
	}
*/
$dsn = '';

/*
	Allow only access from a list of clients (OPTIONAL)	
*/
$clients = [];

/*
	Define path where API is configured. (OPTIONAL, by default '')	
	For instance, if you have a 'api' folder in your website path you should access using this url: http://mydomain.com/api
	so you must configure $prefix using $prefix = '/api'; 
*/
$prefix = ''; 

/*
	Allows to connect from any origin. (OPTIONAL, by default true)
	By default is true
*/
$allowAnyOrigin=true;

/*
	Allows OPTIONS method (OPTIONAL, by default true)
*/
$enableOptionsRequest=true;

/*
	ALIASES (OPTIONAL)
	
	Define table aliases. An table alias can get diferent GET,POST,PUT and DELETE conditions and can be used in all following operations
	
	ArrestDBConfig::alias($alias,$table);		
*/
ArrestDBConfig::alias("CategoryVisible","Category");//This is an example, remove it

/*
	RELATIONS (OPTIONAL)
	
	Create relations for use extends in GET queries. This allow to get objects an related objects.
	
	Examples
	------------
	
	- with objects: Each product has only one category
	ArrestDBConfig::relation("Product","Category",ArrestDBConfig::prepareRelationObject("Category","Category_id"));
	
	- with lists: Each category has a list of products.
	ArrestDBConfig::relation("Category","Products",ArrestDBConfig::prepareRelationList("Product","Category_id"));
	
	
	Details
	------------
	
	ArrestDBConfig::relation($table,$name,$config)
	
	- $table: the table name (equal to table name) witch contains relation
	- $name: the variable where relation is loaded
	
	
	Configuration
	
	Objects (one to one, * to one), prepareRelationObject($foreignTable,$key,$foreingKey="id")
	
	- $foreignTable: Related table name (equal to table name)
	- $key: Table identifier key of relation
	- $foreingKey: Foreign table indentifier key of relation, by default "id"
	
	List (one to *), prepareRelationList($foreignTable,$foreingKey,$key="id")
	
	- $foreignTable: Related table name (equal to table name)
	- $foreingKey: Foreign table indentifier key of relation
	- $key: Table identifier key of relation, by default "id"
*/
ArrestDBConfig::relation("Category","Products",ArrestDBConfig::prepareRelationList("Product","Category_id")); //This is an example, remove it


/*	
	AUTH (OPTIONAL)
	
	Check if authorization is required to access to a table or function. Return true if is authorized. By default all is authorized
	
	ArrestDBConfig::auth($filter,$function)
	- $filter: define the filter
	- $function: define callback function(returns true or false), function($method,$table,$id){return true}
	
	If you define a list of auth methods, when system match with one, all afther that are not checked
*/

//This is an example where authorization is requiered for all tables except for CategoryVisible that is always authorized 
ArrestDBConfig::auth(
	[
		"table"=>"Category"
	],
	function($method,$table,$id){
		return true;
	});

ArrestDBConfig::auth(
	[],
	function($method,$table,$id){
		global $user;
		
		if (!isset($_SERVER['PHP_AUTH_USER'])||!isset($_SERVER['PHP_AUTH_PW'])) {
		    header('WWW-Authenticate: Basic realm="My Realm"');
		    header('HTTP/1.0 401 Unauthorized');
		    echo 'Invalid Auth';
		    exit;
		} else {
		    $user=$_SERVER['PHP_AUTH_USER'];
		    $pass=sha1($_SERVER['PHP_AUTH_PW']);
	
			$query=ArrestDB::PrepareQueryGET([
			    "TABLE"=>"User",
			    "WHERE"=>["email='$user'","password='$pass'"]
			]);
	
			$result=ArrestDB::Query($query);
	
			if (count($result)==0){
				header('WWW-Authenticate: Basic realm="My Realm"');
			    header('HTTP/1.0 401 Unauthorized');
			    echo 'Invalid Auth';
			    exit;
			}
			
			$user=$result[0];
			
			return true;
		}
	});
	

/*
	ALLOW (OPTIONAL)
	
	It's similar to auth but it's used in other cases when is checked out if it's allowed to execute a method over a table or function. Return true if is allowed. By default all is allowed
	
	ArrestDBConfig::allow($filter,$function)
	- $filter: define the filter
	- $function: define callback function(returns true or false), function($method,$table,$id){return true}
	
	If you define a list of allow methods, when system match with one, all afther that are not checked
*/

//In this example is not allowed Access directly to UserInfo or do deletes	
ArrestDBConfig::allow(
	[
		"table"=>"UserInfo",
		"method"=>"GET"
	],
	function ($method,$table,$id){
		return false;
	});


ArrestDBConfig::allow(
	[
		"method"=>"DELETE"
	],
	function ($method,$table,$id){
		return false;
	});
	
	
/*
	MODIFY QUERY (OPTIONAL)
	
	Modify a query before execute this.
	
	In GET, GET_INTERNAL methods you can modify
	- SELECT atributes (string)
	- WHERE conditions (array)
	- TABLE name
	- ORDER BY
	- LIMIT
	- OFFSET
	
	In POST and PUT methods you can modify
	- VALUES (array)
	
	function ($method,$table,$id,$query)
	- $method
	- $table
	- $id
	- $query: query values
*/

//In this example only it's viewed non deleted tables
ArrestDBConfig::modifyQuery(
	[
		"method"=>["GET","GET_INTERNAL"]
	],
	function ($method,$table,$id,$query){
		$query["WHERE"][]="deleted=0";
		return $query;
	});
	
//In this example when a new user is created password is ofuscated with MD5 and is added a new value
ArrestDBConfig::modifyQuery(
	[
		"table"=>"User",
		"method"=>"POST"
	],
	function ($method,$table,$id,$query){
		$query["VALUES"]["password"]=md5($query["VALUES"]["password"]);
		$query["VALUES"]["createdByApi"]=1;
		return $query;
	});

	
/*
	POST PROCESS (Optional)
	
	It's called after an operation, and it allows to modify result or do any operation before send to caller
	
	function($method,$table,$id,$data)
	- $method
	- $table
	- $id: In POST case, $id is the id of created object.
	- $data: Data to return. Data can be an array or object (as array). See the following example to understand how to act in each case.
*/

//In this case remove password on User table before return the data
ArrestDBConfig::postProcess(
	[
		"table"=>"User",
		"method"=>["GET","GET_INTERNAL"],
	],
	function($method,$table,$id,$data){
		if (isset($data[0]))
			foreach ($data as $k=>$item)
				unset($item["password"]);
		else
			unset($data["password"]);
					
		return $data;
	});

//In this case when a new user is created, it's inserted 
ArrestDBConfig::postProcess(
	[
		"method"=>"POST",
		"table"=>"User"
	],
	function($method,$table,$id,$data){
		if (isset($_GET["Group_id"])){
			$group_id=$_GET["Group_id"];
			ArrestDB::query("INSERT INTO UserInGroup(Group_id,User_id) VALUES ({$group_id},{$id})");
		}
		
		return $data;
	});
	

/**
	CALL function (optional)
	
	Allows to call a function to do complex operations. All functions use POST method. Remember this when you'll call it.
	
	function ($func,$data)
	- $func: function name
	- $data: values in $_POST variable
*/

//In this case 
ArrestDBConfig::fnc("sendMsg",
	function ($func,$data){
		return sendMsg($data);
	});