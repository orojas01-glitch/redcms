<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session(); 

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

?>

<?php
//$compgroup = group name of the tool.  Can be part of the Content or part of the Areas (sections,categories,subcategories)
//$cparea = to identify where to send the html back.  its the id of the div.
#[\AllowDynamicProperties]
class add_tools
{
	public function add_tools_grid($countpage,$Section,$Category,$SubCategory,$Article,$VarPosition,$Language,$layout,$compgroup,$cparea)
	{
/*		echo 'countpage='.$countpage.'<br/>';
		echo 'Section='.$Section.'<br/>';
		echo 'Category='.$Category.'<br/>';
		echo 'SubCategory='.$SubCategory.'<br/>';
		echo 'Article='.$Article.'<br/>';
		echo 'VarPosition='.$VarPosition.'<br/>'; 
		echo 'Language='.$Language.'<br/>'; */
		$cpareastyle=strtolower($cparea);
		
	
?>
<div class="container_12 cp_padtop">
    <div class="wrapper">
    	<?php
        // READ SESSION 'AdminComponents'
        // FOR EACH COMPONENT ADD BUTTON.
        
        $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
        $resultC = $db->query("SELECT * FROM RED_Tools WHERE CompGroup = '".$compgroup."'");
        //echo ($resultC->num_rows);
		while($row = mysqli_fetch_assoc($resultC))
		{
			$UniqueName=$row['UniqueName'];
			//$Layout=$row['Layout'];
			$ButtonTag=$row['ButtonTag'];
			$AltContent=$row['AltContent'];
			
			echo '<script language="JavaScript" type="text/javascript">'. "\n";
			echo '<!--' ."\n";
			echo 'function add_'.strtolower($UniqueName).'_'.$cpareastyle.' (contenttype){'. "\n";
			echo '$.ajax({'. "\n";
			echo 'type: "POST", '. "\n";
			echo 'url: "/admin/bin/tool_'.strtolower($UniqueName).'.php", '. "\n";
			echo 'data: "Type=" + contenttype + "&CountPage='.countpage.'&Section='.$Section.'&Category='.$Category.'&SubCategory='.$SubCategory.'&Article='.$Article.'&VarPosition='.$VarPosition .'&Language='.$Language.'&cparea='.$cparea.'&compgroup='.$compgroup.'&Layout='.$layout.'", '. "\n";
			echo 'success: function(data) { '. "\n";
			echo '/*alert (data);'. "\n";
			echo 'return false;*/'. "\n";
			echo 'if (data) '. "\n";
			echo '{'. "\n";
			echo '$("#tools_'.$cpareastyle.'_grid").hide();'. "\n";
			echo '$("#msggbox_tools_'.$cpareastyle.'").html(data)'. "\n";
			echo '.fadeIn(1500, function() {'. "\n";
			echo '$("#msggbox_tools_'.$cpareastyle.'");'. "\n";
			echo '});'. "\n";
			echo '//alert ("data");'. "\n";
			echo '}'. "\n";
			echo 'else '. "\n";
			echo '{'. "\n";
			echo '$("#msggbox_tools_'.$cpareastyle.'").html("Error. Please try again.")'. "\n";
			echo '.fadeIn(1500, function() {'. "\n";
			echo '$("#msggbox_tools_'.$cpareastyle.'");'. "\n";
			echo '});'. "\n";
			echo '//alert ("no data");'. "\n";
			echo '}'. "\n";
			echo '}'. "\n";
			echo '});'. "\n";
			echo 'return false;'. "\n";
			echo '}'. "\n";
			echo '-->'. "\n";
			echo '</script>';
			echo '<div class="cp_addcontent" id="cp_tools"><a href="#cp_'.$cpareastyle.'" onClick="add_'.strtolower($UniqueName).'_'.$cpareastyle.'(\''.$layout.'\');" title="'.$AltContent.'" class="cp_addcontent_button">'.$ButtonTag.'</a></div>';

		$db->close();
		}
		?>
        
    </div>
</div> 
<?php
	}
}
?>