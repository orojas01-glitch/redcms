<?php 
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
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
#[\AllowDynamicProperties]
class add_menu
{
	private function add_content_card_key($uniqueName, $layout)
	{
		$uniqueName = strtolower(red_admin_tool_identifier($uniqueName));
		$layout = strtolower(red_admin_tool_identifier($layout));

		if ($uniqueName === 'form') {
			return $layout === 'login' ? 'form-login' : 'form-builder';
		}
		if ($uniqueName === 'gallery') {
			return in_array($layout, ['banner', 'gallery', 'video'], true) ? $layout : 'gallery';
		}
		if (in_array($uniqueName, ['article', 'ftp', 'other'], true)) {
			return $uniqueName;
		}

		return 'default';
	}

	private function add_content_card_description($cardKey)
	{
		$descriptions = [
			'article' => 'Add text, images and page content',
			'banner' => 'Add a wide promotional image',
			'form-builder' => 'Build contact, response and registration forms',
			'form-login' => 'Add the protected administrator sign-in form',
			'ftp' => 'Upload and link a file',
			'gallery' => 'Create an image collection',
			'other' => 'Add flexible custom content',
			'video' => 'Embed video content',
			'default' => 'Add this content type',
		];

		return $descriptions[$cardKey] ?? $descriptions['default'];
	}

	private function add_content_card_icon($cardKey)
	{
		$icons = [
			'article' => '<svg viewBox="0 0 24 24" focusable="false"><path d="M6.5 3.5h8l3 3v14h-11z"/><path d="M14.5 3.5v4h4M9 12h6M9 15.5h4"/></svg>',
			'banner' => '<svg viewBox="0 0 24 24" focusable="false"><rect x="3.5" y="5.5" width="17" height="13" rx="2"/><circle cx="8.5" cy="10" r="1.5"/><path d="m5.5 16 4-4 3 3 2-2 4 3"/></svg>',
			'form-builder' => '<svg viewBox="0 0 24 24" focusable="false"><rect x="3.5" y="4.5" width="17" height="15" rx="2"/><path d="M7 9h2M12 9h5M7 14h2M12 14h5"/><circle cx="8" cy="9" r="1.5"/><circle cx="8" cy="14" r="1.5"/></svg>',
			'form-login' => '<svg viewBox="0 0 24 24" focusable="false"><rect x="6.5" y="10" width="11" height="9.5" rx="2"/><path d="M9 10V7.5a3 3 0 0 1 6 0V10M12 14v2"/></svg>',
			'ftp' => '<svg viewBox="0 0 24 24" focusable="false"><path d="M7 18.5H5.5a3 3 0 0 1-.4-6A6 6 0 0 1 16.7 10a4.5 4.5 0 0 1 1.8 8.5H17"/><path d="M12 20V11M8.5 14.5 12 11l3.5 3.5"/></svg>',
			'gallery' => '<svg viewBox="0 0 24 24" focusable="false"><rect x="5.5" y="4.5" width="15" height="13" rx="2"/><path d="M5.5 14 10 10l3 3 2-2 5.5 5M3.5 8v10a2 2 0 0 0 2 2h12"/><circle cx="15.5" cy="8.5" r="1.5"/></svg>',
			'other' => '<svg viewBox="0 0 24 24" focusable="false"><circle cx="8" cy="8" r="3"/><rect x="13.5" y="4.5" width="6" height="6" rx="1"/><path d="M5 17h6M8 14v6M14 15h6l-3 5z"/></svg>',
			'video' => '<svg viewBox="0 0 24 24" focusable="false"><rect x="3.5" y="5.5" width="17" height="13" rx="2"/><path d="m10 9 5 3-5 3z"/></svg>',
			'default' => '<svg viewBox="0 0 24 24" focusable="false"><rect x="4.5" y="4.5" width="15" height="15" rx="3"/><path d="M12 8v8M8 12h8"/></svg>',
		];

		return $icons[$cardKey] ?? $icons['default'];
	}

