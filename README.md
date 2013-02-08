@ Dynamic Sqlite Readme

@ License Information -- Released under MIT Open Source License, Please see included license 

Copyright (c) 2012 Nick Woodward


#Dynamic SQLite API
The Dynamic SQLite API provides several methods for easily performing CRUD operations on SQLite databases.

##Database Methods
###open\_database($dbase\_relative\_pathname)
Open a database. Input is the relative pathname of the database file. Return is the PDO handle of the database.

**Note:** If the database file does not exist, it will be created, so databases can be created using this method.


####Usage:
		$pdo = $dyna->open_database('db/myDatabase.sqlite');

###delete\_database($dbase\_relative\_pathname)
####Usage:
Delete an SQLite database. Returns 0 on failure, 1 on success.

		$dyna->delete_database('db/myDatabase.sqlite');

###export\_database($pdo, $array\_of\_tables\_to\_export = null)

Export a database to an array of tables and their schemas and rows.

**Note:**Not recommended for large databases.

**Note:**If $array\_of\_tables\_to\_export is null, all tables will be exported.

####Usage:

		$db = $dyna->export_database($pdo, array('table1','table2'));

		/* Output:
		$db = array( 
			[table1] => array(
				[schema] => array(
					[id] => 'INTEGER',
					[name] => 'TEXT'
				)
				[data] => array(
					[0] => array([id] => 1, [name] => 'Bill'),
					[1] => array([id] => 2, [name] => 'Suzy') 
				)
			)
			[table2] => array(
				[schema] => array(
					[id] => 'INTEGER',
					[animal] => 'TEXT'
					[danger] => 'INTEGER'
				)
				[data] => array(
					[0] => array([id] => 1, [animal] => 'sheep', [danger] => 3), 
					[1] => array([id] => 2, [animal] => 'wolf', [danger] => 9) 
				)
			)
		)

		*/


###import\_database($pdo, $array\_of\_tables\_schemas\_and\_columns)

Import an array of tables and their schemas and elements into a database.
**Note:** See the usage section of export\_database() for the format of $array\_of\_tables\_schemas\_and\_columns.
####Usage:

		$db = $dyna->export_database($pdo1);
		$dyna->import_database($pdo2, $db);


<hr>
##Tables Methods
###create\_table($pdo, $table\_name, $columns = array())

Creates a table in a database with column names and types defined by $columns.
####Usage:

		$columns = array('id' => 'INTEGER PRIMARY KEY', 'name' => 'TEXT', 'age' => 'NUMERIC');
		$dyna->create_table($pdo, 'myTable', $columns);

###delete\_table($pdo, $table\_name)

Deletes a table in a database.
####Usage:

		$dyna->delete_table($pdo, 'myTable');

###style='font-size:1.05em'>export\_table($pdo, $table\_name, $order\_by = null, $limit=999, $offset=0)
Export a table or a portion of a table to an array.

**Note:** $limit sets the number of rows to export ($limit=10 will export 10 rows).

**Note:** $offset sets which row to start selection ($offset = 5 will ignore the first 5 rows, which are dictated by the $order\_by clause).

