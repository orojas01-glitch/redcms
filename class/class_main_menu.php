<?php
/**
 * Red Sphere - Unique php CMS
 * @version: 1.0 - (2012/02/25)
 * @version: 2.0 - (2014/02/25)
 * @version: 4.0 - (2025/03/06)
 * @requires linux v1.2.2 or later 
 * @author Oscar Rojas
 * Examples and documentation at: http://red-sphere.com/red-cms/documentation/ 
 * Licensed under MIT licence:
 *   http://www.opensource.org/licenses/mit-license.php
**/

/**
* THIS CLASS CONSTRUCT THE MAIN MENU. 2 LEVELS OF BUTTONS.
**/
/*<nav class="navbar navbar-default navbar-static-top tm_navbar clearfix" role="navigation">
	<ul class="nav sf-menu clearfix">
		
		<li class="active"><a href="index.html"><i>Home</i><em></em></a></li>
		<li class="sub-menu"><a href="index-1.html">about us<span></span></a>
		
			<ul class="submenu">
				<li><a href="#">Dolore ipsu</a></li>
				<li><a href="#">Consecte</a></li>
				<li><a href="#">Elit Conseq <span></span></a>
					<ul class="submenu">
						<li><a href="#">Dolore ipsu</a></li>
						<li><a href="#">Consecte</a></li>
						<li><a href="#">Elit Conseq</a></li>
						<li class="tr"></li>
					</ul>
				</li>
			</ul>
		</li>
		<li><a href="index-2.html">lessons</a></li>
		<li><a href="index-3.html">blog</a></li>
		<li><a href="index-4.html">contacts</a></li>
	</ul>
</nav>

<nav class="navbar navbar-default navbar-static-top tm_navbar clearfix" role="navigation">
	<ul class="nav sf-menu clearfix">
		<li class="active"><a href="/" target="">Home</a></li>
		<li class="sub-menu "><a href="/clases/" target="">Clases<span></span></a>
			<ul class="submenu">
				<li><a href="/products/" target="">Web Design<span></span></a>
					<ul class="submenu">
						<li><a href="/products/" target="">Business Package</a></li>
						<li class="tr"></li>
					</ul>
				</li>
				<li><a href="/clases/piano" target="">Piano<span></span></a>
					<ul class="submenu">
						<li><a href="/clases/piano/clases-piano-adultos" target="">Adultos</a></li>
						<li><a href="/clases/piano/clases-piano-ninos" target="">Niños</a></li>
						<li class="tr"></li>
					</ul>
				</li>
				<li><a href="/products/" target="">Marketing</a></li>
				<li><a href="/products/" target="">Audio Production</a></li>
			</ul>
		</li>
		<li class="sub-menu ">
		<a href="/portfolio/" target="">Portfolio<span></span></a>
		<ul class="submenu">
		<li><a href="/portfolio/" target="">Web Design</a></li>
		<li><a href="/portfolio/" target="">Graphic Design</a></li>
		<li><a href="/portfolio/" target="">Audio Production</a></li>
		</ul>
		</li>
		<li class=""><a href="/contact/" target="">contact</a></li>
	</ul>
</nav>
*/

