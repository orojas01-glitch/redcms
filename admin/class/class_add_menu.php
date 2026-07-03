<?php 
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
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
#[\AllowDynamicProperties]
class add_menu
{
	public function add_menu_grid($countpage,$Section,$Category,$SubCategory,$Article,$VarPosition,$Language,$layout)
	{
/*		echo 'countpage='.$countpage.'<br/>';
		echo 'Section='.$Section.'<br/>';
		echo 'Category='.$Category.'<br/>';
		echo 'SubCategory='.$SubCategory.'<br/>';
		echo 'Article='.$Article.'<br/>';
		echo 'VarPosition='.$VarPosition.'<br/>'; 
		echo 'Language='.$Language.'<br/>'; */
		
	
?>
<div class="container_12 cp_padtop">
    <div class="wrapper">
    	<?php
        // READ SESSION 'AdminComponents'
        // FOR EACH COMPONENT ADD BUTTON.
        $AdminComponents = explode(",", $_SESSION['AdminComponents']);
        //echo($_SESSION['AdminComponents'].'='.count($AdminComponents.'<br/>'));
        for ($w=0; $w<count($AdminComponents); $w++)
        {
        $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
        $resultC = $db->query("SELECT * FROM RED_Components WHERE RecordID='".$AdminComponents[$w]."'");
        //echo ($resultC->num_rows);
		while($row = mysqli_fetch_assoc($resultC))
		{
			$UniqueName=$row['UniqueName'];
			$Layout=$row['Layout'];
			$ButtonTag=$row['ButtonTag'];
			
			echo '<script language="JavaScript" type="text/javascript">'. "\n";
			echo '<!--' ."\n";
			echo 'function add_'.strtolower($UniqueName).' (contenttype){'. "\n";
			echo '$.ajax({'. "\n";
			echo 'type: "POST", '. "\n";
			echo 'url: "/admin/bin/new_'.strtolower($UniqueName).'.php", '. "\n";
			echo 'data: "Type=" + contenttype + "&CountPage='.countpage.'&Section='.$Section.'&Category='.$Category.'&SubCategory='.$SubCategory.'&Article='.$Article.'&VarPosition='.$VarPosition .'&Language='.$Language.'&Layout='.$layout.'", '. "\n";
			echo 'success: function(data) { '. "\n";
			echo '/*alert (data);'. "\n";
			echo 'return false;*/'. "\n";
			echo 'if (data) '. "\n";
			echo '{'. "\n";
			echo '$("#add_content_grid").hide();'. "\n";
			echo '$("#msggbox_add_content").html(data)'. "\n";
			echo '.fadeIn(1500, function() {'. "\n";
			echo '$("#msggbox_add_content");'. "\n";
			echo '});'. "\n";
			echo '//alert ("data");'. "\n";
			echo '}'. "\n";
			echo 'else '. "\n";
			echo '{'. "\n";
			echo '$("#msggbox_add_content").html("Error. Please try again.")'. "\n";
			echo '.fadeIn(1500, function() {'. "\n";
			echo '$("#msggbox_add_content");'. "\n";
			echo '});'. "\n";
			echo '//alert ("no data");'. "\n";
			echo '}'. "\n";
			echo '}'. "\n";
			echo '});'. "\n";
			echo 'return false;'. "\n";
			echo '}'. "\n";
			echo '-->'. "\n";
			echo '</script>';
			echo '<div class="cp_addcontent" id="cp_'.strtolower($UniqueName).'"><a href="#atop" onClick="add_'.strtolower($UniqueName).'(\''.$Layout.'\');" class="cp_addcontent_button">'.$ButtonTag.'</a></div>';
		}
		$db->close();
		}
		?>
        
    </div>
</div> 
<?php
	}
}
?>