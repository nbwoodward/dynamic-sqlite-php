<?
/*
*	The dynamic_sqlite class simplifies CRUD operations for SQLite databases.This class makes rapid database 
*	development easier since no formal schema needs to be pre-defined.  Add elements to the schema just by
*	adding data (two auto-defined types: TEXT and NUMERIC based upon the type of the data element).  Later the 
*	schema can be cleaned up with methods to remove or rename a table or column.
*
*	SQLITE_BASEDIR shout NOT be accessible via the web
*
*	This software is released under the MIT Open Source License viewable here: http://opensource.org/licenses/mit-license.php/ 
*	For licensing information view Readme file included in this package.  
*
*/
define("SQLITE_BASEDIR", "../var/dbase");

class dynamic_sqlite {
	function __construct($auto_transaction=true)
	{
		$this->auto_transaction = $auto_transaction;
	}
	/**
	 *  PDO object of more recently opened database.
	 *
	 * -This is automatically set when you open a database.
	 *
	 * @see 	open_database()
	 * @var		PDO_obj	$pdo
	 */
	public $pdo;
	/**
	 * For use in prepared SQL statements.
	 * 
	 *@see		begin_transaction()
	 *@var		bool	$in_transaction
	 */
	public $in_transaction = false;

	/**
	 * Make sure to commit any transactions on destruct.
	 */
	function __destruct()
	{
		$this->commit($this->pdo);
	}
	
	/**
	* Begin a transaction.
	*
	* -Used to begin an SQL transaction. Use commit() to commit the transaction.
	* -Use begin_transaction/commit for updating many rows at once. Or use update_all method.
	* -See http://www.sqlite.org/faq.html#q19 for more information.
	* @uses 	$pdo 	to indicate a database.
	* @param	PDO_obj	$pdo
	*
	*Example use:
	*	$rows //array of many rows to insert or update
	*
	*	$dyna->begin_transaction();
	*	
	*	foreach($rows as $row){
	*		$dyna->update($pdo,'my_table',$row);
	*	}
	*	
	*	$dyna->commit();
	*/
	function begin_transaction($pdo)
	{
		if ( ! $this->in_transaction ) {
			$pdo->beginTransaction();

			$this->in_transaction = true;
		}
	}

	/**
	 * Commit a transaction of a prepared statement.
	 *
	 * -Used in conjuction with begin_transaction() to commit a prepared statement.
	 * -SQLite databases are by default in autocommit mode.
	 * -Use begin_transaction/commit for updating many rows at once. Or use update_all method.
	 * -For example use see begin_transaction() above.
	 * @uses	$pdo 	to indicate a database.
	 * @param	PDO_obj	$pdo
	 */
	function commit($pdo)
	{
		$result = false;
		if ($this->in_transaction) {
			$this->in_transaction = false;
			$result = $pdo->commit();
		}
		return $result;
	}
	
	/**
	* Set the error mode for a database.
	*
	* -Default error mode is PDO::ERRMODE_SILENT
	* @uses 	$pdo 	to indicate a database.
	* @uses 	$mode 	to indicate an error mode.
	* @param	PDO_obj	$pdo
	* @param	integer	$mode
	*/
	function set_error_mode($pdo, $mode){
		$pdo->setAttribute(PDO::ATTR_ERRMODE, $mode);
	}
	
	/*------------------------------------------------------------------*\
	          FUNCTIONS FOR SQLITE TABLE/column MODIFICATION

	  All function that do not specify a return value, return a bool
	  value
	\*------------------------------------------------------------------*/

	/** 
	* Open an SQLite database.
	*
	* -If the database doesn't exist, it will be created.
	* -Returns PDO handle of database.
	* @uses 	$dbase_rel_path_name 	to indicate a database file.
	* @param	string	$dbase_rel_path_name 	
	* @return 	PDO_obj
	*/
	function open_database($dbase_rel_path_name)
	{
		touch($dbase_rel_path_name);
		$pdo = new PDO('sqlite:'.$dbase_rel_path_name);
		
		if($pdo) {
			$this->pdo = $pdo;
			
			if ($this->auto_transaction) {
				$this->begin_transaction();
			}
		}
		return $pdo;
	}

