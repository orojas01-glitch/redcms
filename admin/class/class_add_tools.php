<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/admin_tool_helpers.php";
red_start_session();
red_require_admin();

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
		$cpareastyle=strtolower(red_admin_tool_identifier($cparea));
        if ($cpareastyle === '') {
            $cpareastyle = 'content';
        }
		
	
?>
<div class="container_12 cp_padtop">
    <div class="wrapper">
    	<?php
        // READ SESSION 'AdminComponents'
        // FOR EACH COMPONENT ADD BUTTON.
        
        $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
        $tools = red_admin_tool_rows_by_group($db->connection, $compgroup);
		foreach($tools as $row)
		{
			$UniqueName=red_admin_tool_identifier($row['UniqueName'] ?? '');
            if ($UniqueName === '') {
                continue;
            }
            $UniqueNameLower=strtolower($UniqueName);
			//$Layout=$row['Layout'];
			$ButtonTag=red_admin_tool_html($row['ButtonTag'] ?? '');
			$AltContent=red_admin_tool_html($row['AltContent'] ?? '');
            $buttonOnClick = 'add_'.$UniqueNameLower.'_'.$cpareastyle.'('.json_encode(red_admin_tool_text($layout)).');';
			
			echo '<script language="JavaScript" type="text/javascript">'. "\n";
			echo '<!--' ."\n";
			echo 'function add_'.$UniqueNameLower.'_'.$cpareastyle.' (contenttype){'. "\n";
			echo '$.ajax({'. "\n";
			echo 'type: "POST", '. "\n";
			echo 'url: "/admin/bin/tool_'.$UniqueNameLower.'.php", '. "\n";
			echo 'data: {Type: contenttype, CountPage: '.json_encode(red_admin_tool_scalar($countpage)).', Section: '.json_encode(red_admin_tool_text($Section)).', Category: '.json_encode(red_admin_tool_text($Category)).', SubCategory: '.json_encode(red_admin_tool_text($SubCategory)).', Article: '.json_encode(red_admin_tool_text($Article)).', VarPosition: '.json_encode(red_admin_tool_text($VarPosition)).', Language: '.json_encode(red_admin_tool_text($Language)).', cparea: '.json_encode(red_admin_tool_text($cparea)).', compgroup: '.json_encode(red_admin_tool_text($compgroup)).', Layout: '.json_encode(red_admin_tool_text($layout)).'}, '. "\n";
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
			echo '<div class="cp_addcontent" id="cp_tools"><a href="#cp_'.$cpareastyle.'" onClick="'.red_admin_tool_html($buttonOnClick).'" title="'.$AltContent.'" class="cp_addcontent_button">'.$ButtonTag.'</a></div>';

		}
        $db->close();
		?>
        
    </div>
</div> 
<?php
	}
}
?>
