<?
/*
	The dynamic_sqlite class simplifies CRUD operations for SQLite databases.

	SQLITE_BASEDIR shout NOT be accessible via the web

	This software is released under the MIT Open Source License viewable here: http://opensource.org/licenses/mit-license.php/ 
	For licensing information view Readme file included in this package.  
*/
define("SQLITE_BASEDIR", "../var/dbase");

class dynamic_sqlite {
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
	 * Commit a transaction of a prepared statement.
	 *
	 * -Used in conjuction with begin_transaction() to commit a prepared statement.
	 * -SQLite databases are by default in autocommit mode.
	 * @uses	$pdo 	to indicate a database.
	 * @param	PDO_obj	$pdo
	 */
	function commit($pdo)
	{
		if ($this->in_transaction) {
			$pdo->commit();
			$this->in_transaction = false;
		}
	}
	
	/**
	* Begin a transaction.
	*
	* -Used to begin an SQL transaction. Use commit() to commit the transaction.
	* @uses 	$pdo 	to indicate a database.
	* @param	PDO_obj	$pdo
	*/
	function begin_transaction($pdo)
	{
		if ( ! $this->in_transaction ) {
			$pdo->beginTransaction();

			$this->in_transaction = true;
		}
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
	          FUNCTIONS FOR SQLITE TABLE/ELEMENT MODIFICATION

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
	* -Note: $array_of_tables_schemas_and_elements is of the same format as the return of export_database(). 
	* @uses 	$pdo 	to indicate a database to import into.
	* @uses 	$array_of_tables_schemas_and_elements 	to pass the data.
	* @param	PDO_obj	$pdo
	* @param	array	$array_of_tables_schemas_and_elements
	*
	*Example array input:
	*	$array_of_tables_schemas_and_elements
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
	function import_database( $pdo, $array_of_tables_schemas_and_elements )
	{
		$result = false;
		foreach($array_of_tables_schemas_and_elements as $table => $values){
			$result = $this->import_table($pdo,$table,$values);
			if($result == 0) break;
		}
		return ( ($result != false) ? 1 : 0);
	}

	/**
	* Export several or all tables in a database.
	*
	* -The return array can be imported into another database using import_database().
	* @uses 	$pdo 	to indicate a database to export.
	* @uses 	$array_of_tables_to_export	to indicate which tables to export. If left as null, all tables will be exported.
	* @param	PDO_obj	$pdo
	* @param 	array $array_of_tables_to_export
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
	*/

	function delete_database($database_rel_path_name){
		$result = false;
		if(file_exists($database_rel_path_name)){
			$result = unlink($database_rel_path_name);
		}
		return ( ($result != false) ? 1 : 0);
	}

	/*
	* Import a table into a database.				//FIXME Make it optional to import into an existing table?
	*
	* -This imports an array of elements into a new table.
	* -If the table already exists this will fail.
	* @uses 	$pdo 	to indicate a database.
	* @uses 	$table_name 	to indicate a name for the new table.
	* @uses 	$array_of_schema_and_elements to pass the data.	
	* @param	PDO_obj	$pdo
	* @param	string	$table_name
	* @param 	array	$array_of_schema_and_elements
	*
	* Example of array input:
	*	$array_of_schema_and_elements
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

	function import_table( $pdo, $table_name, $array_of_schema_and_elements)
	{
		$schema = $array_of_schema_and_elements['schema'];
		$result = $this->create_table($pdo, $table_name, $schema);
		if($result){ 
			$data = $array_of_schema_and_elements['data'];
			foreach($data as $row){
				$result = $this->update($pdo, $table_name, $row);
				if($result == 0) break;
			}
		}

		return ( ($result != false) ? 1 : 0);
	}
	
	/*
		return $array_of_schema_and_elements
	*/
	function export_table( $pdo, $table_name, $order_by = 'id', $limit_start=0, $nrows=999)
	{

		$query = "SELECT * FROM $table_name ORDER BY $order_by LIMIT $nrows OFFSET $limit_start"; 	
		$table = $pdo->query($query);

		$array_of_schema_and_elements = array();
		if($table){

			$record_array = $table->fetchAll(PDO::FETCH_ASSOC);
			
			$schema = $this->get_table_schema($pdo, $table_name);

			$array_of_schema_and_elements['schema'] = $schema;
			$array_of_schema_and_elements['data'] = $record_array;
		}

		return $array_of_schema_and_elements;
	}
	/*
		$elements is a schema array: 
			$elements = array(
				'id' => INTEGER PRIMARY KEY,
				'name' => TEXT,
				...
			);
	*/
	function create_table( $pdo, $name, $elements = array() )
	{
		$result = false;
		$elements['id'] = "INTEGER PRIMARY KEY";				//force an id element
		if ($this->table_name_is_valid($pdo, $name)) {
			$element_string_array = array();
			foreach($elements as $key => $type){
				$element_string_array[] = $key . " " . $type;
			}
			$element_string = implode(",",$element_string_array);
			$query = "CREATE TABLE IF NOT EXISTS $name ($element_string)";
			$result = $pdo->exec($query);
		}

		return ( ($result !== false) ? 1 : 0);
	}

	function delete_table( $pdo, $table_name )
	{
		$query = "DROP TABLE IF EXISTS $table_name";
		$result = $pdo->exec($query);
		return ( ($result !== false) ? 1 : 0);
	}

	function rename_table( $pdo, $table_name, $new_name )
	{
		$query = "ALTER TABLE $table_name RENAME TO $new_name";
		$result = $pdo->exec($query);
		return ( ($result !== false) ? 1 : 0);
	}

	/* 
		Should we assume connection with $new_pdo or should we connect to it?

		Currently assuming connection.
		Also this does not remove the table from the original database.
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

	function table_name_is_valid($pdo, $table_name){
		$result = false;
		$pattern = '/^[a-zA-Z][a-zA-Z0-9_]*$/';

		if(preg_match($pattern,$table_name)){
			$result = true;
		}
		
		return $result;
	}
	
	/*------------------------------------------------------------------*\
		Element methods
	\*------------------------------------------------------------------*/
	function element_type_is_valid( $element_type )
	{
		static $valid_types = array("numeric"=>1, "text"=>1, "blob"=>1, "interger primary key"=>1 );

		return $valid_types[ $element_type ]===1;
	}

	function element_name_is_valid( $element_name )
	{
		$result = false;
		$pattern = '/^[a-zA-Z][a-zA-Z0-9_]*$/';

		if(preg_match($pattern,$element_name)){
			$result = true;
		}
		return ( ($result != false) ? 1 : 0);
	}

	function add_element( $pdo, $table_name, $element_name, $element_type = 'text')
	{
		$result = false;

		if ($this->element_name_is_valid( $element_name ) && $this->element_type_is_valid( $element_type ) ) {
			$result = $pdo->exec("ALTER TABLE $table_name ADD $element_name $element_type");
			if($result !== false){ $result = true; }
		}

		return ( ($result != false) ? 1 : 0);
	}

	function delete_element( $pdo, $table_name, $element_name)
	{
		$result = $this->alter_element($pdo, $table_name, $element_name);
		return $result;
	}

	function rename_element( $pdo, $table_name, $element_name, $new_name ) 
	{
		$result = $this->alter_element($pdo, $table_name, $element_name, $new_name);
		return $result;
	}


	function alter_element( $pdo, $table_name, $element_name, $new_name = null )
	{
		$result = false;
		$backup_table_name = $table_name.'_backup';

		$schema = $this->get_table_schema($pdo, $table_name);
		if($new_name == null){
			unset($schema[$element_name]);
			$result = true;
		} else {
			if(!$schema[$new_name]){
				$new_schema = array();
				foreach($schema as $key => $value){
					if($key == $element_name){
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
							$row[$new_name] = $row[$element_name];
						}
						unset($row[$element_name]);
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

	/*
		$record_array 
			an array of elements and values.  This can be all elements in the table's schema
			or just one or two elements.
			
			eg.	array(
					'id' 	=> 0,
					'name'	=> bill,
					'pay' 	=> ,
					'looks'	=> 'bad'
				)

		return is 'id' of row
			
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
					$this->add_element($pdo,$table_name,$key);
				}
			}

			$result = $pdo->exec($query_string);
		}
		return ( ($result != false) ? $pdo->lastinsertid() : 0);


	}
	

	/*
		$stuff = $this->delete_table($pdo, 'foobar');
		echo  $stuff;
		return is array -- either row of table's record requested or an empty array, if not found
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

	/*
		return is array -- either row of table's record requested or an empty array, if not found
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

	function delete($pdo, $table_name, $id)
	{
		$result = $pdo->exec("DELETE FROM $table_name WHERE id = $id");
		return ( ($result != false) ? 1 : 0);

	}

	function delete_by_query($pdo, $table_name, $query)
	{
		$result = $pdo->exec("DELETE FROM $table_name WHERE $query");
		return ( ($result != false) ? 1 : 0);

	}
	

	/*------------------------------------------------------------------*\
		utility functions
	\*------------------------------------------------------------------*/

	function escape($str)
	{
		return $this->pdo->quote($str);
	}

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

	function get_table_schema($pdo, $table_name){

		$table_info = $pdo->query("PRAGMA table_info($table_name)");

		$schema = array();
		foreach($table_info as $row){
			$schema[$row['name']] = $row['type'];
		}

		return $schema;
	}


	function build_sql_insert_or_replace_into_string($table, $row)
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

		return "INSERT OR REPLACE INTO $table (".join(', ',$names).") VALUES (".join(', ', $values).")";
	}
}