	public function add_menu_grid($countpage,$Section,$Category,$SubCategory,$Article,$VarPosition,$Language,$layout)
	{
/*		echo 'countpage='.$countpage.'<br/>';
		echo 'Section='.$Section.'<br/>';
		echo 'Category='.$Category.'<br/>';
		echo 'SubCategory='.$SubCategory.'<br/>';
		echo 'Article='.$Article.'<br/>';
		echo 'VarPosition='.$VarPosition.'<br/>'; 
		echo 'Language='.$Language.'<br/>'; */
		// READ SESSION 'AdminComponents'
		// FOR EACH COMPONENT ADD BUTTON.
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		$components = red_admin_tool_components_for_admin($db->connection, red_admin_tool_admin_component_ids($_SESSION['AdminComponents'] ?? ''));
		$publicFormRows = [];
		$loginFormRow = null;
		$displayComponents = [];
		foreach ($components as $componentRow) {
			$componentName = red_admin_tool_identifier($componentRow['UniqueName'] ?? '');
			$componentLayout = red_admin_tool_text($componentRow['Layout'] ?? '');
			if (strcasecmp($componentName, 'Form') !== 0) {
				$displayComponents[] = $componentRow;
				continue;
			}

			if (strcasecmp($componentLayout, 'Login') === 0) {
				$componentRow['ButtonTag'] = 'Admin Login';
				$loginFormRow = $componentRow;
				continue;
			}

			$publicFormRows[strtolower($componentLayout)] = $componentRow;
		}

		if (!empty($publicFormRows)) {
			$preferredPublicTypes = ['contact', 'response', 'register', 'other'];
			$publicFormRow = reset($publicFormRows);
			foreach ($preferredPublicTypes as $preferredType) {
				if (isset($publicFormRows[$preferredType])) {
					$publicFormRow = $publicFormRows[$preferredType];
					break;
				}
			}
			$publicFormRow['ButtonTag'] = 'Form Builder';
			$displayComponents[] = $publicFormRow;
		}
		if ($loginFormRow !== null) {
			$displayComponents[] = $loginFormRow;
		}
		$components = $displayComponents;
		usort($components, function ($left, $right) {
			$labelComparison = strnatcasecmp(
				red_admin_tool_text($left['ButtonTag'] ?? ''),
				red_admin_tool_text($right['ButtonTag'] ?? '')
			);
			if ($labelComparison !== 0) {
				return $labelComparison;
			}

			return strnatcasecmp(
				red_admin_tool_text(($left['UniqueName'] ?? '') . ' ' . ($left['Layout'] ?? '')),
				red_admin_tool_text(($right['UniqueName'] ?? '') . ' ' . ($right['Layout'] ?? ''))
			);
		});
		$componentCount = count($components);
		$db->close();
	?>
	<div class="red-admin-card-chooser">
	<div class="container_12 red-admin-add-content">
	    <div class="red-admin-add-content__header">
	        <div>
	            <span class="red-admin-add-content__eyebrow">Create</span>
	            <h2 class="red-admin-add-content__title">Choose a content type</h2>
	        </div>
	        <span class="red-admin-add-content__count" aria-label="<?php echo (int) $componentCount; ?> content types available"><?php echo (int) $componentCount; ?> options</span>
	    </div>
	    <?php if ($componentCount === 0): ?>
	        <p class="red-admin-add-content__empty">No content types are available for this account.</p>
	    <?php else: ?>
	    <div class="wrapper red-admin-add-content__grid" role="list" aria-label="Content types">
	    <?php
		$cardNumber = 0;
		$renderedAddFunctions = [];
	        foreach ($components as $row)
	        {
			$UniqueName=red_admin_tool_identifier($row['UniqueName'] ?? '');
			if ($UniqueName === '') {
				continue;
			}
			$UniqueNameLower=strtolower($UniqueName);
			$Layout=red_admin_tool_text($row['Layout'] ?? '');
			$ButtonLabel=red_admin_tool_text($row['ButtonTag'] ?? '');
			if ($ButtonLabel === '') {
				$ButtonLabel=$UniqueName;
			}
			$ButtonTag=red_admin_tool_html($ButtonLabel);
			$CardKey=$this->add_content_card_key($UniqueName, $Layout);
			$CardDescription=$this->add_content_card_description($CardKey);
			$CardIcon=$this->add_content_card_icon($CardKey);
			$CardId='cp_addcontent-'.$CardKey.'-'.$cardNumber;
			$buttonOnClick = 'add_'.$UniqueNameLower.'('.json_encode($Layout).');';

			if (!isset($renderedAddFunctions[$UniqueNameLower])) {
			$renderedAddFunctions[$UniqueNameLower] = true;
			echo '<script language="JavaScript" type="text/javascript">'. "\n";
			echo '<!--' ."\n";
			echo 'function add_'.$UniqueNameLower.' (contenttype){'. "\n";
			echo '$.ajax({'. "\n";
			echo 'type: "POST", '. "\n";
			echo 'url: "/admin/bin/new_'.$UniqueNameLower.'.php", '. "\n";
			echo 'data: {Type: contenttype, CountPage: '.json_encode(red_admin_tool_scalar($countpage)).', Section: '.json_encode(red_admin_tool_text($Section)).', Category: '.json_encode(red_admin_tool_text($Category)).', SubCategory: '.json_encode(red_admin_tool_text($SubCategory)).', Article: '.json_encode(red_admin_tool_text($Article)).', VarPosition: '.json_encode(red_admin_tool_text($VarPosition)).', Language: '.json_encode(red_admin_tool_text($Language)).', Layout: '.json_encode(red_admin_tool_text($layout)).'}, '. "\n";
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
			}
			echo '<div class="cp_addcontent red-admin-add-card red-admin-add-card--'.red_admin_tool_html($CardKey).'" id="'.red_admin_tool_html($CardId).'" role="listitem" data-content-type="'.red_admin_tool_html($UniqueName).'" data-content-layout="'.red_admin_tool_html($Layout).'">';
			echo '<a href="#atop" onClick="'.red_admin_tool_html($buttonOnClick).'" class="cp_addcontent_button red-admin-add-card__link" aria-label="Add '.$ButtonTag.'">';
			echo '<span class="red-admin-add-card__icon" aria-hidden="true">'.$CardIcon.'</span>';
			echo '<span class="red-admin-add-card__copy"><span class="red-admin-add-card__label">'.$ButtonTag.'</span><span class="red-admin-add-card__description">'.red_admin_tool_html($CardDescription).'</span></span>';
			echo '<span class="red-admin-add-card__action" aria-hidden="true">+</span>';
			echo '</a></div>';
			$cardNumber++;
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
