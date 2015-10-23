#ArrestDB

ArrestDB is a "plug-n-play" RESTful API for SQLite, MySQL and PostgreSQL databases.

ArrestDB provides a REST API that maps directly to your database structure with no configuration.

##Usage

Lets suppose you have set up ArrestDB at `http://api.example.com/` and that your database has a table named `customers`.
To get a list of all the customers in the table you would simply need to do:

	GET http://api.example.com/customers/
	GET http://api.example.com/customers (optional without /)

As a response, you would get a JSON formatted list of customers.

Or, if you only want to get one customer, then you would append the customer `id` to the URL:

	GET http://api.example.com/customers/123
	GET http://api.example.com/customers/123/
	GET http://api.example.com/customers(123) (OData compatibility)

If you want to load a customer and all purchases and the products in each purchase, also user info
	
	GET http://api.example.com/customers/123/?extends=purchases,purchases/products,usser
	
As RESTful API, operations are:
	
	* (C)reate > POST   /table
	* (R)ead   > GET    /table[/id]
	* (R)ead   > GET    /table[/column/content]
	* (U)pdate > PUT    /table/id
	* (D)elete > DELETE /table/id
	
'GET' has different modifiers.

	* extends : allow to get a tree of relation objects in one call
	* limit : specify max elements to return
	* order : specify witch order list must returned. `ASC` or `DESC`
	* by : (use with order) specify what field is used to order

Additionally, `POST` and `PUT` requests accept JSON-encoded and/or zlib-compressed payloads.

> `POST` and `PUT` requests are only able to parse data encoded in `application/x-www-form-urlencoded`.
> Support for `multipart/form-data` payloads will be added in the future.

If your client does not support certain methods, you can use the `X-HTTP-Method-Override` header:

- `PUT` = `POST` + `X-HTTP-Method-Override: PUT`
- `DELETE` = `GET` + `X-HTTP-Method-Override: DELETE`

Alternatively, you can also override the HTTP method by using the `_method` query string parameter.

Since 1.5.0, it's also possible to atomically `INSERT` a batch of records by POSTing an array of arrays.


##Responses

All responses are in the JSON format. A `GET` response from the `customers` table might look like this:

```json
[
    {
        "id": "114",
        "customerName": "Australian Collectors, Co.",
        "contactLastName": "Ferguson",
        "contactFirstName": "Peter",
        "phone": "123456",
        "addressLine1": "636 St Kilda Road",
        "addressLine2": "Level 3",
        "city": "Melbourne",
        "state": "Victoria",
        "postalCode": "3004",
        "country": "Australia",
        "salesRepEmployeeNumber": "1611",
        "creditLimit": "117300"
    },
    ...
]
```

Successful `POST` responses will look like:

```json
{
    "success": {
        "code": 201,
        "status": "Created"
    }
}
```

Successful `PUT` and `DELETE` responses will look like:

```json
{
    "success": {
        "code": 200,
        "status": "OK"
    }
}
```

Errors are expressed in the format:

```json
{
    "error": {
        "code": 400,
        "status": "Bad Request"
    }
}
```

The following codes and message are available:

* `200` OK
* `201` Created
* `204` No Content
* `400` Bad Request
* `403` Forbidden
* `404` Not Found
* `409` Conflict
* `503` Service Unavailable

