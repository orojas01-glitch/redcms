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

#[\AllowDynamicProperties]
class content
{
    
	
	public function articles($query, $VarFeatures, $VarPosition, $position, $layout, $limit)
	{
		
		
		//echo $this->query;
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		// display all active records. Position is Required.
		$result = $db->query("SELECT * FROM RED_Articles WHERE Active='Y' AND StartDate <= NOW() AND Language='" . language . "' AND ".$VarPosition."='".$position."' ".$query." ORDER BY ".$VarPosition."Order ASC, StartDate DESC LIMIT ".$limit."");
		//echo ("SELECT * FROM RED_Articles WHERE Active='Y' AND StartDate <= NOW() AND Language='" . language . "' AND ".$VarPosition."='".$position."' ".$query." ORDER BY ".$VarPosition."Order ASC, StartDate DESC LIMIT ".$limit."");
		//echo ('ini:'. $result->num_rows);
		$result_counter = $result->num_rows;
		while($row = mysqli_fetch_assoc($result))
		{
			//echo $row['Component'];
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
            //echo $row['Component'];
			switch ($row['Component'])
			{
				//////COMPONENTS///////
				case 'Article':
				if ($ActiveDate) {
				$this->recordid=$row['RecordID'];
                $comp = new Article();
                $comp->Article($this->recordid,$layout,article,$position);
				//$comp = new article($this->recordid,$layout,article,$position);
				//echo '<div class="clear-1"></div>';
				}
				////////////////////////////////////////////////////
				break;
				
								
				
				case 'Form':
				if ($ActiveDate) {
				$this->recordid=$row['RecordID'];
				$comp = new forms();
				$comp->form($this->recordid);
				echo '<div class="clear-1"></div>';
				}
				////////////////////////////////////////////////////
				break;
				
				case 'Gallery':
				if ($ActiveDate) {
				$this->recordid=$row['RecordID'];
				$comp = new gallery();
				$comp->album($position, $this->recordid, $layout, $row['SmallPict']);
				echo '<div class="clear-1"></div>';
				}
				////////////////////////////////////////////////////
				break;
				
				
                    
                case 'Other':
				if ($ActiveDate) {
				$this->recordid=$row['RecordID'];
                $comp = new other();
				$comp->other($this->recordid,$layout,article,$position);
				
				}
				////////////////////////////////////////////////////
				break;
				
			}
			
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
	public function cp_articles($query, $VarFeatures, $VarPosition, $position, $layout, $limit, $Table)
	{
		
        //echo $this->query;
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		// display all active records. Position is Required.
		$result = $db->query("SELECT * FROM RED_Articles WHERE Active='Y' AND Language='" . language . "' AND ".$VarPosition."='".$position."' ".$query." ORDER BY ".$VarPosition."Order ASC, StartDate DESC LIMIT ".$limit."");
		//echo ("SELECT * FROM RED_Articles WHERE Active='Y' AND Language='" . language . "' AND ".$VarPosition."='".$position."' ".$query." ORDER BY ".$VarPosition."Order ASC, StartDate DESC LIMIT ".$limit."");
		//echo ($result->num_rows);
		$result_counter = $result->num_rows;
		$w=0;
		while($row = mysqli_fetch_assoc($result))
		{
			$RecordID=$row['RecordID'];
			$Alias=$row['Alias'];
			$Alias=preg_replace('/-/','_',$Alias);
			$PosOrder=$row[$VarPosition."Order"];
			
			if ($result_counter === $result->num_rows){
				if ($position!='0')
			echo '<div class="cp_titles">';
			}
			if ($position==='0'){
            	echo '<div style="float:left; padding-right:5px; margin-right:5px;">';
			}else
				$this->OrderQuery=$this->OrderQuery.'<input name="PosOrder['.$w.']" type="text" id="PosOrder['.$w.']" style="width:15px; margin-bottom:35px; " value="'.$PosOrder.'" /><input name="VarPosition['.$w.']" type="hidden" id="VarPosition['.$w.']" value="'.$VarPosition.'" /><input name="RecordID['.$w.']" type="hidden" id="RecordID['.$w.']" value="'.$RecordID.'" /><br clear="all" />';
			
			switch ($row['Component'])
			{
				
				//////COMPONENTS///////
				case 'Article':
				$this->recordid=$row['RecordID'];
                $comp=new cp_Article();
                $comp->cp_Article($position, $this->recordid, $VarPosition, $layout);
				////////////////////////////////////////////////////
				break;
				
				
				
				
								
				case 'Other':
				$this->recordid=$row['RecordID'];
                $comp=new cp_other();    
				$comp->cp_other($position, $this->recordid, $VarPosition, $layout);
				////////////////////////////////////////////////////
				break;
				
				
				case 'Form':
				$this->recordid=$row['RecordID'];
				$comp = new forms();
				$comp->cp_form($this->recordid, $VarFeatures, $VarPosition, $Table, $position, $layout);
				////////////////////////////////////////////////////
				break;
				
				case 'Gallery':
				
				$this->recordid=$row['RecordID'];
				$comp = new gallery();
				$comp->cp_album($position, $this->recordid, $layout, $VarFeatures, $VarPosition, $Table);
				////////////////////////////////////////////////////
				break;
				
				
				
				
			}
			
			if ($position==='0'){
				echo '</div>';
			}
			
		$result_counter = ($result_counter - 1);
		
		if ($result_counter === 0){
			if ($position!='0')
			echo '</div>';
		}
		$w++;
		}
		//echo 'end'. $result_counter;
		
		
		if ($this->OrderQuery!=''){
		echo '<div class="cp_update_order"><h7 id="cp">Order</h7>';
		echo '<script type="text/javascript">'. "\n";
		echo '<!--' ."\n";
		echo 'function run_update_order_'.$position.' (update_order_'.$position.')'. "\n".'{' . "\n"; 
			echo '$.ajax({ '. "\n";
			echo'type: "POST", '. "\n";
			echo 'url: "/admin/bin/update_order.php", '. "\n";
			echo 'data: $("#update_order_'.$position.'").serialize(), '. "\n";
			echo 'success: function(data) { '. "\n";
			//echo 'alert (data);'. "\n";
			//echo 'return false;'. "\n";
			//echo 'if (data)'. "\n";
			echo 'if (data==\'yes\')'. "\n"; 
			echo '{'. "\n";
			echo '$(\'#msggbox_alert_'.$position.'\').html("Order Updated")'. "\n"; 
			echo '.hide()'. "\n";
			echo '.fadeIn(1500, function() {'. "\n";
			echo '$(\'#msggbox_edit_content\');'. "\n";
			echo 'window.location.reload();'. "\n";
			echo '});'. "\n";
			echo '}'. "\n";
			echo 'else'. "\n"; 
			echo '{'. "\n";
			echo '$(\'#msggbox_alert_'.$position.'\').html("Nothing to Update. Please try again.")'. "\n";
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
					
		echo '<form id="update_order_'.$position.'" name="update_order_'.$position.'" method="post" onSubmit="return run_update_order_'.$position.'(this);">';
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
