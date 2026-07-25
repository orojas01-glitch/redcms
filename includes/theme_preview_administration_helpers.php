<?php
/**
 * Fixed read-only Administration landing provider for the isolated starter.
 *
 * Four fixed SELECT reads reconstruct only active Spanish Section 25, its
 * exact Login/Instructions/Video composition, the two root navigation rows,
 * and two bounded text settings. The provider reads no request/session state,
 * exposes no caller-selected route or query, and never participates in live
 * rendering, theme selection, settings persistence, or activation.
 */

require_once __DIR__ . '/theme_preview_helpers.php';

if (!function_exists('red_theme_administration_preview_canary')) {
    function red_theme_administration_preview_canary()
    {
        return [
            'sectionRecordId' => 25,
            'section' => 'administracion',
            'legacyLanguage' => 'sp',
            'documentLanguage' => 'es',
            'route' => '/administracion/',
            'layout' => 'index-3',
            'queryLimit' => 100,
            'loginArticleRecordId' => 966111194,
            'loginArticleAlias' => 'login',
            'loginFormRecordId' => 884542279,
            'loginFormAlias' => 'login',
            'instructionsArticleRecordId' => 89196971,
            'instructionsArticleAlias' => 'instructions',
            'instructionsRoute' => '/administracion/instructions',
            'videoArticleRecordId' => 880701099,
            'videoArticleAlias' => 'admin-video',
            'videoGalleryRecordId' => 1968830051,
            'videoGalleryAlias' => 'admin-video',
            'videoProvider' => 'youtube',
            'videoId' => 'pP8VJwjSnqA',
        ];
    }
}

if (!function_exists('red_theme_administration_preview_query_inventory')) {
    function red_theme_administration_preview_query_inventory()
    {
        return [
            'administration-section' =>
                "SELECT RecordID, Sections, Title, Layout, QueryLimit, Description, Tags, Features, Language, Active\n" .
                "FROM RED_Sections\n" .
                "WHERE RecordID=25 AND Sections='administracion' AND Language='sp' AND Active='Y'\n" .
                'LIMIT 2',
            'administration-composition' =>
                "SELECT a.RecordID AS ArticleRecordID, a.Alias AS ArticleAlias, a.Title AS ArticleTitle,\n" .
                "a.Component, a.SectionPosition, a.SectionPositionOrder, a.Layout AS ArticleLayout,\n" .
                "a.PagePosition, a.ShortDesc AS ArticleShortDesc, a.Sections, a.Language, a.Active,\n" .
                "f.RecordID AS FormRecordID, f.RefID AS FormRefID, f.Alias AS FormAlias,\n" .
                "f.Title AS FormTitle, f.FormType, f.LongDesc AS FormTemplate,\n" .
                "g.RecordID AS GalleryRecordID, g.RefID AS GalleryRefID, g.Alias AS GalleryAlias,\n" .
                "g.Title AS GalleryTitle, g.GalleryType, g.ShortDesc AS GalleryCaption,\n" .
                "g.LongDesc AS GallerySource, g.Link AS GalleryLink, g.NewWindow AS GalleryNewWindow\n" .
                "FROM RED_Articles AS a\n" .
                "LEFT JOIN RED_C_Form AS f ON CAST(f.RefID AS UNSIGNED)=a.RecordID\n" .
                "LEFT JOIN RED_C_Gallery AS g ON CAST(g.RefID AS UNSIGNED)=a.RecordID\n" .
                "WHERE a.RecordID IN (966111194, 89196971, 880701099)\n" .
                "AND a.Sections='administracion' AND a.Language='sp' AND a.Active='Y'\n" .
                'ORDER BY a.SectionPosition ASC, a.SectionPositionOrder ASC, a.RecordID ASC LIMIT 4',
            'administration-navigation' =>
                "SELECT RecordID, RootOrder, Label, Link, NewWindow, MenuOrder, Active, Language\n" .
                "FROM RED_Menu\n" .
                "WHERE RootOrder='1' AND Parent=0 AND Language='sp' AND Active='Y'\n" .
                'ORDER BY MenuOrder ASC, RecordID ASC LIMIT 20',
            'administration-settings' =>
                "SELECT RecordID, Item, Content, Language\n" .
                "FROM RED_Advanced\n" .
                "WHERE Language='sp' AND Item IN ('Website_Footer', 'Website_Title')\n" .
                'ORDER BY Item ASC, RecordID ASC LIMIT 3',
        ];
    }
}

if (!function_exists('red_theme_administration_preview_assert_query_inventory')) {
    function red_theme_administration_preview_assert_query_inventory(array $queries)
    {
        if (array_keys($queries) !== [
            'administration-section',
            'administration-composition',
            'administration-navigation',
            'administration-settings',
        ]) {
            throw new RuntimeException(
                'Administration preview query inventory must contain exactly four fixed reads.'
            );
        }

        $allowedTables = [
            'RED_Sections' => true,
            'RED_Articles' => true,
            'RED_C_Form' => true,
            'RED_C_Gallery' => true,
            'RED_Menu' => true,
            'RED_Advanced' => true,
        ];
        foreach ($queries as $id => $sql) {
            if (!is_string($sql)
                || preg_match('/\ASELECT\s/i', ltrim($sql)) !== 1
                || strpos($sql, ';') !== false
                || preg_match(
                    '/\b(?:ALTER|CALL|CREATE|DELETE|DROP|GRANT|INSERT|LOAD|LOCK|RENAME|REPLACE|REVOKE|TRUNCATE|UPDATE)\b/i',
                    $sql
                ) === 1
                || preg_match('/(?:--|#|\/\*)/', $sql) === 1
            ) {
                throw new RuntimeException(
                    'Administration preview query "' . $id . '" is not a single fixed SELECT.'
                );
            }
            if (preg_match_all('/\b(?:FROM|JOIN)\s+([A-Za-z0-9_]+)/i', $sql, $matches) < 1) {
                throw new RuntimeException(
                    'Administration preview query "' . $id . '" has no declared source table.'
                );
            }
            foreach ($matches[1] as $table) {
                if (!isset($allowedTables[$table])) {
                    throw new RuntimeException(
                        'Administration preview query "' . $id . '" uses an unexpected table.'
                    );
                }
            }
        }

        return true;
    }
}

