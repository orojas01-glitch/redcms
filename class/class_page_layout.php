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
* THIS CLASS CONSTRUCT THE PAGE LAYOUT.
* THE DEFAULT TEMPLATE IS BASED ON A GRID SYSTEM THAT USES 12 COLUMNS (bootstrap 5.02).

DEFINED VARIABLES ENTERING THIS CLASS
* $articlequery - The articlequery was created based on the url.  It determines section, category, subcategory or article selected. Refer to class_build_page.
* $VarFeatures - The VarFeatures was created based on the url.  Refer to class_build_query.php
* $VarPosition - The VarPosition was created based on the url.  Refer to class/class_build_page.php

OTHER CLASSES USED
* $this->limit - Get the limit of articles for page. refer to class/class_limit.php
* $this->layout - Get the layout for page. refer to class/class_layout.php

**/
require_once __DIR__ . '/../includes/legacy_layout_helpers.php';
require_once __DIR__ . '/../includes/public_route_fallback_helpers.php';

#[\AllowDynamicProperties]
class page_layout
{
	private function render_layout_slot(
		$articlequery,
		$VarFeatures,
		$VarPosition,
		$position,
		$Table = null,
		$controlPanel = false
	) {
		$context = red_legacy_layout_slot_context(
			$articlequery,
			$VarFeatures,
			$VarPosition,
			$position,
			$this->layout,
			$this->limit,
			$controlPanel ? 'control-panel' : 'public',
			$Table
		);
		red_legacy_render_layout_slot($context);
	}
	