**Note:** If $order\_by is set to null the order will default to either the primary key or the SQLite auto incremented ROWID. 
####Usage:

		$myTable = $dyna->export_table($pdo,'myTable');
		/*Output:
		$myTable =  array(
			[schema] => array(
				[id] => 'INTEGER',
				[animal] => 'TEXT'
				[danger] => 'INTEGER'
			)
			[data] => array(
				[0] => array([id] => 1, [animal] => 'tiger', [danger] => 10), 
				[1] => array([id] => 2, [animal] => 'sheep', [danger] => 3), 
				[2] => array([id] => 3, [animal] => 'wolf', [danger] => 9) 
			)
		)
		*/

		$myTableLimited = $dyna->export_table($pdo,'myTable', 'name, 2, 1);

		/*Output:
		$myTableLimited =  array(
			[schema] => array(
				[id] => 'INTEGER',
				[animal] => 'TEXT'
				[danger] => 'INTEGER'
			)
			[data] => array(
				[0] => array([id] => 1, [animal] => 'tiger', [danger] => 10), 
				[1] => array([id] => 3, [animal] => 'wolf', [danger] => 9) 
			)
		)
		*/

###import\_table($pdo, $table\_name, $array\_of\_schema\_and\_columns)

Import an array of schema and columns into a database.
<br>**Note:** For the format of the array, see the usage section of export\_table().
####Usage:

		$myTable = $dyna->export_table($pdo, 'myTable');
		$dyna->import_table($pdo, 'myOtherTable', $myTable);

###rename\_table($pdo, $table\_name, $new\_name)

Rename a table in a database.
####Usage:

		$dyna->rename_table($pdo, 'myTable', 'yourTable');

###copy\_table\_to\_other\_sqlite\_dbase($pdo, $table\_name, $new\_pdo)

Copy a table from one database to another.
####Usage:

		$pdo1 = open_database('db1.sqlite');
		$pdo2 = open_database('db2.sqlite');
		$dyna->copy_table_to_other_sqlite_dbase($pdo1, 'myTable', $pdo2);

<hr>
##Column Methods
###add\_column($pdo, $table\_name, $column\_name, $column\_type = 'text')

Add a column to a table.

**Note:** See <a target='\_blank' href='http://www.sqlite.org/datatype3.html'>SQLite3 Datatypes</a> for a description of valid column types.
####Usage:

		$dyna->add_column($pdo, 'myTable', 'age', 'numeric');

###delete\_column($pdo, $table\_name, $column\_name)

Delete a column from a table.
####Usage:

		$dyna->delete_column($pdo, 'myTable', 'age');

###rename\_column($pdo, $table\_name, $column\_name, $new\_name)

Rename a column in a table.
####Usage:

		$dyna->rename_column($pdo, 'myTable', 'name', 'animal');

<hr>
##Row Methods
###update($pdo, $table\_name, $record\_array, $strict=false)

Update or insert a row into a table.

**Note:** Setting $strict=false allows new columns can be added through a record. Setting $strict=true will reject any entry that attempts to add a column. See usage.

**Note:** The type of a column added through a record will be assigned 'text' or 'numeric' accordingly.
####Usage:

		$schema = array('id' => 'integer primary key', 'name' => 'text');
		$dyna->create_table($pdo, 'myTable', $schema)

		$record = array('id' => 0 , 'name' => 'lion', 'danger' = 9);
		$dyna->update($pdo, 'myTable, $record);
		//This adds the column 'danger' to the table (since $strict defaults to false)

		$record = array('id' => 0 , 'name' => 'sheep', 'fluffyness' = 8);
		$dyna->update($pdo, 'myTable, $record, true);
		//The row is not added since $strict=true and the record 
		//attempted to add the column 'fluffyness'.

###update_all($pdo, $table\_name, $array\_of\_records, $strict=false)

Update or insert many rows in a table.

**Note:** This uses prepared statements to significantly decrease the time needed for multiple transactions. 
####Usage:

		$schema = array('id' => 'integer primary key', 'name' => 'text');
		$dyna->create_table($pdo, 'myTable', $schema)

		$record_array = array();
		for ($i=0; $i<20; $i++){
			$record = array(
				"name" => "person $i",
				"age" => $i
		}
		$dyna->update_all($pdo, 'myTable, $record_array);
		//This inserts all of the records into the database much faster than inserting them individually.

###get($pdo, $table\_name, $id)

Get a row by its id. 
####Usage:

		$dyna->get($pdo,'myTable', 1) //Returns the row with 'id' = 1.

###delete($pdo, $table\_name, $id)

Delete a row with a given id.
####Usage:

		$dyna->delete($pdo, 'myTable', 1) //Deletes rows with 'id' = 1

###query( $pdo, $query )

Query a database. Returns all rows that satisfy the query.
####Usage:

		$query = "SELECT * FROM myTable WHERE 'name' = 'bob'"
		$dyna->query($pdo,$query); 
		//Returns all rows where 'name' = 'bob'

###delete\_by\_query($pdo, $table\_name, $query)

Delete all rows that satisfy a given query.
####Usage:

		$query = "'name' = 'bob'"
		$dyna->delete_by_query($pdo, 'myTable', $query); 
		//Deletes all rows where 'name' = 'bob'

<hr>
##Utility/Other Methods
###escape($str)

Adds quotes to a string and escapes special charaters.
<br>**Note:**See documentation on <a target='_blank' href='http://php.net/manual/en/pdo.quote.php'>PDO::quote()</a> for explanation and usage.
###get\_table\_schema($pdo, $table\_name)

Returns the schema of a table. 
####Usage:

		$schema = $dyna->get_table_schema($pdo, 'myTable');
		
		/* Output:
		$schema = array(
			[id] => 'integer',
			[name] => 'text'
		)
		*/

