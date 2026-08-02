<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/admin_tool_helpers.php";
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/addon_admin_tool_helpers.php";
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
	private function tool_card_key($uniqueName)
	{
		$uniqueName = strtolower(red_admin_tool_identifier($uniqueName));
		if ($uniqueName === 'movecontent') {
			return 'move';
		}
		if ($uniqueName === 'filterareas') {
			return 'filter';
		}

		return 'tool-default';
	}

	private function tool_card_description($cardKey, $fallback)
	{
		$descriptions = [
			'move' => 'Move content between site areas',
			'filter' => 'Filter content by site area',
		];

		$fallback = red_admin_tool_text($fallback);
		return $descriptions[$cardKey] ?? ($fallback !== '' ? $fallback : 'Open this content tool');
	}

	private function tool_card_icon($cardKey)
	{
		$icons = [
			'move' => '<svg viewBox="0 0 24 24" focusable="false"><rect x="3.5" y="5.5" width="6" height="13" rx="1.5"/><rect x="14.5" y="5.5" width="6" height="13" rx="1.5"/><path d="M8 9h8M13.5 6.5 16 9l-2.5 2.5M16 15H8M10.5 12.5 8 15l2.5 2.5"/></svg>',
			'filter' => '<svg viewBox="0 0 24 24" focusable="false"><path d="M4 5h16l-6.5 7.5V19l-3 1v-7.5z"/><path d="M15.5 16.5h5M18 14v5"/></svg>',
			'tool-default' => '<svg viewBox="0 0 24 24" focusable="false"><path d="M14.5 6.5a4 4 0 0 0-5 5L4 17l3 3 5.5-5.5a4 4 0 0 0 5-5l-2.5 2.5-3-3z"/></svg>',
		];

		return $icons[$cardKey] ?? $icons['tool-default'];
	}

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
		// READ SESSION 'AdminTools' AND KEEP THE RENDERED ORDER HUMAN-FRIENDLY.
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		$tools = red_admin_tool_rows_by_group($db->connection, $compgroup, red_admin_session_id_list('AdminTools'));
		$addonTools = red_addon_admin_tool_catalog(
			$db->connection,
			(int) ($_SESSION['AdminRecordID'] ?? 0)
		);
		usort($tools, function ($left, $right) {
			return strnatcasecmp(
				red_admin_tool_text($left['ButtonTag'] ?? ''),
				red_admin_tool_text($right['ButtonTag'] ?? '')
			);
		});
		$toolCount = count($tools) + count($addonTools);
		$db->close();
	?>
	<div class="red-admin-card-chooser">
	<div class="container_12 red-admin-add-content red-admin-tools-content">
	    <div class="red-admin-add-content__header">
	        <div>
	            <span class="red-admin-add-content__eyebrow">Tools</span>
	            <h2 class="red-admin-add-content__title">Choose a tool</h2>
	        </div>
	        <span class="red-admin-add-content__count" aria-label="<?php echo (int) $toolCount; ?> tools available"><?php echo (int) $toolCount; ?> <?php echo $toolCount === 1 ? 'option' : 'options'; ?></span>
	    </div>
	    <?php if ($toolCount === 0): ?>
	        <p class="red-admin-add-content__empty">No tools are available for this account.</p>
	    <?php else: ?>
	    <div class="wrapper red-admin-add-content__grid" role="list" aria-label="Available tools">
	    <?php
		$cardNumber = 0;
		foreach($tools as $row)
		{
			$UniqueName=red_admin_tool_identifier($row['UniqueName'] ?? '');
			if ($UniqueName === '') {
				continue;
			}
			$UniqueNameLower=strtolower($UniqueName);
			$ButtonLabel=red_admin_tool_text($row['ButtonTag'] ?? '');
			if ($ButtonLabel === '') {
				$ButtonLabel=$UniqueName;
			}
			$AltContentText=red_admin_tool_text($row['AltContent'] ?? '');
			$ButtonTag=red_admin_tool_html($ButtonLabel);
			$AltContent=red_admin_tool_html($AltContentText);
			$CardKey=$this->tool_card_key($UniqueName);
			$CardDescription=$this->tool_card_description($CardKey, $AltContentText);
			$CardIcon=$this->tool_card_icon($CardKey);
			$CardId='cp_tool-'.$CardKey.'-'.$cardNumber;
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
			echo '<div class="cp_addcontent red-admin-add-card red-admin-add-card--'.red_admin_tool_html($CardKey).'" id="'.red_admin_tool_html($CardId).'" role="listitem" data-tool="'.red_admin_tool_html($UniqueName).'">';
			echo '<a href="#cp_'.$cpareastyle.'" onClick="'.red_admin_tool_html($buttonOnClick).'" title="'.$AltContent.'" class="cp_addcontent_button red-admin-add-card__link" aria-label="Open '.$ButtonTag.' tool">';
			echo '<span class="red-admin-add-card__icon" aria-hidden="true">'.$CardIcon.'</span>';
			echo '<span class="red-admin-add-card__copy"><span class="red-admin-add-card__label">'.$ButtonTag.'</span><span class="red-admin-add-card__description">'.red_admin_tool_html($CardDescription).'</span></span>';
			echo '<span class="red-admin-add-card__action" aria-hidden="true">→</span>';
			echo '</a></div>';
			$cardNumber++;
		}
		if ($addonTools !== []) {
			echo '<script type="text/javascript">' . "\n";
			echo 'function redAdminOpenAddonTool(toolId,targetId){' . "\n";
			echo '$.ajax({type:"POST",url:"/admin/bin/view_addon_tool.php",data:{tool:toolId},success:function(data){$("#tools_"+targetId+"_grid").hide();$("#msggbox_tools_"+targetId).html(data).fadeIn(150);}});' . "\n";
			echo 'return false;}' . "\n";
			echo '</script>';
		}
		foreach ($addonTools as $addonTool) {
			$toolId = (string) $addonTool['tool'];
			$label = red_admin_tool_html($addonTool['label']);
			$description = red_admin_tool_html($addonTool['description']);
			$cardId = 'cp_tool-addon-' . substr(hash('sha256', $toolId), 0, 12);
			$buttonOnClick = 'return redAdminOpenAddonTool(' .
				json_encode($toolId) . ',' . json_encode($cpareastyle) . ');';
			echo '<div class="cp_addcontent red-admin-add-card red-admin-add-card--tool-default" id="' . red_admin_tool_html($cardId) . '" role="listitem" data-addon-tool="' . red_admin_tool_html($toolId) . '">';
			echo '<a href="#cp_' . red_admin_tool_html($cpareastyle) . '" onClick="' . red_admin_tool_html($buttonOnClick) . '" class="cp_addcontent_button red-admin-add-card__link" aria-label="Open ' . $label . ' add-on tool">';
			echo '<span class="red-admin-add-card__icon" aria-hidden="true">' . $this->tool_card_icon('tool-default') . '</span>';
			echo '<span class="red-admin-add-card__copy"><span class="red-admin-add-card__label">' . $label . '</span><span class="red-admin-add-card__description">' . $description . '</span></span>';
			echo '<span class="red-admin-add-card__action" aria-hidden="true">→</span>';
			echo '</a></div>';
		}
		?>
	    </div>
	    <?php endif; ?>
	</div>
	</div>
<?php
	}
}
?>
