<?php
/**
 * Legacy public Other component view.
 *
 * Core supplies the selected article alias, exact layout dimensions, and the
 * ordered RED_Articles render rows. This view preserves the historical public
 * markup and performs no database, request, session, or component dispatch.
 */

if (!isset($redThemeOtherContext) || !is_array($redThemeOtherContext)) {
    throw new InvalidArgumentException('The legacy Other view context is unavailable.');
}

$redThemeOtherContext = red_legacy_public_other_view_context_from_data(
    $redThemeOtherContext['article'] ?? null,
    $redThemeOtherContext['dimensions'] ?? [],
    $redThemeOtherContext['rows'] ?? []
);
$article = $redThemeOtherContext['article'];
$width = $redThemeOtherContext['dimensions']['Width'];

foreach ($redThemeOtherContext['rows'] as $row) {
    if ($article <> '') {
        if ($article === $row['Alias']) {
            if ($row['SmallPict2'] != '') {
                echo '<img src="/images/articles/' . red_public_html($row['SmallPict2']) .
                    '" align="' . red_public_html($row['SmallPictAlign2']) .
                    '" title="' . red_public_display_text($row['Title']) .
                    '" class="SmallPict_' . red_public_html($row['SmallPictAlign2']) . '">';
            }
            echo($row['LongDesc']);
        } else {
            if ($row['SmallPict'] != '') {
                echo '<img src="/images/resize.php?w=' . $width .
                    '&amp;img=/images/articles/' . red_public_html($row['SmallPict']) .
                    '" align="' . red_public_html($row['SmallPictAlign']) .
                    '" title="' . red_public_display_text($row['Title']) .
                    '" class="SmallPict_' . red_public_html($row['SmallPictAlign']) . '">';
            }
            echo($row['ShortDesc']);
        }
    } else {
        if ($row['SmallPict'] != '' && $row['SmallPictAlign'] != 'Top') {
            echo '<img src="/images/resize.php?w=' . $width .
                '&amp;img=/images/articles/' . red_public_html($row['SmallPict']) .
                '" align="' . red_public_html($row['SmallPictAlign']) .
                '" title="' . red_public_display_text($row['Title']) .
                '" class="SmallPict_' . red_public_html($row['SmallPictAlign']) . '">';
        }
        echo($row['ShortDesc']);
    }
}
