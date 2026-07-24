<?php
/**
 * Compatibility helpers for public URLs inherited from earlier RED-CMS sites.
 */

if (!function_exists('red_public_route_article_alias')) {
    function red_public_route_article_alias($segment, $segmentCount)
    {
        $segment = is_string($segment) ? $segment : '';
        if ((int) $segmentCount !== 2
            || preg_match('/\A([A-Za-z0-9][A-Za-z0-9_-]*)\.html\z/i', $segment, $matches) !== 1
        ) {
            return $segment;
        }

        $alias = (string) $matches[1];
        return strtolower($alias) === 'index' ? '' : $alias;
    }
}