	/**
	* Move a database to another file/directory.
	*
	* @uses 	$old_dbase_rel_path_name 	to indicate a database file to move.
	* @uses 	$new_dbase_rel_path_name 	to indicate a destination database file.
	* @param	string	$new_dbase_rel_path_name
	* @param	string	$old_dbase_rel_path_name
	* @return	bool
	*/
	function move_database( $old_dbase_rel_path_name, $new_dbase_rel_path_name )
	{									
		$result = false;
		if(!file_exists($new_dbase_rel_path_name)){
			rename($old_dbase_rel_path_name,$new_dbase_rel_path_name);	
			$result = $this->open_database($new_dbase_rel_path_name);
		}
		return ( ($result != false) ? $result : 0);
	}

	/**
	* Import a database.						//FIXME Iterate process to avoid large arrays. Replace array input with a PDO input.
	* 
	* -This imports an array of tables into a database.
	* -Note: $array_of_tables_schemas_and_columns is of the same format as the return of export_database(). 
	* @uses 	$pdo 	to indicate a database to import into.
	* @uses 	$array_of_tables_schemas_and_columns 	to pass the data.
	* @param	PDO_obj	$pdo
	* @param	array	$array_of_tables_schemas_and_columns
	* @return	bool
	*
	*Example array input:
	*	$array_of_tables_schemas_and_columns
	*		This paramater is an array structure describing the database
	*		array(
	*			'table1' => array(
	*				'schema' => array( 
	*					'id'		=> "INTEGER PRIMARY KEY",
	*					'name'		=> "TEXT",
	*					'email'		=> "TEXT",
	*					'age'		=> "NUMERIC",
	*					'hourly_rate'	=> "NUMERIC",
	*					'photo'		=> "BLOB"
	*				),
	*				'data' => array(
	*					array('id' => 1, 'name' => "Ted", 'email' => "ted@gmail.com", 'age' => 22, 'hourly_rate' => 11.50, ...),
	*					array('id' => 2, 'name' => "Jan", 'email' => "jan@yahoo.com", 'age' => 41, 'hourly_rate' => 33.80, ...),
	*					...
	*				)
	*			),
	*			'table2' => array(...)
	*		)
	*/
	function import_database( $pdo, $array_of_tables_schemas_and_columns )
	{
		$result = false;
		foreach($array_of_tables_schemas_and_columns as $table => $values){
			$result = $this->import_table($pdo,$table,$values);
			if($result == 0) break;
		}
		return ( ($result != false) ? 1 : 0);
	}

	function import_database_from_pdo($pdo_to_export, $receiving_pdo, $array_of_tables_to_import = null){

		if($array_of_tables_to_import == null){ 		//If $array_of_tables_to_import is null, find all table names.
			$array_of_tables_to_import = array();
			$query = "SELECT name FROM sqlite_master WHERE type = 'table'";
			$table_names = $this->query($pdo_to_export, $query);
			foreach($table_names as $row){
				$array_of_tables_to_import[] = $row['name'];
			}
		}

		$result = array();
		foreach($array_of_tables_to_import as $table_name){
			$result = $this->copy_table_to_other_sqlite_dbase($pdo_to_export, $table_name, $receiving_pdo);
			echo "-----".$result."-----";
		}
		return $result;
	}

	/**
	* Export several or all tables in a database.
	*
	* -The return array can be imported into another database using import_database().
	* @uses 	$pdo 	to indicate a database to export.
	* @uses 	$array_of_tables_to_export	to indicate which tables to export. If left as null, all tables will be exported.
	* @param	PDO_obj	$pdo
	* @param 	array $array_of_tables_to_export
	* @return	array
	*/
	function export_database( $pdo, $array_of_tables_to_export=null )
	{
		if($array_of_tables_to_export == null){ 		//If $array_of_tables_to_export is null, find all table names.
			$array_of_tables_to_export = array();
			$query = "SELECT name FROM sqlite_master WHERE type = 'table'";
			$table_names = $this->query($pdo, $query);
			foreach($table_names as $row){
				$array_of_tables_to_export[] = $row['name'];
			}
		}
		$result = array();
		foreach($array_of_tables_to_export as $table_name){
			$result[$table_name] = $this->export_table($pdo, $table_name);
		}
		return $result;
	}

	/**
	* Delete a database.
	*
	*@uses 	$database_rel_path_name 	to indicate which database to delete.
	*@param 	string	$database_rel_path_name	
	* @return	bool
	*/

