<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
red_require_admin(true); ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php' ?>
<?php
if(empty($_SESSION['alias']))
	header('Location: http://'.BASE_URL.'');
	else {

	$Language = preg_replace("'<[^>]+>'U", '', $_POST['Language'] ?? '');
	if ($Language === '') {
		echo 'no';
		exit;
	}

	$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
	$createsection=true;
	
	$result = $db->query("SELECT Item FROM RED_Advanced WHERE Language='".$Language."'");
	$result_counter = $result->num_rows;
	
	
	if ($result_counter > 0){
		echo 'error';
		$createsection=false;
	}
	
	
	if($createsection){
	$result2 = $db->query("SELECT * FROM RED_Advanced WHERE Item='Website_Header'"); // copy same to avoid empty
		$result_counter = $result2->num_rows;
		while($row = mysqli_fetch_assoc($result2))
		{
			$Website_Header=$row['Content'];
		}
		if ($result = $db->insert("INSERT INTO RED_Advanced (Item, Content, Language) VALUES ('Website_Title', '', '".$Language."')"))
		echo 'yes';
		if ($result = $db->insert("INSERT INTO RED_Advanced (Item, Content, Language) VALUES ('Website_Slogan', '', '".$Language."')"))
		echo 'yes';
		if ($result = $db->insert("INSERT INTO RED_Advanced (Item, Content, Language) VALUES ('Website_Logo', '', '".$Language."')"))
		echo 'yes';
		if ($result = $db->insert("INSERT INTO RED_Advanced (Item, Content, Language) VALUES ('Website_Footer', '', '".$Language."')"))
		echo 'yes';
		if ($result = $db->insert("INSERT INTO RED_Advanced (Item, Content, Language) VALUES ('Website_Header', '".$Website_Header."', '".$Language."')"))
		echo 'yes';
		if ($result = $db->insert("INSERT INTO RED_Advanced (Item, Content, Language) VALUES ('Website_CSS', '', '".$Language."')"))
		echo 'yes';
	
	}
	$db->close();
}
?>
