<?php
require_once __DIR__.'/video_url_helpers.php';
require_once __DIR__ . '/addon_component_render_helpers.php';
require_once __DIR__ . '/addon_component_persistence_helpers.php';
require_once __DIR__ . '/addon_component_editor_authorization_helpers.php';
require_once __DIR__ . '/admin_authorization_helpers.php';
/**
 * Prepared inputs and fixed core dispatch for the legacy public components.
 *
 * The prepared context remains non-executable. The renderer registry below is
 * owned by core code and preserves the existing Gallery class call plus the
 * manifest-declared legacy Article, Form, and Other views. Form template,
 * alias, and action inputs plus Gallery media inputs are prepared here without
 * making them executable; manifests, requests, and database rows cannot supply
 * an executable class, method, renderer, or callback.
 */

if (!function_exists('red_legacy_public_component_input_inventory')) {
    function red_legacy_public_component_input_inventory()
    {
        static $inventory = [
            'Article' => ['recordId', 'layout', 'article', 'position'],
            'Form' => ['recordId'],
            'Gallery' => ['position', 'recordId', 'layout', 'smallPicture'],
            'Other' => ['recordId', 'layout', 'article', 'position'],
        ];

        return $inventory;
    }
}

if (!function_exists('red_legacy_public_component_context')) {
    function red_legacy_public_component_context(
        array $row,
        $layout,
        $article,
        $position,
        $active,
        $connection = null
    )
    {
        $component = isset($row['Component']) ? (string) $row['Component'] : '';
        $inventory = red_legacy_public_component_input_inventory();
        if (!isset($inventory[$component])) {
            if ($connection !== null
                && red_addon_component_persistence_binding(
                    $connection,
                    array_key_exists('RecordID', $row) ? $row['RecordID'] : null,
                    $component
                ) === null
            ) {
                return null;
            }
            return red_addon_public_component_context(
                $component,
                array_key_exists('RecordID', $row) ? $row['RecordID'] : null,
                $layout,
                $article,
                $position,
                (bool) $active
            );
        }

        $availableInputs = [
            'recordId' => array_key_exists('RecordID', $row) ? $row['RecordID'] : null,
            'layout' => $layout,
            'article' => $article,
            'position' => $position,
            'smallPicture' => array_key_exists('SmallPict', $row) ? $row['SmallPict'] : null,
        ];
        $inputs = [];
        foreach ($inventory[$component] as $inputName) {
            $inputs[$inputName] = $availableInputs[$inputName];
        }

        return [
            'component' => $component,
            'active' => (bool) $active,
            'inputs' => $inputs,
        ];
    }
}

if (!function_exists('red_legacy_control_panel_component_input_inventory')) {
    function red_legacy_control_panel_component_input_inventory()
    {
        static $inventory = [
            'Article' => ['position', 'recordId', 'varPosition', 'layout'],
            'Other' => ['position', 'recordId', 'varPosition', 'layout'],
            'Form' => ['recordId', 'varFeatures', 'varPosition', 'table', 'position', 'layout'],
            'Gallery' => ['position', 'recordId', 'layout', 'varFeatures', 'varPosition', 'table'],
        ];

        return $inventory;
    }
}

