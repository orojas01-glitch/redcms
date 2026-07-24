<?php
/**
 * Isolated rendering helpers for explicitly audited portable theme fixtures.
 *
 * This boundary deliberately has no database, request, session, activation,
 * settings, administrator, or live-runtime dependency. It executes only the
 * allowlisted package through strictly reconstructed data from either its
 * deterministic fixture or a separately validated read-only input provider.
 */

require_once __DIR__ . '/theme_helpers.php';

if (!function_exists('red_theme_preview_require_exact_keys')) {
    function red_theme_preview_require_exact_keys(array $value, array $required, array $optional, $context)
    {
        foreach ($required as $key) {
            if (!array_key_exists($key, $value)) {
                throw new InvalidArgumentException($context . ' is missing required key "' . $key . '".');
            }
        }

        $allowed = array_fill_keys(array_merge($required, $optional), true);
        foreach (array_keys($value) as $key) {
            if (!is_string($key) || !isset($allowed[$key])) {
                throw new InvalidArgumentException($context . ' contains an unexpected key.');
            }
        }
    }
}

if (!function_exists('red_theme_preview_string')) {
    function red_theme_preview_string($value, $context, $allowEmpty = false, $maximumLength = 500)
    {
        if (!is_string($value)
            || (!$allowEmpty && trim($value) === '')
            || strpos($value, "\0") !== false
            || strlen($value) > $maximumLength
        ) {
            throw new InvalidArgumentException($context . ' must be a valid string.');
        }

        return $value;
    }
}

if (!function_exists('red_theme_preview_url')) {
    function red_theme_preview_url($value, $context, $allowEmpty = false)
    {
        $url = red_theme_preview_string($value, $context, $allowEmpty, 500);
        if ($url === '' && $allowEmpty) {
            return '';
        }
        if ($url === '#' || preg_match('/\A#[A-Za-z][A-Za-z0-9_-]*\z/', $url) === 1) {
            return $url;
        }
        if ($url[0] === '/' && substr($url, 0, 2) !== '//') {
            return $url;
        }
        if (filter_var($url, FILTER_VALIDATE_URL) !== false && stripos($url, 'https://') === 0) {
            return $url;
        }

        throw new InvalidArgumentException($context . ' must be a fragment, root-relative URL, or HTTPS URL.');
    }
}

if (!function_exists('red_theme_preview_assert_non_executable')) {
    function red_theme_preview_assert_non_executable($value, $context = 'Preview data')
    {
        static $forbiddenKeys = [
            'callback' => true,
            'callable' => true,
            'class' => true,
            'connection' => true,
            'csrf' => true,
            'endpoint' => true,
            'function' => true,
            'include' => true,
            'method' => true,
            'query' => true,
            'renderer' => true,
            'require' => true,
            'session' => true,
            'table' => true,
            'template' => true,
        ];

        if (is_object($value) || is_resource($value)) {
            throw new InvalidArgumentException($context . ' may contain only arrays and scalar values.');
        }
        if (is_string($value)) {
            if (strpos($value, "\0") !== false || strpos($value, '<?') !== false) {
                throw new InvalidArgumentException($context . ' contains executable or unsafe text.');
            }
            return true;
        }
        if (!is_array($value)) {
            return true;
        }

        foreach ($value as $key => $child) {
            if (is_string($key) && isset($forbiddenKeys[strtolower($key)])) {
                throw new InvalidArgumentException($context . ' contains forbidden executable mapping key "' . $key . '".');
            }
            red_theme_preview_assert_non_executable($child, $context);
        }

        return true;
    }
}

