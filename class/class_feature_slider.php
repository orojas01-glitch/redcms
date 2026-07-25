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
require_once __DIR__ . '/../includes/public_theme_helpers.php';
require_once __DIR__ . '/../includes/admin_authorization_helpers.php';
require_once __DIR__ . '/../includes/admin_feature_helpers.php';

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
		$redThemeHeroContext = red_public_legacy_hero_context_from_rows($enabled, $rows);
		require __DIR__ . '/../themes/legacy-bootstrap/partials/hero.php';
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

		$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		$rows = red_admin_filter_authorized_articles($db->connection, $rows);
		$db->close();

		$alias = 'feature_slider';
		foreach ($rows as $row) {
			$alias = $this->safe_js_alias($row['Alias'] ?? $alias);
		}

		echo '<script type="text/javascript">'. "\n";
		echo '<!--' ."\n";
		echo 'function edit_feature_slider_'.$alias.' (slider_'.$alias.')'. "\n".'{' . "\n";
		echo 'if (window.redAdminOpenSliderEditor) {' . "\n";
		echo 'return window.redAdminOpenSliderEditor(slider_'.$alias.');' . "\n";
		echo '}' . "\n";
		echo 'return false;' . "\n";
		echo '}'. "\n";
		echo '-->'. "\n";
		echo '</script>';

		$selectedCount = count($rows);
		$scopeLabel = red_admin_feature_scope_label($this->VarFeatures);
		$countLabel = $selectedCount === 1 ? '1 selected slide' : $selectedCount . ' selected slides';

		echo '<section class="red-admin-slider-launcher" aria-label="'.red_public_html($scopeLabel).' hero slider">';
		echo '<div class="red-admin-slider-launcher__identity">';
		echo '<span class="red-admin-slider-launcher__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3.5" y="5" width="17" height="14" rx="2"></rect><path d="M7 15l3.2-3.2 2.7 2.4 2.6-2.2 2.5 3"></path><circle cx="8.5" cy="9" r="1.2"></circle></svg></span>';
		echo '<span class="red-admin-slider-launcher__copy">';
		echo '<span class="red-admin-slider-launcher__eyebrow">'.red_public_html($scopeLabel).' hero</span>';
		echo '<strong>Edit slider</strong>';
		echo '<small>Choose featured Articles and control their presentation order.</small>';
		echo '</span>';
		echo '</div>';
		echo '<div class="red-admin-slider-launcher__preview" aria-label="'.red_public_html($countLabel).'">';
		if ($selectedCount > 0) {
			echo '<span class="red-admin-slider-launcher__thumbnails" aria-hidden="true">';
			foreach (array_slice($rows, 0, 4) as $row) {
				$bigPict = trim((string) ($row['BigPict'] ?? ''));
				$title = red_public_html(red_public_plain_text($row['Title'] ?? ''));
				if ($bigPict !== '') {
					echo '<img src="/images/resize.php?w=72&amp;h=54&amp;img=/images/articles/'.red_public_html($bigPict).'" alt="" title="'.$title.'">';
				} else {
					echo '<span class="red-admin-slider-launcher__thumbnail-empty"><svg viewBox="0 0 24 24"><path d="M4 18l5-5 3 3 3-2 5 4"></path><circle cx="9" cy="9" r="1.5"></circle><rect x="3.5" y="4.5" width="17" height="15" rx="2"></rect></svg></span>';
				}
			}
			echo '</span>';
		} else {
			echo '<span class="red-admin-slider-launcher__empty">No slides selected yet</span>';
		}
		echo '<span class="red-admin-slider-launcher__count">'.red_public_html($countLabel).'</span>';
		echo '</div>';
		echo '<form id="slider_'.$alias.'" class="form red-admin-slider-launcher__form" name="slider_'.$alias.'" method="post" onSubmit="return edit_feature_slider_'.$alias.'(this);">';
		echo red_csrf_input();
		echo '<button type="submit" name="Edit" class="red-admin-slider-launcher__button"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M7 12h10M9 18h6"></path><circle cx="8" cy="6" r="2"></circle><circle cx="15" cy="12" r="2"></circle><circle cx="12" cy="18" r="2"></circle></svg><span>Manage slides</span></button>';
		echo '<input type="hidden" name="VarFeatures" id="VarFeatures" value="'.red_public_html($this->VarFeatures).'" />';
		echo '<input type="hidden" name="Query" id="Query" value="'.red_public_html($this->query).'" />';
		echo '<input type="hidden" name="Language" id="Language" value="'.red_public_html(red_public_language()).'" />';
		echo '</form>';
		echo '</section>';
	}
}

?>