if (!function_exists('red_theme_administration_preview_scope')) {
    function red_theme_administration_preview_scope($databaseReads)
    {
        return red_theme_preview_scope([
            'databaseReads' => $databaseReads,
            'databaseWrites' => 0,
            'sessionReads' => 0,
            'sessionWrites' => 0,
            'liveRuntimeChanges' => 0,
        ]);
    }
}

if (!function_exists('red_theme_administration_preview_read_rows')) {
    function red_theme_administration_preview_read_rows($connection)
    {
        if (!is_object($connection)
            || !method_exists($connection, 'query')
            || !method_exists($connection, 'commit')
            || !method_exists($connection, 'rollback')
        ) {
            throw new InvalidArgumentException('Administration preview requires a valid mysqli connection.');
        }

        $queries = red_theme_administration_preview_query_inventory();
        red_theme_administration_preview_assert_query_inventory($queries);
        $started = false;
        try {
            if ($connection->query('START TRANSACTION READ ONLY') !== true) {
                throw new RuntimeException('Administration preview could not start a read-only transaction.');
            }
            $started = true;
            $rows = [];
            foreach ($queries as $id => $sql) {
                $result = $connection->query($sql);
                if (!is_object($result) || !method_exists($result, 'fetch_assoc')) {
                    throw new RuntimeException('Administration preview fixed read "' . $id . '" failed.');
                }
                $resultRows = [];
                while ($row = $result->fetch_assoc()) {
                    if (!is_array($row)) {
                        throw new RuntimeException('Administration preview received an invalid database row.');
                    }
                    $resultRows[] = $row;
                    if (count($resultRows) > 20) {
                        throw new RuntimeException(
                            'Administration preview query exceeded its fixed row boundary.'
                        );
                    }
                }
                if (method_exists($result, 'free')) {
                    $result->free();
                }
                $rows[$id] = $resultRows;
            }
            if (!$connection->commit()) {
                throw new RuntimeException('Administration preview could not close its read-only transaction.');
            }
            $started = false;
        } catch (Throwable $exception) {
            if ($started) {
                $connection->rollback();
            }
            throw $exception;
        }

        return [
            'rows' => [
                'section' => $rows['administration-section'],
                'composition' => $rows['administration-composition'],
                'navigation' => $rows['administration-navigation'],
                'settings' => $rows['administration-settings'],
            ],
            'scope' => red_theme_administration_preview_scope(count($queries)),
        ];
    }
}

if (!function_exists('red_theme_administration_preview_require_row_keys')) {
    function red_theme_administration_preview_require_row_keys($row, array $expectedKeys, $context)
    {
        if (!is_array($row) || array_keys($row) !== $expectedKeys) {
            throw new InvalidArgumentException($context . ' must contain the exact selected columns in order.');
        }

        return $row;
    }
}

if (!function_exists('red_theme_administration_preview_source_string')) {
    function red_theme_administration_preview_source_string(
        $value,
        $context,
        $allowEmpty = false,
        $maximumLength = 500
    ) {
        $value = red_theme_preview_string($value, $context, $allowEmpty, $maximumLength);
        if (strpos($value, '<?') !== false || preg_match('//u', $value) !== 1) {
            throw new InvalidArgumentException($context . ' contains unsafe or invalid text.');
        }

        return $value;
    }
}

if (!function_exists('red_theme_administration_preview_integer')) {
    function red_theme_administration_preview_integer(
        $value,
        $context,
        $minimum = 0,
        $maximum = 2147483647
    ) {
        if ((is_int($value) && $value >= 0)
            || (is_string($value) && preg_match('/\A(?:0|[1-9][0-9]*)\z/', $value) === 1)
        ) {
            $integer = (int) $value;
            if ((string) $integer === (string) $value || is_int($value)) {
                if ($integer >= $minimum && $integer <= $maximum) {
                    return $integer;
                }
            }
        }

        throw new InvalidArgumentException($context . ' must be a bounded unsigned integer.');
    }
}

