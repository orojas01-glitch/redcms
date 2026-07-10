<?php
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
require_once __DIR__ . '/../includes/public_render_helpers.php';

#[\AllowDynamicProperties]
class feature_slider
{
	private function load_query_context()
	{
		$tquery = new Build_Query();
		$rquery = $tquery->get_query();
		$this->query = $rquery[0];
		$this->VarPosition = $rquery[1];
		$this->VarFeatures = $rquery[2];
		$this->metaquery = $rquery[3];
		$this->Table = $rquery[4];

		return !empty($this->Table);
	}

	private function is_feature_context()
	{
		return !empty($this->Table) && $this->Table !== 'Articles';
	}

	private function is_article_active(array $row)
	{
		if (empty($row['ExpDate']) || $row['ExpDate'] === '0000-00-00 00:00:00') {
			return true;
		}

		date_default_timezone_set('America/New_York');
		return $row['ExpDate'] >= date('Y-m-d H:i:s');
	}

	private function article_link(array $row)
	{
		$link = (string) ($row['Link'] ?? '');

		if (!empty($row['LongDesc'])) {
			$link = (string) ($row['Alias'] ?? '');

			if (!empty($row['SubCategories'])) {
				$link = $row['SubCategories'] . '/' . $link;
			}
			if (!empty($row['Categories'])) {
				$link = $row['Categories'] . '/' . $link;
			}
			if (!empty($row['Sections']) && $row['Sections'] !== 'home') {
				$link = $row['Sections'] . '/' . $link;
			} else {
				$link = '/' . $link;
			}
		}

		if (!empty($row['Link'])) {
			$link = (string) $row['Link'];
		}

		return $link;
	}

	private function safe_js_alias($alias)
	{
		$alias = str_replace('-', '_', (string) $alias);
		$alias = preg_replace('/[^A-Za-z0-9_]/', '_', $alias);
		return $alias !== '' ? $alias : 'feature_slider';
	}

	private function load_slider_rows()
	{
		if (!$this->is_feature_context()) {
			return [false, []];
		}

		$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		$enabled = red_public_feature_enabled($db->connection, $this->Table, 'slider');
		$rows = $enabled ? red_public_feature_articles($db->connection, $this->VarFeatures, 'slider', 5) : [];
		$db->close();

		return [$enabled, $rows];
	}

	public function slider()
	{
		if (!$this->load_query_context()) {
			return;
		}

		list($enabled, $rows) = $this->load_slider_rows();
		if (!$enabled) {
			return;
		}

		echo('<div class="container"><div class="row"><article class="col-lg-12 col-md-12 col-sm-12 slider"><div class="camera_wrap">');

		foreach ($rows as $row) {
			if (!$this->is_article_active($row)) {
				continue;
			}

			$link = $this->article_link($row);
			$targetAttr = (($row['NewWindow'] ?? '') === 'Y') ? '_blank' : '_self';
			$bigPict = (string) ($row['BigPict'] ?? '');
			$title = red_public_html(red_public_plain_text($row['Title'] ?? ''));
			$sliderDesc = red_public_html(red_public_plain_text($row['SliderDesc'] ?? ''));

			echo(
				'<div data-src="images/articles/' . red_public_html($bigPict) . '" title="' . $title . '">' .
				'<div class="camera-caption fadeIn"></div><div class="camera_caption"><p>' . $sliderDesc . '</p>' .
				'<a href="' . red_public_html($link) . '" target="' . red_public_html($targetAttr) . '" class="btn-default btn2">leer más</a></div></div>'
			);
		}

		echo('</div></article></div></div>');
	}

	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	//////////////////////////////////////////////CONTROL PANEL//////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////

	public function cp_slider()
	{
		if (!$this->load_query_context()) {
			return;
		}

		list($enabled, $rows) = $this->load_slider_rows();
		if (!$enabled) {
			return;
		}

		echo ('<div class="container_12 cp_padtop">');
		echo ('<article class="grid_12 cp_admin" style="text-align:center">');

		$alias = 'feature_slider';
		foreach ($rows as $row) {
			$alias = $this->safe_js_alias($row['Alias'] ?? $alias);
			$bigPict = (string) ($row['BigPict'] ?? '');
			$title = red_public_html(red_public_plain_text($row['Title'] ?? ''));

			echo '<div style="float:left; padding-right:2px; margin-right:2px;">';
			echo '<img src="/images/resize.php?w=57&amp;h=41&amp;img=/images/articles/' . red_public_html($bigPict) . '" title="' . $title . '"><br/>';
			echo '</div>';
		}

		echo ('<div class="clear"></div>');
		echo '<script type="text/javascript">'. "\n";
		echo '<!--' ."\n";
		echo 'function edit_feature_slider_'.$alias.' (slider_'.$alias.')'. "\n".'{' . "\n";
		echo '$.ajax({ '. "\n";
		echo'type: "POST", '. "\n";
		echo 'url: "/admin/bin/edit_feature_slider.php", '. "\n";
		echo 'cache: false,'. "\n";
		echo 'data: $("#slider_'.$alias.'").serialize(), '. "\n";
		echo 'success: function(data) { '. "\n";
		echo 'if (data)'. "\n";
		echo '{'. "\n";
		echo '$(\'#edit_content_grid\').hide();'. "\n";
		echo '$(\'#msggbox_edit_content\').html(data)'. "\n";
		echo '.fadeIn(1500, function() {'. "\n";
		echo '$(\'#message_'.$alias.'\');'. "\n";
		echo '});'. "\n";
		echo '}'. "\n";
		echo 'else'. "\n";
		echo '{'. "\n";
		echo '$(\'#msggbox_edit_content\').html("error.")'. "\n";
		echo '.append("<p>Please try again.</p>")'. "\n";
		echo '.hide()'. "\n";
		echo '.fadeIn(1500, function() {'. "\n";
		echo '$(\'#message_'.$alias.'\');'. "\n";
		echo '});'. "\n";
		echo '}'. "\n";
		echo '}'. "\n";
		echo '});'. "\n";
		echo 'return false;'. "\n";
		echo '}'. "\n";
		echo '-->'. "\n";
		echo '</script>';

		echo '<form id="slider_'.$alias.'" class="form" name="slider_'.$alias.'" method="post" onSubmit="return edit_feature_slider_'.$alias.'(this);">';
		echo red_csrf_input();
		echo '<input type="submit" name="Edit" id="cp" value="Edit Slider"/>';
		echo '<input type="hidden" name="VarFeatures" id="VarFeatures" value="'.red_public_html($this->VarFeatures).'" />';
		echo '<input type="hidden" name="Query" id="Query" value="'.red_public_html($this->query).'" />';
		echo '<input type="hidden" name="Language" id="Language" value="'.red_public_html(red_public_language()).'" />';
		echo '</form>';

		echo ('</article>');
		echo ('</div>');
	}
}

?>