if (!function_exists('red_theme_preview_trusted_article_html')) {
    function red_theme_preview_trusted_article_html($html)
    {
        if (!is_string($html) || $html === '' || strlen($html) > 4000000) {
            throw new InvalidArgumentException(
                'Preview trusted Article HTML must be a non-empty bounded string.'
            );
        }
        if (strpos($html, "\0") !== false
            || strpos($html, '<?') !== false
            || preg_match('/<!|<!--|<\/?(?:html|head|body)\b/i', $html) === 1
        ) {
            throw new InvalidArgumentException(
                'Preview trusted Article HTML contains a forbidden document or executable boundary.'
            );
        }
        if (!class_exists('DOMDocument')) {
            throw new RuntimeException('Preview trusted Article HTML requires the DOM extension.');
        }

        $previousErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $document = new DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadHTML(
            '<html><body><div id="red-theme-preview-trusted-root">' . $html . '</div></body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
        if (!$loaded) {
            throw new InvalidArgumentException('Preview trusted Article HTML could not be parsed safely.');
        }
        $root = $document->getElementById('red-theme-preview-trusted-root');
        if (!$root instanceof DOMElement) {
            throw new InvalidArgumentException('Preview trusted Article HTML root is unavailable.');
        }

        $allowedTags = array_fill_keys(
            ['h3', 'h4', 'h5', 'h6', 'p', 'ul', 'ol', 'li', 'a', 'hr', 'strong', 'img', 'br', 'em', 'blockquote'],
            true
        );
        $allowedAttributes = [
            'h3' => ['id'],
            'h4' => ['id'],
            'h5' => ['id'],
            'h6' => ['id'],
            'p' => ['id'],
            'ul' => ['id'],
            'ol' => ['id', 'type'],
            'li' => ['id'],
            'a' => ['href', 'id', 'name'],
            'hr' => ['id'],
            'strong' => ['id'],
            'img' => ['alt', 'decoding', 'height', 'loading', 'src', 'style', 'width'],
            'br' => ['id'],
            'em' => ['id'],
            'blockquote' => ['id'],
        ];
        $targetPattern = '/\A[A-Za-z0-9][A-Za-z0-9_-]{0,79}\z/';
        $targets = [];
        $hrefTargets = [];
        $imageCount = 0;
        $imageBytes = 0;
        $nodes = [$root];
        while ($nodes !== []) {
            $node = array_pop($nodes);
            foreach ($node->childNodes as $child) {
                if ($child instanceof DOMElement) {
                    $nodes[] = $child;
                } elseif ($child instanceof DOMText) {
                    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $child->nodeValue) === 1) {
                        throw new InvalidArgumentException(
                            'Preview trusted Article HTML contains unsafe control text.'
                        );
                    }
                } else {
                    throw new InvalidArgumentException(
                        'Preview trusted Article HTML contains an unsupported node type.'
                    );
                }
            }
            if ($node === $root) {
                continue;
            }
            $tag = strtolower($node->tagName);
            if (!isset($allowedTags[$tag])) {
                throw new InvalidArgumentException(
                    'Preview trusted Article HTML contains unsupported element ' . $tag . '.'
                );
            }
            $attributeNames = [];
            foreach ($node->attributes as $attribute) {
                $name = strtolower($attribute->name);
                if (!in_array($name, $allowedAttributes[$tag], true)) {
                    throw new InvalidArgumentException(
                        'Preview trusted Article HTML contains unsupported ' . $tag . ' attribute ' . $name . '.'
                    );
                }
                $attributeNames[] = $name;
            }
            if ($node->hasAttribute('id')) {
                $id = $node->getAttribute('id');
                if (preg_match($targetPattern, $id) !== 1 || isset($targets[$id])) {
                    throw new InvalidArgumentException(
                        'Preview trusted Article HTML ids must be unique safe fragment targets.'
                    );
                }
                $targets[$id] = true;
            }
            if ($tag === 'a' && $node->hasAttribute('name')) {
                $name = $node->getAttribute('name');
                if (preg_match($targetPattern, $name) !== 1 || isset($targets[$name])) {
                    throw new InvalidArgumentException(
                        'Preview trusted Article HTML names must be unique safe fragment targets.'
                    );
                }
                $targets[$name] = true;
            }
            if ($tag === 'a' && $node->hasAttribute('href')) {
                $href = $node->getAttribute('href');
                if (preg_match('/\A#([A-Za-z0-9][A-Za-z0-9_-]{0,79})\z/', $href, $match) !== 1) {
                    throw new InvalidArgumentException(
                        'Preview trusted Article links must be local fragment references.'
                    );
                }
                $hrefTargets[] = $match[1];
            }
            if ($tag === 'ol' && $node->hasAttribute('type') && $node->getAttribute('type') !== 'a') {
                throw new InvalidArgumentException('Preview trusted Article ordered-list type is unsupported.');
            }
            if ($tag !== 'img') {
                continue;
            }

            sort($attributeNames, SORT_STRING);
            if ($attributeNames !== ['alt', 'decoding', 'height', 'loading', 'src', 'style', 'width']) {
                throw new InvalidArgumentException(
                    'Preview trusted Article images must use the exact core-owned attribute shape.'
                );
            }
            if ($node->getAttribute('loading') !== 'lazy'
                || $node->getAttribute('decoding') !== 'async'
                || $node->getAttribute('style') !==
                    'display:block;max-width:100%;height:auto;margin:1rem auto;'
            ) {
                throw new InvalidArgumentException(
                    'Preview trusted Article image behavior must match the fixed responsive policy.'
                );
            }
            $width = $node->getAttribute('width');
            $height = $node->getAttribute('height');
            if (preg_match('/\A[1-9][0-9]{0,4}\z/', $width) !== 1
                || preg_match('/\A[1-9][0-9]{0,4}\z/', $height) !== 1
                || (int) $width > 4096
                || (int) $height > 4096
            ) {
                throw new InvalidArgumentException(
                    'Preview trusted Article image dimensions are outside the fixed safe range.'
                );
            }
            $source = $node->getAttribute('src');
            if (preg_match(
                '/\Adata:image\/(png|jpeg);base64,([A-Za-z0-9+\/]+={0,2})\z/D',
                $source,
                $match
            ) !== 1) {
                throw new InvalidArgumentException(
                    'Preview trusted Article images must be embedded PNG or JPEG data.'
                );
            }
            $bytes = base64_decode($match[2], true);
            $image = $bytes === false ? false : @getimagesizefromstring($bytes);
            if ($bytes === false
                || $bytes === ''
                || strlen($bytes) > 1000000
                || !is_array($image)
                || ($image['mime'] ?? '') !== 'image/' . $match[1]
                || (int) ($image[0] ?? 0) !== (int) $width
                || (int) ($image[1] ?? 0) !== (int) $height
            ) {
                throw new InvalidArgumentException(
                    'Preview trusted Article embedded image facts do not match their declared shape.'
                );
            }
            if (strlen($node->getAttribute('alt')) > 300) {
                throw new InvalidArgumentException('Preview trusted Article image alt text is too long.');
            }
            $imageCount++;
            $imageBytes += strlen($bytes);
        }
        foreach ($hrefTargets as $target) {
            if (!isset($targets[$target])) {
                throw new InvalidArgumentException(
                    'Preview trusted Article link target is missing from the sanitized document.'
                );
            }
        }
        if ($imageCount < 1 || $imageCount > 40 || $imageBytes > 3000000) {
            throw new InvalidArgumentException(
                'Preview trusted Article embedded media is outside the fixed bounded inventory.'
            );
        }

        return $html;
    }
}

if (!function_exists('red_theme_preview_document_data')) {
    function red_theme_preview_document_data($value)
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException('Preview document data must be an object.');
        }
        red_theme_preview_require_exact_keys(
            $value,
            ['language', 'title', 'description'],
            [],
            'Preview document data'
        );
        $language = red_theme_preview_string($value['language'], 'Preview document language', false, 12);
        if (preg_match('/\A[a-z]{2}(?:-[A-Z]{2})?\z/', $language) !== 1) {
            throw new InvalidArgumentException('Preview document language must use a supported language tag.');
        }

        return [
            'language' => $language,
            'title' => red_theme_preview_string($value['title'], 'Preview document title', false, 160),
            'description' => red_theme_preview_string(
                $value['description'],
                'Preview document description',
                false,
                300
            ),
        ];
    }
}

if (!function_exists('red_theme_preview_header_data')) {
    function red_theme_preview_header_data($value)
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException('Preview header data must be an object.');
        }
        red_theme_preview_require_exact_keys($value, ['siteTitle', 'homeUrl'], [], 'Preview header data');

        return [
            'siteTitle' => red_theme_preview_string($value['siteTitle'], 'Preview site title', false, 120),
            'homeUrl' => red_theme_preview_url($value['homeUrl'], 'Preview home URL'),
        ];
    }
}

if (!function_exists('red_theme_preview_navigation_data')) {
    function red_theme_preview_navigation_data($value)
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException('Preview navigation data must be an object.');
        }
        red_theme_preview_require_exact_keys($value, ['items'], [], 'Preview navigation data');
        if (!is_array($value['items']) || $value['items'] === [] || !array_is_list($value['items'])) {
            throw new InvalidArgumentException('Preview navigation items must be a non-empty list.');
        }

        $items = [];
        foreach ($value['items'] as $index => $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('Preview navigation item ' . $index . ' must be an object.');
            }
            red_theme_preview_require_exact_keys(
                $item,
                ['label', 'url', 'current'],
                [],
                'Preview navigation item ' . $index
            );
            if (!is_bool($item['current'])) {
                throw new InvalidArgumentException('Preview navigation current state must be boolean.');
            }
            $items[] = [
                'label' => red_theme_preview_string($item['label'], 'Preview navigation label', false, 80),
                'url' => red_theme_preview_url($item['url'], 'Preview navigation URL'),
                'current' => $item['current'],
            ];
        }

        return ['items' => $items];
    }
}