if (!function_exists('red_legacy_control_panel_component_context_from_data')) {
    function red_legacy_control_panel_component_context_from_data(
        array $row,
        $varFeatures,
        $varPosition,
        $position,
        $layout,
        $table,
        $authorized,
        $orderIndex
    ) {
        if (!is_bool($authorized) || !is_int($orderIndex) || $orderIndex < 0) {
            throw new InvalidArgumentException('Invalid legacy control-panel component state.');
        }

        if (!$authorized) {
            return [
                'authorized' => false,
                'component' => null,
                'supported' => false,
                'alias' => null,
                'order' => null,
                'inputs' => [],
            ];
        }

        if (!is_string($varPosition) || $varPosition === '') {
            throw new InvalidArgumentException('Invalid legacy control-panel position field.');
        }
        $orderField = $varPosition . 'Order';
        foreach (['Component', 'RecordID', 'Alias', $orderField] as $requiredField) {
            if (!array_key_exists($requiredField, $row)) {
                throw new InvalidArgumentException('Incomplete legacy control-panel component row.');
            }
        }

        $component = (string) $row['Component'];
        $recordId = $row['RecordID'];
        $inventory = red_legacy_control_panel_component_input_inventory();
        $availableInputs = [
            'position' => $position,
            'recordId' => $recordId,
            'varPosition' => $varPosition,
            'layout' => $layout,
            'varFeatures' => $varFeatures,
            'table' => $table,
        ];
        $inputs = [];
        if (isset($inventory[$component])) {
            foreach ($inventory[$component] as $inputName) {
                $inputs[$inputName] = $availableInputs[$inputName];
            }
        }

        return [
            'authorized' => true,
            'component' => $component,
            'supported' => isset($inventory[$component]),
            'alias' => preg_replace('/-/', '_', (string) $row['Alias']),
            'order' => [
                'index' => $orderIndex,
                'value' => $row[$orderField],
                'varPosition' => $varPosition,
                'recordId' => $recordId,
            ],
            'inputs' => $inputs,
        ];
    }
}

if (!function_exists('red_legacy_control_panel_component_context')) {
    function red_legacy_control_panel_component_context(
        $connection,
        array $row,
        $varFeatures,
        $varPosition,
        $position,
        $layout,
        $table,
        $orderIndex
    ) {
        if (!function_exists('red_admin_article_access_allowed')) {
            throw new RuntimeException('The legacy control-panel authorization helper is unavailable.');
        }

        $component = is_string($row['Component'] ?? null)
            ? (string) $row['Component']
            : '';
        $legacyInventory = red_legacy_control_panel_component_input_inventory();
        if (!isset($legacyInventory[$component])) {
            $recordId = (int) ($row['RecordID'] ?? 0);
            $binding = red_addon_component_persistence_binding(
                $connection,
                $recordId,
                $component
            );
            $manifest = is_array($binding)
                ? red_addon_runtime_manifest($binding['package'] ?? '')
                : null;
            $authorized = is_array($manifest);
            foreach (['view', 'edit'] as $operation) {
                $decision = $authorized
                    ? red_addon_component_editor_permission_decision(
                        $connection,
                        $manifest,
                        $component,
                        $operation,
                        (int) ($_SESSION['AdminRecordID'] ?? 0)
                    )
                    : ['authorized' => false];
                if (empty($decision['authorized'])) {
                    $authorized = false;
                    break;
                }
            }
            $context = red_legacy_control_panel_component_context_from_data(
                $row,
                $varFeatures,
                $varPosition,
                $position,
                $layout,
                $table,
                $authorized,
                $orderIndex
            );
            if ($context['authorized']) {
                $context['supported'] = true;
                $context['inputs'] = ['recordId' => $recordId];
            }
            return $context;
        }

        $authorized = red_admin_article_access_allowed(
            $connection,
            (int) ($row['RecordID'] ?? 0)
        );

        return red_legacy_control_panel_component_context_from_data(
            $row,
            $varFeatures,
            $varPosition,
            $position,
            $layout,
            $table,
            (bool) $authorized,
            $orderIndex
        );
    }
}

if (!function_exists('red_legacy_public_article_row_inventory')) {
    function red_legacy_public_article_row_inventory()
    {
        static $inventory = [
            'RecordID',
            'Alias',
            'Title',
            'ShortDesc',
            'LongDesc',
            'Link',
            'NewWindow',
            'Component',
            'Sections',
            'Categories',
            'SubCategories',
            'SmallPict',
            'SmallPictAlign',
            'SmallPict2',
            'SmallPictAlign2',
        ];

        return $inventory;
    }
}

