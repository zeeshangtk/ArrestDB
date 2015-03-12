#ArrestDB

ArrestDB is a "plug-n-play" RESTful API for SQLite, MySQL and PostgreSQL databases.

ArrestDB provides a REST API that maps directly to your database stucture with no configuation.

Lets suppose you have set up ArrestDB at `http://api.example.com/` and that your database has a table named `customers`.
To get a list of all the customers in the table you would simply need to do:

	GET http://api.example.com/customers/

As a response, you would get a JSON formatted list of customers.

Or, if you only want to get one customer, then you would append the customer `id` to the URL:

	GET http://api.example.com/customers/123/

If you want to load a customer and all purchases and the products in each purchase, also user info
	
	GET http://api.example.com/customers/123/?extends=purchases,purchases/products,usser

##Requirements

- PHP 5.4+ & PDO
- SQLite / MySQL / PostgreSQL

##Installation

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

If are you using nginx try this:

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

Rename `config-example.php` to `config.php` and change the `$dsn` variable located at the top, here are some examples:

- SQLite: `$dsn = 'sqlite://./path/to/database.sqlite';`
- MySQL: `$dsn = 'mysql://[user[:pass]@]host[:port]/db/;`
- PostgreSQL: `$dsn = 'pgsql://[user[:pass]@]host[:port]/db/;`

After you're done editing the file, place it in a public directory (feel free to change the filename).

If you want to restrict access to allow only specific IP addresses, add them to the `$clients` array:

```php
$clients = array
(
	'127.0.0.1',
	'127.0.0.2',
	'127.0.0.3',
);
```

If your API must be in a subdirectory you can add `$prefix` variable. For instance, if your api is in `http://www.example.com/api` add:

```php
$prefix="/api"
```

### Extends (optional)
If your want to use `extends` option you must define `$relations` variable. For instance, in this case we want when get a customer o a list of customers obtain also:

- user (object)
- all purchases (array)
- all products in this purchase


```
http://api.example.com/Customer/?extends=user,purchases/purchaseProducts/product
```


```

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
 ..|- id         |              |Customer |                 +----+
   |- customer_id|--------------|- id     |                 |User|
   +-------------+              |- user_id|---------------- |- id|
                                +---------+                 +----+
```

The relations config must be:

```php
$relations=[
	"Customer"=>[
		"purchases"=>["type"=>"array","ftable"=>"purchase","fkey"=>"customer_id"],
		"user"=>["type"=>"object","key"=>"user_id","ffable"=>"user"]
	],
	"Purchase"=>[
		"purchaseProducts"=>["type"=>"object","ftable"=>"PurchaseProduct","fkey"=>"purchase_id"],
	],
	"PurchaseProduct"=>[
		"product"=>["type"=>"object","key"=>"product_id","ftable"=>"Product"]	
	}
}
```

Where:
- type : kind of relation (a customer has multiple purchases, a customer only have a user)
- ftable : Table related with (Customer table are related with User and with Purchase)
- fkey (optional, "id" by default) : key name in foreign table
- key (optional, "id" by default) : key name in current table.

### Sequrity / Allow access to table (optinal)

To allow access to table and id you can create "ArrestDB_allow" function that controls if a table($id) can be loaded. Returns a boolean, if it returns false, a Forbidden (403) is returned. 

Method can be:
- GET, POST, PUT and DELETE : Are direct API Calls
- GET_INTERNAL : Is an internal get for extends

In example case, list all Users is not allowed in direct api query but all extends access are allowed

```php
function ArrestDB_allow($method,$table,$id){
	if ($method=="GET_INTERNAL")
		return true;
		
	if ($table=="User" && $id=="")
		return false;
		
	return true;
}
```

Note: If you are using "extends", this works for each level.

### Sequrity / Id obfuscation (optinal)

You can ofuscate Ids

In this case, a global User with id field exists. The key for encription is a concat of an standard key, the table name and the user id. It prevent than:
- Use the same obfuscated id in different tables
- Use the same obfuscated id by different users

```php
function ArrestDB_obfuscate_id($table,$id,$reverse){
	global $user;
	
	$key="12345";
	
	if ($reverse){
		$id=base64_decode(strtr($id, '-_,', '+/='));
		return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key.$table.$user["id"], $id, MCRYPT_MODE_ECB));
	}
	else{
		$res=mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key.$table.$user["id"], trim($id), MCRYPT_MODE_ECB);
		return strtr(base64_encode($res), '+/=', '-_,'); 
	}
}

```

### Query modifications (optional)

To change query parameters you can create "ArrestDB_modify_query" function. "$query" parameter is an array with this sections:
- $query["SELECT"] : Select parameters, by default "*"
- $query["FROM"] : Query table, by default $table
- $query["WHERE"] : Is an other array with all AND conditions.

Other sections are "ORDER BY", "LIMIT", "OFFSET".

Must return the "$query" array

```php
//In this case only allows objects with "deleted=0"
function ArrestDB_modify_query($method,$table,$id,$query){
	$query["WHERE"][]="deleted=0";
	return $query;
}
```

Note: If you are using "extends", this works for each level.

### Result modifications (optional)

