<?php
if (!isset($redThemeNavigationContext)
    || !is_array($redThemeNavigationContext)
    || !isset($redThemeNavigationContext['items'])
    || !is_array($redThemeNavigationContext['items'])
) {
    throw new RuntimeException('Legacy navigation context is unavailable.');
}

$redThemeNavigationItems = $redThemeNavigationContext['items'];
if (!$redThemeNavigationItems) {
    return;
}

echo('<nav class="navbar navbar-default navbar-static-top tm_navbar clearfix" role="navigation"><ul class="nav sf-menu clearfix">');
foreach ($redThemeNavigationItems as $item) {
    $children = $item['children'];
    echo('<li class="' . red_public_html($item['itemClass']) . '">');
    if ($item['isHome']) {
        echo('<a href="' . red_public_html($item['link']) . '" target="' . red_public_html($item['newWindow']) . '"><i>Home</i><em></em>');
        if ($children) {
            echo('<span></span>');
        }
        echo('</a>');
    } else {
        echo('<a href="' . red_public_html($item['link']) . '" target="' . red_public_html($item['newWindow']) . '">' . red_public_html($item['label']));
        if ($children) {
            echo('<span></span>');
        }
        echo('</a>');
    }

    if ($children) {
        echo('<ul class="submenu">');
        foreach ($children as $child) {
            $grandchildren = $child['children'];
            if ($grandchildren) {
                echo('<li><a href="' . red_public_html($child['link']) . '" target="' . red_public_html($child['newWindow']) . '">' . red_public_html($child['label']) . '<span></span></a>');
                echo('<ul class="submenu">');
                foreach ($grandchildren as $grandchild) {
                    echo('<li><a href="' . red_public_html($grandchild['link']) . '" target="' . red_public_html($grandchild['newWindow']) . '">' . red_public_html($grandchild['label']) . '</a></li>');
                }
                echo('<li class="tr"></li></ul></li>');
            } else {
                echo('<li><a href="' . red_public_html($child['link']) . '" target="' . red_public_html($child['newWindow']) . '">' . red_public_html($child['label']) . '</a></li>');
            }
        }
        echo('</ul>');
    }

    echo('</li>');
}
echo('</ul></nav>');