if (!function_exists('red_theme_administration_preview_plain_text')) {
    function red_theme_administration_preview_plain_text(
        $value,
        $context,
        $allowEmpty = false,
        $maximumLength = 500
    ) {
        $source = red_theme_administration_preview_source_string(
            $value,
            $context,
            $allowEmpty,
            max($maximumLength * 4, $maximumLength)
        );
        $plain = html_entity_decode(strip_tags($source), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = preg_replace('/\s+/u', ' ', trim($plain));
        if (!is_string($plain)
            || (!$allowEmpty && $plain === '')
            || strlen($plain) > $maximumLength
        ) {
            throw new InvalidArgumentException($context . ' must reduce to bounded plain text.');
        }

        return $plain;
    }
}

if (!function_exists('red_theme_administration_preview_section')) {
    function red_theme_administration_preview_section($rows)
    {
        if (!is_array($rows) || !array_is_list($rows) || count($rows) !== 1) {
            throw new InvalidArgumentException(
                'Administration preview requires exactly one Administration section row.'
            );
        }
        $row = red_theme_administration_preview_require_row_keys(
            $rows[0],
            [
                'RecordID',
                'Sections',
                'Title',
                'Layout',
                'QueryLimit',
                'Description',
                'Tags',
                'Features',
                'Language',
                'Active',
            ],
            'Administration section row'
        );
        $canary = red_theme_administration_preview_canary();
        $recordId = red_theme_administration_preview_integer(
            $row['RecordID'],
            'Administration section RecordID',
            1
        );
        $section = red_theme_administration_preview_source_string(
            $row['Sections'],
            'Administration section alias',
            false,
            100
        );
        $layout = red_theme_administration_preview_source_string(
            $row['Layout'],
            'Administration section layout',
            false,
            64
        );
        $queryLimit = red_theme_administration_preview_integer(
            $row['QueryLimit'],
            'Administration section query limit',
            1,
            1000
        );
        $features = red_theme_administration_preview_source_string(
            $row['Features'],
            'Administration section features',
            true,
            200
        );
        $language = red_theme_administration_preview_source_string(
            $row['Language'],
            'Administration section language',
            false,
            2
        );
        $active = red_theme_administration_preview_source_string(
            $row['Active'],
            'Administration section active state',
            false,
            1
        );
        if ($recordId !== $canary['sectionRecordId']
            || $section !== $canary['section']
            || $layout !== $canary['layout']
            || $queryLimit !== $canary['queryLimit']
            || $features !== ''
            || $language !== $canary['legacyLanguage']
            || $active !== 'Y'
        ) {
            throw new InvalidArgumentException(
                'Administration section row does not match the fixed active canary.'
            );
        }

        return [
            'recordId' => $recordId,
            'section' => $section,
            'title' => red_theme_administration_preview_plain_text(
                $row['Title'],
                'Administration section title',
                false,
                120
            ),
            'layout' => $layout,
            'queryLimit' => $queryLimit,
            'description' => red_theme_administration_preview_plain_text(
                $row['Description'],
                'Administration section description',
                true,
                300
            ),
            'tags' => red_theme_administration_preview_plain_text(
                $row['Tags'],
                'Administration section tags',
                true,
                300
            ),
            'features' => [],
            'language' => $language,
            'active' => $active,
        ];
    }
}

if (!function_exists('red_theme_administration_preview_navigation')) {
    function red_theme_administration_preview_navigation($rows)
    {
        if (!is_array($rows) || !array_is_list($rows) || count($rows) !== 2) {
            throw new InvalidArgumentException(
                'Administration preview requires exactly the active Spanish Home and Contact root navigation rows.'
            );
        }
        $canary = red_theme_administration_preview_canary();
        $items = [];
        $recordIds = [];
        $links = [];
        $lastOrder = -1;
        foreach ($rows as $index => $sourceRow) {
            $row = red_theme_administration_preview_require_row_keys(
                $sourceRow,
                ['RecordID', 'RootOrder', 'Label', 'Link', 'NewWindow', 'MenuOrder', 'Active', 'Language'],
                'Administration navigation row ' . $index
            );
            $recordId = red_theme_administration_preview_integer(
                $row['RecordID'],
                'Administration navigation RecordID',
                1
            );
            if (isset($recordIds[$recordId])) {
                throw new InvalidArgumentException('Administration navigation RecordIDs must be unique.');
            }
            $rootOrder = red_theme_administration_preview_source_string(
                $row['RootOrder'],
                'Administration navigation RootOrder',
                false,
                2
            );
            $link = red_theme_preview_url(
                red_theme_administration_preview_source_string(
                    $row['Link'],
                    'Administration navigation link',
                    false,
                    500
                ),
                'Administration navigation link'
            );
            $newWindow = red_theme_administration_preview_source_string(
                $row['NewWindow'],
                'Administration navigation new-window state',
                true,
                6
            );
            $menuOrder = red_theme_administration_preview_integer(
                $row['MenuOrder'],
                'Administration navigation order',
                0,
                9999
            );
            $active = red_theme_administration_preview_source_string(
                $row['Active'],
                'Administration navigation active state',
                false,
                1
            );
            $language = red_theme_administration_preview_source_string(
                $row['Language'],
                'Administration navigation language',
                false,
                2
            );
            if ($rootOrder !== '1'
                || !in_array($newWindow, ['', 'N', 'Y'], true)
                || $menuOrder <= $lastOrder
                || $active !== 'Y'
                || $language !== $canary['legacyLanguage']
                || isset($links[$link])
            ) {
                throw new InvalidArgumentException(
                    'Administration navigation row is outside the fixed root-menu contract.'
                );
            }
            $recordIds[$recordId] = true;
            $links[$link] = true;
            $lastOrder = $menuOrder;
            $items[] = [
                'recordId' => $recordId,
                'label' => red_theme_administration_preview_plain_text(
                    $row['Label'],
                    'Administration navigation label',
                    false,
                    80
                ),
                'url' => $link,
                'current' => false,
                'menuOrder' => $menuOrder,
            ];
        }
        if (array_keys($links) !== ['/', '/contacto/']
            || $items[0]['label'] !== 'Inicio'
            || $items[1]['label'] !== 'Contacto'
        ) {
            throw new InvalidArgumentException(
                'Administration navigation rows do not match the fixed Home/Contact canary.'
            );
        }

        return $items;
    }
}

if (!function_exists('red_theme_administration_preview_settings')) {
    function red_theme_administration_preview_settings($rows)
    {
        if (!is_array($rows) || !array_is_list($rows) || count($rows) > 2) {
            throw new InvalidArgumentException(
                'Administration preview settings must contain at most two fixed rows.'
            );
        }
        $allowedItems = ['Website_Footer' => true, 'Website_Title' => true];
        $settings = [];
        $recordIds = [];
        foreach ($rows as $index => $sourceRow) {
            $row = red_theme_administration_preview_require_row_keys(
                $sourceRow,
                ['RecordID', 'Item', 'Content', 'Language'],
                'Administration setting row ' . $index
            );
            $recordId = red_theme_administration_preview_integer(
                $row['RecordID'],
                'Administration setting RecordID',
                1
            );
            $item = red_theme_administration_preview_source_string(
                $row['Item'],
                'Administration setting item',
                false,
                50
            );
            $language = red_theme_administration_preview_source_string(
                $row['Language'],
                'Administration setting language',
                false,
                2
            );
            if (!isset($allowedItems[$item])
                || isset($settings[$item])
                || isset($recordIds[$recordId])
                || $language !== red_theme_administration_preview_canary()['legacyLanguage']
            ) {
                throw new InvalidArgumentException(
                    'Administration setting row is duplicated or outside the fixed allowlist.'
                );
            }
            $recordIds[$recordId] = true;
            $settings[$item] = red_theme_administration_preview_plain_text(
                $row['Content'],
                'Administration setting content',
                true,
                180
            );
        }

        return $settings;
    }
}

if (!function_exists('red_theme_administration_preview_boolean')) {
    function red_theme_administration_preview_boolean($value, $context)
    {
        $value = red_theme_administration_preview_source_string($value, $context, false, 5);
        if ($value === 'true') {
            return true;
        }
        if ($value === 'false') {
            return false;
        }

        throw new InvalidArgumentException($context . ' must be exactly true or false.');
    }
}

if (!function_exists('red_theme_administration_preview_login_template')) {
    function red_theme_administration_preview_login_template($template)
    {
        $template = red_theme_administration_preview_source_string(
            $template,
            'Administration legacy Login definition',
            false,
            3000
        );
        $records = explode(';', str_replace(["\r\n", "\r"], "\n", $template));
        $parsedRecords = [];
        foreach ($records as $record) {
            $record = trim($record);
            if ($record !== '') {
                $parsedRecords[] = $record;
            }
        }
        if (count($parsedRecords) !== 3) {
            throw new InvalidArgumentException(
                'Administration legacy Login definition must contain two fields and one preview-only button.'
            );
        }

        $allowedKeys = [
            'question' => true,
            'name' => true,
            'type' => true,
            'required' => true,
            'displayname' => true,
            'initialvalue' => true,
        ];
        $fields = [];
        $fieldNames = [];
        $submitLabel = null;
        foreach ($parsedRecords as $recordIndex => $record) {
            $parts = explode('|', $record);
            if (array_shift($parts) !== '#' || $parts === []) {
                throw new InvalidArgumentException(
                    'Administration legacy Login record prefix is invalid.'
                );
            }
            $definition = [];
            foreach ($parts as $part) {
                $separator = strpos($part, '=');
                if ($separator === false) {
                    throw new InvalidArgumentException(
                        'Administration legacy Login record contains a malformed property.'
                    );
                }
                $key = substr($part, 0, $separator);
                $value = substr($part, $separator + 1);
                if ($key === '' || !isset($allowedKeys[$key]) || array_key_exists($key, $definition)) {
                    throw new InvalidArgumentException(
                        'Administration legacy Login record contains an unknown or duplicated property.'
                    );
                }
                $definition[$key] = red_theme_administration_preview_source_string(
                    $value,
                    'Administration legacy Login property',
                    true,
                    300
                );
            }
            foreach (['question', 'name', 'type', 'displayname'] as $requiredKey) {
                if (!array_key_exists($requiredKey, $definition)) {
                    throw new InvalidArgumentException(
                        'Administration legacy Login record is missing a required property.'
                    );
                }
            }
            if ($definition['question'] !== '') {
                throw new InvalidArgumentException(
                    'Administration legacy Login questions must remain empty.'
                );
            }
            $name = $definition['name'];
            $type = $definition['type'];
            if ($type === 'button') {
                if ($recordIndex !== count($parsedRecords) - 1
                    || $submitLabel !== null
                    || $name !== 'Submit'
                    || array_diff(array_keys($definition), ['question', 'name', 'type', 'displayname']) !== []
                ) {
                    throw new InvalidArgumentException(
                        'Administration legacy Login button must be the final minimal preview-only record.'
                    );
                }
                $submitLabel = red_theme_administration_preview_plain_text(
                    $definition['displayname'],
                    'Administration legacy Login button label',
                    false,
                    80
                );
                continue;
            }
            foreach (['required', 'initialvalue'] as $requiredKey) {
                if (!array_key_exists($requiredKey, $definition)) {
                    throw new InvalidArgumentException(
                        'Administration legacy Login field is missing a required safety property.'
                    );
                }
            }
            if (!in_array($name, ['username', 'password'], true)
                || isset($fieldNames[$name])
                || $definition['initialvalue'] !== ''
                || $definition['required'] !== 'true'
                || ($name === 'username' && $type !== 'textfield')
                || ($name === 'password' && $type !== 'password')
            ) {
                throw new InvalidArgumentException(
                    'Administration legacy Login field is unsupported or unsafe.'
                );
            }
            $fieldNames[$name] = true;
            $fields[] = [
                'name' => $name,
                'label' => red_theme_administration_preview_plain_text(
                    $definition['displayname'],
                    'Administration legacy Login field label',
                    false,
                    100
                ),
                'type' => $name === 'password' ? 'password' : 'text',
                'autocomplete' => $name === 'password' ? 'current-password' : 'username',
                'required' => red_theme_administration_preview_boolean(
                    $definition['required'],
                    'Administration legacy Login required state'
                ),
            ];
        }
        if (array_keys($fieldNames) !== ['username', 'password'] || $submitLabel === null) {
            throw new InvalidArgumentException(
                'Administration legacy Login fields do not match the fixed canary.'
            );
        }

        return ['fields' => $fields, 'submitLabel' => $submitLabel];
    }
}

if (!function_exists('red_theme_administration_preview_video_source')) {
    function red_theme_administration_preview_video_source($source)
    {
        $source = red_theme_administration_preview_source_string(
            $source,
            'Administration Gallery Video source',
            false,
            500
        );
        $patterns = [
            'youtube' => [
                '~\Ahttps://(?:www\.)?youtube\.com/watch\?v=([A-Za-z0-9_-]{11})(?:&feature=youtu\.be)?\z~',
                '~\Ahttps://youtu\.be/([A-Za-z0-9_-]{11})\z~',
                '~\Ahttps://(?:www\.)?youtube\.com/embed/([A-Za-z0-9_-]{11})(?:\?wmode=transparent)?\z~',
            ],
            'vimeo' => [
                '~\Ahttps://vimeo\.com/([1-9][0-9]{5,11})\z~',
                '~\Ahttps://player\.vimeo\.com/video/([1-9][0-9]{5,11})\z~',
            ],
        ];
        foreach ($patterns as $provider => $providerPatterns) {
            foreach ($providerPatterns as $pattern) {
                if (preg_match($pattern, $source, $matches) === 1) {
                    return ['provider' => $provider, 'id' => $matches[1]];
                }
            }
        }

        throw new InvalidArgumentException(
            'Administration Gallery Video source is outside the fixed HTTPS provider shapes.'
        );
    }
}

if (!function_exists('red_theme_administration_preview_require_null_columns')) {
    function red_theme_administration_preview_require_null_columns(array $row, array $keys, $context)
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $row) || $row[$key] !== null) {
                throw new InvalidArgumentException($context . ' has an unexpected paired child row.');
            }
        }
    }
}