if (!function_exists('red_legacy_public_article_view_context_from_data')) {
    function red_legacy_public_article_view_context_from_data(
        $url,
        $article,
        array $dimensions,
        array $rows
    ) {
        $dimensionKeys = ['Width', 'WidthDivisor', 'Height', 'vWidth', 'vHeight'];
        if (!is_string($url)
            || !is_string($article)
            || array_keys($dimensions) !== $dimensionKeys
        ) {
            throw new InvalidArgumentException('Invalid legacy Article view data.');
        }
        foreach ($dimensionKeys as $dimensionKey) {
            if (!is_int($dimensions[$dimensionKey]) && !is_float($dimensions[$dimensionKey])) {
                throw new InvalidArgumentException('Invalid legacy Article layout dimensions.');
            }
        }

        $rowKeys = red_legacy_public_article_row_inventory();
        $preparedRows = [];
        foreach ($rows as $row) {
            if (!is_array($row) || array_keys($row) !== $rowKeys) {
                throw new InvalidArgumentException('Invalid legacy Article row.');
            }

            $linked = $row['LongDesc'] != '' || $row['Link'] != '' || $row['Component'] == 'Article';
            $href = '';
            if ($linked) {
                $href = (string) $row['Alias'];
                if ($row['SubCategories']) {
                    $href = $row['SubCategories'] . '/' . $href;
                }
                if ($row['Categories']) {
                    $href = $row['Categories'] . '/' . $href;
                }
                if ($row['Sections'] != 'home') {
                    $href = $row['Sections'] . '/' . $href;
                }
                $href = '/' . $href;
                if ($row['Link']) {
                    $href = (string) $row['Link'];
                }
            }

            $preparedRows[] = [
                'record' => $row,
                'selected' => $article === $row['Alias'],
                'closeLine' => [
                    'linked' => $linked,
                    'href' => $href,
                    'target' => $row['NewWindow'] === 'Y' ? '_blank' : '_self',
                ],
            ];
        }

        return [
            'url' => $url,
            'article' => $article,
            'dimensions' => $dimensions,
            'rows' => $preparedRows,
        ];
    }
}

if (!function_exists('red_legacy_public_article_view_context_validate')) {
    function red_legacy_public_article_view_context_validate($context)
    {
        if (!is_array($context)
            || array_keys($context) !== ['url', 'article', 'dimensions', 'rows']
            || !is_array($context['dimensions'])
            || !is_array($context['rows'])
        ) {
            throw new InvalidArgumentException('Invalid legacy Article view context.');
        }

        $sourceRows = [];
        foreach ($context['rows'] as $preparedRow) {
            if (!is_array($preparedRow)
                || array_keys($preparedRow) !== ['record', 'selected', 'closeLine']
                || !is_array($preparedRow['record'])
            ) {
                throw new InvalidArgumentException('Invalid prepared legacy Article row.');
            }
            $sourceRows[] = $preparedRow['record'];
        }

        $prepared = red_legacy_public_article_view_context_from_data(
            $context['url'],
            $context['article'],
            $context['dimensions'],
            $sourceRows
        );
        if ($prepared !== $context) {
            throw new InvalidArgumentException('Legacy Article view context does not match its source data.');
        }

        return $prepared;
    }
}

if (!function_exists('red_legacy_public_article_view_context')) {
    function red_legacy_public_article_view_context($recordId, $layout, $article, $position, $url)
    {
        if (!class_exists('connection')
            || !function_exists('red_public_layout_dimensions')
            || !function_exists('red_public_article_render_rows')
        ) {
            throw new RuntimeException('Legacy Article view preparation dependencies are unavailable.');
        }

        $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
        try {
            $dimensions = red_public_layout_dimensions($db->connection, $layout, $position);
            $rows = red_public_article_render_rows($db->connection, $recordId);
        } finally {
            $db->close();
        }

        return red_legacy_public_article_view_context_from_data(
            (string) $url,
            (string) $article,
            $dimensions,
            $rows
        );
    }
}