	function delete_database($database_rel_path_name){
		$result = false;
		if(file_exists($database_rel_path_name)){
			$result = unlink($database_rel_path_name);
		}
		return ( ($result != false) ? 1 : 0);
	}

	/*
	* Import a table into a database.				//FIXME Make it optional to import into an existing table.
	*
	* -This imports an array of columns into a new table.
	* -If the table already exists this will fail.
	* @uses 	$pdo 	to indicate a database.
	* @uses 	$table_name 	to indicate a name for the new table.
	* @uses 	$array_of_schema_and_columns to pass the data.	
	* @param	PDO_obj	$pdo
	* @param	string	$table_name
	* @param 	array	$array_of_schema_and_columns
	* @return	bool
	*
	* Example of array input:
	*	$array_of_schema_and_columns
	*		This parameter is an array structure describing the table
	*			array(
	*				'schema' => array( 
	*					'id'		=> "INTEGER PRIMARY KEY",
	*					'name'		=> "TEXT",
	*					'email'		=> "TEXT",
	*					'age'		=> "NUMERIC",
	*					'hourly_rate'	=> "NUMERIC",
	*					'photo'		=> "BLOB"
	*				),
	*				'data' => array(
	*					array('id' => 1, 'name' => "Ted", 'email' => "ted@gmail.com", 'age' => 22, 'hourly_rate' => 11.50, ...),
	*					array('id' => 2, 'name' => "Jan", 'email' => "jan@yahoo.com", 'age' => 41, 'hourly_rate' => 33.80, ...),
	*					...
	*				)
	*			),
	*/

	function import_table( $pdo, $table_name, $array_of_schema_and_columns)
	{
		$schema = $array_of_schema_and_columns['schema'];
		$result = $this->create_table($pdo, $table_name, $schema);
		if($result){ 
			$data = $array_of_schema_and_columns['data'];
			foreach($data as $row){
				$result = $this->update($pdo, $table_name, $row);
				if($result == 0) break;
			}
		}

		return ( ($result != false) ? 1 : 0);
	}
	
	/**
	* Export a table to an array.
	*
	* -Exports some or all of the rows of a table to an array.
	* @uses 	$pdo 	to indicate a database.
	* @uses 	$table_name 	to indicate a table to export.
	* @uses 	$order_by 	to indicate an column to order by.
	* @uses 	$limit 		to limit the number of rows exported.
	* @uses 	$offset 	to indicate which row to start counting at. An offset of 0 starts at the first row.
	* @param	PDO_obj	$pdo
	* @param 	string	$table_name
	* @param 	string	$order_by
	* @param 	integer	$limit
	* @param 	integer	$offset
	* @return	array
	*/
	function export_table( $pdo, $table_name, $order_by = null, $limit=999, $offset=0)
	{

		$query = "SELECT * FROM $table_name"; 	
		if($order_by){
			$query .= " ORDER BY $order_by";
		}
		$query .=  " LIMIT $limit OFFSET $offset";

		$table = $pdo->query($query);

		$array_of_schema_and_columns = array();
		if($table){

			$record_array = $table->fetchAll(PDO::FETCH_ASSOC);
			
			$schema = $this->get_table_schema($pdo, $table_name);

			$array_of_schema_and_columns['schema'] = $schema;
			$array_of_schema_and_columns['data'] = $record_array;
		}

		return $array_of_schema_and_columns;
	}
	/**
	* Create a table in a database.
	*
	* -If $columns is not included, column names and data types will be created dynamically in the update() function.
	* @uses 	$pdo 	to indicate a database.
	* @uses 	$name 	to indicate the name of the table to be created.
	* @uses 	$columns 	to create columns and indicate their 
	* @param	PDO_obj	$pdo
	* @param	string	$name
	* @param	array	$columns
	* @return	bool
	*
	*Example columns array:
	*	$columns is a schema array: 
	*		$columns = array(
	*			'id' => 'INTEGER PRIMARY KEY',
	*			'name' => 'TEXT',
	*			...
	*		);
	*/

