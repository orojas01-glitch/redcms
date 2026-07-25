<?php
/**
 * Core-owned breadcrumb data for the authenticated administrator overlay.
 *
 * This intentionally ignores a standard theme's public breadcrumb setting:
 * templates may suppress public breadcrumb chrome while administrators still
 * retain a compact, useful indication of the page they are editing.
 */

require_once __DIR__ . '/public_render_helpers.php';

if (!function_exists('red_admin_breadcrumb_fallback_label')) {
    function red_admin_breadcrumb_fallback_label($alias)
    {
        $label = rawurldecode((string) $alias);
        $label = str_replace(['-', '_'], ' ', $label);
        $label = trim(preg_replace('/\s+/', ' ', $label));
        return $label !== '' ? ucwords($label) : 'Page';
    }
}

if (!function_exists('red_admin_breadcrumb_items')) {
    function red_admin_breadcrumb_items()
    {
        $section = defined('section') ? (string) section : 'home';
        $category = defined('category') ? (string) category : '';
        $subcategory = defined('subcategory') ? (string) subcategory : '';
        $article = defined('article') ? (string) article : '';
        $routeDepth = defined('countpage') ? max(2, (int) countpage) : 2;
        $hasSectionLevel = $routeDepth >= 3
            && $section !== ''
            && strtolower($section) !== 'home';
        $hasCategoryLevel = $routeDepth >= 4 && $category !== '';
        $hasSubcategoryLevel = $routeDepth >= 5 && $subcategory !== '';

        $levels = [[
            'table' => 'Sections',
            'alias' => 'Home',
            'url' => '/',
        ]];

        if ($hasSectionLevel) {
            $levels[] = [
                'table' => 'Sections',
                'alias' => $section,
                'url' => '/' . rawurlencode($section) . '/',
            ];
        }
        if ($hasCategoryLevel) {
            $levels[] = [
                'table' => 'Categories',
                'alias' => $category,
                'url' => '/' . rawurlencode($section) . '/' . rawurlencode($category) . '/',
            ];
        }
        if ($hasSubcategoryLevel) {
            $levels[] = [
                'table' => 'SubCategories',
                'alias' => $subcategory,
                'url' => '/' . rawurlencode($section) . '/' . rawurlencode($category) . '/' .
                    rawurlencode($subcategory) . '/',
            ];
        }
        if ($article !== '') {
            $articleSegments = [];
            if ($hasSectionLevel) {
                $articleSegments[] = $section;
            }
            if ($hasCategoryLevel) {
                $articleSegments[] = $category;
            }
            if ($hasSubcategoryLevel) {
                $articleSegments[] = $subcategory;
            }
            $articleSegments[] = $article;
            $levels[] = [
                'table' => 'Articles',
                'alias' => $article,
                'url' => '/' . implode('/', array_map('rawurlencode', $articleSegments)),
            ];
        }

        $connection = null;
        try {
            if (class_exists('connection')) {
                $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
                $connection = $db->connection;
            }

            $items = [];
            foreach ($levels as $level) {
                $label = $connection
                    ? red_public_plain_text(
                        red_public_breadcrumb_title($connection, $level['table'], $level['alias'])
                    )
                    : '';
                $items[] = [
                    'label' => $label !== ''
                        ? $label
                        : red_admin_breadcrumb_fallback_label($level['alias']),
                    'url' => $level['url'],
                ];
            }
        } catch (Throwable $exception) {
            error_log('Administrator breadcrumb lookup failed: ' . $exception->getMessage());
            $items = array_map(function ($level) {
                return [
                    'label' => red_admin_breadcrumb_fallback_label($level['alias']),
                    'url' => $level['url'],
                ];
            }, $levels);
        } finally {
            if (isset($db) && $db instanceof connection) {
                $db->close();
            }
        }

        $deduplicated = [];
        foreach ($items as $item) {
            $lastIndex = count($deduplicated) - 1;
            if ($lastIndex >= 0
                && strtolower(trim((string) $deduplicated[$lastIndex]['label'])) ===
                    strtolower(trim((string) $item['label']))
            ) {
                $deduplicated[$lastIndex] = $item;
                continue;
            }
            $deduplicated[] = $item;
        }

        $currentIndex = count($deduplicated) - 1;
        if ($currentIndex >= 0) {
            $deduplicated[$currentIndex]['url'] = '';
        }

        return $deduplicated;
    }
}