if (!function_exists('red_legacy_public_form_row_inventory')) {
    function red_legacy_public_form_row_inventory()
    {
        static $inventory = [
            'RecordID',
            'RefID',
            'Alias',
            'Title',
            'FormType',
            'LongDesc',
        ];

        return $inventory;
    }
}

if (!function_exists('red_legacy_public_form_template_fields')) {
    function red_legacy_public_form_template_fields($template)
    {
        if (!is_string($template)) {
            throw new InvalidArgumentException('Invalid legacy Form template.');
        }

        $fields = [];
        foreach (explode(';', $template) as $fieldSource) {
            $field = [];
            foreach (explode('|', $fieldSource) as $valueSource) {
                $parts = explode('=', $valueSource, 2);
                $field[$parts[0]] = isset($parts[1]) ? $parts[1] : '';
            }
            $fields[] = $field;
        }

        return $fields;
    }
}

if (!function_exists('red_legacy_public_form_alias_inputs')) {
    function red_legacy_public_form_alias_inputs($alias)
    {
        $rawAlias = (string) $alias;
        $javascriptAlias = preg_replace('/[^A-Za-z0-9_]+/', '_', $rawAlias);
        $javascriptAlias = trim($javascriptAlias, '_');
        if ($javascriptAlias === '') {
            $javascriptAlias = 'form';
        }
        if (!preg_match('/^[A-Za-z_]/', $javascriptAlias)) {
            $javascriptAlias = 'form_' . $javascriptAlias;
        }

        return [
            'raw' => $rawAlias,
            'javascript' => $javascriptAlias,
        ];
    }
}

if (!function_exists('red_legacy_public_form_action_inputs')) {
    function red_legacy_public_form_action_inputs($formType)
    {
        $formType = (string) $formType;
        static $actions = [
            'Contact' => [
                'endpoint' => '/bin/contact.php',
                'payloadMode' => 'serialized-form',
            ],
            'Login' => [
                'endpoint' => '/bin/login.php',
                'payloadMode' => 'data-string',
            ],
            'Response' => [
                'endpoint' => '/bin/response.php',
                'payloadMode' => 'serialized-form',
            ],
            'Register' => [
                'endpoint' => '/bin/register.php',
                'payloadMode' => 'serialized-form',
            ],
        ];

        $action = $actions[$formType] ?? [
            'endpoint' => '',
            'payloadMode' => 'native-submit',
        ];

        return [
            'formType' => $formType,
            'endpoint' => $action['endpoint'],
            'payloadMode' => $action['payloadMode'],
        ];
    }
}

if (!function_exists('red_legacy_public_form_context_from_data')) {
    function red_legacy_public_form_context_from_data(array $rows)
    {
        $rowKeys = red_legacy_public_form_row_inventory();
        $preparedRows = [];
        foreach ($rows as $row) {
            if (!is_array($row) || array_keys($row) !== $rowKeys) {
                throw new InvalidArgumentException('Invalid legacy Form row.');
            }

            $preparedRows[] = [
                'record' => $row,
                'fields' => red_legacy_public_form_template_fields($row['LongDesc']),
                'alias' => red_legacy_public_form_alias_inputs($row['Alias']),
                'action' => red_legacy_public_form_action_inputs($row['FormType']),
            ];
        }

        return ['rows' => $preparedRows];
    }
}

if (!function_exists('red_legacy_public_form_context_validate')) {
    function red_legacy_public_form_context_validate($context)
    {
        if (!is_array($context)
            || array_keys($context) !== ['rows']
            || !is_array($context['rows'])
        ) {
            throw new InvalidArgumentException('Invalid legacy Form view context.');
        }

        $sourceRows = [];
        foreach ($context['rows'] as $preparedRow) {
            if (!is_array($preparedRow)
                || array_keys($preparedRow) !== ['record', 'fields', 'alias', 'action']
                || !is_array($preparedRow['record'])
            ) {
                throw new InvalidArgumentException('Invalid prepared legacy Form row.');
            }
            $sourceRows[] = $preparedRow['record'];
        }

        $prepared = red_legacy_public_form_context_from_data($sourceRows);
        if ($prepared !== $context) {
            throw new InvalidArgumentException('Legacy Form view context does not match its source data.');
        }

        return $prepared;
    }
}