	function create_table( $pdo, $name, $columns = array() )
	{
		$result = false;
		$columns['id'] = "INTEGER PRIMARY KEY";				//force an id column
		if ($this->table_name_is_valid($name)) {
			$column_string_array = array();
			foreach($columns as $key => $type){
				$column_string_array[] = $key . " " . $type;
			}
			$column_string = implode(",",$column_string_array);
			$query = "CREATE TABLE IF NOT EXISTS $name ($column_string)";
			$result = $pdo->exec($query);
		}

		return ( ($result !== false) ? 1 : 0);
	}

	/**
	* Delete a table.
	*
	* @uses 	$pdo 	to indicate a database.
	* @uses 	$table_name 	to indicate a table to delete.
	* @param	PDO_obj	$pdo
	* @param	string	$table_name
	* @return	bool
	*/
	function delete_table( $pdo, $table_name )
	{
		$query = "DROP TABLE IF EXISTS $table_name";
		$result = $pdo->exec($query);
		return ( ($result !== false) ? 1 : 0);
	}
	
	/**
	* Rename a table
	*
	* @uses 	$pdo 	to indicate a database.
	* @uses 	$table_name 	to indicate a table to rename.
	* @uses 	$new_name 	to indicate the new name of a table.
	* @param	PDO_obj	$pdo
	* @param	string	$table_name
	* @param	string	$new_name
	* @return	bool
	*/

	function rename_table( $pdo, $table_name, $new_name )
	{
		$result = false;
		if ($this->table_name_is_valid($name)) {
			$query = "ALTER TABLE $table_name RENAME TO $new_name";
			$result = $pdo->exec($query);
		}
		return ( ($result !== false) ? 1 : 0);
	}

	/**
	* Copy a table from one database to another.
	*
	* @uses 	$pdo 		to indicate a database to copy from.
	* @uses 	$table_name 	to indicate a table to copy.
	* @uses 	$new_pdo 	to indicate a database to copy to.
	* @param	PDO_obj	$pdo
	* @param	string	$table_name
	* @param	PDO_obj	$new_pdo
	* @return	bool
	*/

	function copy_table_to_other_sqlite_dbase($pdo, $table_name, $new_pdo) 
	{
		$schema = $this->get_table_schema($pdo, $table_name);
		$result = $this->create_table($new_pdo, $table_name, $schema);
		
		$query = $pdo->query("SELECT COUNT(*) FROM $table_name");
		$number_of_rows = $query->fetch(PDO::FETCH_ASSOC);
		
		$number_of_rows = $number_of_rows['COUNT(*)'] + 0;
		
		if( ($result != 0) && ($number_of_rows != 0) ){

			for($i=1; $i <= $number_of_rows; $i++){
				$record = $this->get($pdo, $table_name, $i);
				if(count($record)){
					$result = $this->update($new_pdo, $table_name, $record);
					if($result == 0) break;
				}
			}
			
		}

		return ( ($result != false) ? 1 : 0);
	}

	/**
	* A function to approve the name of a table.
	*
	* -This is called in create_table().
	* @uses 	$table_name 	to indicate a table name.
	* @param	string	$table_name.
	* @return	bool
	*/

	function table_name_is_valid($table_name){
		$result = false;
		$pattern = '/^[a-zA-Z][a-zA-Z0-9_]*$/';

		if(preg_match($pattern,$table_name)){
			$result = true;
		}
		
		return $result;
	}
	
	/*------------------------------------------------------------------*\
		column methods
	\*------------------------------------------------------------------*/
	/**
	* Determing if the type of a column is valid.
	*
	* -Used when adding a new column.
	* @uses 	$column_type 	to indicate the type of the column 
	* @param	string	$column_type
	* @return	bool
	*/
	function column_type_is_valid( $column_type )
	{
		static $valid_types = array("numeric"=>1, "text"=>1, "blob"=>1, "interger primary key"=>1 );

		return $valid_types[ $column_type ]===1;
	}

	/**
	* Determing if the name of a column is valid
	*
	* -Used when adding a new column.
	* @uses 	$column_name 	to indicate the name of a column.
	* @param	string	$column_name
	* @return	bool
	*/

	function column_name_is_valid( $column_name )
	{
		$result = false;
		$pattern = '/^[a-zA-Z][a-zA-Z0-9_]*$/';

		if(preg_match($pattern,$column_name)){
			$result = true;
		}
		return ( ($result != false) ? 1 : 0);
	}