Also, if the `callback` query string is set *and* is valid, the returned result will be a [JSON-P response](http://en.wikipedia.org/wiki/JSONP):

```javascript
callback(JSON);
```

Ajax-like requests will be minified, whereas normal browser requests will be human-readable.



##Example used in this documentation
	
	This is an example to explain some concepts in this example. A Customer have only one User, but can have some Purchases. A Purchase has some Products, using relation PurchaseProduct
	
   +---------------+
   |PurchaseProduct|        +-------+
   |- id           |        |Product|
   |- product_id   | ...... |- id   |
 ..|- purchase_id  |        +-------+
 | |- quantity     |
 | +---------------+
 |
 |
 | +-------------+
 | |Purchase     |              +---------+
 ..|- id         |              |Customer |                 +----+    +----------+
   |- customer_id|--------------|- id     |                 |User|    | UserInfo |
   +-------------+              |- user_id|---------------- |- id|----| - user_id|
                                +---------+                 +----+	  +----------+


	Examples
	
	Get all rows from the "customers" extending information to User, userinfo and purchase
	GET http://api.example.com/customers/?extends=User/UserInfo,Purchase

	Get a single row from the "customers" table (where "123" is the ID)
	GET http://api.example.com/customers/123
	GET http://api.example.com/customers/123/
	GET http://api.example.com/customers(123) //OData compatibility

	Get all rows from the "customers" table where the "country" field matches "Australia" (`LIKE`)
	GET http://api.example.com/customers/country/Australia/

	Get 50 rows from the "customers" table
	GET http://api.example.com/customers/?limit=50

	Get 50 rows from the "customers" table ordered by the "date" field
	GET http://api.example.com/customers/?limit=50&by=date&order=desc

	Create a new row in the "customers" table where the POST data corresponds to the database fields
	POST http://api.example.com/customers/

	# Update customer "123" in the "customers" table where the PUT data corresponds to the database fields
	PUT http://api.example.com/customers/123/

	# Delete customer "123" from the "customers" table
	DELETE http://api.example.com/customers/123/
	


##Requirements

- PHP 5.4+ & PDO
- SQLite / MySQL / PostgreSQL

##Installation web server

If you're using Apache, you can use the following `mod_rewrite` rules in a `.htaccess` file:

```apache
<IfModule mod_rewrite.c>
	RewriteEngine	On
	RewriteCond		%{REQUEST_FILENAME}	!-d
	RewriteCond		%{REQUEST_FILENAME}	!-f
	RewriteRule		^(.*)$ index.php/$1	[L,QSA]
</IfModule>
```

***Nota bene:*** You must access the file directly, including it from another file won't work.

If you are using nginx try this:

```
server {
        listen       80;
        server_name  myDomain.es *.myDomain.es;
		root         /var/www/;

        try_files $uri /index.php?$args;

        location /index.php {
            fastcgi_connect_timeout 3s;     # default of 60s is just too long
            fastcgi_read_timeout 10s;       # default of 60s is just too long
            include fastcgi_params;   
        	fastcgi_pass unix:/var/run/php5-fpm.sock;
		}
	}
```

##Configuration

### DB access
Rename `config-example.php` to `config.php` and change the `$dsn` variable located at the top, here are some examples:

- SQLite: `$dsn = 'sqlite://./path/to/database.sqlite';`
- MySQL: `$dsn = 'mysql://[user[:pass]@]host[:port]/db/;`
- PostgreSQL: `$dsn = 'pgsql://[user[:pass]@]host[:port]/db/;`

After you're done editing the file, place it in a public directory (feel free to change the filename).

With bad configuration any operation returns:
```php    
    {
	    "error": 
	    {
	        "code": 503,
	        "status": "Service Unavailable"
	    }
	
	}
```

### Other optional configurations

If you want to restrict access to allow only specific IP addresses, add them to the `$clients` array:

```php
$clients = array
(
	'127.0.0.1',
	'127.0.0.2',
	'127.0.0.3',
);
```

Define path where API is configured. (OPTIONAL, by default '')	
For instance, if you have a 'api' folder in your website path you should access using this url: http://mydomain.com/api so you must configure $prefix using $prefix = '/api'

```php
$prefix="/api"
```

To allow any origin active `$allowAnyOrigin` in config file (OPTIONAL, by default true)

```php
$allowAnyOrigin=true;
```

To enable Access-Control headers are received during OPTIONS requests add `enableOptionsRequest` in config file (OPTIONAL, by default true)
```php
$enableOptionsRequest=true;
```

## Extended ArrestDB

Extended ArrestDB allows to create a complete API with advanced functions, as get an object and relations in one query.

The internal proceses are:



		
		                                     _______
	                               __..--....       ....----...__
	                          _.--'                               .--.._
	                      _,-'                                          .--._
	                    -'                           +-----------+           '
	      +----+     +-----+      +------------+     |Get objects|       +-------+
	 .....|Auth|-----|Allow|------|Modify Query|-----|  from DB  |-------|Extends|
	      +----+     +-----+      +------------+     +-----------+       +-------+
	        |no         |no                                                  |finish
	        |           |                                                    |
	     .-----.     .-----.                                           +------------+
	     |error|     |error|                              result ------|Post process|
	     | 403 |     | 403 |                                           +------------+
	     `-----'     `-----'	


	1. First Query is checked by auth, if it's allowed continues, other ways returns error.
	2. Query is checked by allow (GET and GET_INTERNAL already are different here).
	3. Query can be modified by ModifyQuery
	4. System get objects from DB using prepared Query
	5. System check if its necessary to extend information, If its the case renew the loop for all extended objects using GET_INTERNAL instead of GET
	6. PostProcess can filter and manipulate the information to return. 


### Configure

To use extended ArrestDB you can include EasyConfig in config.php

```php
require_once("easyConfig.php");
```
Following operations are available to use.

* alias: Define an alias for a table
* relation: Define a relation of a table with other
* auth: Define auth functions
* allow: Define allow functions
* modifyQuery: Define modify query functions
* postProcess: Define post process functions
* fnc: Define api functions available

All are optionals. All are accumulative but order is important because determine the order to apply.


### Aliases configuration

Define table aliases. An table alias can get different GET,POST,PUT and DELETE conditions and can be used in all following operations
	
ArrestDBConfig::alias($alias,$table);		

```php
ArrestDBConfig::alias("CategoryVisible","Category");//This is an example, remove it
```

### Relations configuration
	
Define relations for use extends in GET queries. This allow to get objects an related objects.

Usage:

```php
ArrestDBConfig::relation($table,$name,$config)
```

- $table: the table name (equal to table name) witch contains relation
- $name: the variable where relation is loaded

To prepare a config you can use prepareRelationObject and prepareRelationList functions

Objects (one to one, * to one), prepareRelationObject($foreignTable,$key,$foreingKey="id")

- $foreignTable: Related table name (equal to table name)
- $key: Table identifier key of relation
- $foreingKey: Foreign table identifier key of relation, by default "id"

List (one to *), prepareRelationList($foreignTable,$foreingKey,$key="id")

- $foreignTable: Related table name (equal to table name)
- $foreingKey: Foreign table identifier key of relation
- $key: Table identifier key of relation, by default "id"

Example
------------
```php
// Define a relation with other object (one to one): Each product has only one category
ArrestDBConfig::relation("Product","Category",ArrestDBConfig::prepareRelationObject("Category","Category_id"));

// Define a relation with list (one to some): Each category has a list of products.
ArrestDBConfig::relation("Category","Products",ArrestDBConfig::prepareRelationList("Product","Category_id"));
```

## Access to api authorization
	
Check if authorization is required to access to a table, alias or function. It's used to control access to api. 

By default all is authorized

Usage:

```php
ArrestDBConfig::auth($filter,$function)
```
- $filter: define the filter
- $function: define callback function(returns true or false), function($method,$table,$id){return true}

Returns a boolean. If it returns true, api continues execution, if it returns false, a Forbidden (403) is returned.

You can define a list of auth methods, when system match with one, all after that are not checked. By default is all authorized, so if is not defined any auth or they are no applicable system automatically authorize the api call.


Example
------------

```php
// Query Category table is allways authorized
ArrestDBConfig::auth(
	[
		"table"=>"Category",
		"method"=>"GET"
	],
	function($method,$table,$id){
		return true;
	});

// Other tables and operations are restringed and require HTTP authorization that is checked on DB User Table
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
			//Prepare params
		    $user=$_SERVER['PHP_AUTH_USER'];
		    $pass=sha1($_SERVER['PHP_AUTH_PW']);
	
			//Prepare query
			$query=ArrestDB::PrepareQueryGET([
			    "TABLE"=>"User",
			    "WHERE"=>["email='$user'","password='$pass'"]
			]);
	
			//Execute query
			$result=ArrestDB::Query($query);
	
			//Check if thereis one result
			if (count($result)==0){
				header('WWW-Authenticate: Basic realm="My Realm"');
			    header('HTTP/1.0 401 Unauthorized');
			    echo 'Invalid Auth';
			    exit;
			}
			
			//Set global user
			$user=$result[0];
			
			return true;
		}
	});
```

## allow access to a table, alias or function
It's similar to AUTH but it's used when is checked out if it's allowed to execute a method over a table or function. Return true if is allowed. By default all is allowed
	
Usage:

```php
ArrestDBConfig::allow($filter,$function)
```

- $filter: define the filter
- $function: define callback function(returns true or false), function($method,$table,$id){return true}

Returns a boolean. If it returns true, api continues execution, if it returns false, a Forbidden (403) is returned.

If you define a list of allow methods, when system match with one, next ones are are not checked

Example
------------

```php
// UserInfo is only accesible by extends (GET_INTERNAL), and internal operations
ArrestDBConfig::allow(
	[
		"table"=>"UserInfo",
		"method"=>["GET","POST","PUT","DELETE"]
	],
	function ($method,$table,$id){
		return false;
	});

//	All deletes are forbidden
ArrestDBConfig::allow(
	[
		"method"=>"DELETE"
	],
	function ($method,$table,$id){
		return false;
	});
```

### Modify a query
	
Modify a query before execute for instance adding more conditions

Usage:

```php
ArrestDBConfig::modifyQuery($filter,$function)
```

- $filter: define the filter
- $function: function($method,$table,$id,$query){return $query}

Returns a query. Returns modified $query

function ($method,$table,$id,$query)
- $method
- $table
- $id
- $query: query structura


These are fields you can modify in $query.

In GET, GET_INTERNAL methods you can modify
- SELECT attributes (string)
- WHERE conditions (array)
- TABLE name
- ORDER BY
- LIMIT
- OFFSET

In POST and PUT methods you can modify
- VALUES (array)



Example
------------
```php
//Query only non deleted tables
ArrestDBConfig::modifyQuery(
	[
		"method"=>["GET","GET_INTERNAL"]
	],
	function ($method,$table,$id,$query){
		$query["WHERE"][]="deleted=0";
		return $query;
	});

Obfuscate password (with md5) when User is created	
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
```

## Post process result
	
It's called after an operation, and it allows to modify result or do any operation before send to client

Usage:

```php
ArrestDBConfig::allow($filter,$function)
```
- $filter: define the filter
- $function: function($method,$table,$id,$data){return $data}

Returns the modified (or not) $data array

function($method,$table,$id,$data)
- $method
- $table
- $id: In POST case, $id is the id of created object.
- $data: Data to return. Data can be an array or object (as array). See the following example to understand how to act in each case.


Example
------------
	
```php
//Remove password on User table in queries before return the data
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

//Create a new UserInfo when User is created. Param Name is required
ArrestDBConfig::postProcess(
	[
		"method"=>"POST",
		"table"=>"User"
	],
	function($method,$table,$id,$data){
		if (isset($_GET["Name"])){
			$name=$_GET["Name"];
			ArrestDB::query("INSERT INTO UserInfo(Name,User_id) VALUES ({$name},{$id})");
		}
		
		return $data;
	});
```

## Calling functions
	
Allows to call a function to do complex operations. All functions use POST method. Remember this when you'll call it.

Usage:
```php
ArrestDBConfig::fnc($name,$function)
```
- $name: name of function
- $function: function($func,$data){return $data}

Returns a string that could be returned as response. It could be a JSON string

function ($func,$data)
- $func: function name
- $data: values in $_POST variable



Example
------------

```php
// version() api function returns string "Beta 1"
ArrestDBConfig::fnc("version",
	function ($func,$data){
		return json_encode(array("version"=>"Beta 1","minor"=>123));
	});
	
// sendMsg() api function returns result of calling to method sendMsg
ArrestDBConfig::fnc("sendMsg",
	function ($func,$data){
		return sendMsg($data);
	});
	
function sendMsg($data){
	//TODO. Do something
	return true	
}
```

##Credits

ArrestDB is son of [ArrestDB](https://github.com/ilausuch/ArrestDB) but with some optimizations and additional features.

##License (MIT)

Copyright (c) 2014 Alix Axel (alix.axel+github@gmail.com) - original ArrestDB
Copyright (c) 2015 Ivan Lausuch (ilausuch@gmail.com) - featured ArrestDB