if (!function_exists('red_theme_preview_hero_data')) {
    function red_theme_preview_hero_data($value)
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException('Preview hero data must be an object.');
        }
        red_theme_preview_require_exact_keys($value, ['title', 'summary', 'action'], [], 'Preview hero data');
        if (!is_array($value['action'])) {
            throw new InvalidArgumentException('Preview hero action must be an object.');
        }
        red_theme_preview_require_exact_keys(
            $value['action'],
            ['label', 'url'],
            [],
            'Preview hero action'
        );

        return [
            'title' => red_theme_preview_string($value['title'], 'Preview hero title', false, 180),
            'summary' => red_theme_preview_string($value['summary'], 'Preview hero summary', false, 600),
            'action' => [
                'label' => red_theme_preview_string($value['action']['label'], 'Preview hero action label', false, 80),
                'url' => red_theme_preview_url($value['action']['url'], 'Preview hero action URL'),
            ],
        ];
    }
}

if (!function_exists('red_theme_preview_footer_data')) {
    function red_theme_preview_footer_data($value)
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException('Preview footer data must be an object.');
        }
        red_theme_preview_require_exact_keys($value, ['copyright'], [], 'Preview footer data');

        return [
            'copyright' => red_theme_preview_string(
                $value['copyright'],
                'Preview footer copyright',
                false,
                180
            ),
        ];
    }
}

if (!function_exists('red_theme_preview_breadcrumb_data')) {
    function red_theme_preview_breadcrumb_data($value)
    {
        if (!is_array($value) || $value === [] || !array_is_list($value)) {
            throw new InvalidArgumentException('Preview breadcrumb must be a non-empty list.');
        }
        $items = [];
        foreach ($value as $index => $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('Preview breadcrumb item ' . $index . ' must be an object.');
            }
            red_theme_preview_require_exact_keys(
                $item,
                ['label', 'url'],
                [],
                'Preview breadcrumb item ' . $index
            );
            $items[] = [
                'label' => red_theme_preview_string($item['label'], 'Preview breadcrumb label', false, 100),
                'url' => red_theme_preview_url($item['url'], 'Preview breadcrumb URL', true),
            ];
        }

        return $items;
    }
}

if (!function_exists('red_theme_preview_component_data')) {
    function red_theme_preview_component_data($componentId, $value)
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException('Preview ' . $componentId . ' data must be an object.');
        }

        if ($componentId === 'Article') {
            if (array_key_exists('bodyHtml', $value)) {
                red_theme_preview_require_exact_keys(
                    $value,
                    ['title', 'bodyHtml'],
                    [],
                    'Preview trusted Article data'
                );
                return [
                    'title' => red_theme_preview_string(
                        $value['title'],
                        'Preview trusted Article title',
                        false,
                        180
                    ),
                    'bodyHtml' => red_theme_preview_trusted_article_html($value['bodyHtml']),
                ];
            }
            red_theme_preview_require_exact_keys(
                $value,
                ['title', 'summary', 'url', 'linkLabel'],
                [],
                'Preview Article data'
            );
            return [
                'title' => red_theme_preview_string($value['title'], 'Preview Article title', false, 180),
                'summary' => red_theme_preview_string($value['summary'], 'Preview Article summary', false, 1000),
                'url' => red_theme_preview_url($value['url'], 'Preview Article URL'),
                'linkLabel' => red_theme_preview_string(
                    $value['linkLabel'],
                    'Preview Article link label',
                    false,
                    80
                ),
            ];
        }

        if ($componentId === 'Form') {
            red_theme_preview_require_exact_keys(
                $value,
                ['title', 'fields', 'submitLabel'],
                [],
                'Preview Form data'
            );
            if (!is_array($value['fields']) || $value['fields'] === [] || !array_is_list($value['fields'])) {
                throw new InvalidArgumentException('Preview Form fields must be a non-empty list.');
            }
            $fields = [];
            $fieldNames = [];
            foreach ($value['fields'] as $index => $field) {
                if (!is_array($field)) {
                    throw new InvalidArgumentException('Preview Form field ' . $index . ' must be an object.');
                }
                red_theme_preview_require_exact_keys(
                    $field,
                    ['name', 'label', 'type', 'autocomplete', 'required'],
                    [],
                    'Preview Form field ' . $index
                );
                $name = red_theme_preview_string($field['name'], 'Preview Form field name', false, 60);
                if (preg_match('/\A[a-z][a-z0-9_-]*\z/', $name) !== 1 || isset($fieldNames[$name])) {
                    throw new InvalidArgumentException('Preview Form field names must be unique safe ids.');
                }
                $type = red_theme_preview_string($field['type'], 'Preview Form field type', false, 20);
                if (!in_array($type, ['text', 'email', 'tel', 'password', 'textarea'], true)) {
                    throw new InvalidArgumentException('Preview Form field type is unsupported.');
                }
                if (!is_bool($field['required'])) {
                    throw new InvalidArgumentException('Preview Form required state must be boolean.');
                }
                $fieldNames[$name] = true;
                $fields[] = [
                    'name' => $name,
                    'label' => red_theme_preview_string($field['label'], 'Preview Form field label', false, 100),
                    'type' => $type,
                    'autocomplete' => red_theme_preview_string(
                        $field['autocomplete'],
                        'Preview Form autocomplete',
                        true,
                        60
                    ),
                    'required' => $field['required'],
                ];
            }
            return [
                'title' => red_theme_preview_string($value['title'], 'Preview Form title', false, 180),
                'fields' => $fields,
                'submitLabel' => red_theme_preview_string(
                    $value['submitLabel'],
                    'Preview Form submit label',
                    false,
                    80
                ),
            ];
        }

        if ($componentId === 'Gallery') {
            if (array_key_exists('video', $value)) {
                red_theme_preview_require_exact_keys(
                    $value,
                    ['title', 'video'],
                    [],
                    'Preview Gallery Video data'
                );
                if (!is_array($value['video'])) {
                    throw new InvalidArgumentException('Preview Gallery Video must be an object.');
                }
                red_theme_preview_require_exact_keys(
                    $value['video'],
                    ['provider', 'id', 'caption'],
                    [],
                    'Preview Gallery Video'
                );
                $provider = red_theme_preview_string(
                    $value['video']['provider'],
                    'Preview Gallery Video provider',
                    false,
                    12
                );
                if (!in_array($provider, ['youtube', 'vimeo'], true)) {
                    throw new InvalidArgumentException(
                        'Preview Gallery Video provider must be exactly youtube or vimeo.'
                    );
                }
                $id = red_theme_preview_string(
                    $value['video']['id'],
                    'Preview Gallery Video id',
                    false,
                    64
                );
                if (($provider === 'youtube' && preg_match('/\A[A-Za-z0-9_-]{11}\z/', $id) !== 1)
                    || ($provider === 'vimeo' && preg_match('/\A[1-9][0-9]{5,11}\z/', $id) !== 1)
                ) {
                    throw new InvalidArgumentException(
                        'Preview Gallery Video id does not match the fixed provider-specific shape.'
                    );
                }

                return [
                    'title' => red_theme_preview_string(
                        $value['title'],
                        'Preview Gallery Video title',
                        false,
                        180
                    ),
                    'video' => [
                        'provider' => $provider,
                        'id' => $id,
                        'caption' => red_theme_preview_string(
                            $value['video']['caption'],
                            'Preview Gallery Video caption',
                            false,
                            180
                        ),
                    ],
                ];
            }

            red_theme_preview_require_exact_keys($value, ['title', 'items'], [], 'Preview Gallery data');
            if (!is_array($value['items']) || $value['items'] === [] || !array_is_list($value['items'])) {
                throw new InvalidArgumentException('Preview Gallery items must be a non-empty list.');
            }
            $items = [];
            foreach ($value['items'] as $index => $item) {
                if (!is_array($item)) {
                    throw new InvalidArgumentException('Preview Gallery item ' . $index . ' must be an object.');
                }
                red_theme_preview_require_exact_keys(
                    $item,
                    ['image', 'caption'],
                    [],
                    'Preview Gallery item ' . $index
                );
                $image = red_theme_preview_string($item['image'], 'Preview Gallery image', false, 240);
                if (!red_theme_valid_relative_path($image)) {
                    throw new InvalidArgumentException('Preview Gallery image must be a safe theme-relative path.');
                }
                $items[] = [
                    'image' => $image,
                    'caption' => red_theme_preview_string(
                        $item['caption'],
                        'Preview Gallery caption',
                        false,
                        180
                    ),
                ];
            }
            return [
                'title' => red_theme_preview_string($value['title'], 'Preview Gallery title', false, 180),
                'items' => $items,
            ];
        }

        if ($componentId === 'Other') {
            red_theme_preview_require_exact_keys($value, ['title', 'text'], [], 'Preview Other data');
            return [
                'title' => red_theme_preview_string($value['title'], 'Preview Other title', false, 180),
                'text' => red_theme_preview_string($value['text'], 'Preview Other text', false, 1000),
            ];
        }

        throw new InvalidArgumentException('Preview component "' . $componentId . '" is unsupported.');
    }
}

