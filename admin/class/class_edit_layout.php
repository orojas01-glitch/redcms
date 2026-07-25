<?php
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/admin_area_helpers.php";
red_start_session();
red_require_admin();

#[\AllowDynamicProperties]
class editlayout
{
	private function layout_preview($definition, $assignedLayout)
	{
		$panelId = 'red-admin-layout-map';
		$titleId = $panelId . '-title';
		$layoutLabel = is_array($definition) && isset($definition['label'])
			? trim((string) $definition['label'])
			: trim((string) $assignedLayout);
		if ($layoutLabel === '') {
			$layoutLabel = 'Current layout';
		}

		$positions = is_array($definition) && isset($definition['positions']) && is_array($definition['positions'])
			? $definition['positions']
			: [];
		$previewRows = is_array($definition) && isset($definition['previewRows']) && is_array($definition['previewRows'])
			? $definition['previewRows']
			: [];
		$previewIsFallback = is_array($definition) && !empty($definition['previewIsFallback']);
		$positionCount = count($positions);
		$positionCountLabel = $positionCount . ' editable ' . ($positionCount === 1 ? 'position' : 'positions');
		$triggerLabel = $positionCount > 0
			? 'Show layout map for ' . $layoutLabel . ', ' . $positionCountLabel
			: 'Show layout map availability for ' . $layoutLabel;

		echo '<button type="button" class="red-admin-layout-preview__trigger" data-layout-preview-trigger';
		echo ' aria-expanded="false" aria-controls="' . red_admin_area_html($panelId) . '"';
		echo ' aria-label="' . red_admin_area_html($triggerLabel) . '" title="Preview layout positions">';
		echo '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">';
		echo '<rect x="3" y="4" width="8" height="7" rx="1"></rect>';
		echo '<rect x="13" y="4" width="8" height="7" rx="1"></rect>';
		echo '<rect x="3" y="13" width="18" height="7" rx="1"></rect>';
		echo '</svg><span>Map</span></button>';

		echo '<section class="red-admin-layout-preview__panel" id="' . red_admin_area_html($panelId) . '"';
		echo ' data-layout-preview-panel role="region" aria-labelledby="' . red_admin_area_html($titleId) . '" hidden>';
		echo '<header class="red-admin-layout-preview__header"><div>';
		echo '<span class="red-admin-layout-preview__eyebrow">';
		echo $previewIsFallback ? 'Position order' : 'Desktop layout';
		echo '</span>';
		echo '<h3 id="' . red_admin_area_html($titleId) . '">' . red_admin_area_html($layoutLabel) . '</h3>';
		echo '</div>';
		if ($positionCount > 0) {
			echo '<span class="red-admin-layout-preview__count">' . red_admin_area_html($positionCountLabel) . '</span>';
		}
		echo '</header>';

		if ($positionCount === 0 || $previewRows === []) {
			echo '<p class="red-admin-layout-preview__unavailable">Visual map unavailable. The saved layout assignment is preserved.</p>';
		} else {
			echo '<div class="red-admin-layout-preview__diagram" aria-hidden="true">';
			foreach ($previewRows as $previewRow) {
				if (!is_array($previewRow) || $previewRow === []) {
					continue;
				}
				echo '<div class="red-admin-layout-preview__row">';
				foreach ($previewRow as $previewCell) {
					$positionId = is_array($previewCell) ? (int) ($previewCell['position'] ?? 0) : 0;
					$positionLabel = is_array($previewCell) ? (string) ($previewCell['label'] ?? '') : '';
					$weight = is_array($previewCell) ? (int) ($previewCell['weight'] ?? 1) : 1;
					$weight = max(1, min(12, $weight));
					if ($positionId < 1 || !isset($positions[$positionId])) {
						continue;
					}
					echo '<div class="red-admin-layout-preview__cell" style="--red-admin-layout-weight:' . $weight . '">';
					echo '<span class="red-admin-layout-preview__number">' . $positionId . '</span>';
					echo '<span class="red-admin-layout-preview__cell-label">' . red_admin_area_html($positionLabel) . '</span>';
					echo '</div>';
				}
				echo '</div>';
			}
			echo '</div>';
			if ($previewIsFallback) {
				echo '<p class="red-admin-layout-preview__note">Exact desktop geometry is not declared; positions follow manifest order.</p>';
			} else {
				echo '<p class="red-admin-layout-preview__note">Desktop structure; columns stack on smaller screens.</p>';
			}
			echo '<ol class="red-admin-layout-preview__legend" aria-label="Editable layout positions">';
			foreach ($positions as $positionId => $positionLabel) {
				echo '<li><span class="red-admin-layout-preview__legend-number">' . (int) $positionId . '</span>';
				echo '<span>' . red_admin_area_html($positionLabel) . '</span></li>';
			}
			echo '</ol>';
		}
		echo '</section>';
	}

