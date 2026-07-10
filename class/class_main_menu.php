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
require_once __DIR__ . '/../includes/public_render_helpers.php';

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
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		$rows = red_public_menu_rows($db->connection);
		$db->close();

		if (!$rows) {
			return;
		}

		$children = [];
		$roots = [];
		foreach ($rows as $row) {
			if ((string) $row['RootOrder'] === '1') {
				$roots[] = $row;
			}
			$children[(string) $row['Parent']][] = $row;
		}

		if (!$roots) {
			return;
		}

		echo('<nav class="navbar navbar-default navbar-static-top tm_navbar clearfix" role="navigation"><ul class="nav sf-menu clearfix">');
		foreach ($roots as $index => $row) {
			$this->RecordID = $row['RecordID'];
			$this->Label = $row['Label'];
			$this->Link = $row['Link'];
			$this->NewWindow = $row['NewWindow'];
			$this->active = '';

			$GetCurSection=explode("/", $this->Link);
			$this->Section = isset($GetCurSection[1]) ? $GetCurSection[1] : '';
            $this->Article = isset($GetCurSection[2]) ? $GetCurSection[2] : '';

			if (strtolower(section)===strtolower($this->Section)) {
				$this->active='active';
			} elseif (countpage <= 2 && $index === 0 && strtolower(article)===strtolower($this->Article)) {
				$this->active='active';
			}

			$secondLevel = array_values(array_filter($children[(string) $this->RecordID] ?? [], function ($child) {
				return (string) $child['RootOrder'] !== '1';
			}));

			$liClass = $secondLevel ? 'sub-menu ' . $this->active : $this->active;
			echo('<li class="' . red_public_html($liClass) . '">');
			if(strtolower($this->Label)=='inicio') {
				echo('<a href="' . red_public_html($this->Link) . '" target="' . red_public_html($this->NewWindow) . '"><i>Home</i><em></em>');
				if ($secondLevel) {
					echo('<span></span>');
				}
				echo('</a>');
			} else {
				echo('<a href="' . red_public_html($this->Link) . '" target="' . red_public_html($this->NewWindow) . '">' . red_public_html($this->Label));
				if ($secondLevel) {
					echo('<span></span>');
				}
				echo('</a>');
			}

			if ($secondLevel) {
				echo('<ul class="submenu">');
				foreach ($secondLevel as $child) {
					$this->RecordID = $child['RecordID'];
					$this->SubLabel = $child['Label'];
					$this->SubLink = $child['Link'];
					$this->SubNewWindow = $child['NewWindow'];

					$thirdLevel = array_values(array_filter($children[(string) $this->RecordID] ?? [], function ($grandchild) {
						return (string) $grandchild['RootOrder'] !== '1' && (string) $grandchild['RootOrder'] !== '2';
					}));

					if ($thirdLevel) {
						echo('<li><a href="' . red_public_html($this->SubLink) . '" target="' . red_public_html($this->SubNewWindow) . '">' . red_public_html($this->SubLabel) . '<span></span></a>');
						echo('<ul class="submenu">');
						foreach ($thirdLevel as $grandchild) {
							echo('<li><a href="' . red_public_html($grandchild['Link']) . '" target="' . red_public_html($grandchild['NewWindow']) . '">' . red_public_html($grandchild['Label']) . '</a></li>');
						}
						echo('<li class="tr"></li></ul></li>');
					} else {
						echo('<li><a href="' . red_public_html($this->SubLink) . '" target="' . red_public_html($this->SubNewWindow) . '">' . red_public_html($this->SubLabel) . '</a></li>');
					}
				}
				echo('</ul>');
			}

			echo('</li>');
		}
		echo('</ul></nav>');
	}
	
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	//////////////////////////////////////////////CONTROL PANEL//////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	
	public function cp_menu()
	{
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		$row = red_public_main_menu_root($db->connection);
		$db->close();
		$RecordID = isset($row['RecordID']) ? (int) $row['RecordID'] : 0;
		$Title = isset($row['Title']) ? (string) $row['Title'] : '';

		echo ('<article class="col-lg-12 col-md-12 col-sm-12">');
		echo ('<div class="container_12 cp_padtop">');
		echo ('<div class="wrapper">');
		echo ('<article class="grid_12 cp_admin" style="text-align:center">');
		
		//echo ('result main nav='.$result_counter.'<br/>');
		if($Title=='')
		{
			$Alias='Menu';
			$Title='Menu';
		} else {
			$Alias=$Title;
			$Alias=preg_replace('/ /','_',$Alias);
			$Alias=preg_replace('/-/','_',$Alias);
			$Alias=preg_replace('/[^A-Za-z0-9_]/','_',$Alias);
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

				echo '<form id="main_menu_'.red_public_html($Alias).'" class="form" name="main_menu_'.red_public_html($Alias).'" method="post" onSubmit="return edit_main_menu_'.$Alias.'(this);">';

				echo '<h7 id="cp"> '.red_public_html($Title).'</h7><br/><input type="submit" name="Edit" id="cp" value="Edit"/>';
				echo '<input type="hidden" name="RecordID" id="RecordID" value="'.red_public_html($RecordID).'" />';
				echo '</form>';
				
			//echo('<br clear="all">');
		
		
		echo ('</article>');	
		echo ('</div>');
		echo ('</div>');
		echo ('</article>');
		
	}
	
}
?>