if (!function_exists('red_theme_preview_contract')) {
    function red_theme_preview_contract(array $fixture, array $validation)
    {
        red_theme_preview_assert_non_executable($fixture);
        red_theme_preview_require_exact_keys(
            $fixture,
            ['schemaVersion', 'theme', 'document', 'regions', 'page'],
            [],
            'Preview fixture'
        );
        if ($fixture['schemaVersion'] !== 1) {
            throw new InvalidArgumentException('Preview fixture schemaVersion must be the integer 1.');
        }
        $themeId = red_theme_preview_string($fixture['theme'], 'Preview fixture theme', false, 64);
        if ($themeId !== ($validation['theme'] ?? '')) {
            throw new InvalidArgumentException('Preview fixture theme must match the validated package id.');
        }
        if (!is_array($fixture['regions'])) {
            throw new InvalidArgumentException('Preview regions must be an object.');
        }
        red_theme_preview_require_exact_keys(
            $fixture['regions'],
            ['header', 'navigation', 'hero', 'footer'],
            [],
            'Preview regions'
        );
        if (!is_array($fixture['page'])) {
            throw new InvalidArgumentException('Preview page must be an object.');
        }
        red_theme_preview_require_exact_keys(
            $fixture['page'],
            ['layout', 'breadcrumb', 'slots'],
            [],
            'Preview page'
        );

        $manifest = $validation['manifest'];
        $layoutId = red_theme_preview_string($fixture['page']['layout'], 'Preview layout id', false, 64);
        if (!isset($manifest['layouts'][$layoutId]) || !is_array($manifest['layouts'][$layoutId])) {
            throw new InvalidArgumentException('Preview layout is not declared by the validated theme.');
        }
        $declaredPositions = [];
        foreach ($manifest['layouts'][$layoutId]['positions'] as $position) {
            $declaredPositions[(string) $position['id']] = (int) $position['id'];
        }
        if (!is_array($fixture['page']['slots'])) {
            throw new InvalidArgumentException('Preview slots must be an object.');
        }
        $slotKeys = [];
        foreach (array_keys($fixture['page']['slots']) as $slotKey) {
            $slotKeys[(string) $slotKey] = true;
        }
        if (array_keys($declaredPositions) !== array_keys($slotKeys)) {
            throw new InvalidArgumentException('Preview slots must exactly match the selected layout positions.');
        }

        $slots = [];
        foreach ($declaredPositions as $positionKey => $positionId) {
            $entries = $fixture['page']['slots'][$positionKey] ?? $fixture['page']['slots'][$positionId] ?? null;
            if (!is_array($entries) || !array_is_list($entries)) {
                throw new InvalidArgumentException('Preview slot ' . $positionId . ' must be a component list.');
            }
            $components = [];
            foreach ($entries as $index => $entry) {
                if (!is_array($entry)) {
                    throw new InvalidArgumentException(
                        'Preview slot ' . $positionId . ' component ' . $index . ' must be an object.'
                    );
                }
                red_theme_preview_require_exact_keys(
                    $entry,
                    ['component', 'data'],
                    [],
                    'Preview slot component'
                );
                $componentId = red_theme_preview_string(
                    $entry['component'],
                    'Preview component id',
                    false,
                    40
                );
                if (!isset($manifest['components'][$componentId])) {
                    throw new InvalidArgumentException(
                        'Preview component "' . $componentId . '" is not declared by the validated theme.'
                    );
                }
                $components[] = [
                    'component' => $componentId,
                    'data' => red_theme_preview_component_data($componentId, $entry['data']),
                ];
            }
            $slots[$positionId] = $components;
        }

        $contract = [
            'schemaVersion' => 1,
            'theme' => $themeId,
            'document' => red_theme_preview_document_data($fixture['document']),
            'regions' => [
                'header' => red_theme_preview_header_data($fixture['regions']['header']),
                'navigation' => red_theme_preview_navigation_data($fixture['regions']['navigation']),
                'hero' => red_theme_preview_hero_data($fixture['regions']['hero']),
                'footer' => red_theme_preview_footer_data($fixture['regions']['footer']),
            ],
            'page' => [
                'layout' => $layoutId,
                'breadcrumb' => red_theme_preview_breadcrumb_data($fixture['page']['breadcrumb']),
                'slots' => $slots,
            ],
        ];
        red_theme_preview_assert_non_executable($contract, 'Prepared preview contract');

        return $contract;
    }
}