if (!function_exists('red_legacy_public_form_context')) {
    function red_legacy_public_form_context($recordId)
    {
        if (!class_exists('connection') || !function_exists('red_public_form_rows')) {
            throw new RuntimeException('Legacy Form preparation dependencies are unavailable.');
        }

        $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
        try {
            $rows = red_public_form_rows($db->connection, $recordId);
        } finally {
            $db->close();
        }

        return red_legacy_public_form_context_from_data($rows);
    }
}

if (!function_exists('red_legacy_public_other_row_inventory')) {
    function red_legacy_public_other_row_inventory()
    {
        static $inventory = [
            'RecordID',
            'Alias',
            'Title',
            'ShortDesc',
            'LongDesc',
            'Link',
            'NewWindow',
            'Component',
            'Sections',
            'Categories',
            'SubCategories',
            'SmallPict',
            'SmallPictAlign',
            'SmallPict2',
            'SmallPictAlign2',
        ];

        return $inventory;
    }
}

if (!function_exists('red_legacy_public_other_view_context_from_data')) {
    function red_legacy_public_other_view_context_from_data($article, array $dimensions, array $rows)
    {
        $dimensionKeys = ['Width', 'WidthDivisor', 'Height', 'vWidth', 'vHeight'];
        if (!is_string($article) || array_keys($dimensions) !== $dimensionKeys) {
            throw new InvalidArgumentException('Invalid legacy Other view data.');
        }
        foreach ($dimensionKeys as $dimensionKey) {
            if (!is_int($dimensions[$dimensionKey]) && !is_float($dimensions[$dimensionKey])) {
                throw new InvalidArgumentException('Invalid legacy Other layout dimensions.');
            }
        }

        $rowKeys = red_legacy_public_other_row_inventory();
        foreach ($rows as $row) {
            if (!is_array($row) || array_keys($row) !== $rowKeys) {
                throw new InvalidArgumentException('Invalid legacy Other article row.');
            }
        }

        return [
            'article' => $article,
            'dimensions' => $dimensions,
            'rows' => array_values($rows),
        ];
    }
}

if (!function_exists('red_legacy_public_other_view_context')) {
    function red_legacy_public_other_view_context($recordId, $layout, $article, $position)
    {
        if (!class_exists('connection')
            || !function_exists('red_public_layout_dimensions')
            || !function_exists('red_public_article_render_rows')
        ) {
            throw new RuntimeException('Legacy Other view preparation dependencies are unavailable.');
        }

        $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
        try {
            $dimensions = red_public_layout_dimensions($db->connection, $layout, $position);
            $rows = red_public_article_render_rows($db->connection, $recordId);
        } finally {
            $db->close();
        }

        return red_legacy_public_other_view_context_from_data($article, $dimensions, $rows);
    }
}

if (!function_exists('red_legacy_public_gallery_row_inventory')) {
    function red_legacy_public_gallery_row_inventory()
    {
        static $inventory = [
            'RecordID',
            'RefID',
            'Alias',
            'Title',
            'GalleryType',
            'ShortDesc',
            'LongDesc',
            'Link',
            'NewWindow',
        ];

        return $inventory;
    }
}

if (!function_exists('red_legacy_public_gallery_link_url')) {
    function red_legacy_public_gallery_link_url($value)
    {
        $url = trim((string) $value);
        if ($url === '') {
            return '';
        }

        if ($url[0] === '/') {
            return strpos($url, '//') === 0 || preg_match('/[\x00-\x1F\x7F]/', $url) === 1
                ? ''
                : $url;
        }

        $parts = parse_url($url);
        if (!is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return '';
        }

        return $url;
    }
}

