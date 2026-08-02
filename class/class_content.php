<?php
/**
 * Red Sphere - Unique php CMS
 * @version: 1.0 - (2012/02/25)
 * @version: 4.0 - (2025/03/06)
 * @requires linux v1.2.2 or later 
 * @author Oscar Rojas
 * Examples and documentation at: http://red-sphere.tv/documentation/ 
 * Licensed under MIT licence:
 *   http://www.opensource.org/licenses/mit-license.php
**/

/**
* THIS CLASS CONSTRUCT THE ARTICLE(S) WITHIN SECTION, CATEGORY OR ARTICLE SELECTED.
* ONLY FULL-WIDTH IS SET TO WRITE ARTICLES THAT DONT HAVE A POSITION ESTABLISHED.

* OTHER VARIABLES ARE PASS FROM CONFIG.PHP. ( $countpage, $section, $category, $article, $link )

* $section - Main sections: i.e: Services. Portfolio. About. Contact.
* $category - SubCategory: i.e: WebDesign. Technology. Content. Marketing. WebDesign. Multimedia. Print-Identity. Other-Media. About. Contact.
* $article - article selected (alias).  This is set only if last part of the url do NOT has a backslash. (this-is-a-section/)  vs (/this-is-an-article)
* $link - Select link from RED_Menu. This is to obtain the layout of the articles: Full-Width. Two-Columns. Three-Columns. Four-Columns. Multi-Columns1. Multi-Columns2)
* $query - The query was created based on the url.  The first folder determine the language, second is section and third is category, fourth is article selected. Refer to class_build_page.
* $VarPosition - The VarPosition was created based on the url. i.e: SectionPosition, CategoryPosition, PagePosition.  Refer to class_build_page.
* VarFeatured - The VarFeatured was created based on the url. i.e: HomepageFeatured, SectionFeatured, CategoryFeatured. Refer to class_build_page. 
* $position - 5 options: 1. 2. 3. 4. null. 
* $layout - Full-Width. Two-Columns. Three-Columns. Four-Columns. Multi-Columns1. Multi-Columns2.
**/
require_once __DIR__ . '/../includes/public_render_helpers.php';
require_once __DIR__ . '/../includes/admin_authorization_helpers.php';
require_once __DIR__ . '/../includes/legacy_component_helpers.php';