	/**
	* Add a column to a table.
	*
	* @uses 	$pdo 	to indicate a database.
	* @uses 	$table_name 	to indicate a table to add a column to.
	* @uses 	$column_name 	to indicate the name of the added column.
	* @uses 	$column_type 	to indicate the type of the added column.
	* @param	PDO_obj	$pdo
	* @param	string	$table_name
	* @param	string	$column_name
	* @param	string	$column_type
	* @return	bool
	*/
	function add_column( $pdo, $table_name, $column_name, $column_type = 'text')
	{
		$result = false;

		if ($this->column_name_is_valid( $column_name ) && $this->column_type_is_valid( $column_type ) ) {
			$result = $pdo->exec("ALTER TABLE $table_name ADD $column_name $column_type");
			if($result !== false){ $result = true; }
		}

		return ( ($result != false) ? 1 : 0);
	}

	/**
	* Delete a column in a table.
	*
	* @uses 	$pdo 	to indicate a database.
	* @uses 	$table_name 	to indicate a table to delete a column from.
	* @uses 	$column_name 	to indicate the name of the column to delete.
	* @param	PDO_obj	$pdo
	* @param	string	$table_name
	* @param	string	$column_name
	* @return	bool
	*/
	function delete_column( $pdo, $table_name, $column_name)
	{
		$result = $this->alter_column($pdo, $table_name, $column_name);
		return $result;
	}

	/**
	* Rename a column in a table.
	*
	* @uses 	$pdo 	to indicate a database.
	* @uses 	$table_name 	to indicate a table to rename a column in.
	* @uses 	$column_name 	to indicate the name of the column to rename.
	* @uses 	$new_name 	to indicate the new name of the column.
	* @param	PDO_obj	$pdo
	* @param	string	$table_name
	* @param	string	$column_name
	* @return	bool
	*/
	function rename_column( $pdo, $table_name, $column_name, $new_name ) 
	{
		if($this->column_name_is_valid($new_name)){
			$result = $this->alter_column($pdo, $table_name, $column_name, $new_name);
		}
		return $result;
	}

	/**
	* Alter a column in a table.
	*
	* -This method is called by delete_column() and rename_column(). 
	* -This method, if called explicitly, will rename $column_name if $new_new name is provided. Otherwise $column_name will be deleted.
	* @uses 	$pdo 	to indicate a database.
	* @uses 	$table_name 	to indicate a table to alter a column in.
	* @uses 	$column_name 	to indicate the name of the column to alter.
	* @uses 	$new_name 	to indicate the new name of the column if the column is being renamed.
	* @param	PDO_obj	$pdo
	* @param	string	$table_name
	* @param	string	$column_name
	* @return	bool
	*/

	function alter_column( $pdo, $table_name, $column_name, $new_name = null )
	{
		$result = false;
		$backup_table_name = $table_name.'_backup';

		$schema = $this->get_table_schema($pdo, $table_name);
		if($new_name == null){
			unset($schema[$column_name]);
			$result = true;
		} else {
			if(!$schema[$new_name]){
				$new_schema = array();
				foreach($schema as $key => $value){
					if($key == $column_name){
						$key = $new_name;
					}
					$new_schema[$key] = $value;
				}
				$schema = $new_schema;
				$result = true;
			}
		}
		if($result == true){
			$result = $this->create_table($pdo, $backup_table_name, $schema);
			
			$query = $pdo->query("SELECT COUNT(*) FROM $table_name");
			$number_of_rows = 0;
			if($query){
				$number_of_rows = $query->fetch(PDO::FETCH_ASSOC);
				$number_of_rows = $number_of_rows['COUNT(*)'] + 0;
			} 
			
			if( ($number_of_rows != 0) && ($result != 0) ){
				for($i=1; $i <= $number_of_rows; $i++){
					$row = $this->get($pdo, $table_name, $i);
					if(count($row)){
						if($new_name != null){	
							$row[$new_name] = $row[$column_name];
						}
						unset($row[$column_name]);
						$result = $this->update($pdo, $backup_table_name, $row);
						if($result == 0) break;
					}

				}
				
				unset($query, $number_of_rows); //If we don't unset these, delete_table fails because the table is locked.
				if($result != 0){
					$this->delete_table($pdo, $table_name);	
					$this->rename_table($pdo, $backup_table_name, $table_name);
				}

			}
		}
		
		return ( ($result != false) ? 1 : 0);
	}