if (!function_exists('red_theme_administration_preview_composition')) {
    function red_theme_administration_preview_composition($rows)
    {
        if (!is_array($rows) || !array_is_list($rows) || count($rows) !== 3) {
            throw new InvalidArgumentException(
                'Administration preview requires exactly three ordered composition rows.'
            );
        }
        $expectedKeys = [
            'ArticleRecordID',
            'ArticleAlias',
            'ArticleTitle',
            'Component',
            'SectionPosition',
            'SectionPositionOrder',
            'ArticleLayout',
            'PagePosition',
            'ArticleShortDesc',
            'Sections',
            'Language',
            'Active',
            'FormRecordID',
            'FormRefID',
            'FormAlias',
            'FormTitle',
            'FormType',
            'FormTemplate',
            'GalleryRecordID',
            'GalleryRefID',
            'GalleryAlias',
            'GalleryTitle',
            'GalleryType',
            'GalleryCaption',
            'GallerySource',
            'GalleryLink',
            'GalleryNewWindow',
        ];
        $formKeys = [
            'FormRecordID',
            'FormRefID',
            'FormAlias',
            'FormTitle',
            'FormType',
            'FormTemplate',
        ];
        $galleryKeys = [
            'GalleryRecordID',
            'GalleryRefID',
            'GalleryAlias',
            'GalleryTitle',
            'GalleryType',
            'GalleryCaption',
            'GallerySource',
            'GalleryLink',
            'GalleryNewWindow',
        ];
        $canary = red_theme_administration_preview_canary();
        $expectedOrder = [
            $canary['loginArticleRecordId'],
            $canary['instructionsArticleRecordId'],
            $canary['videoArticleRecordId'],
        ];
        $prepared = [];
        $observedOrder = [];

        foreach ($rows as $index => $sourceRow) {
            $row = red_theme_administration_preview_require_row_keys(
                $sourceRow,
                $expectedKeys,
                'Administration composition row ' . $index
            );
            $articleRecordId = red_theme_administration_preview_integer(
                $row['ArticleRecordID'],
                'Administration Article RecordID',
                1
            );
            $observedOrder[] = $articleRecordId;
            $articleAlias = red_theme_administration_preview_source_string(
                $row['ArticleAlias'],
                'Administration Article alias',
                false,
                100
            );
            $articleTitle = red_theme_administration_preview_plain_text(
                $row['ArticleTitle'],
                'Administration Article title',
                false,
                180
            );
            $component = red_theme_administration_preview_source_string(
                $row['Component'],
                'Administration Article component',
                false,
                40
            );
            $position = red_theme_administration_preview_integer(
                $row['SectionPosition'],
                'Administration Article section position',
                0,
                2
            );
            $order = red_theme_administration_preview_integer(
                $row['SectionPositionOrder'],
                'Administration Article section order',
                0,
                9999
            );
            $articleLayout = red_theme_administration_preview_source_string(
                $row['ArticleLayout'],
                'Administration Article layout',
                true,
                64
            );
            $pagePosition = red_theme_administration_preview_integer(
                $row['PagePosition'],
                'Administration Article page position',
                0,
                4
            );
            $section = red_theme_administration_preview_source_string(
                $row['Sections'],
                'Administration Article section',
                false,
                100
            );
            $language = red_theme_administration_preview_source_string(
                $row['Language'],
                'Administration Article language',
                false,
                2
            );
            $active = red_theme_administration_preview_source_string(
                $row['Active'],
                'Administration Article active state',
                false,
                1
            );
            if ($section !== $canary['section']
                || $language !== $canary['legacyLanguage']
                || $active !== 'Y'
            ) {
                throw new InvalidArgumentException(
                    'Administration composition row is outside the fixed active section canary.'
                );
            }

            if ($articleRecordId === $canary['loginArticleRecordId']) {
                if ($articleAlias !== $canary['loginArticleAlias']
                    || $component !== 'Form'
                    || $position !== 1
                    || $order !== 1
                    || $articleLayout !== 'index-2'
                    || $pagePosition !== 1
                ) {
                    throw new InvalidArgumentException(
                        'Administration Login row does not match the fixed position canary.'
                    );
                }
                red_theme_administration_preview_require_null_columns(
                    $row,
                    $galleryKeys,
                    'Administration Login row'
                );
                $formRecordId = red_theme_administration_preview_integer(
                    $row['FormRecordID'],
                    'Administration Login Form RecordID',
                    1
                );
                $formRefId = red_theme_administration_preview_integer(
                    $row['FormRefID'],
                    'Administration Login Form RefID',
                    1
                );
                $formAlias = red_theme_administration_preview_source_string(
                    $row['FormAlias'],
                    'Administration Login Form alias',
                    false,
                    100
                );
                $formType = red_theme_administration_preview_source_string(
                    $row['FormType'],
                    'Administration Login Form type',
                    false,
                    20
                );
                if ($formRecordId !== $canary['loginFormRecordId']
                    || $formRefId !== $articleRecordId
                    || $formAlias !== $canary['loginFormAlias']
                    || $formType !== 'Login'
                ) {
                    throw new InvalidArgumentException(
                        'Administration Login Form does not match the fixed paired canary.'
                    );
                }
                $template = red_theme_administration_preview_login_template($row['FormTemplate']);
                $prepared['login'] = [
                    'articleRecordId' => $articleRecordId,
                    'articleAlias' => $articleAlias,
                    'articleTitle' => $articleTitle,
                    'component' => $component,
                    'sectionPosition' => $position,
                    'sectionPositionOrder' => $order,
                    'summary' => red_theme_administration_preview_plain_text(
                        $row['ArticleShortDesc'],
                        'Administration Login summary',
                        false,
                        180
                    ),
                    'formRecordId' => $formRecordId,
                    'formTitle' => red_theme_administration_preview_plain_text(
                        $row['FormTitle'],
                        'Administration Login Form title',
                        false,
                        180
                    ),
                    'formType' => $formType,
                    'fields' => $template['fields'],
                    'submitLabel' => $template['submitLabel'],
                ];
                continue;
            }

            if ($articleRecordId === $canary['instructionsArticleRecordId']) {
                if ($articleAlias !== $canary['instructionsArticleAlias']
                    || $component !== 'Article'
                    || $position !== 2
                    || $order !== 1
                    || $articleLayout !== 'index-2'
                    || $pagePosition !== 1
                ) {
                    throw new InvalidArgumentException(
                        'Administration Instructions row does not match the fixed position canary.'
                    );
                }
                red_theme_administration_preview_require_null_columns(
                    $row,
                    array_merge($formKeys, $galleryKeys),
                    'Administration Instructions row'
                );
                $prepared['instructions'] = [
                    'articleRecordId' => $articleRecordId,
                    'articleAlias' => $articleAlias,
                    'articleTitle' => $articleTitle,
                    'component' => $component,
                    'sectionPosition' => $position,
                    'sectionPositionOrder' => $order,
                    'summary' => red_theme_administration_preview_plain_text(
                        $row['ArticleShortDesc'],
                        'Administration Instructions summary',
                        false,
                        1000
                    ),
                ];
                continue;
            }

            if ($articleRecordId === $canary['videoArticleRecordId']) {
                if ($articleAlias !== $canary['videoArticleAlias']
                    || $component !== 'Gallery'
                    || $position !== 2
                    || $order !== 2
                    || $articleLayout !== ''
                    || $pagePosition !== 1
                    || red_theme_administration_preview_source_string(
                        $row['ArticleShortDesc'],
                        'Administration Video Article summary',
                        true,
                        500
                    ) !== ''
                ) {
                    throw new InvalidArgumentException(
                        'Administration Video row does not match the fixed position canary.'
                    );
                }
                red_theme_administration_preview_require_null_columns(
                    $row,
                    $formKeys,
                    'Administration Video row'
                );
                $galleryRecordId = red_theme_administration_preview_integer(
                    $row['GalleryRecordID'],
                    'Administration Video Gallery RecordID',
                    1
                );
                $galleryRefId = red_theme_administration_preview_integer(
                    $row['GalleryRefID'],
                    'Administration Video Gallery RefID',
                    1
                );
                $galleryAlias = red_theme_administration_preview_source_string(
                    $row['GalleryAlias'],
                    'Administration Video Gallery alias',
                    false,
                    100
                );
                $galleryType = red_theme_administration_preview_source_string(
                    $row['GalleryType'],
                    'Administration Video Gallery type',
                    false,
                    20
                );
                $galleryLink = red_theme_administration_preview_source_string(
                    $row['GalleryLink'],
                    'Administration Video Gallery link',
                    true,
                    500
                );
                $galleryNewWindow = red_theme_administration_preview_source_string(
                    $row['GalleryNewWindow'],
                    'Administration Video Gallery new-window state',
                    true,
                    6
                );
                if ($galleryRecordId !== $canary['videoGalleryRecordId']
                    || $galleryRefId !== $articleRecordId
                    || $galleryAlias !== $canary['videoGalleryAlias']
                    || $galleryType !== 'Video'
                    || $galleryLink !== ''
                    || $galleryNewWindow !== ''
                ) {
                    throw new InvalidArgumentException(
                        'Administration Video Gallery does not match the fixed paired canary.'
                    );
                }
                $video = red_theme_administration_preview_video_source($row['GallerySource']);
                if ($video['provider'] !== $canary['videoProvider']
                    || $video['id'] !== $canary['videoId']
                ) {
                    throw new InvalidArgumentException(
                        'Administration Video provider/id does not match the fixed media canary.'
                    );
                }
                $galleryTitle = red_theme_administration_preview_plain_text(
                    $row['GalleryTitle'],
                    'Administration Video Gallery title',
                    false,
                    180
                );
                $galleryCaption = red_theme_administration_preview_plain_text(
                    $row['GalleryCaption'],
                    'Administration Video Gallery caption',
                    true,
                    180
                );
                $prepared['video'] = [
                    'articleRecordId' => $articleRecordId,
                    'articleAlias' => $articleAlias,
                    'articleTitle' => $articleTitle,
                    'component' => $component,
                    'sectionPosition' => $position,
                    'sectionPositionOrder' => $order,
                    'galleryRecordId' => $galleryRecordId,
                    'galleryAlias' => $galleryAlias,
                    'galleryTitle' => $galleryTitle,
                    'galleryType' => $galleryType,
                    'provider' => $video['provider'],
                    'id' => $video['id'],
                    'caption' => $galleryCaption !== '' ? $galleryCaption : $galleryTitle,
                    'captionSource' => $galleryCaption !== '' ? 'gallery.ShortDesc' : 'gallery.Title',
                ];
                continue;
            }

            throw new InvalidArgumentException(
                'Administration composition contains an unexpected Article RecordID.'
            );
        }

        if ($observedOrder !== $expectedOrder
            || array_keys($prepared) !== ['login', 'instructions', 'video']
        ) {
            throw new InvalidArgumentException(
                'Administration composition rows do not match the fixed ordered canary.'
            );
        }

        return $prepared;
    }
}