	public function layout($articlequery, $VarFeatures, $VarPosition)
	{
		
		$tlay = new limit();
		$this->limit=$tlay->get_limit();
		
		$tlay = new layout();
		$this->layout=$tlay->get_layout();

		if (red_public_route_fallback_render($this->layout)) {
			return;
		}

			global $redThemeAdapter;
			if (isset($redThemeAdapter) && is_callable([$redThemeAdapter, 'supportsPublicLayout'])) {
				if ($redThemeAdapter->supportsPublicLayout($this->layout)) {
					$redThemeAdapter->renderPublicLayout(
						$this->layout,
						$articlequery,
						$VarFeatures,
						$VarPosition,
						$this->limit
					);
					return;
				}
				if (is_callable([$redThemeAdapter, 'renderControlPanelLayout'])) {
					throw new RuntimeException('The active standard theme does not support the assigned layout id.');
				}
			}

			$layoutInventory = red_legacy_layout_slot_inventory();
			if (is_string($this->layout) && isset($layoutInventory[$this->layout])) {
				if (!isset($redThemeAdapter) || !is_callable([$redThemeAdapter, 'renderPublicLayout'])) {
					throw new RuntimeException('The legacy public layout adapter is unavailable.');
			}

			$redThemeAdapter->renderPublicLayout(
				$this->layout,
				$articlequery,
				$VarFeatures,
				$VarPosition,
				$this->limit
			);
			return;
		}

		switch ($this->layout)
		{
        ////////////////////
		case 'index-4':
		////////////////////
		
		echo ('<div class="container px-4 pb-0 pt-3">');
                echo('<div class="row">');
						
			$page=new Build_Breadcrumb();
			$page->get_breadcrumb();

				
				//column 1
				echo('<div class="col-lg-4 col-md-4 col-sm-4">');
					//echo('<div class="wrapper">');
						$comp = new content();
						$position = '1';
						$comp->articles($articlequery, $VarFeatures, $VarPosition, $position, $this->layout, $this->limit);
						////////////////////////////////////////////////////
					//echo('</div>');
							
				echo('</div>');
						
						//column 2
				echo('<div class="col-lg-8 col-md-8 col-sm-8">');
						//echo('<div class="wrapper">');
						$comp = new content();
						$position = '2';
						$comp->articles($articlequery, $VarFeatures, $VarPosition, $position, $this->layout, $this->limit);
						////////////////////////////////////////////////////
						//echo('</div>');
							
				echo('</div>');
							
									
		echo ('</div>');
		echo ('</div>');
		
		break; 
                
        ////////////////////
		case 'index-5':
		////////////////////
		
		echo ('<div class="container px-4 pb-0 pt-3">');
                echo('<div class="row">');
						
			$page=new Build_Breadcrumb();
			$page->get_breadcrumb();
			
				
				//column 1
				echo('<div class="col-lg-8 col-md-8 col-sm-8">');
					//echo('<div class="wrapper">');
						$comp = new content();
						$position = '1';
						$comp->articles($articlequery, $VarFeatures, $VarPosition, $position, $this->layout, $this->limit);
						////////////////////////////////////////////////////
					//echo('</div>');
							
				echo('</div>');
						
						//column 2
				echo('<div class="col-lg-4 col-md-4 col-sm-4">');
						//echo('<div class="wrapper">');
						$comp = new content();
						$position = '2';
						$comp->articles($articlequery, $VarFeatures, $VarPosition, $position, $this->layout, $this->limit);
						////////////////////////////////////////////////////
						//echo('</div>');
							
				echo('</div>');
							
									
		echo ('</div>');
        echo('<div class="row">');
                echo('<div class="col-lg-12 col-md-12 col-sm-12">');
						//echo('<div class="wrapper">');
						$comp = new content();
						$position = '3';
						$comp->articles($articlequery, $VarFeatures, $VarPosition, $position, $this->layout, $this->limit);
						////////////////////////////////////////////////////
						//echo('</div>');
							
				echo('</div>');
        echo ('</div>');        
                
		echo ('</div>');
		
		break;     
                
                
        ////////////////////
		case 'index-6':
		////////////////////
		
		echo ('<div class="container px-4 pb-0 pt-3">');
                echo('<div class="row">');
						
			$page=new Build_Breadcrumb();
			$page->get_breadcrumb();
			
				
				//column 1
				echo('<div class="col-lg-4 col-md-4 col-sm-4">');
					//echo('<div class="wrapper">');
						$comp = new content();
						$position = '1';
						$comp->articles($articlequery, $VarFeatures, $VarPosition, $position, $this->layout, $this->limit);
						////////////////////////////////////////////////////
					//echo('</div>');
							
				echo('</div>');
						
						//column 2
				echo('<div class="col-lg-8 col-md-8 col-sm-8">');
						//echo('<div class="wrapper">');
						$comp = new content();
						$position = '2';
						$comp->articles($articlequery, $VarFeatures, $VarPosition, $position, $this->layout, $this->limit);
						////////////////////////////////////////////////////
						//echo('</div>');
							
				echo('</div>');
							
									
		echo ('</div>');
                
        echo('<div class="row">');
                echo('<div class="col-lg-12 col-md-12 col-sm-12">');
						//echo('<div class="wrapper">');
						$comp = new content();
						$position = '3';
						$comp->articles($articlequery, $VarFeatures, $VarPosition, $position, $this->layout, $this->limit);
						////////////////////////////////////////////////////
						//echo('</div>');
							
				echo('</div>');
        echo ('</div>');         
                
		echo ('</div>');
		
		break;         
                
		}
		
		
	}
	
	
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	//////////////////////////////////////////////CONTROL PANEL//////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	
	public function cp_layout($articlequery, $VarFeatures, $VarPosition, $Table)
	{
		
		$tlay = new limit();
		$this->limit=$tlay->get_limit();
		
			$tlay = new layout();
			$this->layout=$tlay->get_layout();

			// The administrator overlay has no editable layout grid for an
			// unmatched route or a deliberately blank assigned layout. Leave the
			// overlay intact and let the public layout pass own status/body output.
			if (red_public_route_fallback_classify($this->layout) !== null) {
				return;
			}

			global $redThemeAdapter;
			if (isset($redThemeAdapter) && is_callable([$redThemeAdapter, 'renderControlPanelLayout'])) {
				if (!is_callable([$redThemeAdapter, 'supportsPublicLayout'])
					|| !$redThemeAdapter->supportsPublicLayout($this->layout)
				) {
					throw new RuntimeException('The active standard theme does not support the assigned control-panel layout id.');
				}
				$redThemeAdapter->renderControlPanelLayout(
					$this->layout,
					$articlequery,
					$VarFeatures,
					$VarPosition,
					$this->limit,
					$Table
				);
				return;
			}
			if (isset($redThemeAdapter) && is_callable([$redThemeAdapter, 'resolvePublicLayoutId'])) {
				$resolvedControlPanelLayout = $redThemeAdapter->resolvePublicLayoutId($this->layout);
				if ($resolvedControlPanelLayout !== null) {
					$this->layout = $resolvedControlPanelLayout;
				}
			}

			//echo $this->layout;
		switch ($this->layout)
		{
		//////////////////
		case 'index':
		//////////////////
		
		//column 1
		echo ('<div class="row">');
		echo ('<div class="col-lg-6">');
			
				
				echo('<div class="wrapper cp_admin" title="Position 1"><div class="position">1</div>');
					$position = '1';
					$this->render_layout_slot($articlequery, $VarFeatures, $VarPosition, $position, $Table, true);
					////////////////////////////////////////////////////
				echo('<span id="msggbox_alert_'.$position.'" style="display:none;"></span>');	
				echo('</div>');
			
				
			echo('</div>');
			
			//column 2
			echo('<div class="col-lg-6">');
				
				echo('<div class="wrapper cp_admin" title="Position 2"><div class="position">2</div>');
					$position = '2';
					$this->render_layout_slot($articlequery, $VarFeatures, $VarPosition, $position, $Table, true);
					////////////////////////////////////////////////////
				echo('<span id="msggbox_alert_'.$position.'" style="display:none;"></span>');
				echo('</div>');
			
			
			
			echo('</div>');
        echo('</div>');
                
        echo ('<div class="row">');
		echo ('<div class="col-lg-12">');
			
				echo('<div class="wrapper cp_admin" title="Position 3"><div class="position">3</div>');
					$position = '3';
					$this->render_layout_slot($articlequery, $VarFeatures, $VarPosition, $position, $Table, true);
					////////////////////////////////////////////////////
				echo('<span id="msggbox_alert_'.$position.'" style="display:none;"></span>');
				echo('</div>');
		
        echo('</div>');        
		echo('</div>');
		
		
		//end column
        echo ('<div class="row">');        
		echo ('<div class="col-lg-12">');
			
				echo('<div class="wrapper position0_background cp_admin" title="Position 0 Hidden"><div class="position">0</div>');
					$position = '0';
					$this->render_layout_slot($articlequery, $VarFeatures, $VarPosition, $position, $Table, true);
					////////////////////////////////////////////////////
				echo('<div class="clear"></div><span id="msggbox_alert_'.$position.'" style="display:none;"></span>');
				echo('</div>');
			
		echo('</div>');
		echo('</div>');
		break;
		
		
		/////////////////////
			case 'index-1':
		/////////////////////
		
			
				//column 1
				echo ('<div class="row">');
                echo('<div class="col-lg-12">');
			
				echo('<div class="wrapper cp_admin" title="Position 1"><div class="position">1</div>');
					$position = '1';
					$this->render_layout_slot($articlequery, $VarFeatures, $VarPosition, $position, $Table, true);
					////////////////////////////////////////////////////
				echo('<span id="msggbox_alert_'.$position.'" style="display:none;"></span>');	
				echo('</div>');
				
			echo('</div>');
        echo('</div>');
       
            echo('<div class="row">');
			
			//column 2
			echo('<div class="col-lg-4">');
			
				echo ('<div class="wrapper cp_admin" title="Position 2"><div class="position">2</div>');
					$position = '2';
					$this->render_layout_slot($articlequery, $VarFeatures, $VarPosition, $position, $Table, true);
					////////////////////////////////////////////////////
				echo('<span id="msggbox_alert_'.$position.'" style="display:none;"></span>');	
				echo('</div>');
				
			echo('</div>');
			
			//column 3
			echo('<div class="col-lg-4">');
			
				echo('<div class="wrapper cp_admin" title="Position 3"><div class="position">3</div>');
					$position = '3';
					$this->render_layout_slot($articlequery, $VarFeatures, $VarPosition, $position, $Table, true);
					////////////////////////////////////////////////////
				echo('<span id="msggbox_alert_'.$position.'" style="display:none;"></span>');	
				echo('</div>');
			
			echo('</div>');
                
            //column 4
            echo('<div class="col-lg-4">');
			
				echo ('<div class="wrapper cp_admin" title="Position 4"><div class="position">4</div>');
					$position = '4';
					$this->render_layout_slot($articlequery, $VarFeatures, $VarPosition, $position, $Table, true);
					////////////////////////////////////////////////////
				echo('<span id="msggbox_alert_'.$position.'" style="display:none;"></span>');	
				echo('</div>');
				
			echo('</div>');    
                
		    echo('</div>');  
				
				//end column
		echo ('<div class="row">');
		echo ('<div class="col-lg-12">');
			
				echo('<div class="wrapper position0_background cp_admin" title="Position 0 Hidden"><div class="position">0</div>');
					$position = '0';
					$this->render_layout_slot($articlequery, $VarFeatures, $VarPosition, $position, $Table, true);
					////////////////////////////////////////////////////
				echo('<div class="clear"></div><span id="msggbox_alert_'.$position.'" style="display:none;"></span>');
				echo('</div>');
			
		echo('</div>');
		echo('</div>');
			
	
		break;
		
		
		/////////////////
		case 'index-2':
		/////////////////
		
		//full width
        echo ('<div class="row">');        
		echo ('<div class="col-lg-12">');
			
			echo('<div class="wrapper cp_admin"><div class="position">Position 1</div>');
				$position = '1';
				$this->render_layout_slot($articlequery, $VarFeatures, $VarPosition, $position, $Table, true);
				////////////////////////////////////////////////////
			echo('<span id="msggbox_alert_'.$position.'" style="display:none;"></span>');
			echo('</div>');
			
			echo('<div class="wrapper cp_admin"><div class="position">Position 2</div>');	
				$position = '2';
				$this->render_layout_slot($articlequery, $VarFeatures, $VarPosition, $position, $Table, true);
				////////////////////////////////////////////////////
			echo('<span id="msggbox_alert_'.$position.'" style="display:none;"></span>');
			echo('</div>');
			
			echo('<div class="wrapper cp_admin"><div class="position">Position 3</div>');	
				$position = '3';
				$this->render_layout_slot($articlequery, $VarFeatures, $VarPosition, $position, $Table, true);
				////////////////////////////////////////////////////
			echo('<span id="msggbox_alert_'.$position.'" style="display:none;"></span>');
			echo('</div>');
			
			echo('<div class="wrapper cp_admin"><div class="position">Position 4</div>');	
				$position = '4';
				$this->render_layout_slot($articlequery, $VarFeatures, $VarPosition, $position, $Table, true);
				////////////////////////////////////////////////////
			echo('<span id="msggbox_alert_'.$position.'" style="display:none;"></span>');
			echo('</div>');
			
			//end column
			echo('<div class="wrapper position0_background cp_admin" title="Position 0 Hidden"><div class="position">Position 0</div>');
				$position = '0';
				$this->render_layout_slot($articlequery, $VarFeatures, $VarPosition, $position, $Table, true);
				////////////////////////////////////////////////////
			echo('<span id="msggbox_alert_'.$position.'" style="display:none;"></span>');
			echo('</div>');
		
		echo('</div>');
		echo('</div>');
                
		break;
		
		
		
		////////////////////
			case 'index-3':
		////////////////////
		
			//column 1
			echo ('<div class="row">');        
		      echo ('<div class="col-lg-8">');
			
				echo('<div class="wrapper cp_admin" title="Position 1"><div class="position">1</div>');
					$position = '1';
					$this->render_layout_slot($articlequery, $VarFeatures, $VarPosition, $position, $Table, true);
					////////////////////////////////////////////////////
				echo('<span id="msggbox_alert_'.$position.'" style="display:none;"></span>');	
				echo('</div>');
			
			echo('</div>');
			
			//column 2
			echo('<div class="col-lg-4">');
			
				echo('<div class="wrapper cp_admin" title="Position 2"><div class="position">2</div>');
					$position = '2';
					$this->render_layout_slot($articlequery, $VarFeatures, $VarPosition, $position, $Table, true);
					////////////////////////////////////////////////////	
				echo('<span id="msggbox_alert_'.$position.'" style="display:none;"></span>');	
				echo('</div>');
			
			echo('</div>');
			echo('</div>');
			
			
			//end column
                echo ('<div class="row">');
		echo ('<div class="col-lg-12">');
			
			echo('<div class="wrapper position0_background cp_admin" title="Position 0 Hidden"><div class="position">0</div>');
				$position = '0';
				$this->render_layout_slot($articlequery, $VarFeatures, $VarPosition, $position, $Table, true);
				////////////////////////////////////////////////////
			echo('<div class="clear"></div><span id="msggbox_alert_'.$position.'" style="display:none;"></span>');
			echo('</div>');
		
                echo('</div>');
                echo('</div>');
		
		break;
                
                
       ////////////////////
			case 'index-4':
		////////////////////
		
			//column 1
			echo ('<div class="row">');        
		      echo ('<div class="col-lg-4">');
			
				echo('<div class="wrapper cp_admin" title="Position 1"><div class="position">1</div>');
					$comp = new content();
					$position = '1';
					$comp->cp_articles($articlequery, $VarFeatures, $VarPosition, $position, $this->layout, $this->limit, $Table);
					////////////////////////////////////////////////////
				echo('<span id="msggbox_alert_'.$position.'" style="display:none;"></span>');	
				echo('</div>');
			
			echo('</div>');
			
			//column 2
			echo('<div class="col-lg-8">');
			
				echo('<div class="wrapper cp_admin" title="Position 2"><div class="position">2</div>');
					$comp = new content();
					$position = '2';
					$comp->cp_articles($articlequery, $VarFeatures, $VarPosition, $position, $this->layout, $this->limit, $Table);
					////////////////////////////////////////////////////	
				echo('<span id="msggbox_alert_'.$position.'" style="display:none;"></span>');	
				echo('</div>');
			
			echo('</div>');
			echo('</div>');
			
			
			//end column
                echo ('<div class="row">');
		echo ('<div class="col-lg-12">');
			
			echo('<div class="wrapper position0_background cp_admin" title="Position 0 Hidden"><div class="position">0</div>');
				$comp = new content();
				$position = '0';
				$comp->cp_articles($articlequery, $VarFeatures, $VarPosition, $position, $this->layout, $this->limit, $Table);
				////////////////////////////////////////////////////
			echo('<div class="clear"></div><span id="msggbox_alert_'.$position.'" style="display:none;"></span>');
			echo('</div>');
		
                echo('</div>');
                echo('</div>');
		
		break;  
                
		////////////////////
			case 'index-5':
		////////////////////
		
			//column 1
			echo ('<div class="row">');        
		      echo ('<div class="col-lg-8">');
			
				echo('<div class="wrapper cp_admin" title="Position 1"><div class="position">1</div>');
					$comp = new content();
					$position = '1';
					$comp->cp_articles($articlequery, $VarFeatures, $VarPosition, $position, $this->layout, $this->limit, $Table);
					////////////////////////////////////////////////////
				echo('<span id="msggbox_alert_'.$position.'" style="display:none;"></span>');	
				echo('</div>');
			
			echo('</div>');
			
			//column 2
			echo('<div class="col-lg-4">');
			
				echo('<div class="wrapper cp_admin" title="Position 2"><div class="position">2</div>');
					$comp = new content();
					$position = '2';
					$comp->cp_articles($articlequery, $VarFeatures, $VarPosition, $position, $this->layout, $this->limit, $Table);
					////////////////////////////////////////////////////	
				echo('<span id="msggbox_alert_'.$position.'" style="display:none;"></span>');	
				echo('</div>');
			
			echo('</div>');
			echo('</div>');
			echo ('<div class="row">');
                echo ('<div class="col-lg-12">');
                echo('<div class="wrapper cp_admin" title="Position 3"><div class="position">2</div>');
					$comp = new content();
					$position = '3';
					$comp->cp_articles($articlequery, $VarFeatures, $VarPosition, $position, $this->layout, $this->limit, $Table);
					////////////////////////////////////////////////////	
				echo('<span id="msggbox_alert_'.$position.'" style="display:none;"></span>');	
				echo('</div>');
                echo('</div>');
           echo('</div>');     
			
			//end column
                echo ('<div class="row">');
		echo ('<div class="col-lg-12">');
			
			echo('<div class="wrapper position0_background cp_admin" title="Position 0 Hidden"><div class="position">0</div>');
				$comp = new content();
				$position = '0';
				$comp->cp_articles($articlequery, $VarFeatures, $VarPosition, $position, $this->layout, $this->limit, $Table);
				////////////////////////////////////////////////////
			echo('<div class="clear"></div><span id="msggbox_alert_'.$position.'" style="display:none;"></span>');
			echo('</div>');
		
                echo('</div>');
                echo('</div>');
		
		break;
                
       ////////////////////
			case 'index-6':
		////////////////////
		
			//column 1
			echo ('<div class="row">');        
		      echo ('<div class="col-lg-4">');
			
				echo('<div class="wrapper cp_admin" title="Position 1"><div class="position">1</div>');
					$comp = new content();
					$position = '1';
					$comp->cp_articles($articlequery, $VarFeatures, $VarPosition, $position, $this->layout, $this->limit, $Table);
					////////////////////////////////////////////////////
				echo('<span id="msggbox_alert_'.$position.'" style="display:none;"></span>');	
				echo('</div>');
			
			echo('</div>');
			
			//column 2
			echo('<div class="col-lg-8">');
			
				echo('<div class="wrapper cp_admin" title="Position 2"><div class="position">2</div>');
					$comp = new content();
					$position = '2';
					$comp->cp_articles($articlequery, $VarFeatures, $VarPosition, $position, $this->layout, $this->limit, $Table);
					////////////////////////////////////////////////////	
				echo('<span id="msggbox_alert_'.$position.'" style="display:none;"></span>');	
				echo('</div>');
			
			echo('</div>');
			echo('</div>');
			
			echo ('<div class="row">');
                echo ('<div class="col-lg-12">');
                echo('<div class="wrapper cp_admin" title="Position 3"><div class="position">2</div>');
					$comp = new content();
					$position = '3';
					$comp->cp_articles($articlequery, $VarFeatures, $VarPosition, $position, $this->layout, $this->limit, $Table);
					////////////////////////////////////////////////////	
				echo('<span id="msggbox_alert_'.$position.'" style="display:none;"></span>');	
				echo('</div>');
                echo('</div>');
           echo('</div>');
                
                
			//end column
                echo ('<div class="row">');
		echo ('<div class="col-lg-12">');
			
			echo('<div class="wrapper position0_background cp_admin" title="Position 0 Hidden"><div class="position">0</div>');
				$comp = new content();
				$position = '0';
				$comp->cp_articles($articlequery, $VarFeatures, $VarPosition, $position, $this->layout, $this->limit, $Table);
				////////////////////////////////////////////////////
			echo('<div class="clear"></div><span id="msggbox_alert_'.$position.'" style="display:none;"></span>');
			echo('</div>');
		
                echo('</div>');
                echo('</div>');
		
		break;           
                
		
		}
		
	
	/*echo ('</div>');
	echo ('</div>');*/
	}
	
}
?>
