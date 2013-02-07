<?
include 'dynamic_sqlite.php';
//Initiate dynamic_sqlite class.
	$dyna = new dynamic_sqlite();

//Open database.
	$pdo = $dyna->open_database('test.sqlite');

//Create a table.
	$dyna->create_table($pdo,'table1');

//Enter data into the table;
	$row = array('id'=>0, 'name'=>'Stu', 'age'=> 3, 'stuff'=>'none');
	//We make strict=true which will fail since we havn't entered the columns 'name' and 'age' (id is put in by default).
	$result = $dyna->update($pdo, 'table1', $row, true);
	echo $result ? "Add row with strict=true succeeded.<br>" : "Add row with strict=true failed.<br>";

	//By ignoring the strict it will succeed and return true. Notice the column type will default to 'TEXT' when we don't add a column manually.
	$result = $dyna->update($pdo, 'table1', $row);
	echo $result ? "Add row with strict=false succeeded.<br>" : "Add row with strict=false failed.<br>";

	//Enter another item, without an entry for one of the columns. We can set strict=true since we know all columns are represented.
	$row = array('id'=>0, 'name'=>'Suzy');
	$result = $dyna->update($pdo, 'table1', $row, true);
	echo $result ? "Add row with strict=true succeeded.<br>" : "Add row with strict=true failed.<br>";

	$table1 = $dyna->export_table($pdo,'table1');
	echo "<pre>Table1:";
	print_r($table1);
	echo "</pre>";

//Rename the table.
	echo "Rename table1 to table2:<br>";
	$dyna->rename_table($pdo, 'table1', 'table2');
	$table1 = $dyna->export_table($pdo, 'table1');
	$table2 = $dyna->export_table($pdo, 'table2');

	echo "<pre>Table1:";
	print_r($table1);
	echo "</pre>";
	echo "<pre>Table2:";
	print_r($table2);
	echo "</pre>";

//Copy table to a new database. This uses export_table() and import_table(), thus a table that is exported can be imported as-is.
	$pdo2 = $dyna->open_database('test2.sqlite');
	$dyna->copy_table_to_other_sqlite_dbase($pdo, 'table2', $pdo2);

	$table2 = $dyna->export_table($pdo2, 'table2');
	echo '<pre>Table2 in pdo2:';
	print_r($table2);
	echo "</pre>";

//Delete a row, rename an entry, change an entry, add an entry, and delete another entry.
	$result = $dyna->delete($pdo2,'table2', 2);
	$dyna->rename_element($pdo2, 'table2', 'age', 'failure');
	$dyna->delete_element($pdo2, 'table2', 'stuff');
	
	//Update 'failure' from 3 to 10 and add the entry 'email'.
	$row = array('id'=>1, 'name'=>'stu', 'failure'=> 10, 'email' => 'stu@gmail.com');
	$dyna->update($pdo2,'table2', $row);

	$table2 = $dyna->export_table($pdo2, 'table2');
	echo '<pre>Table2 with deleted row (id=2),<br> renamed element (age to failure),<br> changed value (failure = 10),<br> added entry (email)<br> and deleted element (stuff):';
	print_r($table2);
	echo "</pre>";

//Move database, export and import.
	$pdo3 = $dyna->move_database('test2.sqlite','test3.sqlite');

	$db3 = $dyna->export_database($pdo3);
	$pdo4 = $dyna->open_database('test4.sqlite');
	$dyna->import_database($pdo4, $db3);

	echo '<pre>db3:';
	print_r($db3);
	echo "</pre>";
	$table2 = $dyna->export_table($pdo4, 'table2');
	echo '<pre>table2 in db4:';
	print_r($table2);
	echo "</pre>";

//delete databases
$dyna->delete_database('test.sqlite');
$dyna->delete_database('test2.sqlite');
$dyna->delete_database('test3.sqlite');
$dyna->delete_database('test4.sqlite');