if (!function_exists('red_theme_administration_preview_prepare_rows')) {
    function red_theme_administration_preview_prepare_rows(array $rows)
    {
        red_theme_preview_require_exact_keys(
            $rows,
            ['section', 'composition', 'navigation', 'settings'],
            [],
            'Administration preview source rows'
        );
        $section = red_theme_administration_preview_section($rows['section']);
        $composition = red_theme_administration_preview_composition($rows['composition']);
        $navigation = red_theme_administration_preview_navigation($rows['navigation']);
        $settings = red_theme_administration_preview_settings($rows['settings']);
        $canary = red_theme_administration_preview_canary();

        $siteTitleSource = !empty($settings['Website_Title'])
            ? 'advanced.Website_Title'
            : 'section.Title';
        $footerSource = !empty($settings['Website_Footer'])
            ? 'advanced.Website_Footer'
            : 'section.Title';
        $descriptionSource = $section['description'] !== ''
            ? 'section.Description'
            : 'login.ShortDesc';
        $siteTitle = $siteTitleSource === 'advanced.Website_Title'
            ? $settings['Website_Title']
            : $section['title'];
        $footer = $footerSource === 'advanced.Website_Footer'
            ? $settings['Website_Footer']
            : $section['title'];
        $description = $descriptionSource === 'section.Description'
            ? $section['description']
            : $composition['login']['summary'];

        $navigationItems = [];
        foreach ($navigation as $item) {
            $navigationItems[] = [
                'label' => $item['label'],
                'url' => $item['url'],
                'current' => $item['current'],
            ];
        }

        $fixture = [
            'schemaVersion' => 1,
            'theme' => 'starter-reference',
            'document' => [
                'language' => $canary['documentLanguage'],
                'title' => $section['title'] . ' — ' . $composition['instructions']['articleTitle'],
                'description' => $description,
            ],
            'regions' => [
                'header' => [
                    'siteTitle' => $siteTitle,
                    'homeUrl' => '/',
                ],
                'navigation' => [
                    'items' => $navigationItems,
                ],
                'hero' => [
                    'title' => $section['title'],
                    'summary' => $description,
                    'action' => [
                        'label' => 'View ' . $composition['instructions']['articleTitle'],
                        'url' => '#overview',
                    ],
                ],
                'footer' => [
                    'copyright' => $footer,
                ],
            ],
            'page' => [
                'layout' => $section['layout'],
                'breadcrumb' => [
                    [
                        'label' => $navigation[0]['label'],
                        'url' => $navigation[0]['url'],
                    ],
                    [
                        'label' => $section['title'],
                        'url' => '',
                    ],
                ],
                'slots' => [
                    '1' => [
                        [
                            'component' => 'Form',
                            'data' => [
                                'title' => $composition['login']['formTitle'],
                                'fields' => $composition['login']['fields'],
                                'submitLabel' => $composition['login']['submitLabel'],
                            ],
                        ],
                    ],
                    '2' => [
                        [
                            'component' => 'Article',
                            'data' => [
                                'title' => $composition['instructions']['articleTitle'],
                                'summary' => $composition['instructions']['summary'],
                                'url' => $canary['instructionsRoute'],
                                'linkLabel' => 'Read instructions',
                            ],
                        ],
                        [
                            'component' => 'Gallery',
                            'data' => [
                                'title' => $composition['video']['galleryTitle'],
                                'video' => [
                                    'provider' => $composition['video']['provider'],
                                    'id' => $composition['video']['id'],
                                    'caption' => $composition['video']['caption'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $source = [
            'mode' => 'read-only-administration-preview',
            'canary' => [
                'sectionRecordId' => $section['recordId'],
                'section' => $section['section'],
                'legacyLanguage' => $section['language'],
                'route' => $canary['route'],
                'layout' => $section['layout'],
                'queryLimit' => $section['queryLimit'],
                'features' => $section['features'],
            ],
            'login' => [
                'articleRecordId' => $composition['login']['articleRecordId'],
                'articleAlias' => $composition['login']['articleAlias'],
                'formRecordId' => $composition['login']['formRecordId'],
                'formType' => $composition['login']['formType'],
                'fieldCount' => count($composition['login']['fields']),
                'sectionPosition' => $composition['login']['sectionPosition'],
                'sectionPositionOrder' => $composition['login']['sectionPositionOrder'],
            ],
            'instructions' => [
                'articleRecordId' => $composition['instructions']['articleRecordId'],
                'articleAlias' => $composition['instructions']['articleAlias'],
                'summaryBytes' => strlen($composition['instructions']['summary']),
                'summarySha256' => hash('sha256', $composition['instructions']['summary']),
                'sectionPosition' => $composition['instructions']['sectionPosition'],
                'sectionPositionOrder' => $composition['instructions']['sectionPositionOrder'],
            ],
            'video' => [
                'articleRecordId' => $composition['video']['articleRecordId'],
                'articleAlias' => $composition['video']['articleAlias'],
                'galleryRecordId' => $composition['video']['galleryRecordId'],
                'galleryAlias' => $composition['video']['galleryAlias'],
                'provider' => $composition['video']['provider'],
                'id' => $composition['video']['id'],
                'captionSource' => $composition['video']['captionSource'],
                'sectionPosition' => $composition['video']['sectionPosition'],
                'sectionPositionOrder' => $composition['video']['sectionPositionOrder'],
            ],
            'queryIds' => array_keys(red_theme_administration_preview_query_inventory()),
            'rowCounts' => [
                'section' => count($rows['section']),
                'composition' => count($rows['composition']),
                'navigation' => count($rows['navigation']),
                'settings' => count($rows['settings']),
            ],
            'fallbacks' => [
                'siteTitle' => $siteTitleSource,
                'description' => $descriptionSource,
                'footer' => $footerSource,
                'videoCaption' => $composition['video']['captionSource'],
            ],
        ];
        red_theme_preview_assert_non_executable($fixture, 'Administration prepared preview input');
        red_theme_preview_assert_non_executable($source, 'Administration preview source metadata');

        return ['fixture' => $fixture, 'source' => $source];
    }
}

if (!function_exists('red_theme_administration_preview_render_rows')) {
    function red_theme_administration_preview_render_rows(
        array $rows,
        $projectRoot = null,
        $databaseReads = 0
    ) {
        if (!in_array($databaseReads, [0, 4], true)) {
            throw new InvalidArgumentException(
                'Administration preview database-read count must be zero or four.'
            );
        }
        $validation = red_theme_preview_validate_reference_theme('starter-reference', $projectRoot);
        $prepared = red_theme_administration_preview_prepare_rows($rows);
        $contract = red_theme_preview_contract($prepared['fixture'], $validation);
        $result = red_theme_preview_render_prepared_contract(
            $validation,
            $contract,
            'read-only-administration-preview',
            red_theme_administration_preview_scope($databaseReads)
        );
        $result['source'] = $prepared['source'];

        return $result;
    }
}

if (!function_exists('red_theme_administration_preview_render')) {
    function red_theme_administration_preview_render($connection, $projectRoot = null)
    {
        $read = red_theme_administration_preview_read_rows($connection);
        $result = red_theme_administration_preview_render_rows(
            $read['rows'],
            $projectRoot,
            $read['scope']['databaseReads']
        );
        if ($result['scope'] !== $read['scope']) {
            throw new RuntimeException(
                'Administration preview side-effect scope changed during rendering.'
            );
        }

        return $result;
    }
}
