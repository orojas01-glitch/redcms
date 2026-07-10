<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/admin_tool_helpers.php";
red_start_session();
red_require_admin(); ?>
<?php
#[\AllowDynamicProperties]
class edit_inactive_article
{
	public function inactive_article_form($layout)
	{
		echo '<div class="container_12 cp_padtop"><div class="wrapper"><article class="grid_12 cp_admin"><div class="scroll"><div style="padding:10px;">';
		echo '<form id="edit_inactive_article" name="edit_inactive_article" class="cp"><fieldset>';
		echo '<div class="header">';
		echo '<div class="titleleft longtitle"><strong>Article Title</strong>';
		echo '</div>';
		echo '<div class="titleleft component"><strong>Component</strong>';
		echo '</div>';
		echo '<div class="titleright editico"><strong>Edit</strong>';
		echo '</div>';
		echo '</div>';
		echo '<div class="clear-cp"></div>';
		
        $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
        $adminComponentIds = red_admin_tool_admin_component_ids($_SESSION['AdminComponents'] ?? '');
        $articles = red_admin_tool_fetch_all(
            $db->connection,
            "SELECT Alias, Title, Component, RecordID FROM RED_Articles WHERE Language=? AND Active <> 'Y' ORDER BY Updated ASC",
            's',
            [red_admin_area_language()],
            'RED_Articles inactive admin list lookup failed'
        );

        foreach($articles as $article)
        {
            $RecordID=(int) ($article['RecordID'] ?? 0);
            $Alias=red_admin_tool_js_suffix($article['Alias'] ?? '', $RecordID);
			$Title=red_admin_tool_html(preg_replace('/<[^>]*>/', '', $article['Title'] ?? ''));
            $Component=red_admin_tool_identifier($article['Component'] ?? '');
            $ComponentLabel=red_admin_tool_html($article['Component'] ?? '');

            $access = $Component !== ''
                ? red_admin_tool_component_access($db->connection, $Component, $adminComponentIds, $RecordID)
                : ['authorized' => false, 'comp_group' => '', 'component_record_id' => 0];

            if (!$access['authorized']) {
                $this->render_unauthorized_row($Alias, $Title, $ComponentLabel);
                continue;
            }

            if ($access['comp_group'] === 'Y') {
                $CRecordID = (int) $access['component_record_id'];
                if ($CRecordID <= 0) {
                    $this->render_unauthorized_row($Alias, $Title, $ComponentLabel);
                    continue;
                }

                $this->render_edit_script($Alias, $Component, $layout, true);
                $this->render_row($Alias, $Title, $ComponentLabel, 'javascript:showdiv(\'editcontent\'); edit_inactive_article_'.$Alias.'(' .$RecordID . ','.$CRecordID.');');
                continue;
            }

            $this->render_edit_script($Alias, $Component, $layout, false);
            $this->render_row($Alias, $Title, $ComponentLabel, 'javascript:showdiv(\'editcontent\'); edit_inactive_article_'.$Alias.'(' .$RecordID . ');');
        }
        $db->close();
		
		echo '</fieldset></form>';
		echo '</div></div></article></div></div>';
       
	}

    private function render_unauthorized_row($Alias, $Title, $ComponentLabel)
    {
        echo '<script type="text/javascript">'. "\n";
        echo '<!--' ."\n";
        echo 'function edit_inactive_article_'.$Alias.' (){'. "\n";
        echo '$(\'#msggbox_edit_inactive_article\').html("You\'re not authorized to edit this content.")'. "\n";
        echo '.fadeIn(1500, function() {'. "\n";
        echo '});'. "\n";
        echo 'return false;'. "\n";
        echo '}'. "\n";
        echo '-->'. "\n";
        echo '</script>';
        $this->render_row($Alias, $Title, $ComponentLabel, 'edit_inactive_article_'.$Alias.'();');
    }

    private function render_edit_script($Alias, $Component, $layout, $isGroup)
    {
        $url = json_encode('/admin/bin/edit_'.strtolower($Component).'.php');
        $layout = json_encode(red_admin_tool_text($layout));

        echo '<script language="JavaScript" type="text/javascript">'. "\n";
        echo '<!--' ."\n";
        echo 'function edit_inactive_article_'.$Alias.($isGroup ? ' (RecordID,CRecordID)' : ' (RecordID)').'{'. "\n";
        echo '$.ajax({'. "\n";
        echo 'type: "POST", '. "\n";
        echo 'url: '.$url.', '. "\n";
        if ($isGroup) {
            echo 'data: {RecordID: CRecordID, ArtRecordID: RecordID, Layout: '.$layout.'}, '. "\n";
        } else {
            echo 'data: {RecordID: RecordID, Layout: '.$layout.'}, '. "\n";
        }
        echo 'success: function(data) { '. "\n";
        echo 'if (data) '. "\n";
        echo '{'. "\n";
        echo '$(\'#edit_content_grid\').hide();'. "\n";
        echo '$(\'#msggbox_edit_content\').html(data)'. "\n";
        echo '.fadeIn(1500, function() {'. "\n";
        echo '});'. "\n";
        echo '}'. "\n";
        echo 'else '. "\n";
        echo '{'. "\n";
        echo '$("#msggbox_edit_inactive_article").html("Error. Please try again.")'. "\n";
        echo '.fadeIn(1500, function() {'. "\n";
        echo '$("#msggbox_edit_inactive_article");'. "\n";
        echo '});'. "\n";
        echo '}'. "\n";
        echo '}'. "\n";
        echo '});'. "\n";
        echo 'return false;'. "\n";
        echo '}'. "\n";
        echo '-->'. "\n";
        echo '</script>';
    }

    private function render_row($Alias, $Title, $ComponentLabel, $onClick)
    {
        echo '<div class="wrapper row2">';
        echo '<label style="display:inline;">';
        echo '<div class="titleleft longtitle">';
        echo '<strong>'.$Title.'</strong>';
        echo '</div>';
        echo '<div class="titleleft component">';
        echo $ComponentLabel;
        echo '</div>';
        echo '<div class="titleright editico">';
        echo '<img src="/admin/images/ico_edit.png" onClick="'.$onClick.'" title="Edit" style="cursor:pointer">';
        echo '</div>';
        echo '</label>';
        echo '</div>';
    }
}