if (!function_exists('red_legacy_public_gallery_context_from_data')) {
    function red_legacy_public_gallery_context_from_data(array $dimensions, array $rows)
    {
        $dimensionKeys = ['Width', 'WidthDivisor', 'Height', 'vWidth', 'vHeight'];
        if (array_keys($dimensions) !== $dimensionKeys) {
            throw new InvalidArgumentException('Invalid legacy Gallery layout data.');
        }
        foreach ($dimensionKeys as $dimensionKey) {
            if (!is_int($dimensions[$dimensionKey]) && !is_float($dimensions[$dimensionKey])) {
                throw new InvalidArgumentException('Invalid legacy Gallery layout dimensions.');
            }
        }

        $rowKeys = red_legacy_public_gallery_row_inventory();
        $width = $dimensions['Width'];
        $preparedRows = [];
        foreach ($rows as $row) {
            if (!is_array($row) || array_keys($row) !== $rowKeys) {
                throw new InvalidArgumentException('Invalid legacy Gallery row.');
            }
            foreach ($row as $value) {
                if (!is_string($value) && !is_int($value) && !is_float($value)) {
                    throw new InvalidArgumentException('Invalid legacy Gallery row value.');
                }
            }

            $target = $row['GalleryType'] !== 'Gallery' && $row['NewWindow'] === 'Y'
                ? '_blank'
                : '_self';

            $gallery = [
                'presentation' => $row['GalleryType'] === 'Gallery' && $row['NewWindow'] === 'Y'
                    ? 'carousel'
                    : 'stack',
                'width' => $width,
                'photos' => [],
            ];
            $video = red_video_url_empty_data();
            $banner = ['image' => ''];

            switch ($row['GalleryType']) {
                case 'Gallery':
                    $width = $width / $dimensions['WidthDivisor'];
                    $gallery['width'] = $width;
                    if ($row['LongDesc'] != '') {
                        $photos = explode(',', (string) $row['LongDesc']);
                        $descriptions = explode(',', (string) $row['ShortDesc']);
                        foreach ($photos as $index => $photo) {
                            $description = explode(';', $descriptions[$index] ?? '');
                            $gallery['photos'][] = [
                                'file' => $photo,
                                'title' => $description[0] ?? '',
                                'url' => red_legacy_public_gallery_link_url($description[1] ?? ''),
                            ];
                        }
                    }
                    break;

                case 'Video':
                    $preparedVideo = red_video_url_data($row['LongDesc']);
                    if (is_array($preparedVideo)) {
                        $video = $preparedVideo;
                    }
                    break;

                case 'Banner':
                    $banner['image'] = (string) $row['LongDesc'];
                    break;
            }

            $linkHref = (string) $row['Link'];
            if ($row['GalleryType'] === 'Video') {
                $linkHref = red_legacy_public_gallery_link_url($linkHref);
            }

            $preparedRows[] = [
                'record' => $row,
                'link' => [
                    'href' => $linkHref,
                    'target' => $target,
                ],
                'gallery' => $gallery,
                'video' => $video,
                'banner' => $banner,
            ];
        }

        return [
            'dimensions' => $dimensions,
            'rows' => $preparedRows,
        ];
    }
}

if (!function_exists('red_legacy_public_gallery_context_validate')) {
    function red_legacy_public_gallery_context_validate($context)
    {
        if (!is_array($context)
            || array_keys($context) !== ['dimensions', 'rows']
            || !is_array($context['dimensions'])
            || !is_array($context['rows'])
        ) {
            throw new InvalidArgumentException('Invalid legacy Gallery context.');
        }

        $sourceRows = [];
        foreach ($context['rows'] as $preparedRow) {
            if (!is_array($preparedRow)
                || array_keys($preparedRow) !== ['record', 'link', 'gallery', 'video', 'banner']
                || !is_array($preparedRow['record'])
            ) {
                throw new InvalidArgumentException('Invalid prepared legacy Gallery row.');
            }
            $sourceRows[] = $preparedRow['record'];
        }

        $prepared = red_legacy_public_gallery_context_from_data(
            $context['dimensions'],
            $sourceRows
        );
        if ($prepared !== $context) {
            throw new InvalidArgumentException('Legacy Gallery context does not match its source data.');
        }

        return $prepared;
    }
}