	public function layout_form($countpage,$section,$category,$subcategory,$article,$layout)
	{
		if (!red_admin_can_manage_site()) {
			return;
		}
		echo '<script type="text/javascript">'. "\n";
		echo '<!--' ."\n";
	
		echo 'function run_update_layout (update_layout)'. "\n".'{' . "\n"; 
				
		//echo 'alert (dataString);'. "\n";
		//echo 'return false;'. "\n";
		
		echo '$.ajax({ '. "\n";
		echo'type: "POST", '. "\n";
		echo 'url: "/admin/bin/update_layout.php", '. "\n";
		echo 'data: $("#update_layout").serialize(), '. "\n";
		echo 'success: function(data) { '. "\n";
		//echo 'alert (data);'. "\n";
		echo 'if (data)'. "\n"; 
		echo '{'. "\n";
		echo '$(\'#msggbox_update_layout\').html("Updated.")'. "\n";
		echo '.hide()'. "\n";
		echo '.fadeIn(1500, function() {'. "\n";
		echo '$(\'#msggbox_update_layout\');'. "\n";
		echo 'window.location.reload();'. "\n";
		echo '});'. "\n";
		echo '}'. "\n";
		echo 'else'. "\n"; 
		echo '{'. "\n";
		//echo '$(\'#form_'.$Alias.'\').html("<div id=\'message_'.$Alias.'\'></div>");'. "\n";
		echo '$(\'#msggbox_update_layout\').html("Error. Please try again.")'. "\n";
		echo '.hide()'. "\n";
		echo '.fadeIn(1500, function() {'. "\n";
		echo '$(\'#msggbox_update_layout\');'. "\n";
		echo '});'. "\n";
		echo '}'. "\n";
		echo '}'. "\n";
		echo '});'. "\n";
		echo 'return false;'. "\n";
		echo '}'. "\n";
		echo '-->'. "\n";
		echo '</script>';
		
		echo '<div class="red-admin-layout-preview" data-layout-preview>';
		echo '<form id="update_layout" name="update_layout" method="post">';
		echo '<input type="hidden" name="countpage" id="countpage" value="'.red_admin_area_html($countpage).'" />';
		echo '<input type="hidden" name="sections" id="section" value="'.red_admin_area_html($section).'" />';
		echo '<input type="hidden" name="categories" id="category" value="'.red_admin_area_html($category).'" />';
		echo '<input type="hidden" name="subcategories" id="subcategory" value="'.red_admin_area_html($subcategory).'" />';
		echo '<input type="hidden" name="article" id="article" value="'.red_admin_area_html($article).'" />';
		echo '<label class="red-admin-visually-hidden" for="layout">Page layout</label>';
		echo '<select name="Layout" id="layout" onChange="return run_update_layout(update_layout);">';
		
        $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
	        foreach (red_admin_area_layout_options($db->connection, $layout) as $uniqueName => $label) {
	            $selected = red_admin_text($uniqueName) === red_admin_text($layout) ? ' selected="selected"' : '';
	            $escapedName = red_admin_area_html($uniqueName);
	            $escapedLabel = red_admin_area_html($label);
	            echo '<option value="'.$escapedName.'"'.$selected.'>'.$escapedLabel.' ('.$escapedName.')</option>';
	        }
		$layoutDefinition = red_admin_area_layout_definition($db->connection, $layout);
        $db->close();

		
		echo '</select>';
		echo '</form>';
		$this->layout_preview($layoutDefinition, $layout);
		$builderLayoutId = red_custom_layout_valid_id((string) $layout) ? (string) $layout : '';
		echo '<button type="button" class="red-admin-layout-preview__trigger red-admin-layout-builder-shortcut"';
		echo ' data-layout-builder-shortcut="' . red_admin_area_html($builderLayoutId) . '"';
		echo ' onclick="return redAdminOpenLayoutBuilder(this.getAttribute(\'data-layout-builder-shortcut\'));"';
		echo ' aria-label="Open Layout Builder" title="Create and manage reusable layouts">';
		echo '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">';
		echo '<path d="M4 5h16v5H4zM4 14h7v5H4zM15 14h5v5h-5z"></path>';
		echo '<path d="M12 8v8M8 12h8"></path>';
		echo '</svg><span>Build</span></button>';
		echo '</div>';
		echo '<span id="msggbox_update_layout" style="display:none; height:30px"></span>';
		
	}
}
