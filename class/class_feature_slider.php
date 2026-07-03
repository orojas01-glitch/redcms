<?php
/**
 * Red Sphere - Unique php CMS
 * @version: 1.0 - (2012/02/25)
 * @version: 2.0 - (2014/02/25) 
 * @version: 3.0 - (2015/04/7)
 * @version: 4.0 - (2025/03/06)
 * @PHP 5.5.0
 * @author Oscar Rojas
 * Examples and documentation @: http://red-sphere.com/
 * Licensed under MIT licence:
 *   http://www.opensource.org/licenses/mit-license.php
**/

#[\AllowDynamicProperties]
class feature_slider

{
	public function slider ()

	{

		$tquery = new Build_Query();
		$rquery=$tquery->get_query();
		$this->query=$rquery[0];
		$this->VarPosition=$rquery[1];
		$this->VarFeatures=$rquery[2];	
		$this->metaquery=$rquery[3];
		$this->Table=$rquery[4];	
        
        if (empty($this->Table)) {
            // Optionally, log an error or perform an alternative action here
            return;
        }
		//

		if ($this->Table!='Articles'){

			//echo "SELECT Features FROM RED_".$this->Table." WHERE Active='Y' AND Language='" . language . "' ".$this->metaquery." LIMIT 1";

			$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);

			$result = $db->query("SELECT Features FROM RED_".$this->Table." WHERE Active='Y' AND Language='" . language . "' ".$this->metaquery." LIMIT 1");


			//echo ($result->num_rows);
			$result_counter = $result->num_rows;
			while($row = mysqli_fetch_assoc($result))
			{

			$Features=$row['Features'];

			//echo $Features;

			$Features = explode(",", $Features);

			//echo(count($Features.'<br/>'));

	

				for ($w=0; $w<count($Features); $w++)

				{

					//echo($Features[$w].'<br/>');

					switch ($Features[$w])

					{

					case 'slider': //unique name



						echo('<div class="container"><div class="row"><article class="col-lg-12 col-md-12 col-sm-12 slider"><div class="camera_wrap">');

						$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);

						$result = $db->query("SELECT * FROM RED_Articles WHERE Active='Y' AND Language='".language."' AND ".$this->VarFeatures." LIKE '%slider%' ORDER BY ".$this->VarFeatures."_Order ASC limit 5");

						

						//echo ($result->num_rows);

						$result_counter = $result->num_rows;

						while($row = mysqli_fetch_assoc($result))

							{

							//CHECK DATE EXPIRATION//

							if ($row['ExpDate']!='0000-00-00 00:00:00'){

								date_default_timezone_set('America/New_York');

								if( date($row['ExpDate']) < date('Y-m-d H:i:s', mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y"))) ) {

									$ActiveDate=false;

								}

								else {

									 $ActiveDate=true;

								}

				

							} else {

								$ActiveDate=true;

							}

								

								if ($ActiveDate) {	

									$Title = $row['Title'];

									$Link = $row['Link'];
                                    $targetAttr = isset($target) ? $target : '_self';
									if ($row['NewWindow']==='Y')
									$targetAttr='_blank';	

									$BigPict = $row['BigPict'];

									

																		

									// IF THERE IS NO EXTERNAL LINK, THEN CHECK FIRST ARTICLE, SUBCATEGORY, CATEGORY, SECTION.

									if ($row['LongDesc']) // IF THERE IS CONTENT, LINK TO THE FULL CONTENT PAGE.

									$Link=$row['Alias'];

									if ($row['SubCategories'])

									$Link=$row['SubCategories'].'/'.$Link;

									if ($row['Categories'])

									$Link=$row['Categories'].'/'.$Link;

									if ($row['Sections']!='home')

									$Link=$row['Sections'].'/'.$Link;

									else

									$Link='/'.$Link;

									

									// ADD LANGUAGE.

									//$Link='/'.language.'/'.$Link;

									

									// GET THE LINK FOR THIS LABEL. FIRST, CHECK FOR EXTERNAL LINKS.

								

									if ($row['Link'])

									$Link=$row['Link'];

									echo('<div data-src="images/articles/'.$BigPict.'" title="'.$row['Title'].'"><div class="camera-caption fadeIn"></div><div class="camera_caption"><p>'.$row['SliderDesc'].'</p><a href="'.$Link.'" target="'.$targetAttr.'" class="btn-default btn2">leer más</a></div></div>');

									

									$result_counter = ($result_counter - 1);

								}

							}

							


						echo('</div></article></div></div>');

						

						

					break;

					}

				}

			}

		}

	}

	

	/////////////////////////////////////////////////////////////////////////////////////////////////////////////

	//////////////////////////////////////////////CONTROL PANEL//////////////////////////////////////////////////

	/////////////////////////////////////////////////////////////////////////////////////////////////////////////

	/////////////////////////////////////////////////////////////////////////////////////////////////////////////

	

	public function cp_slider ()

	{

		$tquery = new Build_Query();
		$rquery=$tquery->get_query();
		$this->query=$rquery[0];
		$this->VarPosition=$rquery[1];
		$this->VarFeatures=$rquery[2];	
		$this->metaquery=$rquery[3];
		$this->Table=$rquery[4];

        if (empty($this->Table)) {
            // Optionally, log an error or perform an alternative action here
            return;
        }
		//echo $this->VarPosition.'<br/>';
		//echo $this->query;
		//echo $this->metaquery.'<br/>';
		//echo $this->Table.'<br/>';
		//
		

		if ($this->Table!='Articles'){

			//echo "SELECT Features FROM RED_".$this->Table." WHERE Active='Y' AND Language='" . language . "' ".$this->metaquery." LIMIT 1";
            
			$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
			$result = $db->query("SELECT Features FROM RED_".$this->Table." WHERE Active='Y' AND Language='" . language . "' ".$this->metaquery." LIMIT 1");

			$result_counter = $result->num_rows;

			while($row = mysqli_fetch_assoc($result))

			{

			$Features=$row['Features'];

			//echo $Features;

			$Features = explode(",", $Features);

			//echo(count($Features.'<br/>'));

	

				for ($w=0; $w<count($Features); $w++)

				{

					//echo($Features[$w].'<br/>');

					switch ($Features[$w])

					{

					case 'slider': //unique name

						//echo ('<article class="col-lg-12 col-md-12 col-sm-12">');

						echo ('<div class="container_12 cp_padtop">');

						//echo ('<div class="wrapper">');

						echo ('<article class="grid_12 cp_admin" style="text-align:center">');

						$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);

						$result = $db->query("SELECT * FROM RED_Articles WHERE Active='Y' AND Language='".language."' AND ".$this->VarFeatures." LIKE '%slider%' ORDER BY ".$this->VarFeatures."_Order ASC limit 5");

						//echo ($result->num_rows);

						$result_counter = $result->num_rows;
                        $Alias = ''; 
						while($row = mysqli_fetch_assoc($result))

							{

							$Alias = $row['Alias'];

							$Alias=preg_replace('/-/','_',$Alias);

							$BigPict = $row['BigPict'];

							

							echo '<div style="float:left; padding-right:2px; margin-right:2px;">';

							echo '<img src="/images/resize.php?w=57&h=41&amp;img=/images/articles/'.$BigPict.'" title="'.$row['Title'].'"><br/>';

							echo '</div>';

							

							$result_counter = ($result_counter - 1);

							

							}

						echo ('<div class="clear"></div>');	

						echo '<script type="text/javascript">'. "\n";

						echo '<!--' ."\n";

					

						echo 'function edit_feature_slider_'.$Alias.' (slider_'.$Alias.')'. "\n".'{' . "\n"; 

								

						//echo 'alert (dataString);'. "\n";

						//echo 'return false;'. "\n";

						

						echo '$.ajax({ '. "\n";

						echo'type: "POST", '. "\n";

						echo 'url: "/admin/bin/edit_feature_slider.php", '. "\n";

						echo 'cache: false,'. "\n"; 

						//echo 'data: dataString, '. "\n";

						echo 'data: $("#slider_'.$Alias.'").serialize(), '. "\n";

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

					

						echo '<form id="slider_'.$Alias.'" class="form" name="slider_'.$Alias.'" method="post" onSubmit="return edit_feature_slider_'.$Alias.'(this);">';

						echo '<input type="submit" name="Edit" id="cp" value="Edit Slider"/>';

						echo '<input type="hidden" name="VarFeatures" id="VarFeatures" value="'.$this->VarFeatures.'" />';

						echo '<input type="hidden" name="Query" id="Query" value="'.$this->query.'" />';

						echo '<input type="hidden" name="Language" id="Language" value="'.language.'" />';

						echo '</form>';

						

						echo ('</article>');	

						//echo ('</div>');

						echo ('</div>');

						//echo ('</article>');

					break;

					}

				}

			}

		}

		

		

	}

}

?>