if (!function_exists('red_legacy_public_gallery_context')) {
    function red_legacy_public_gallery_context($recordId, $layout, $position)
    {
        if (!class_exists('connection')
            || !function_exists('red_public_layout_dimensions')
            || !function_exists('red_public_gallery_rows')
        ) {
            throw new RuntimeException('Legacy Gallery preparation dependencies are unavailable.');
        }

        $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
        try {
            $dimensions = red_public_layout_dimensions($db->connection, $layout, $position);
            $rows = red_public_gallery_rows($db->connection, $recordId);
        } finally {
            $db->close();
        }

        return red_legacy_public_gallery_context_from_data($dimensions, $rows);
    }
}

if (!function_exists('red_legacy_public_component_renderer_registry')) {
    function red_legacy_public_component_renderer_registry()
    {
        static $registry;
        if ($registry === null) {
            $registry = [
                'Article' => static function (array $inputs) {
                    global $redThemeAdapter;
                    if (!isset($redThemeAdapter)
                        || !is_callable([$redThemeAdapter, 'renderPublicArticleComponent'])
                    ) {
                        throw new RuntimeException('The legacy public Article view adapter is unavailable.');
                    }

                    $redThemeAdapter->renderPublicArticleComponent($inputs);
                },
                'Form' => static function (array $inputs) {
                    global $redThemeAdapter;
                    if (!isset($redThemeAdapter)
                        || !is_callable([$redThemeAdapter, 'renderPublicFormComponent'])
                    ) {
                        throw new RuntimeException('The legacy public Form view adapter is unavailable.');
                    }

                    $redThemeAdapter->renderPublicFormComponent($inputs);
                    echo '<div class="clear-1"></div>';
                },
                'Gallery' => static function (array $inputs) {
                    global $redThemeAdapter;
                    if (!isset($redThemeAdapter)
                        || !is_callable([$redThemeAdapter, 'renderPublicGalleryComponent'])
                    ) {
                        throw new RuntimeException('The legacy public Gallery view adapter is unavailable.');
                    }

                    $redThemeAdapter->renderPublicGalleryComponent($inputs);
                    echo '<div class="clear-1"></div>';
                },
                'Other' => static function (array $inputs) {
                    global $redThemeAdapter;
                    if (!isset($redThemeAdapter)
                        || !is_callable([$redThemeAdapter, 'renderPublicOtherComponent'])
                    ) {
                        throw new RuntimeException('The legacy public Other view adapter is unavailable.');
                    }

                    $redThemeAdapter->renderPublicOtherComponent($inputs);
                },
            ];
        }

        return $registry;
    }
}

if (!function_exists('red_legacy_render_public_component')) {
    function red_legacy_render_public_component($context, $renderer = null)
    {
        if ($context === null) {
            return false;
        }
        if (!is_array($context)
            || array_keys($context) !== ['component', 'active', 'inputs']
            || !is_string($context['component'])
            || !is_bool($context['active'])
            || !is_array($context['inputs'])
        ) {
            throw new InvalidArgumentException('Invalid legacy public component context.');
        }

        $inventory = red_legacy_public_component_input_inventory();
        $component = $context['component'];
        if (!isset($inventory[$component])) {
            if ($renderer !== null) {
                throw new InvalidArgumentException('Add-on public components use the core renderer.');
            }
            return red_addon_public_component_render($context);
        }
        if (array_keys($context['inputs']) !== $inventory[$component]) {
            throw new InvalidArgumentException('Unsupported legacy public component context.');
        }
        if (!$context['active']) {
            return false;
        }

        if ($renderer === null) {
            $registry = red_legacy_public_component_renderer_registry();
            $renderer = $registry[$component];
        } elseif (!is_callable($renderer)) {
            throw new InvalidArgumentException('Invalid legacy public component renderer.');
        }

        $renderer($context['inputs']);
        return true;
    }
}
