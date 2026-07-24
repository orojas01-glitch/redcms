<?php
/**
 * Legacy public Article component view.
 *
 * Core supplies the current URL, selected article alias, exact layout
 * dimensions, ordered RED_Articles render rows, and derived link/target state.
 * This view preserves the historical public markup and performs no database,
 * request, session, route, or component-dispatch work.
 */

if (!isset($redThemeArticleContext) || !is_array($redThemeArticleContext)) {
    throw new InvalidArgumentException('The legacy Article view context is unavailable.');
}

$redThemeArticleContext = red_legacy_public_article_view_context_validate($redThemeArticleContext);
$url = $redThemeArticleContext['url'];
$width = $redThemeArticleContext['dimensions']['Width'];

foreach ($redThemeArticleContext['rows'] as $preparedRow) {
    $row = $preparedRow['record'];
    $selected = $preparedRow['selected'];
    $linked = $preparedRow['closeLine']['linked'];
    $link = $preparedRow['closeLine']['href'];
    $targetAttr = $preparedRow['closeLine']['target'];

    if ($linked) {
        $closeline = '<a href="' . red_public_html($link) . '" target="' .
            red_public_html($targetAttr) .
            '" class="btn-default btn5">Leer m&aacute;s</a><div class="clear-1"></div>';
    } else {
        $closeline = '<div class="clear-1"></div>';
    }

    if ($selected) {
        $title = '<h2>' . red_public_display_text($row['Title']) . '</h2>';
        $image = '<figure><img src="/images/articles/' . red_public_html($row['SmallPict2']) .
            '" align="' . red_public_html($row['SmallPictAlign2']) .
            '" title="' . red_public_display_text($row['Title']) .
            '" class="SmallPict_' . red_public_html($row['SmallPictAlign2']) . '"></figure>';
    } else {
        if ($linked) {
            $title = '<h2><a href="' . red_public_html($link) . '" target="' .
                red_public_html($targetAttr) . '" class="link-article">' .
                red_public_display_text($row['Title']) . '</a></h2>';
            $image = '<figure><a href="' . red_public_html($link) . '" target="' .
                red_public_html($targetAttr) . '"><img src="/images/articles/' .
                red_public_html($row['SmallPict']) . '" align="' .
                red_public_html($row['SmallPictAlign']) . '" title="' .
                red_public_display_text($row['Title']) . '" class="SmallPict_' .
                red_public_html($row['SmallPictAlign']) .
                '" border="0" style="margin-bottom:20px;"></a></figure>';
        } else {
            $title = '<h2>' . red_public_display_text($row['Title']) . '</h2>';
            $image = '<figure><img src="/images/resize.php?w=' . $width .
                '&amp;img=/images/articles/' . red_public_html($row['SmallPict']) .
                '" align="' . red_public_html($row['SmallPictAlign']) .
                '" title="' . red_public_display_text($row['Title']) .
                '" class="SmallPict_' . red_public_html($row['SmallPictAlign']) .
                '" border="0"></figure>';
        }
    }

    if ($selected) {
        echo '<div class="thumb-pad3 clearfix">';
        echo '<div class="thumbnail">';
        echo '<div class="badgeBox">';
        echo $title;
        if ($row['SmallPict2'] != '') {
            echo $image;
        }
        echo $row['LongDesc'];
        echo '<div class="clear-1"></div>';
        echo '<div class="fb-like" data-href="' . $url .
            '" data-width="500" data-layout="" data-action="" data-size="" data-share="true"></div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    } else {
        echo '<div class="thumb-pad6 clearfix">';
        echo '<div class="thumbnail">';
        echo '<div class="badgeBox">';
        echo '<div class="caption <!--maxheight-->">';
        echo $title;
        if ($row['SmallPict'] != '') {
            echo $image;
        }
        echo $row['ShortDesc'];
        echo '</div>';
        echo $closeline;
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
}