#[\AllowDynamicProperties]
class content
{
    
	
	public function articles($query, $VarFeatures, $VarPosition, $position, $layout, $limit)
	{
		
		
		//echo $this->query;
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		// display all active records. Position is Required.
		$rows = red_public_content_articles($db->connection, $VarPosition, $position, $limit, true);
		$result_counter = count($rows);
		foreach($rows as $row)
		{
			//echo $row['Component'];
			//CHECK DATE EXPIRATION//
			if ($row['ExpDate']!='0000-00-00 00:00:00' && $row['ExpDate']!=''){
				date_default_timezone_set('America/New_York');
				if( $row['ExpDate'] < date('Y-m-d H:i:s', mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y"))) ) {
					$ActiveDate=false;
				}
				else {
					 $ActiveDate=true;
				}
			} else {
				$ActiveDate=true;
			}
            $componentContext = red_legacy_public_component_context(
                $row,
                $layout,
                article,
                $position,
                $ActiveDate,
                $db->connection
            );
			if ($componentContext !== null && $componentContext['active']) {
				$this->recordid=$componentContext['inputs']['recordId'];
			}
			red_legacy_render_public_component($componentContext);
			
		$result_counter = ($result_counter - 1);
		}
		//echo 'end'. $result_counter;
		if ($result_counter == 0);
		
		$db->close();
	}
	
	
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	//////////////////////////////////////////////CONTROL PANEL//////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	public $OrderQuery='';

	private function renderAddonControlPanelComponent(array $componentContext)
	{
		$recordId=(int)($componentContext['inputs']['recordId'] ?? 0);
		if ($recordId < 1) {
			return;
		}
		echo '<button type="button" class="cp red-admin-component-action red-admin-component-action--addon" data-red-addon-component-edit data-content-record-id="'.$recordId.'">Edit component</button>';
	}

	private function renderStructuredControlPanelComponent(array $componentContext)
	{
		switch ($componentContext['component'])
		{
			case 'Article':
				$componentInputs=$componentContext['inputs'];
				$this->recordid=$componentInputs['recordId'];
				$comp=new cp_Article();
				$comp->cp_Article($componentInputs['position'], $this->recordid, $componentInputs['varPosition'], $componentInputs['layout'], true);
				break;

			case 'Other':
				$componentInputs=$componentContext['inputs'];
				$this->recordid=$componentInputs['recordId'];
				$comp=new cp_other();
				$comp->cp_other($componentInputs['position'], $this->recordid, $componentInputs['varPosition'], $componentInputs['layout'], true);
				break;

			case 'Form':
				$componentInputs=$componentContext['inputs'];
				$this->recordid=$componentInputs['recordId'];
				$comp = new forms();
				$comp->cp_form($this->recordid, $componentInputs['varFeatures'], $componentInputs['varPosition'], $componentInputs['table'], $componentInputs['position'], $componentInputs['layout']);
				break;

			case 'Gallery':
				$componentInputs=$componentContext['inputs'];
				$this->recordid=$componentInputs['recordId'];
				$comp = new gallery();
				$comp->cp_album($componentInputs['position'], $this->recordid, $componentInputs['layout'], $componentInputs['varFeatures'], $componentInputs['varPosition'], $componentInputs['table']);
				break;

			default:
				$this->renderAddonControlPanelComponent($componentContext);
				break;
		}
	}

	public function cp_articles(
		$query,
		$VarFeatures,
		$VarPosition,
		$position,
		$layout,
		$limit,
		$Table,
		$controlPanelSlotContext = null,
		$structuredEditor = false
	)
	{
		$preparedSlotContext = null;
		if ($controlPanelSlotContext !== null) {
			$preparedSlotContext = red_legacy_control_panel_slot_wrapper_context_validate(
				$controlPanelSlotContext,
				$layout,
				$position
			);
		}

        //echo $this->query;
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		// display all active records. Position is Required.
		$rows = red_public_content_articles($db->connection, $VarPosition, $position, $limit, false);
		if ($structuredEditor) {
			$restricted = false;
			$orderIndex = 0;
			foreach ($rows as $row) {
				$componentContext = red_legacy_control_panel_component_context(
					$db->connection,
					$row,
					$VarFeatures,
					$VarPosition,
					$position,
					$layout,
					$Table,
					$orderIndex
				);
				$orderIndex++;
				if (!$componentContext['authorized'] || !$componentContext['supported']) {
					$restricted = true;
					continue;
				}

				$recordId = (int) ($componentContext['order']['recordId'] ?? 0);
				$storedOrder = (int) ($componentContext['order']['value'] ?? 0);
				$title = trim((string) ($row['Title'] ?? ''));
				if ($title === '') {
					$title = 'Untitled content';
				}
				$escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
				$escapedComponent = htmlspecialchars((string) $componentContext['component'], ENT_QUOTES, 'UTF-8');
				$escapedVarPosition = htmlspecialchars((string) $VarPosition, ENT_QUOTES, 'UTF-8');
				$escapedPosition = htmlspecialchars((string) $position, ENT_QUOTES, 'UTF-8');

				echo '<article class="red-admin-layout-item" draggable="true" data-red-layout-item="true" data-record-id="' . $recordId . '" data-position="' . $escapedPosition . '" data-order="' . $storedOrder . '" data-title="' . $escapedTitle . '">';
				echo '<div class="red-admin-layout-item__arrange">';
				echo '<button type="button" class="red-admin-layout-item__handle" draggable="true" data-red-layout-drag-handle="true" aria-label="Drag ' . $escapedTitle . '" title="Drag to reposition">';
				echo '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="8" cy="6" r="1.4"></circle><circle cx="16" cy="6" r="1.4"></circle><circle cx="8" cy="12" r="1.4"></circle><circle cx="16" cy="12" r="1.4"></circle><circle cx="8" cy="18" r="1.4"></circle><circle cx="16" cy="18" r="1.4"></circle></svg>';
				echo '</button>';
				echo '<span class="red-admin-layout-item__kind">' . $escapedComponent . '</span>';
				echo '<span class="red-admin-layout-item__placement" data-red-layout-placement="true">Position ' . $escapedPosition . '</span>';
				echo '<details class="red-admin-layout-item__menu">';
				echo '<summary aria-label="Arrange ' . $escapedTitle . '" title="Arrange content"><span aria-hidden="true">•••</span></summary>';
				echo '<div class="red-admin-layout-item__menu-panel">';
				echo '<strong>Arrange content</strong>';
				echo '<div class="red-admin-layout-item__step-actions">';
				echo '<button type="button" data-red-layout-action="up">Move up</button>';
				echo '<button type="button" data-red-layout-action="down">Move down</button>';
				echo '</div>';
				echo '<label>Position<select data-red-layout-position-select="true" aria-label="Move ' . $escapedTitle . ' to position"></select></label>';
				echo '</div>';
				echo '</details>';
				echo '</div>';
				echo '<div class="red-admin-layout-item__editor" data-red-layout-editor-card="true" data-var-position="' . $escapedVarPosition . '">';
				$this->renderStructuredControlPanelComponent($componentContext);
				echo '</div>';
				echo '</article>';
			}
			if ($restricted) {
				echo '<span hidden data-red-layout-restricted="true"></span>';
			}
			$db->close();
			return;
		}
		$total_rows = count($rows);
		$result_counter = $total_rows;
		$w=0;
		foreach($rows as $row)
		{
			$componentContext = red_legacy_control_panel_component_context(
				$db->connection,
				$row,
				$VarFeatures,
				$VarPosition,
				$position,
				$layout,
				$Table,
				$w
			);
			if (!$componentContext['authorized']) {
				continue;
			}
			$RecordID=$componentContext['order']['recordId'];
			$Alias=$componentContext['alias'];
			$PosOrder=$componentContext['order']['value'];
			
			if ($result_counter === $total_rows){
				$titlesEnabled = $preparedSlotContext !== null
					? $preparedSlotContext['titles']['enabled']
					: $position!='0';
				if ($titlesEnabled) {
					$titlesClass = $preparedSlotContext !== null
						? $preparedSlotContext['titles']['className']
						: 'cp_titles';
					echo '<div class="'.$titlesClass.'">';
				}
			}
			$hidden = $preparedSlotContext !== null
				? $preparedSlotContext['hidden']
				: $position==='0';
			if ($hidden){
				$hiddenStyle = $preparedSlotContext !== null
					? $preparedSlotContext['item']['hiddenStyle']
					: 'float:left; padding-right:5px; margin-right:5px;';
				echo '<div style="'.$hiddenStyle.'">';
			}else {
				$order = $componentContext['order'];
				$orderIndex = $order['index'];
				$this->OrderQuery=$this->OrderQuery.'<input name="PosOrder['.$orderIndex.']" type="text" id="PosOrder['.$orderIndex.']" style="width:15px; margin-bottom:35px; " value="'.$PosOrder.'" /><input name="VarPosition['.$orderIndex.']" type="hidden" id="VarPosition['.$orderIndex.']" value="'.$order['varPosition'].'" /><input name="RecordID['.$orderIndex.']" type="hidden" id="RecordID['.$orderIndex.']" value="'.$RecordID.'" /><br clear="all" />';
			}
			
			switch ($componentContext['component'])
			{
				
				//////COMPONENTS///////
				case 'Article':
				$componentInputs=$componentContext['inputs'];
				$this->recordid=$componentInputs['recordId'];
                $comp=new cp_Article();
                $comp->cp_Article($componentInputs['position'], $this->recordid, $componentInputs['varPosition'], $componentInputs['layout']);
				////////////////////////////////////////////////////
				break;
				
				
				
				
								
				case 'Other':
				$componentInputs=$componentContext['inputs'];
				$this->recordid=$componentInputs['recordId'];
                $comp=new cp_other();    
				$comp->cp_other($componentInputs['position'], $this->recordid, $componentInputs['varPosition'], $componentInputs['layout']);
				////////////////////////////////////////////////////
				break;
				
				
				case 'Form':
				$componentInputs=$componentContext['inputs'];
				$this->recordid=$componentInputs['recordId'];
				$comp = new forms();
				$comp->cp_form($this->recordid, $componentInputs['varFeatures'], $componentInputs['varPosition'], $componentInputs['table'], $componentInputs['position'], $componentInputs['layout']);
				////////////////////////////////////////////////////
				break;
				
				case 'Gallery':
				
				$componentInputs=$componentContext['inputs'];
				$this->recordid=$componentInputs['recordId'];
				$comp = new gallery();
				$comp->cp_album($componentInputs['position'], $this->recordid, $componentInputs['layout'], $componentInputs['varFeatures'], $componentInputs['varPosition'], $componentInputs['table']);
				////////////////////////////////////////////////////
				break;

				default:
				$this->renderAddonControlPanelComponent($componentContext);
				break;
				
				
				
				
			}
			
			if ($position==='0'){
				echo '</div>';
			}
			
		$result_counter = ($result_counter - 1);
		
			if ($result_counter === 0){
				$titlesEnabled = $preparedSlotContext !== null
					? $preparedSlotContext['titles']['enabled']
					: $position!='0';
				if ($titlesEnabled)
				echo '</div>';
		}
		$w++;
		}
		//echo 'end'. $result_counter;
		
		
		if ($this->OrderQuery!=''){
		$orderEndpoint = $preparedSlotContext !== null ? $preparedSlotContext['order']['endpoint'] : '/admin/bin/update_order.php';
		$orderFormId = $preparedSlotContext !== null ? $preparedSlotContext['order']['formId'] : 'update_order_'.$position;
		$orderFunctionName = $preparedSlotContext !== null ? $preparedSlotContext['order']['functionName'] : 'run_update_order_'.$position;
		$orderAlertId = $preparedSlotContext !== null ? $preparedSlotContext['order']['alertId'] : 'msggbox_alert_'.$position;
		$orderSuccessMessage = $preparedSlotContext !== null ? $preparedSlotContext['order']['successMessage'] : 'Order Updated';
		$orderFailureMessage = $preparedSlotContext !== null ? $preparedSlotContext['order']['failureMessage'] : 'Nothing to Update. Please try again.';
		echo '<div class="cp_update_order"><h7 id="cp">Order</h7>';
		echo '<script type="text/javascript">'. "\n";
		echo '<!--' ."\n";
		echo 'function '.$orderFunctionName.' ('.$orderFormId.')'. "\n".'{' . "\n";
			echo '$.ajax({ '. "\n";
			echo'type: "POST", '. "\n";
			echo 'url: "'.$orderEndpoint.'", '. "\n";
			echo 'data: $("#'.$orderFormId.'").serialize(), '. "\n";
			echo 'success: function(data) { '. "\n";
			//echo 'alert (data);'. "\n";
			//echo 'return false;'. "\n";
			//echo 'if (data)'. "\n";
			echo 'if (data==\'yes\')'. "\n"; 
			echo '{'. "\n";
			echo '$(\'#'.$orderAlertId.'\').html("'.$orderSuccessMessage.'")'. "\n";
			echo '.hide()'. "\n";
			echo '.fadeIn(1500, function() {'. "\n";
			echo '$(\'#msggbox_edit_content\');'. "\n";
			echo 'window.location.reload();'. "\n";
			echo '});'. "\n";
			echo '}'. "\n";
			echo 'else'. "\n"; 
			echo '{'. "\n";
			echo '$(\'#'.$orderAlertId.'\').html("'.$orderFailureMessage.'")'. "\n";
			echo '.hide()'. "\n";
			echo '.fadeIn(1500, function() {'. "\n";
			echo '$(\'msggbox_edit_content\');'. "\n";
			echo '});'. "\n";
			echo '}'. "\n";
			echo '}'. "\n";
			echo '});'. "\n";
			echo 'return false;'. "\n";
			echo '}'. "\n";
			echo '-->'. "\n";
		echo '</script>';
					
		echo '<form id="'.$orderFormId.'" name="'.$orderFormId.'" method="post" onSubmit="return '.$orderFunctionName.'(this);">';
		echo red_csrf_input();
		echo $this->OrderQuery;
		echo '<input type="submit" name="submit" value="Ok!" title="Update Order Position '.$position.'" class="cp" id="cp_update"/>';
		echo '</form></div>';
		echo '<div class="clear-1"></div>';
		//echo '<hr id="cp">';
		}
		$db->close();
	}
	
	
	
	
}
?>