To post process the result you can create "ArrestDB_postProcess". "$data" parameter is an array with result. Function can modify this and must return back.

```php
function ArrestDB_postProcess($method,$table,$id,$data){
	if ($table=="User"){
		if (isset($data[0]))
			foreach ($data as $k=>$item)
				postProcess($method,$table,$id,$data[$k]);
		else
			postProcess($method,$table,$id,$data);
	}

	return $data;
}
```

Note: If you are using "extends", this works for each level

### Auth (optional)

You can add auth access control creating function "ArrestDB_auth". This function returns true when auth is ok, and false or exit when it fails.

This is an example using BASIC AUTH and User table to check auth.

```php
$user=null;

function ArrestDB_auth(){
	global $user;
	
	if (!isset($_SERVER['PHP_AUTH_USER'])||!isset($_SERVER['PHP_AUTH_PW'])) {
	    header('WWW-Authenticate: Basic realm="My Realm"');
	    header('HTTP/1.0 401 Unauthorized');
	    echo 'Invalid Auth';
	    exit;
	} else {
	    $user=$_SERVER['PHP_AUTH_USER'];
	    $pass=$_SERVER['PHP_AUTH_PW'];
	
		$query=ArrestDB::PrepareQuery([
		    "FROM"=>"User",
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
}

```
### Table alias (optional)

You can create views or complex queries using table alias without any query parameters in GET request. In this case you can create alias tables that extends functionality. You probably will use "ArrestDB_tableAlias($table)" with "ArrestDB_modify_query"

Note: you can do the same with views, but in some cases this method are more powerful.

These cases receive real table or the table referred by alias
- ArrestDB_obfuscate_id : table parameter are the real table
- FROM of query
- __table attribute of result objects

In this example, we have been created a virtual view of "Purchase" to refer these purchased that are paid. The name will be "PurchasePaid"

```php
function ArrestDB_tableAlias($table){
	if ($table=="PurchasePaid")
		return "Purchase"
		
	return $table;	
}

function ArrestDB_modify_query($method,$table,$id,$query){
	$query["WHERE"][]="deleted=0";
	
	if ($table=="PurchasePaid")
		$query["WHERE"][]="paid=1";
	
	return $query;
}
```

##API Design

The actual API design is very straightforward and follows the design patterns of the majority of APIs.

	(C)reate > POST   /table
	(R)ead   > GET    /table[/id]
	(R)ead   > GET    /table[/column/content]
	(U)pdate > PUT    /table/id
	(D)elete > DELETE /table/id

To put this into practice below are some example of how you would use the ArrestDB API:

	# Get all rows from the "customers" table
	GET http://api.example.com/customers/

	# Get a single row from the "customers" table (where "123" is the ID)
	GET http://api.example.com/customers/123/

	# Get all rows from the "customers" table where the "country" field matches "Australia" (`LIKE`)
	GET http://api.example.com/customers/country/Australia/

	# Get 50 rows from the "customers" table
	GET http://api.example.com/customers/?limit=50

	# Get 50 rows from the "customers" table ordered by the "date" field
	GET http://api.example.com/customers/?limit=50&by=date&order=desc

	# Create a new row in the "customers" table where the POST data corresponds to the database fields
	POST http://api.example.com/customers/

	# Update customer "123" in the "customers" table where the PUT data corresponds to the database fields
	PUT http://api.example.com/customers/123/

	# Delete customer "123" from the "customers" table
	DELETE http://api.example.com/customers/123/

Please note that `GET` calls accept the following query string variables:

- `by` (column to order by)
  - `order` (order direction: `ASC` or `DESC`)
- `limit` (`LIMIT x` SQL clause)
  - `offset` (`OFFSET x` SQL clause)
 - `extends` (load related objects)

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

The following codes and message are avaiable:

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



##Changelog

- **1.2.0** ~~support for JSON payloads in `POST` and `PUT` (optionally gzipped)~~
- **1.3.0** ~~support for JSON-P responses~~
- **1.4.0** ~~support for HTTP method overrides using the `X-HTTP-Method-Override` header~~
- **1.5.0** ~~support for bulk inserts in `POST`~~
- **1.6.0** ~~added support for PostgreSQL~~
- **1.7.0** ~~fixed PostgreSQL connection bug, other minor improvements~~
- **1.8.0** ~~fixed POST / PUT bug introduced in 1.5.0~~
- **1.9.0** ~~updated to PHP 5.4 short array syntax~~
- **1.10.0** ~~Config file & prefix & nginx suport~~
- **1.11.0** ~~Extends param~~
- **1.12.0** ~~Allow access, Query modifications and Result modifications callback~~
- **1.13.0** ~~Auth & GET_INTERNAL method~~
- **1.14.0** ~~Obfuscated id & not number id allow~~
- **1.15.0** Table aliases, url combinations with and without / fixed

##Credits

ArrestDB is a complete rewrite of [Arrest-MySQL](https://github.com/gilbitron/Arrest-MySQL) with several optimizations and additional features.

##License (MIT)

Copyright (c) 2014 Alix Axel (alix.axel+github@gmail.com).

Contributions, Ivan Lausuch <ilausuch@gmail.com>