	/*------------------------------------------------------------------*\
		table functions
	\*------------------------------------------------------------------*/

	/**
	* Update a row in a table.
	*
	* -This method can be used to input new rows into a table.
	* -This method can also be used to update an existing row by indicating an 'id' field in the $record_array input.
	* -If $strict is set to true then a record containing a column that does not exist in the table will be rejected.
	*	Otherwise, new columns will be added automatically and will receive either the TEXT or NUMERIC type.
	*
	* @uses 	$pdo 	to indicate a database.
	* @uses 	$table_name 	to indicate a table to update.
	* @uses 	$record_array 	to pass data to the table.
	* @uses 	$strict 	to decide whether to allow $record_array to have columns not included in the table. 
	*					If set to true, new columns will not be added, and the record will be rejected.
	* @param	PDO_obj	$pdo
	* @param	string	$table_name
	* @param	array	$record_array
	* @param	bool	$strict
	* @return	bool
	*
	*Example input record array:
	*	$record_array 
	*		an array of columns and values.  This can be all columns in the table's schema
	*		or just one or two columns.
	*		
	*		eg.	array(
	*				'id' 	=> 0,
	*				'name'	=> bill,
	*				'pay' 	=> ,
	*				'looks'	=> 'bad'
	*			)
	*		
	*/
	function update( $pdo, $table_name, $record_array, $strict=false )
	{
		$record_array['id'] += 0;

		if(!($table_exists = $pdo->query("SELECT 1 FROM $table_name"))){
			$keys = $this->get_record_keys($record_array);
			$this->create_table($pdo,$table_name,$keys);
		}

		if($id = $record_array['id']){
			$current_records = $this->get($pdo, $table_name, $id);
			$record_array = array_merge($current_records, $record_array);
		}

		$query_string = $this->build_sql_insert_or_replace_into_string($table_name,$record_array);

		$result = $pdo->exec($query_string);

		if( ($strict == false) && (!$result) ){			
			$table_info = $pdo->query("PRAGMA table_info($table_name)");
			$current_keys=array();
			foreach($table_info as $row){
				$current_keys[] = $row['name'];
			}
			$keys = $this->get_record_keys($record_array);
			foreach($keys as $key => $type){
				if( !in_array($key, $current_keys) ){
					$this->add_column($pdo,$table_name,$key);
				}
			}

			$result = $pdo->exec($query_string);
		}
		return ( ($result != false) ? $pdo->lastinsertid() : 0);


	}

	/**
	* Update many rows in a table quickly.
	*
	* -This method can be used to input new rows into a table.
	* -If $strict is set to true then a record containing a column that does not exist in the table will be rejected.
	*	Otherwise, new columns will be added automatically and will receive either the TEXT or NUMERIC type.
	* -This uses prepared statements and is much faster than manually updating many rows at a time.
	*
	* @param	PDO_obj	$pdo
	* @param	string	$table_name
	* @param	array	$record_array
	* @param	bool	$strict
	* @return	bool
	*
	*Example input record array:
	*	$array_of_records 
	*		an array of arrays of columns and values.  This can be all columns in the table's schema
	*		or just one or two columns.
	*		
	*		eg.	array(
	*				0 =>	array(
	*					'id' 	=> 0,
	*					'name'	=> bill,
	*					'pay' 	=> ,
	*					'looks'	=> 'bad'
	*				),
	*				1 =>	array(
	*					'id' 	=> 3,
	*					'name'	=> nick,
	*					'looks'	=> 'good'
	*				),
	*				...
	*			)
	*/
	function update_all($pdo, $table_name, $array_of_records, $strict = false){
		$this->begin_transaction($pdo);
		
		foreach($array_of_records as $record){
			$this->update($pdo, $table_name, $record, $strict);
		}

		return $this->commit($pdo);
	}
	

	/**
	* Get a row by it's id.
	*
	* -Returns an array of one row.
	* @uses 	$pdo 	to indicate a database.
	* @uses 	$table_name 	to indicate a table to select from.
	* @uses 	$id 	to indicate the id of the row to select.
	* @param	PDO_obj	$pdo
	* @param	string	$table_name
	* @param	integer	$id
	* @return	array
	*/
	function get( $pdo, $table_name, $id )
	{
		$result = array();
		$query = "SELECT * FROM $table_name WHERE id = $id";
		$result_array =  $this->query($pdo, $query);
		if(count($result_array)){
			$result = $result_array[0];
		}

		return $result;
	}