if (!function_exists('red_theme_preview_declared_template_files')) {
    function red_theme_preview_declared_template_files(array $validation)
    {
        $manifest = $validation['manifest'];
        $files = [];
        foreach (['regions', 'layouts', 'components'] as $group) {
            foreach ($manifest[$group] ?? [] as $definition) {
                if (!is_array($definition) || empty($definition['template'])) {
                    continue;
                }
                $file = red_theme_existing_path($validation['path'], $definition['template']);
                if ($file === null || strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php') {
                    throw new RuntimeException('Preview template declaration is missing, unsafe, or not PHP.');
                }
                $files[$file] = $file;
            }
        }
        ksort($files, SORT_STRING);

        return array_values($files);
    }
}

if (!function_exists('red_theme_preview_assert_template_source_safe')) {
    function red_theme_preview_assert_template_source_safe($file)
    {
        $source = file_get_contents($file);
        if ($source === false) {
            throw new RuntimeException('Preview template could not be read: ' . $file);
        }
        if (preg_match(
            '/<(?:script|style|iframe|object|embed|base|link)\b|<meta\b[^>]*http-equiv\s*=|\son[a-z]+\s*=|\sstyle\s*=|\b(?:src|href|action)\s*=\s*["\']\s*(?:https?:)?\/\/|javascript\s*:|data\s*:\s*text\/html/i',
            $source
        ) === 1) {
            throw new RuntimeException('Preview template contains executable browser markup: ' . $file);
        }
        if (preg_match('/(?:\]|\))\s*\(/', $source) === 1) {
            throw new RuntimeException('Preview template contains a dynamic callable expression: ' . $file);
        }
        $tokens = token_get_all($source);
        $forbiddenTokenIds = array_fill_keys(
            [
                T_CLASS,
                T_CLONE,
                T_DECLARE,
                T_DOUBLE_COLON,
                T_EVAL,
                T_FN,
                T_FUNCTION,
                T_GLOBAL,
                T_GOTO,
                T_HALT_COMPILER,
                T_INCLUDE,
                T_INCLUDE_ONCE,
                T_INTERFACE,
                T_NAME_FULLY_QUALIFIED,
                T_NAME_QUALIFIED,
                T_NAME_RELATIVE,
                T_NAMESPACE,
                T_NEW,
                T_OBJECT_OPERATOR,
                T_REQUIRE,
                T_REQUIRE_ONCE,
                T_STATIC,
                T_THROW,
                T_TRAIT,
                T_EXIT,
                T_UNSET,
                T_USE,
                T_YIELD,
                T_YIELD_FROM,
            ],
            true
        );
        if (defined('T_ENUM')) {
            $forbiddenTokenIds[T_ENUM] = true;
        }
        foreach (['T_CURLY_OPEN', 'T_DOLLAR_OPEN_CURLY_BRACES'] as $dynamicVariableToken) {
            if (defined($dynamicVariableToken)) {
                $forbiddenTokenIds[constant($dynamicVariableToken)] = true;
            }
        }
        $forbiddenVariables = array_fill_keys(
            ['$_COOKIE', '$_ENV', '$_FILES', '$_GET', '$_POST', '$_REQUEST', '$_SERVER', '$_SESSION', '$GLOBALS'],
            true
        );
        $allowedFunctions = ['htmlspecialchars' => true];

        foreach ($tokens as $index => $token) {
            if (is_string($token)) {
                if ($token === '`') {
                    throw new RuntimeException('Preview templates may not execute shell expressions.');
                }
                continue;
            }
            [$tokenId, $text] = $token;
            if (isset($forbiddenTokenIds[$tokenId])) {
                throw new RuntimeException('Preview template contains a forbidden executable token: ' . $file);
            }
            if ($tokenId === T_VARIABLE) {
                if (isset($forbiddenVariables[$text])) {
                    throw new RuntimeException('Preview template may not access request/session/global state: ' . $file);
                }
                $next = $index + 1;
                while (isset($tokens[$next])
                    && is_array($tokens[$next])
                    && in_array($tokens[$next][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
                ) {
                    $next++;
                }
                if (isset($tokens[$next]) && $tokens[$next] === '(') {
                    throw new RuntimeException('Preview templates may not invoke variable functions: ' . $file);
                }
            }
            if ($tokenId === T_CONSTANT_ENCAPSED_STRING) {
                $next = $index + 1;
                while (isset($tokens[$next])
                    && is_array($tokens[$next])
                    && in_array($tokens[$next][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
                ) {
                    $next++;
                }
                if (isset($tokens[$next]) && $tokens[$next] === '(') {
                    throw new RuntimeException('Preview templates may not invoke literal callables: ' . $file);
                }
            }
            if ($tokenId !== T_STRING) {
                continue;
            }
            $name = strtolower($text);
            $next = $index + 1;
            while (isset($tokens[$next])
                && is_array($tokens[$next])
                && in_array($tokens[$next][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
            ) {
                $next++;
            }
            $isFunctionCall = isset($tokens[$next]) && $tokens[$next] === '(';
            if ($isFunctionCall && !isset($allowedFunctions[$name])) {
                throw new RuntimeException('Preview template contains a function outside the fixed allowlist: ' . $file);
            }
        }

        return true;
    }
}

if (!function_exists('red_theme_preview_validate_reference_theme')) {
    function red_theme_preview_validate_reference_theme($themeId, $projectRoot = null)
    {
        if ($themeId !== 'starter-reference') {
            throw new RuntimeException(
                'The isolated preview contract currently executes only the audited starter-reference package.'
            );
        }
        return red_theme_preview_validate_fixture_theme($themeId, $projectRoot);
    }
}

if (!function_exists('red_theme_preview_fixture_theme_ids')) {
    function red_theme_preview_fixture_theme_ids()
    {
        return ['starter-reference'];
    }
}

if (!function_exists('red_theme_preview_validate_fixture_theme')) {
    function red_theme_preview_validate_fixture_theme($themeId, $projectRoot = null)
    {
        if (!is_string($themeId)
            || !in_array($themeId, red_theme_preview_fixture_theme_ids(), true)
        ) {
            throw new RuntimeException(
                'The isolated fixture preview executes only explicitly audited portable packages.'
            );
        }
        $validation = red_theme_validate_manifest($themeId, $projectRoot);
        if (empty($validation['valid'])
            || !is_array($validation['manifest'] ?? null)
            || ($validation['manifest']['type'] ?? '') !== 'standard'
        ) {
            throw new RuntimeException('The audited fixture package must be a valid standard theme before preview.');
        }
        foreach (red_theme_preview_declared_template_files($validation) as $file) {
            red_theme_preview_assert_template_source_safe($file);
        }

        return $validation;
    }
}

if (!function_exists('red_theme_preview_load_fixture')) {
    function red_theme_preview_load_fixture(array $validation, $fixturePath = 'fixtures/preview.json')
    {
        if (!red_theme_valid_relative_path($fixturePath)
            || strtolower(pathinfo($fixturePath, PATHINFO_EXTENSION)) !== 'json'
        ) {
            throw new RuntimeException('Preview fixture path must be a safe theme-relative JSON file.');
        }
        $file = red_theme_existing_path($validation['path'], $fixturePath);
        if ($file === null || !is_file($file)) {
            throw new RuntimeException('Preview fixture is missing or resolves outside the theme package.');
        }
        $json = file_get_contents($file);
        if ($json === false) {
            throw new RuntimeException('Preview fixture could not be read.');
        }
        try {
            $fixture = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException('Preview fixture JSON is invalid: ' . $exception->getMessage());
        }
        if (!is_array($fixture)) {
            throw new RuntimeException('Preview fixture root must be an object.');
        }

        return $fixture;
    }
}

if (!function_exists('red_theme_preview_render_view')) {
    function red_theme_preview_render_view($file, $variableName, array $context)
    {
        $allowedVariables = [
            'redThemeArticleContext' => true,
            'redThemeDocumentContext' => true,
            'redThemeFooterContext' => true,
            'redThemeFormContext' => true,
            'redThemeGalleryContext' => true,
            'redThemeHeaderContext' => true,
            'redThemeHeroContext' => true,
            'redThemeLayoutContext' => true,
            'redThemeNavigationContext' => true,
            'redThemeOtherContext' => true,
        ];
        if (!isset($allowedVariables[$variableName])) {
            throw new RuntimeException('Preview view variable is not part of the fixed contract.');
        }

        ob_start();
        try {
            ${$variableName} = $context;
            require $file;
            return ob_get_clean();
        } catch (Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
    }
}

if (!function_exists('red_theme_preview_view_file')) {
    function red_theme_preview_view_file(array $validation, array $definition, $context)
    {
        $file = red_theme_existing_path($validation['path'], $definition['template'] ?? '');
        if ($file === null || strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php') {
            throw new RuntimeException($context . ' view is missing or unsafe.');
        }

        return $file;
    }
}

if (!function_exists('red_theme_preview_image_data_uri')) {
    function red_theme_preview_image_data_uri($themeDirectory, $relativePath)
    {
        $file = red_theme_existing_path($themeDirectory, $relativePath);
        $extension = strtolower(pathinfo((string) $file, PATHINFO_EXTENSION));
        $mimeTypes = [
            'gif' => 'image/gif',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
        ];
        if ($file === null || !isset($mimeTypes[$extension]) || filesize($file) > 1048576) {
            throw new RuntimeException('Preview image is missing, unsafe, unsupported, or too large.');
        }
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException('Preview image could not be read.');
        }
        if ($extension === 'svg'
            && preg_match(
                '/<(?:script|foreignObject|iframe|object|embed)\b|\son[a-z]+\s*=|\b(?:href|xlink:href)\s*=|url\s*\(/i',
                $contents
            ) === 1
        ) {
            throw new RuntimeException('Preview SVG contains executable or external-resource markup.');
        }

        return 'data:' . $mimeTypes[$extension] . ';base64,' . base64_encode($contents);
    }
}

if (!function_exists('red_theme_preview_component_view_context')) {
    function red_theme_preview_component_view_context(
        $componentId,
        array $data,
        $themeDirectory,
        $galleryAssetRoot = null,
        $mode = 'fixture-preview'
    )
    {
        if ($componentId === 'Article') {
            $data = red_theme_preview_component_data('Article', $data);
            if (isset($data['bodyHtml']) && $mode !== 'read-only-instructions-preview') {
                throw new InvalidArgumentException(
                    'Preview trusted Article HTML is confined to the fixed Instructions preview.'
                );
            }
            return $data;
        }
        if ($componentId !== 'Gallery') {
            return $data;
        }

        $data = red_theme_preview_component_data('Gallery', $data);
        if (isset($data['video'])) {
            if (!in_array($mode, ['fixture-preview', 'read-only-administration-preview'], true)
                || $galleryAssetRoot !== null
            ) {
                throw new InvalidArgumentException(
                    'Preview Gallery Video is confined to the offline fixture contract or fixed Administration preview.'
                );
            }
            $providerLabels = ['youtube' => 'YouTube', 'vimeo' => 'Vimeo'];
            return [
                'title' => $data['title'],
                'video' => [
                    'provider' => $data['video']['provider'],
                    'providerLabel' => $providerLabels[$data['video']['provider']],
                    'id' => $data['video']['id'],
                    'caption' => $data['video']['caption'],
                ],
            ];
        }

        $assetRoot = $themeDirectory;
        if ($galleryAssetRoot !== null) {
            if (!is_string($galleryAssetRoot)
                || $galleryAssetRoot === ''
                || realpath($galleryAssetRoot) === false
                || !is_dir($galleryAssetRoot)
            ) {
                throw new InvalidArgumentException('Preview Gallery media root is unavailable or unsafe.');
            }
            $assetRoot = $galleryAssetRoot;
        }

        $items = [];
        foreach ($data['items'] as $item) {
            $items[] = [
                'src' => red_theme_preview_image_data_uri($assetRoot, $item['image']),
                'caption' => $item['caption'],
            ];
        }

        return [
            'title' => $data['title'],
            'items' => $items,
        ];
    }
}

if (!function_exists('red_theme_preview_render_assets')) {
    function red_theme_preview_render_assets(array $validation)
    {
        $head = '';
        $bodyEnd = '';
        foreach (['styles', 'scripts'] as $group) {
            foreach ($validation['manifest']['assets'][$group] as $asset) {
                if ($group === 'scripts') {
                    throw new RuntimeException('Isolated fixture preview does not execute client-side scripts.');
                }
                if (!empty($asset['url']) || empty($asset['path'])) {
                    throw new RuntimeException('Isolated preview supports only local standard-theme assets.');
                }
                $file = red_theme_existing_path($validation['path'], $asset['path']);
                $expectedExtension = $group === 'styles' ? 'css' : 'js';
                if ($file === null || strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== $expectedExtension) {
                    throw new RuntimeException('Preview asset is missing, unsafe, or has the wrong type.');
                }
                $contents = file_get_contents($file);
                if ($contents === false) {
                    throw new RuntimeException('Preview asset could not be read.');
                }
                if (preg_match('/@import\b|url\s*\(|expression\s*\(|behavior\s*:|-moz-binding|<\/style/i', $contents) === 1) {
                    throw new RuntimeException('Preview stylesheet contains an external-resource or executable construct.');
                }
                $id = htmlspecialchars($asset['id'], ENT_QUOTES, 'UTF-8');
                if ($group === 'styles') {
                    $contents = str_ireplace('</style', '<\/style', $contents);
                    $html = '<style data-theme-asset="' . $id . '">' . "\n" . rtrim($contents) . "\n</style>\n";
                } else {
                    $contents = str_ireplace('</script', '<\/script', $contents);
                    $html = '<script data-theme-asset="' . $id . '">' . "\n" . rtrim($contents) . "\n</script>\n";
                }
                if ($asset['location'] === 'head') {
                    $head .= $html;
                } else {
                    $bodyEnd .= $html;
                }
            }
        }

        return ['head' => $head, 'bodyEnd' => $bodyEnd];
    }
}

if (!function_exists('red_theme_preview_layout_view_context')) {
    function red_theme_preview_layout_view_context(array $value, array $layoutDefinition)
    {
        red_theme_preview_require_exact_keys($value, ['layout', 'breadcrumb', 'slots'], [], 'Layout view context');
        $layoutId = red_theme_preview_string($value['layout'], 'Layout view id', false, 64);
        $breadcrumb = red_theme_preview_breadcrumb_data($value['breadcrumb']);
        if (!is_array($value['slots'])) {
            throw new InvalidArgumentException('Layout view slots must be an object.');
        }

        $expectedPositions = [];
        foreach ($layoutDefinition['positions'] as $position) {
            $expectedPositions[] = (int) $position['id'];
        }
        if ($expectedPositions !== array_map('intval', array_keys($value['slots']))) {
            throw new InvalidArgumentException('Layout view slots must exactly match declared positions.');
        }
        $slots = [];
        foreach ($expectedPositions as $position) {
            if (!isset($value['slots'][$position]) || !is_string($value['slots'][$position])) {
                throw new InvalidArgumentException('Layout view slot output must be a rendered HTML string.');
            }
            $slots[$position] = $value['slots'][$position];
        }

        return ['layout' => $layoutId, 'breadcrumb' => $breadcrumb, 'slots' => $slots];
    }
}

if (!function_exists('red_theme_preview_document_view_context')) {
    function red_theme_preview_document_view_context(array $value)
    {
        red_theme_preview_require_exact_keys(
            $value,
            ['mode', 'language', 'title', 'description', 'headAssetsHtml', 'bodyAssetsHtml', 'regions', 'contentHtml'],
            [],
            'Document view context'
        );
        if (!in_array(
            $value['mode'],
            [
                'fixture-preview',
                'read-only-contact-preview',
                'read-only-home-preview',
                'read-only-administration-preview',
                'read-only-instructions-preview',
                'read-only-login-preview',
                'read-only-selected-contact-preview',
            ],
            true
        )) {
            throw new InvalidArgumentException('Document view mode is outside the isolated preview contract.');
        }
        if (!is_array($value['regions'])) {
            throw new InvalidArgumentException('Document view regions must be an object.');
        }
        red_theme_preview_require_exact_keys(
            $value['regions'],
            ['header', 'navigation', 'hero', 'footer'],
            [],
            'Document view regions'
        );
        foreach (['header', 'navigation', 'hero', 'footer'] as $regionId) {
            if (!is_string($value['regions'][$regionId])) {
                throw new InvalidArgumentException('Document view region output must be a rendered HTML string.');
            }
        }
        foreach (['headAssetsHtml', 'bodyAssetsHtml', 'contentHtml'] as $htmlKey) {
            if (!is_string($value[$htmlKey])) {
                throw new InvalidArgumentException('Document view HTML fields must be rendered strings.');
            }
        }
        $document = red_theme_preview_document_data(
            [
                'language' => $value['language'],
                'title' => $value['title'],
                'description' => $value['description'],
            ]
        );

        return array_merge(
            ['mode' => $value['mode']],
            $document,
            [
                'headAssetsHtml' => $value['headAssetsHtml'],
                'bodyAssetsHtml' => $value['bodyAssetsHtml'],
                'regions' => $value['regions'],
                'contentHtml' => $value['contentHtml'],
            ]
        );
    }
}

if (!function_exists('red_theme_preview_scope')) {
    function red_theme_preview_scope($value = null)
    {
        $zeroScope = [
            'databaseReads' => 0,
            'databaseWrites' => 0,
            'sessionReads' => 0,
            'sessionWrites' => 0,
            'liveRuntimeChanges' => 0,
        ];
        if ($value === null) {
            return $zeroScope;
        }
        if (!is_array($value)) {
            throw new InvalidArgumentException('Preview scope must be an object.');
        }
        red_theme_preview_require_exact_keys(
            $value,
            array_keys($zeroScope),
            [],
            'Preview scope'
        );
        foreach ($zeroScope as $key => $zero) {
            if (!is_int($value[$key]) || $value[$key] < 0 || $value[$key] > 100) {
                throw new InvalidArgumentException('Preview scope counters must be bounded non-negative integers.');
            }
        }
        if ($value['databaseWrites'] !== 0
            || $value['sessionReads'] !== 0
            || $value['sessionWrites'] !== 0
            || $value['liveRuntimeChanges'] !== 0
        ) {
            throw new InvalidArgumentException('Isolated preview scope may record database reads only.');
        }

        return $value;
    }
}

if (!function_exists('red_theme_preview_render_prepared_contract')) {
    function red_theme_preview_render_prepared_contract(
        array $validation,
        array $contract,
        $mode = 'fixture-preview',
        $scope = null,
        $galleryAssetRoot = null
    )
    {
        if (!in_array(
            $mode,
            [
                'fixture-preview',
                'read-only-contact-preview',
                'read-only-home-preview',
                'read-only-administration-preview',
                'read-only-instructions-preview',
                'read-only-login-preview',
                'read-only-selected-contact-preview',
            ],
            true
        )) {
            throw new InvalidArgumentException('Preview render mode is unsupported.');
        }
        if ($mode === 'read-only-home-preview') {
            if (!is_string($galleryAssetRoot)
                || $galleryAssetRoot === ''
                || realpath($galleryAssetRoot) === false
                || !is_dir($galleryAssetRoot)
            ) {
                throw new InvalidArgumentException('Home preview requires one fixed local Gallery media root.');
            }
        } elseif ($galleryAssetRoot !== null) {
            throw new InvalidArgumentException('Only Home preview may use the fixed Gallery media root.');
        }
        $scope = red_theme_preview_scope($scope);
        $manifest = $validation['manifest'];
        $regionVariables = [
            'header' => 'redThemeHeaderContext',
            'navigation' => 'redThemeNavigationContext',
            'hero' => 'redThemeHeroContext',
            'footer' => 'redThemeFooterContext',
        ];
        $regions = [];
        foreach ($regionVariables as $regionId => $variableName) {
            $file = red_theme_preview_view_file(
                $validation,
                $manifest['regions'][$regionId],
                'Preview ' . $regionId
            );
            $regions[$regionId] = red_theme_preview_render_view(
                $file,
                $variableName,
                $contract['regions'][$regionId]
            );
        }

        $componentVariables = [
            'Article' => 'redThemeArticleContext',
            'Form' => 'redThemeFormContext',
            'Gallery' => 'redThemeGalleryContext',
            'Other' => 'redThemeOtherContext',
        ];
        $renderedSlots = [];
        foreach ($contract['page']['slots'] as $position => $entries) {
            $renderedEntries = [];
            foreach ($entries as $entry) {
                $componentId = $entry['component'];
                if (!isset($componentVariables[$componentId])) {
                    throw new RuntimeException('Preview component has no fixed view-data boundary.');
                }
                $file = red_theme_preview_view_file(
                    $validation,
                    $manifest['components'][$componentId],
                    'Preview ' . $componentId
                );
                $renderedEntries[] = rtrim(
                    red_theme_preview_render_view(
                        $file,
                        $componentVariables[$componentId],
                        red_theme_preview_component_view_context(
                            $componentId,
                            $entry['data'],
                            $validation['path'],
                            $galleryAssetRoot,
                            $mode
                        )
                    )
                );
            }
            $renderedSlots[(int) $position] = implode("\n", $renderedEntries);
        }

        $layoutId = $contract['page']['layout'];
        $layoutDefinition = $manifest['layouts'][$layoutId];
        $layoutContext = red_theme_preview_layout_view_context(
            [
                'layout' => $layoutId,
                'breadcrumb' => $contract['page']['breadcrumb'],
                'slots' => $renderedSlots,
            ],
            $layoutDefinition
        );
        $layoutHtml = red_theme_preview_render_view(
            red_theme_preview_view_file($validation, $layoutDefinition, 'Preview layout'),
            'redThemeLayoutContext',
            $layoutContext
        );
        $assets = red_theme_preview_render_assets($validation);
        $documentContext = red_theme_preview_document_view_context(
            [
                'mode' => $mode,
                'language' => $contract['document']['language'],
                'title' => $contract['document']['title'],
                'description' => $contract['document']['description'],
                'headAssetsHtml' => $assets['head'],
                'bodyAssetsHtml' => $assets['bodyEnd'],
                'regions' => $regions,
                'contentHtml' => $layoutHtml,
            ]
        );
        $html = red_theme_preview_render_view(
            red_theme_preview_view_file($validation, $manifest['regions']['document'], 'Preview document'),
            'redThemeDocumentContext',
            $documentContext
        );

        return [
            'theme' => $validation['theme'],
            'layout' => $layoutId,
            'html' => $html,
            'bytes' => strlen($html),
            'sha256' => hash('sha256', $html),
            'contract' => $contract,
            'scope' => $scope,
        ];
    }
}

if (!function_exists('red_theme_preview_render_fixture')) {
    function red_theme_preview_render_fixture($themeId, array $fixture, $projectRoot = null)
    {
        $validation = red_theme_preview_validate_reference_theme($themeId, $projectRoot);
        $contract = red_theme_preview_contract($fixture, $validation);

        return red_theme_preview_render_prepared_contract($validation, $contract);
    }
}

if (!function_exists('red_theme_preview_render')) {
    function red_theme_preview_render(
        $themeId = 'starter-reference',
        $projectRoot = null,
        $fixturePath = 'fixtures/preview.json'
    ) {
        $validation = red_theme_preview_validate_reference_theme($themeId, $projectRoot);
        $fixture = red_theme_preview_load_fixture($validation, $fixturePath);
        $contract = red_theme_preview_contract($fixture, $validation);

        return red_theme_preview_render_prepared_contract($validation, $contract);
    }
}

if (!function_exists('red_theme_preview_render_allowed_fixture')) {
    function red_theme_preview_render_allowed_fixture(
        $themeId,
        $projectRoot = null,
        $fixturePath = 'fixtures/preview.json'
    ) {
        $validation = red_theme_preview_validate_fixture_theme($themeId, $projectRoot);
        $fixture = red_theme_preview_load_fixture($validation, $fixturePath);
        $contract = red_theme_preview_contract($fixture, $validation);

        return red_theme_preview_render_prepared_contract($validation, $contract);
    }
}

if (!function_exists('red_theme_preview_temp_output_path')) {
    function red_theme_preview_temp_output_path($path)
    {
        if (!is_string($path) || $path === '' || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'html') {
            throw new InvalidArgumentException('Preview output must be an HTML file inside the system temp directory.');
        }
        $directory = realpath(dirname($path));
        $tempRoots = [];
        foreach ([sys_get_temp_dir(), '/tmp'] as $candidateRoot) {
            $resolvedRoot = realpath($candidateRoot);
            if ($resolvedRoot !== false) {
                $tempRoots[$resolvedRoot] = true;
            }
        }
        $insideTempRoot = false;
        foreach (array_keys($tempRoots) as $tempRoot) {
            if ($directory === $tempRoot || strpos((string) $directory, $tempRoot . DIRECTORY_SEPARATOR) === 0) {
                $insideTempRoot = true;
                break;
            }
        }
        if ($directory === false || !$insideTempRoot || is_link($path)) {
            throw new InvalidArgumentException('Preview output must resolve inside the system temp directory.');
        }

        return $directory . DIRECTORY_SEPARATOR . basename($path);
    }
}