#[\AllowDynamicProperties]
class main_menu
{
    public $RecordID;
	public function menu()
	{
		
		/////FIRST LEVEL/////
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		$result = $db->query("SELECT * FROM RED_Menu WHERE Language='".language."' AND RootOrder='1' AND Active='Y' ORDER BY MenuOrder ASC");

		$result_counter = $result->num_rows;
		while($row = mysqli_fetch_assoc($result))
		{
			if ($result_counter == $result->num_rows)
			echo('<nav class="navbar navbar-default navbar-static-top tm_navbar clearfix" role="navigation"><ul class="nav sf-menu clearfix">');
			else
			$this->RecordID = $row['RecordID'];
			$this->Label = $row['Label'];
			$this->Link = $row['Link'];
			$this->NewWindow = $row['NewWindow'];
			$this->active='';
			

			$GetCurSection=explode("/", $this->Link);
			$this->Section = isset($GetCurSection[1]) ? $GetCurSection[1] : '';
            $this->Article = isset($GetCurSection[2]) ? $GetCurSection[2] : '';

			
			if (strtolower(section)===strtolower($this->Section))
				$this->active='active';
				//echo('<li class="active"><a href="'.$this->Link.'" target="'.$this->NewWindow.'">' .$this->Label . '</a>');
			else
				
				if (countpage <= 2 && $result_counter == $result->num_rows){
					if (strtolower(article)===strtolower($this->Article))
					$this->active='active';
					//echo('<li class="active"><a href="'.$this->Link.'" target="'.$this->NewWindow.'">' .$this->Label . '</a>');
				}else
					$this->active='';
					//echo('<li><a href="'.$this->Link.'" target="'.$this->NewWindow.'">' .$this->Label . '</a>');
					
					/////SECOND LEVEL/////
					$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
					$result2 = $db->query("SELECT * FROM RED_Menu WHERE Language='" . language . "' AND Parent='" . $this->RecordID . "' AND RootOrder <> '1' AND Active='Y' ORDER BY MenuOrder ASC");
					$result2_counter = $result2->num_rows;
					if ($result2->num_rows>0){ 
					if(strtolower($this->Label)=='inicio')
					echo('<li class="sub-menu '.$this->active.'"><a href="'.$this->Link.'" target="'.$this->NewWindow.'"><i>Home</i><em></em><span></span></a>');
					else
					echo('<li class="sub-menu '.$this->active.'"><a href="'.$this->Link.'" target="'.$this->NewWindow.'">' .$this->Label . '<span></span></a>');
					while($row = mysqli_fetch_assoc($result2))
						{
							//echo 'ini'. $result_counter;
							$this->RecordID = $row['RecordID'];
							$this->SubLabel = $row['Label'];
							$this->SubLink = $row['Link'];
							$this->SubNewWindow = $row['NewWindow'];
							
							if ($result2_counter == $result2->num_rows){
								echo('<ul class="submenu">');
								
								/////THIRD LEVEL/////
								$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
								$result3 = $db->query("SELECT * FROM RED_Menu WHERE Language='" . language . "' AND Parent='" . $this->RecordID . "' AND RootOrder <> '1' AND RootOrder <> '2' AND Active='Y' ORDER BY MenuOrder ASC");
								//echo ($result3->num_rows);
								$result3_counter = $result3->num_rows;
								if ($result3->num_rows>0){ 
								echo('<li><a href="'.$this->SubLink.'" target="'.$this->SubNewWindow.'">' .$this->SubLabel . '<span></span></a>');
								while($row = mysqli_fetch_assoc($result3))
									{
										//echo 'ini'. $result_counter;
										$this->RecordID = $row['RecordID'];
										$this->SubLabel = $row['Label'];
										$this->SubLink = $row['Link'];
										$this->SubNewWindow = $row['NewWindow'];
										
										if ($result3_counter == $result3->num_rows)
											if ($result3_counter == 1)
												echo('<ul class="submenu"><li><a href="'.$this->SubLink.'" target="'.$this->SubNewWindow.'">' .$this->SubLabel . '</a></li><li class="tr"></li>');
											else
											echo('<ul class="submenu"><li><a href="'.$this->SubLink.'" target="'.$this->SubNewWindow.'">' .$this->SubLabel . '</a></li>');
										else {
											if ($result3_counter == 1)
											echo('<li><a href="'.$this->SubLink.'" target="'.$this->SubNewWindow.'">' .$this->SubLabel . '</a></li><li class="tr"></li>');
											else
											echo('<li><a href="'.$this->SubLink.'" target="'.$this->SubNewWindow.'">' .$this->SubLabel . '</a></li>');
										}
										$result3_counter = ($result3_counter - 1);
										//echo 'end'. $result2_counter;
										if ($result3_counter == 0)
										echo('</ul></li>');	
									}
								}else
								echo('<li><a href="'.$this->SubLink.'" target="'.$this->SubNewWindow.'">' .$this->SubLabel . '</a></li>');
								/////END THIRD LEVEL/////
								
							}else {
								if ($result2_counter == 1){
								
									
									/////THIRD LEVEL/////
									$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
									$result3 = $db->query("SELECT * FROM RED_Menu WHERE Language='" . language . "' AND Parent='" . $this->RecordID . "' AND RootOrder <> '1' AND RootOrder <> '2' AND Active='Y' ORDER BY MenuOrder ASC");
									//echo ($result3->num_rows);
									$result3_counter = $result3->num_rows;
									if ($result3->num_rows>0){
										echo ('<li><a href="'.$this->SubLink.'" target="'.$this->SubNewWindow.'">' .$this->SubLabel . '<span></span></a>'); 
									while($row = mysqli_fetch_assoc($result3))
										{
											//echo 'ini'. $result_counter;
											$this->RecordID = $row['RecordID'];
											$this->SubLabel = $row['Label'];
											$this->SubLink = $row['Link'];
											$this->SubNewWindow = $row['NewWindow'];
											
											if ($result3_counter == $result3->num_rows)
												if ($result3_counter == 1)
													echo('<ul class="submenu"><li><a href="'.$this->SubLink.'" target="'.$this->SubNewWindow.'">' .$this->SubLabel . '</a></li><li class="tr"></li>');
												else
												echo('<ul class="submenu"><li><a href="'.$this->SubLink.'" target="'.$this->SubNewWindow.'">' .$this->SubLabel . '</a></li>');
											else {
												if ($result3_counter == 1)
												echo('<li><a href="'.$this->SubLink.'" target="'.$this->SubNewWindow.'">' .$this->SubLabel . '</a></li><li class="tr"></li>');
												else
												echo('<li><a href="'.$this->SubLink.'" target="'.$this->SubNewWindow.'">' .$this->SubLabel . '</a></li>');
											}
											$result3_counter = ($result3_counter - 1);
											//echo 'end'. $result2_counter;
											if ($result3_counter == 0)
											echo('</ul></li>');	
										}
									}else
									echo('<li><a href="'.$this->SubLink.'" target="'.$this->SubNewWindow.'">' .$this->SubLabel . '</a></li>');
									/////END THIRD LEVEL/////
								
								}else {
								
									
									/////THIRD LEVEL/////
									$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
									$result3 = $db->query("SELECT * FROM RED_Menu WHERE Language='" . language . "' AND Parent='" . $this->RecordID . "' AND RootOrder <> '1' AND RootOrder <> '2' AND Active='Y' ORDER BY MenuOrder ASC");
									//echo ($result3->num_rows);
									$result3_counter = $result3->num_rows;
									if ($result3->num_rows>0){ 
										echo('<li><a href="'.$this->SubLink.'" target="'.$this->SubNewWindow.'">' .$this->SubLabel . '<span></span></a>');
									while($row = mysqli_fetch_assoc($result3))
										{
											//echo 'ini'. $result_counter;
											$this->RecordID = $row['RecordID'];
											$this->SubLabel = $row['Label'];
											$this->SubLink = $row['Link'];
											$this->SubNewWindow = $row['NewWindow'];
											
											if ($result3_counter == $result3->num_rows)
												if ($result3_counter == 1)
													echo('<ul class="submenu"><li><a href="'.$this->SubLink.'" target="'.$this->SubNewWindow.'">' .$this->SubLabel . '</a></li><li class="tr"></li>');
												else
												echo('<ul class="submenu"><li><a href="'.$this->SubLink.'" target="'.$this->SubNewWindow.'">' .$this->SubLabel . '</a></li>');
											else {
												if ($result3_counter == 1)
												echo('<li><a href="'.$this->SubLink.'" target="'.$this->SubNewWindow.'">' .$this->SubLabel . '</a></li><li class="tr"></li>');
												else
												echo('<li><a href="'.$this->SubLink.'" target="'.$this->SubNewWindow.'">' .$this->SubLabel . '</a></li>');
											}
											$result3_counter = ($result3_counter - 1);
											//echo 'end'. $result2_counter;
											if ($result3_counter == 0)
											echo('</ul></li>');	
										}
									}else
									echo('<li><a href="'.$this->SubLink.'" target="'.$this->SubNewWindow.'">' .$this->SubLabel . '</a></li>');
									/////END THIRD LEVEL/////
									
								}
							}
							$result2_counter = ($result2_counter - 1);
							//echo 'end'. $result2_counter;
							if ($result2_counter == 0)
							echo('</ul></li>');
						}
					} else
					if(strtolower($this->Label)=='inicio')
					echo('<li class="'.$this->active.'"><a href="'.$this->Link.'" target="'.$this->NewWindow.'"><i>Home</i><em></em></a>');
					else
					echo('<li class="'.$this->active.'"><a href="'.$this->Link.'" target="'.$this->NewWindow.'">' .$this->Label . '</a></li>');
					/////END SECOND LEVEL/////
					
					
		$result_counter = ($result_counter - 1);
		
		}
		//echo 'end'. $result_counter;

		if ($result_counter == 0)
		echo('</ul></nav>');
		else
		echo('</li>');
		/////END FIRST LEVEL/////
		
	}
	
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	//////////////////////////////////////////////CONTROL PANEL//////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	
	public function cp_menu()
	{
		//echo "SELECT * FROM RED_C_Menu WHERE RefID='".$recordid."' AND RootOrder='1' ORDER BY MenuOrder ASC<br/>"; 
		
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		$result = $db->query("SELECT * FROM RED_Menu WHERE Language='".language."' AND RootOrder='1' AND Active='Y' ORDER BY MenuOrder ASC LIMIT 1");
		
		$result_counter = $result->num_rows;
		echo ('<article class="col-lg-12 col-md-12 col-sm-12">');
		echo ('<div class="container_12 cp_padtop">');
		echo ('<div class="wrapper">');
		echo ('<article class="grid_12 cp_admin" style="text-align:center">');
		
		//echo ('result main nav='.$result_counter.'<br/>');
		
		while($row = mysqli_fetch_assoc($result))
		{
			
				//echo('edit sub-menu');
				$RecordID=$row['RecordID'];
				$Alias=$row['Title'];
				$Alias=preg_replace('/ /','_',$Alias);
				$Alias=preg_replace('/-/','_',$Alias);
				$Title=$row['Title'];
		}
		if($Title=='')
		{
			$Alias='Menu';
			$Title='Menu';
		}

		
				echo '<script type="text/javascript">'. "\n";
				echo '<!--' ."\n";
			
				echo 'function edit_main_menu_'.$Alias.' (main_menu_'.$Alias.')'. "\n".'{' . "\n"; 
									
					//echo 'alert (dataString);'. "\n";
					//echo 'return false;'. "\n";
					
					echo '$.ajax({ '. "\n";
					echo'type: "POST", '. "\n";
					echo 'url: "/admin/bin/edit_main_menu.php", '. "\n";
					echo 'cache: false,'. "\n"; 
					//echo 'data: dataString, '. "\n";
					echo 'data: $("#main_menu_'.$Alias.'").serialize(), '. "\n";
					echo 'success: function(data) { '. "\n";
					//echo 'alert (data);'. "\n";
					echo 'if (data)'. "\n"; 
					echo '{'. "\n";
					//echo '$(\'#edit_content_grid\').html("<div id=\'message_'.$Alias.'\'></div>");'. "\n";
					echo '$(\'#edit_content_grid\').hide();'. "\n";
					//echo '$(\'#message_'.$Alias.'\').html("<h6>View All.</h6>")'. "\n";
					echo '$(\'#msggbox_edit_content\').html(data)'. "\n";
					echo '.fadeIn(1500, function() {'. "\n";
					echo '$(\'#message_'.$Alias.'\');'. "\n";
					//echo '$(\'#message\').append("<img id=\'checkmark\' src=\'/'.language.'/images/check.png\' />");'. "\n";
					echo '});'. "\n";
					echo '}'. "\n";
					echo 'else'. "\n"; 
					echo '{'. "\n";
					//echo '$(\'#form_'.$Alias.'\').html("<div id=\'message_'.$Alias.'\'></div>");'. "\n";
					echo '$(\'#msggbox_edit_content\').html("error.")'. "\n";
					echo '.append("<p>Please try again.</p>")'. "\n";
					echo '.hide()'. "\n";
					echo '.fadeIn(1500, function() {'. "\n";
					echo '$(\'#message_'.$Alias.'\');'. "\n";
					
					//echo '$(\'#message\').append("<img id=\'checkmark\' src=\'/'.language.'/images/check.png\' />");'. "\n";
					echo '});'. "\n";
					echo '}'. "\n";
					echo '}'. "\n";
					echo '});'. "\n";
					echo 'return false;'. "\n";
					echo '}'. "\n";
					echo '-->'. "\n";
					echo '</script>';

				echo '<form id="main_menu_'.$Alias.'" class="form" name="main_menu_'.$Alias.'" method="post" onSubmit="return edit_main_menu_'.$Alias.'(this);">';

				echo '<h7 id="cp"> '.$Title.'</h7><br/><input type="submit" name="Edit" id="cp" value="Edit"/>';
				echo '<input type="hidden" name="RecordID" id="RecordID" value="'.$RecordID.'" />';
				echo '</form>';
				
			//echo('<br clear="all">');
		
		
		echo ('</article>');	
		echo ('</div>');
		echo ('</div>');
		echo ('</article>');
		
	}
	
}
?>