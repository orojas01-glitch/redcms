<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
red_require_admin(true); ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php' ?>
<?php
if(empty($_SESSION['alias']))
	header('Location: http://'.BASE_URL.'');
	else {
	$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
	
	$x = 0;
	foreach($_POST as $name => $value)
	{
		//
		$name = preg_replace ( "'<[^>]+>'U", "", $name);
		
		switch ($name)
		{
			case 'RecordID':
				$RecordID=mysqli_real_escape_string($db->connection,$value);
			break;
			case 'CSS':
				$CSS=$value;
			break;
			case 'jumpCSS':
				$jumpCSS=$value;
			break;
			case 'Item':
				$Item=mysqli_real_escape_string($db->connection,$value);
			break;
			case 'ShortLine':
				$value = mysqli_real_escape_string($db->connection,$value);
				if ($x===0)
				$queryset = "Content='".$value."'";
				else
				$queryset = $queryset . ", Content='".$value."'";
				$x++;
			break;
			default:
				$value = mysqli_real_escape_string($db->connection,$value);
				if ($x===0)
				$queryset = $name . "='".$value."'";
				else
				$queryset = $queryset . ", ".$name . "='".$value."'";
				$x++;
			break;
		}
	
	}

	if (empty($Item)) {
		echo 'no';
		$db->close();
		exit;
	}
	
	switch ($Item)
	{
		case 'Website_CSS':
			if (empty($jumpCSS)) {
				echo 'no';
				$db->close();
				exit;
			}
			file_put_contents('../../css/'.$jumpCSS, $CSS);
			echo 'yes';
		break;
		case 'Reload':
			if (empty($jumpCSS)) {
				echo 'no';
				$db->close();
				exit;
			}
			$CSS = file_get_contents('../../css/'.$jumpCSS, true);
			echo $CSS;
		break;
		default:
			if (empty($RecordID) || empty($queryset)) {
				echo 'no';
				$db->close();
				exit;
			}
			//echo "UPDATE RED_Advanced SET ".$queryset." WHERE RecordID='".$RecordID."'";
			if ($result = $db->update("UPDATE RED_Advanced SET ".$queryset." WHERE RecordID='".$RecordID."'"))
				echo 'yes';
			else
				echo 'no';
			$db->close();
		break;
	}
	
	
}
?>