	/**
	* Execute a custom query on a database.
	*
	* -Returns an array of resulting rows, or an empty array if nothing is found.
	* @uses 	$pdo 	to indicate a database.
	* @uses 	$query 	to indicate a custom query.
	* @param	PDO_obj	$pdo
	* @param	string	$query
	* @return	array
	*/
	function query( $pdo, $query )
	{
		$result = array();
		$record = $pdo->query($query);
		if($record){
			$result = $record->fetchAll(PDO::FETCH_ASSOC);
		}
		
		return $result;
	}

	/**
	* Delete a row.
	*
	* @uses 	$pdo 	to indicate a database.
	* @uses 	$table_name 	to indicate a table to delete from.
	* @uses 	$id 	to indicate the id of the row to delete.
	* @param	PDO_obj	$pdo
	* @param	string	$table_name
	* @param	integer	$id
	* @return	bool
	*/
	function delete($pdo, $table_name, $id)
	{
		$result = $pdo->exec("DELETE FROM $table_name WHERE id = $id");
		return ( ($result != false) ? 1 : 0);

	}

	/**
	* Delete a series of rows that satisfy a given query.
	*
	* @uses 	$pdo 	to indicate a database.
	* @uses 	$table_name 	to indicate a table to delete from.
	* @uses 	$query 	to select which rows to delete.
	* @param	PDO_obj	$pdo
	* @param	string	$table_name
	* @param	string	$query
	* @return	bool
	*/

	function delete_by_query($pdo, $table_name, $query)
	{
		$result = $pdo->exec("DELETE FROM $table_name WHERE $query");
		return ( ($result != false) ? 1 : 0);

	}
	

	/*------------------------------------------------------------------*\
		utility functions
	\*------------------------------------------------------------------*/

	/**
	* Returns a quoted string to be used in an SQL statment.
	*
	* -Used in build_sql_insert_or_replace_into_string.
	* @uses 	$str 	to indicate a string to escape.
	* @param	string	$str
	* @return	string
	*/
	function escape($str)
	{
		return $this->pdo->quote($str);
	}

	/**
	* Get the record keys and type from a record array.
	*
	* -Used in update().
	* @uses 	$record_array 	to get keys from.
	* @param	array	$record_array
	* @return	array
	*/
	function get_record_keys($record_array){
		foreach ($record_array as $key => $value) {
			$type = "TEXT";
			if (is_numeric($value)) {
				$type = "NUMERIC";
			}
			$keys[$key] = $type;
		}
		return $keys;
	}

	/**
	* Get the schema of columns of a table. 
	*
	* -Used in export_table(), copy_table_to_other_sqlite_dbase() and alter_column(). 
	* @uses 	$pdo 	to indicate a database.
	* @uses 	$table_name 	to indicate a table to get the schema from.
	* @param	PDO_obj	$pdo
	* @param	string	$table_name
	* @return	array
	*/

	function get_table_schema($pdo, $table_name){

		$table_info = $pdo->query("PRAGMA table_info($table_name)");

		$schema = array();
		foreach($table_info as $row){
			$schema[$row['name']] = $row['type'];
		}

		return $schema;
	}


	/**
	* Given a table name and a row of data, build an SQL string to insert or replace the row. 
	*
	* -Used in update().
	* @uses 	$table 	to indicate a table name.
	* @uses 	$row 	to indicate the data being entered.
	* @param	string	$table_name
	* @param	array	$row
	* @return	bool
	*/
	function build_sql_insert_or_replace_into_string($table_name, $row)
	{
		$names = array();
		$values = array();
		$id = $row['id'] + 0;
		foreach ($row as $name=>$value) {
			$names[$name] = $name;
			if (is_string($value)) {
				$values[$name] = $this->escape($value);
			} else {
				$values[$name] = $value | null;
			}
		}
		if ( ! $id ) {
			$values['id']="null";
		} 

		return "INSERT OR REPLACE INTO $table_name (".join(', ',$names).") VALUES (".join(', ', $values).")";
	}
}
