<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/admin_tool_helpers.php";
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/admin_list_ui_helpers.php";
red_start_session();
red_require_admin(); ?>
<?php
#[\AllowDynamicProperties]
class edit_inactive_article
{
	public function inactive_article_form($layout)
	{
        $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
        $adminComponentIds = red_admin_tool_admin_component_ids($_SESSION['AdminComponents'] ?? '');
        $articles = red_admin_tool_fetch_all(
            $db->connection,
            "SELECT Alias, Title, Component, RecordID FROM RED_Articles WHERE Language=? AND Active <> 'Y' ORDER BY Updated ASC",
            's',
            [red_admin_area_language()],
            'RED_Articles inactive admin list lookup failed'
        );
        $visibleArticles = [];

        foreach($articles as $article)
        {
            $RecordID=(int) ($article['RecordID'] ?? 0);
            $Alias=red_admin_tool_js_suffix($article['Alias'] ?? '', $RecordID);
			$TitleText=red_admin_tool_text(preg_replace('/<[^>]*>/', '', $article['Title'] ?? ''));
			if ($TitleText === '') {
				$TitleText = 'Untitled article';
			}
            $Component=red_admin_tool_identifier($article['Component'] ?? '');

            $access = $Component !== ''
                ? red_admin_tool_component_access($db->connection, $Component, $adminComponentIds, $RecordID)
                : ['authorized' => false, 'comp_group' => '', 'component_record_id' => 0, 'subtype' => ''];

            if (!$access['authorized']) {
                continue;
            }

			$Subtype = red_admin_tool_text($access['subtype'] ?? '');
			$SubtypeKey = strtolower($Subtype);
			$ComponentKey = strtolower($Component);
			$ComponentLabel = $Component;

			if ($Component === 'Gallery') {
				$ComponentKey = in_array($SubtypeKey, ['banner', 'gallery', 'video'], true) ? $SubtypeKey : 'gallery';
				$ComponentLabel = ucfirst($ComponentKey);
			} elseif ($Component === 'Form') {
				$ComponentKey = $SubtypeKey === 'login' ? 'form-login' : 'form-builder';
				$ComponentLabel = $SubtypeKey === 'login'
					? 'Admin Login'
					: 'Form'.($Subtype !== '' ? ' · '.$Subtype : '');
			} elseif (!in_array($ComponentKey, ['article', 'other'], true)) {
				$ComponentKey = 'default';
			}

            if ($access['comp_group'] === 'Y') {
                $CRecordID = (int) $access['component_record_id'];
                if ($CRecordID <= 0) {
                    continue;
                }

                $this->render_edit_script($Alias, $Component, $layout, true);
				$visibleArticles[] = [
					'title' => $TitleText,
					'component' => $ComponentLabel,
					'key' => $ComponentKey,
					'onclick' => 'showdiv(\'editcontent\'); edit_inactive_article_'.$Alias.'('.$RecordID.','.$CRecordID.');',
				];
                continue;
            }

            $this->render_edit_script($Alias, $Component, $layout, false);
			$visibleArticles[] = [
				'title' => $TitleText,
				'component' => $ComponentLabel,
				'key' => $ComponentKey,
				'onclick' => 'showdiv(\'editcontent\'); edit_inactive_article_'.$Alias.'('.$RecordID.');',
			];
        }
        $db->close();

		echo '<div class="container_12 cp_padtop red-admin-area-list-container"><div class="wrapper"><article class="grid_12 cp_admin"><div class="red-admin-area-list-shell">';
		echo '<form id="edit_inactive_article" name="edit_inactive_article" class="cp red-admin-area-list-form"><fieldset>';
		echo '<div class="red-admin-area-list red-admin-area-list--inactive" data-red-admin-list="inactive-articles" role="table" aria-label="Inactive articles">';
		echo '<div class="red-admin-area-list__header" role="row">';
		echo '<div class="red-admin-area-list__cell red-admin-area-list__cell--primary" role="columnheader">Inactive article <span class="red-admin-area-list__count">'.red_admin_list_ui_html(red_admin_list_ui_item_count(count($visibleArticles))).'</span></div>';
		echo '<div class="red-admin-area-list__cell red-admin-area-list__cell--layout" role="columnheader">Component</div>';
		echo '<div class="red-admin-area-list__cell red-admin-area-list__cell--status" role="columnheader">Status</div>';
		echo '<div class="red-admin-area-list__cell red-admin-area-list__cell--action" role="columnheader">Action</div>';
		echo '</div>';

		if (count($visibleArticles) === 0) {
			echo '<div class="red-admin-area-list__empty" role="row"><div role="cell"><strong>No inactive articles</strong><span>Content marked inactive will appear here when it needs review.</span></div></div>';
		}

		foreach ($visibleArticles as $visibleArticle) {
			$Title = red_admin_list_ui_html($visibleArticle['title']);
			$ComponentLabel = red_admin_list_ui_html($visibleArticle['component']);
			$ComponentKey = preg_replace('/[^a-z0-9-]+/', '-', (string) $visibleArticle['key']);
			$EditLabel = 'Edit inactive '.$visibleArticle['component'].': '.$visibleArticle['title'];

			echo '<div class="red-admin-area-list__row red-admin-area-list__row--'.$ComponentKey.'" role="row">';
			echo '<div class="red-admin-area-list__cell red-admin-area-list__cell--primary" role="cell"><span class="red-admin-area-list__title">'.$Title.'</span></div>';
			echo '<div class="red-admin-area-list__cell red-admin-area-list__cell--layout" role="cell" data-label="Component"><span class="red-admin-area-list__component">'.$ComponentLabel.'</span></div>';
			echo '<div class="red-admin-area-list__cell red-admin-area-list__cell--status" role="cell" data-label="Status">'.red_admin_list_ui_status('N').'</div>';
			echo '<div class="red-admin-area-list__cell red-admin-area-list__cell--action" role="cell">'.red_admin_list_ui_action_button($visibleArticle['onclick'], $EditLabel).'</div>';
			echo '</div>';
		}

		echo '</div></fieldset></form>';
		echo '</div></article></div></div>';
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

}
