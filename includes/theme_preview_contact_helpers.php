<?php
/**
 * Read-only Contact-canary input provider for the isolated starter preview.
 *
 * The developer CLI and the authenticated administrator preview both call this
 * shared mapping code. The provider itself reads no request or session state;
 * the administrator endpoint authorizes first, closes the session, and only
 * then maps four fixed SELECT results into the portable preview contract. It
 * never participates in live rendering, theme selection, or activation.
 */

require_once __DIR__ . '/theme_preview_helpers.php';

if (!function_exists('red_theme_contact_preview_canary')) {
    function red_theme_contact_preview_canary()
    {
        return [
            'recordId' => 24,
            'section' => 'contacto',
            'legacyLanguage' => 'sp',
            'documentLanguage' => 'es',
            'route' => '/contacto/',
            'layout' => 'index-1',
        ];
    }
}

if (!function_exists('red_theme_contact_preview_query_inventory')) {
    function red_theme_contact_preview_query_inventory()
    {
        return [
            'contact-section' =>
                "SELECT RecordID, Sections, Title, Layout, Description, Tags, Language, Active\n" .
                "FROM RED_Sections\n" .
                "WHERE RecordID=24 AND Sections='contacto' AND Language='sp' AND Active='Y'\n" .
                'LIMIT 2',
            'contact-form' =>
                "SELECT a.RecordID AS ArticleRecordID, a.Alias AS ArticleAlias,\n" .
                "a.Title AS ArticleTitle, a.Component, a.SectionPosition, a.SectionPositionOrder,\n" .
                "a.Sections, a.Language, a.Active, f.RecordID AS FormRecordID, f.RefID,\n" .
                "f.Alias AS FormAlias, f.Title AS FormTitle, f.FormType, f.LongDesc AS FormTemplate\n" .
                "FROM RED_Articles AS a\n" .
                "INNER JOIN RED_C_Form AS f ON CAST(f.RefID AS UNSIGNED)=a.RecordID\n" .
                "WHERE a.Active='Y' AND a.Language='sp' AND a.Sections='contacto' AND a.Component='Form'\n" .
                'ORDER BY a.SectionPositionOrder ASC, a.RecordID ASC LIMIT 2',
            'contact-navigation' =>
                "SELECT RecordID, RootOrder, Label, Link, NewWindow, MenuOrder, Active, Language\n" .
                "FROM RED_Menu\n" .
                "WHERE RootOrder='1' AND Parent=0 AND Language='sp' AND Active='Y'\n" .
                'ORDER BY MenuOrder ASC, RecordID ASC LIMIT 20',
            'contact-settings' =>
                "SELECT RecordID, Item, Content, Language\n" .
                "FROM RED_Advanced\n" .
                "WHERE Language='sp' AND Item IN ('Website_Footer', 'Website_Title')\n" .
                'ORDER BY Item ASC, RecordID ASC LIMIT 3',
        ];
    }
}

if (!function_exists('red_theme_contact_preview_assert_query_inventory')) {
    function red_theme_contact_preview_assert_query_inventory(array $queries)
    {
        $expectedIds = [
            'contact-section',
            'contact-form',
            'contact-navigation',
            'contact-settings',
        ];
        if (array_keys($queries) !== $expectedIds) {
            throw new RuntimeException('Contact preview query inventory must contain exactly four fixed reads.');
        }

        $allowedTables = [
            'RED_Sections' => true,
            'RED_Articles' => true,
            'RED_C_Form' => true,
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
                throw new RuntimeException('Contact preview query "' . $id . '" is not a single fixed SELECT.');
            }
            if (preg_match_all('/\b(?:FROM|JOIN)\s+([A-Za-z0-9_]+)/i', $sql, $matches) < 1) {
                throw new RuntimeException('Contact preview query "' . $id . '" has no declared source table.');
            }
            foreach ($matches[1] as $table) {
                if (!isset($allowedTables[$table])) {
                    throw new RuntimeException('Contact preview query "' . $id . '" uses an unexpected table.');
                }
            }
        }

        return true;
    }
}

if (!function_exists('red_theme_contact_preview_scope')) {
    function red_theme_contact_preview_scope($databaseReads)
    {
        return red_theme_preview_scope(
            [
                'databaseReads' => $databaseReads,
                'databaseWrites' => 0,
                'sessionReads' => 0,
                'sessionWrites' => 0,
                'liveRuntimeChanges' => 0,
            ]
        );
    }
}

if (!function_exists('red_theme_contact_preview_read_rows')) {
    function red_theme_contact_preview_read_rows($connection)
    {
        if (!is_object($connection)
            || !method_exists($connection, 'query')
            || !method_exists($connection, 'commit')
            || !method_exists($connection, 'rollback')
        ) {
            throw new InvalidArgumentException('Contact preview requires a valid mysqli connection.');
        }

        $queries = red_theme_contact_preview_query_inventory();
        red_theme_contact_preview_assert_query_inventory($queries);
        $started = false;
        try {
            if ($connection->query('START TRANSACTION READ ONLY') !== true) {
                throw new RuntimeException('Contact preview could not start a read-only transaction.');
            }
            $started = true;
            $rows = [];
            foreach ($queries as $id => $sql) {
                $result = $connection->query($sql);
                if (!is_object($result) || !method_exists($result, 'fetch_assoc')) {
                    throw new RuntimeException('Contact preview fixed read "' . $id . '" failed.');
                }
                $resultRows = [];
                while ($row = $result->fetch_assoc()) {
                    if (!is_array($row)) {
                        throw new RuntimeException('Contact preview received an invalid database row.');
                    }
                    $resultRows[] = $row;
                    if (count($resultRows) > 20) {
                        throw new RuntimeException('Contact preview query exceeded its fixed row boundary.');
                    }
                }
                if (method_exists($result, 'free')) {
                    $result->free();
                }
                $rows[$id] = $resultRows;
            }
            if (!$connection->commit()) {
                throw new RuntimeException('Contact preview could not close its read-only transaction.');
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
                'section' => $rows['contact-section'],
                'form' => $rows['contact-form'],
                'navigation' => $rows['contact-navigation'],
                'settings' => $rows['contact-settings'],
            ],
            'scope' => red_theme_contact_preview_scope(count($queries)),
        ];
    }
}

if (!function_exists('red_theme_contact_preview_require_row_keys')) {
    function red_theme_contact_preview_require_row_keys($row, array $expectedKeys, $context)
    {
        if (!is_array($row) || array_keys($row) !== $expectedKeys) {
            throw new InvalidArgumentException($context . ' must contain the exact selected columns in order.');
        }

        return $row;
    }
}

if (!function_exists('red_theme_contact_preview_source_string')) {
    function red_theme_contact_preview_source_string(
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

if (!function_exists('red_theme_contact_preview_integer')) {
    function red_theme_contact_preview_integer($value, $context, $minimum = 0, $maximum = 2147483647)
    {
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

if (!function_exists('red_theme_contact_preview_plain_text')) {
    function red_theme_contact_preview_plain_text(
        $value,
        $context,
        $allowEmpty = false,
        $maximumLength = 500
    ) {
        $source = red_theme_contact_preview_source_string(
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

if (!function_exists('red_theme_contact_preview_section')) {
    function red_theme_contact_preview_section($rows)
    {
        if (!is_array($rows) || !array_is_list($rows) || count($rows) !== 1) {
            throw new InvalidArgumentException('Contact preview requires exactly one Contact section row.');
        }
        $row = red_theme_contact_preview_require_row_keys(
            $rows[0],
            ['RecordID', 'Sections', 'Title', 'Layout', 'Description', 'Tags', 'Language', 'Active'],
            'Contact section row'
        );
        $canary = red_theme_contact_preview_canary();
        $recordId = red_theme_contact_preview_integer($row['RecordID'], 'Contact section RecordID', 1);
        $section = red_theme_contact_preview_source_string($row['Sections'], 'Contact section alias', false, 100);
        $layout = red_theme_contact_preview_source_string($row['Layout'], 'Contact section layout', false, 64);
        $language = red_theme_contact_preview_source_string($row['Language'], 'Contact section language', false, 12);
        $active = red_theme_contact_preview_source_string($row['Active'], 'Contact section active state', false, 1);
        if ($recordId !== $canary['recordId']
            || $section !== $canary['section']
            || $layout !== $canary['layout']
            || $language !== $canary['legacyLanguage']
            || $active !== 'Y'
        ) {
            throw new InvalidArgumentException('Contact section row does not match the fixed active canary.');
        }

        return [
            'recordId' => $recordId,
            'section' => $section,
            'title' => red_theme_contact_preview_plain_text($row['Title'], 'Contact section title', false, 120),
            'layout' => $layout,
            'description' => red_theme_contact_preview_plain_text(
                $row['Description'],
                'Contact section description',
                true,
                300
            ),
            'tags' => red_theme_contact_preview_plain_text($row['Tags'], 'Contact section tags', true, 300),
            'language' => $language,
            'active' => $active,
        ];
    }
}

if (!function_exists('red_theme_contact_preview_navigation')) {
    function red_theme_contact_preview_navigation($rows)
    {
        if (!is_array($rows) || !array_is_list($rows) || count($rows) !== 2) {
            throw new InvalidArgumentException(
                'Contact preview requires exactly the active Spanish Home and Contact root navigation rows.'
            );
        }
        $canary = red_theme_contact_preview_canary();
        $items = [];
        $recordIds = [];
        $links = [];
        $lastOrder = -1;
        foreach ($rows as $index => $sourceRow) {
            $row = red_theme_contact_preview_require_row_keys(
                $sourceRow,
                ['RecordID', 'RootOrder', 'Label', 'Link', 'NewWindow', 'MenuOrder', 'Active', 'Language'],
                'Contact navigation row ' . $index
            );
            $recordId = red_theme_contact_preview_integer(
                $row['RecordID'],
                'Contact navigation RecordID',
                1
            );
            if (isset($recordIds[$recordId])) {
                throw new InvalidArgumentException('Contact navigation RecordIDs must be unique.');
            }
            $rootOrder = red_theme_contact_preview_source_string(
                $row['RootOrder'],
                'Contact navigation RootOrder',
                false,
                2
            );
            $link = red_theme_preview_url(
                red_theme_contact_preview_source_string(
                    $row['Link'],
                    'Contact navigation link',
                    false,
                    500
                ),
                'Contact navigation link'
            );
            $newWindow = red_theme_contact_preview_source_string(
                $row['NewWindow'],
                'Contact navigation new-window state',
                true,
                6
            );
            $menuOrder = red_theme_contact_preview_integer(
                $row['MenuOrder'],
                'Contact navigation order',
                0,
                9999
            );
            $active = red_theme_contact_preview_source_string(
                $row['Active'],
                'Contact navigation active state',
                false,
                1
            );
            $language = red_theme_contact_preview_source_string(
                $row['Language'],
                'Contact navigation language',
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
                throw new InvalidArgumentException('Contact navigation row is outside the fixed root-menu contract.');
            }
            $recordIds[$recordId] = true;
            $links[$link] = true;
            $lastOrder = $menuOrder;
            $items[] = [
                'recordId' => $recordId,
                'label' => red_theme_contact_preview_plain_text(
                    $row['Label'],
                    'Contact navigation label',
                    false,
                    80
                ),
                'url' => $link,
                'current' => $link === $canary['route'],
                'menuOrder' => $menuOrder,
            ];
        }
        if (array_keys($links) !== ['/', $canary['route']]
            || $items[0]['label'] !== 'Inicio'
            || $items[1]['label'] !== 'Contacto'
            || $items[0]['current']
            || !$items[1]['current']
        ) {
            throw new InvalidArgumentException('Contact navigation rows do not match the fixed Home/Contact canary.');
        }

        return $items;
    }
}

if (!function_exists('red_theme_contact_preview_settings')) {
    function red_theme_contact_preview_settings($rows)
    {
        if (!is_array($rows) || !array_is_list($rows) || count($rows) > 2) {
            throw new InvalidArgumentException('Contact preview settings must contain at most two fixed rows.');
        }
        $allowedItems = ['Website_Footer' => true, 'Website_Title' => true];
        $settings = [];
        $recordIds = [];
        foreach ($rows as $index => $sourceRow) {
            $row = red_theme_contact_preview_require_row_keys(
                $sourceRow,
                ['RecordID', 'Item', 'Content', 'Language'],
                'Contact setting row ' . $index
            );
            $recordId = red_theme_contact_preview_integer($row['RecordID'], 'Contact setting RecordID', 1);
            $item = red_theme_contact_preview_source_string($row['Item'], 'Contact setting item', false, 50);
            $language = red_theme_contact_preview_source_string(
                $row['Language'],
                'Contact setting language',
                false,
                2
            );
            if (!isset($allowedItems[$item])
                || isset($settings[$item])
                || isset($recordIds[$recordId])
                || $language !== red_theme_contact_preview_canary()['legacyLanguage']
            ) {
                throw new InvalidArgumentException('Contact setting row is duplicated or outside the fixed allowlist.');
            }
            $recordIds[$recordId] = true;
            $settings[$item] = red_theme_contact_preview_plain_text(
                $row['Content'],
                'Contact setting content',
                true,
                180
            );
        }

        return $settings;
    }
}

if (!function_exists('red_theme_contact_preview_boolean')) {
    function red_theme_contact_preview_boolean($value, $context)
    {
        $value = red_theme_contact_preview_source_string($value, $context, false, 5);
        if ($value === 'true') {
            return true;
        }
        if ($value === 'false') {
            return false;
        }

        throw new InvalidArgumentException($context . ' must be exactly true or false.');
    }
}

if (!function_exists('red_theme_contact_preview_form_template')) {
    function red_theme_contact_preview_form_template($template)
    {
        $template = red_theme_contact_preview_source_string(
            $template,
            'Contact legacy Form definition',
            false,
            10000
        );
        $records = explode(';', str_replace(["\r\n", "\r"], "\n", $template));
        $parsedRecords = [];
        foreach ($records as $record) {
            $record = trim($record);
            if ($record !== '') {
                $parsedRecords[] = $record;
            }
        }
        if (count($parsedRecords) !== 7) {
            throw new InvalidArgumentException(
                'Contact legacy Form definition must contain six fields and one preview-only button.'
            );
        }

        $allowedKeys = [
            'question' => true,
            'name' => true,
            'type' => true,
            'required' => true,
            'displayname' => true,
            'initialvalue' => true,
            'readonly' => true,
            'cols' => true,
            'rows' => true,
        ];
        $fields = [];
        $fieldNames = [];
        $submitLabel = null;
        foreach ($parsedRecords as $recordIndex => $record) {
            $parts = explode('|', $record);
            if (array_shift($parts) !== '#' || $parts === []) {
                throw new InvalidArgumentException('Contact legacy Form record prefix is invalid.');
            }
            $definition = [];
            foreach ($parts as $part) {
                $separator = strpos($part, '=');
                if ($separator === false) {
                    throw new InvalidArgumentException('Contact legacy Form record contains a malformed property.');
                }
                $key = substr($part, 0, $separator);
                $value = substr($part, $separator + 1);
                if ($key === '' || !isset($allowedKeys[$key]) || array_key_exists($key, $definition)) {
                    throw new InvalidArgumentException(
                        'Contact legacy Form record contains an unknown or duplicated property.'
                    );
                }
                $definition[$key] = red_theme_contact_preview_source_string(
                    $value,
                    'Contact legacy Form property',
                    true,
                    500
                );
            }
            foreach (['question', 'name', 'type', 'displayname'] as $requiredKey) {
                if (!array_key_exists($requiredKey, $definition)) {
                    throw new InvalidArgumentException(
                        'Contact legacy Form record is missing a required property.'
                    );
                }
            }
            if ($definition['question'] !== '') {
                throw new InvalidArgumentException('Contact legacy Form questions must remain empty.');
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
                        'Contact legacy Form button must be the final minimal preview-only record.'
                    );
                }
                $submitLabel = red_theme_contact_preview_plain_text(
                    $definition['displayname'],
                    'Contact legacy Form button label',
                    false,
                    80
                );
                continue;
            }

            foreach (['required', 'initialvalue'] as $requiredKey) {
                if (!array_key_exists($requiredKey, $definition)) {
                    throw new InvalidArgumentException(
                        'Contact legacy Form field is missing a required safety property.'
                    );
                }
            }
            if (!in_array($type, ['textfield', 'textarea'], true)
                || preg_match('/\A[a-z][a-z0-9_-]*\z/', $name) !== 1
                || isset($fieldNames[$name])
                || $definition['initialvalue'] !== ''
                || (isset($definition['readonly']) && !in_array($definition['readonly'], ['', 'false'], true))
            ) {
                throw new InvalidArgumentException('Contact legacy Form field is unsupported or unsafe.');
            }
            foreach (['cols', 'rows'] as $dimension) {
                if (isset($definition[$dimension])) {
                    red_theme_contact_preview_integer(
                        $definition[$dimension],
                        'Contact legacy Form ' . $dimension,
                        1,
                        100
                    );
                }
            }
            if ($type === 'textfield'
                && (isset($definition['cols']) || isset($definition['rows']) || isset($definition['readonly']))
            ) {
                throw new InvalidArgumentException(
                    'Contact legacy text fields may not carry textarea-only properties.'
                );
            }

            $fieldNames[$name] = true;
            $portableType = 'text';
            if ($name === 'email') {
                $portableType = 'email';
            } elseif (in_array($name, ['telephone', 'fax'], true)) {
                $portableType = 'tel';
            } elseif ($type === 'textarea') {
                $portableType = 'textarea';
            }
            $autocomplete = [
                'name' => 'name',
                'title' => 'organization-title',
                'email' => 'email',
                'telephone' => 'tel',
                'fax' => '',
                'message' => '',
            ][$name] ?? null;
            if ($autocomplete === null) {
                throw new InvalidArgumentException('Contact legacy Form field name is outside the fixed canary.');
            }
            $fields[] = [
                'name' => $name,
                'label' => red_theme_contact_preview_plain_text(
                    $definition['displayname'],
                    'Contact legacy Form field label',
                    false,
                    100
                ),
                'type' => $portableType,
                'autocomplete' => $autocomplete,
                'required' => red_theme_contact_preview_boolean(
                    $definition['required'],
                    'Contact legacy Form required state'
                ),
            ];
        }
        if (array_keys($fieldNames) !== ['name', 'title', 'email', 'telephone', 'fax', 'message']
            || $submitLabel === null
        ) {
            throw new InvalidArgumentException('Contact legacy Form fields do not match the fixed canary.');
        }

        return [
            'fields' => $fields,
            'submitLabel' => $submitLabel,
        ];
    }
}

if (!function_exists('red_theme_contact_preview_form')) {
    function red_theme_contact_preview_form($rows)
    {
        if (!is_array($rows) || !array_is_list($rows) || count($rows) !== 1) {
            throw new InvalidArgumentException('Contact preview requires exactly one paired active Contact Form row.');
        }
        $row = red_theme_contact_preview_require_row_keys(
            $rows[0],
            [
                'ArticleRecordID',
                'ArticleAlias',
                'ArticleTitle',
                'Component',
                'SectionPosition',
                'SectionPositionOrder',
                'Sections',
                'Language',
                'Active',
                'FormRecordID',
                'RefID',
                'FormAlias',
                'FormTitle',
                'FormType',
                'FormTemplate',
            ],
            'Contact Form row'
        );
        $canary = red_theme_contact_preview_canary();
        $articleRecordId = red_theme_contact_preview_integer(
            $row['ArticleRecordID'],
            'Contact Article RecordID',
            1
        );
        $formRecordId = red_theme_contact_preview_integer(
            $row['FormRecordID'],
            'Contact Form RecordID',
            1
        );
        $refId = red_theme_contact_preview_integer($row['RefID'], 'Contact Form RefID', 1);
        $articleAlias = red_theme_contact_preview_source_string(
            $row['ArticleAlias'],
            'Contact Article alias',
            false,
            100
        );
        $component = red_theme_contact_preview_source_string(
            $row['Component'],
            'Contact Article component',
            false,
            50
        );
        $sectionPosition = red_theme_contact_preview_integer(
            $row['SectionPosition'],
            'Contact Article section position',
            0,
            4
        );
        $sectionPositionOrder = red_theme_contact_preview_integer(
            $row['SectionPositionOrder'],
            'Contact Article section order',
            0,
            9999
        );
        $section = red_theme_contact_preview_source_string(
            $row['Sections'],
            'Contact Article section',
            false,
            100
        );
        $language = red_theme_contact_preview_source_string(
            $row['Language'],
            'Contact Article language',
            false,
            2
        );
        $active = red_theme_contact_preview_source_string(
            $row['Active'],
            'Contact Article active state',
            false,
            1
        );
        $formAlias = red_theme_contact_preview_source_string(
            $row['FormAlias'],
            'Contact Form alias',
            false,
            100
        );
        $formType = red_theme_contact_preview_source_string(
            $row['FormType'],
            'Contact Form type',
            false,
            20
        );
        if ($articleRecordId !== $refId
            || $articleAlias !== 'contact'
            || $component !== 'Form'
            || $sectionPosition !== 1
            || $sectionPositionOrder !== 1
            || $section !== $canary['section']
            || $language !== $canary['legacyLanguage']
            || $active !== 'Y'
            || $formAlias !== 'contact'
            || $formType !== 'Contact'
        ) {
            throw new InvalidArgumentException('Contact Form row does not match the fixed paired canary.');
        }
        $template = red_theme_contact_preview_form_template($row['FormTemplate']);

        return [
            'articleRecordId' => $articleRecordId,
            'articleAlias' => $articleAlias,
            'articleTitle' => red_theme_contact_preview_plain_text(
                $row['ArticleTitle'],
                'Contact Article title',
                false,
                180
            ),
            'component' => $component,
            'sectionPosition' => $sectionPosition,
            'sectionPositionOrder' => $sectionPositionOrder,
            'formRecordId' => $formRecordId,
            'formTitle' => red_theme_contact_preview_plain_text(
                $row['FormTitle'],
                'Contact Form title',
                false,
                180
            ),
            'formType' => $formType,
            'fields' => $template['fields'],
            'submitLabel' => $template['submitLabel'],
        ];
    }
}

if (!function_exists('red_theme_contact_preview_prepare_rows')) {
    function red_theme_contact_preview_prepare_rows(array $rows)
    {
        red_theme_preview_require_exact_keys(
            $rows,
            ['section', 'form', 'navigation', 'settings'],
            [],
            'Contact preview source rows'
        );
        $section = red_theme_contact_preview_section($rows['section']);
        $form = red_theme_contact_preview_form($rows['form']);
        $navigation = red_theme_contact_preview_navigation($rows['navigation']);
        $settings = red_theme_contact_preview_settings($rows['settings']);
        $canary = red_theme_contact_preview_canary();

        $siteTitleSource = !empty($settings['Website_Title'])
            ? 'advanced.Website_Title'
            : 'section.Title';
        $footerSource = !empty($settings['Website_Footer'])
            ? 'advanced.Website_Footer'
            : 'section.Title';
        $descriptionSource = $section['description'] !== ''
            ? 'section.Description'
            : 'form.Title';
        $siteTitle = $siteTitleSource === 'advanced.Website_Title'
            ? $settings['Website_Title']
            : $section['title'];
        $footer = $footerSource === 'advanced.Website_Footer'
            ? $settings['Website_Footer']
            : $section['title'];
        $description = $descriptionSource === 'section.Description'
            ? $section['description']
            : $form['formTitle'];

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
                'title' => $form['articleTitle'] . ' — ' . $section['title'],
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
                        'label' => 'View ' . $form['formTitle'] . ' form',
                        'url' => '#preview-form',
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
                        'label' => $form['articleTitle'],
                        'url' => '',
                    ],
                ],
                'slots' => [
                    '1' => [
                        [
                            'component' => 'Form',
                            'data' => [
                                'title' => $form['formTitle'],
                                'fields' => $form['fields'],
                                'submitLabel' => $form['submitLabel'],
                            ],
                        ],
                    ],
                    '2' => [],
                    '3' => [],
                    '4' => [],
                ],
            ],
        ];
        $source = [
            'mode' => 'read-only-contact-preview',
            'canary' => [
                'sectionRecordId' => $section['recordId'],
                'section' => $section['section'],
                'legacyLanguage' => $section['language'],
                'route' => $canary['route'],
                'layout' => $section['layout'],
            ],
            'article' => [
                'recordId' => $form['articleRecordId'],
                'alias' => $form['articleAlias'],
                'component' => $form['component'],
                'sectionPosition' => $form['sectionPosition'],
                'sectionPositionOrder' => $form['sectionPositionOrder'],
            ],
            'form' => [
                'recordId' => $form['formRecordId'],
                'type' => $form['formType'],
                'fieldCount' => count($form['fields']),
            ],
            'queryIds' => array_keys(red_theme_contact_preview_query_inventory()),
            'rowCounts' => [
                'section' => count($rows['section']),
                'form' => count($rows['form']),
                'navigation' => count($rows['navigation']),
                'settings' => count($rows['settings']),
            ],
            'fallbacks' => [
                'siteTitle' => $siteTitleSource,
                'description' => $descriptionSource,
                'footer' => $footerSource,
            ],
        ];
        red_theme_preview_assert_non_executable($fixture, 'Contact prepared preview input');
        red_theme_preview_assert_non_executable($source, 'Contact preview source metadata');

        return [
            'fixture' => $fixture,
            'source' => $source,
        ];
    }
}

if (!function_exists('red_theme_contact_preview_render_rows')) {
    function red_theme_contact_preview_render_rows(
        array $rows,
        $projectRoot = null,
        $databaseReads = 0
    ) {
        if (!in_array($databaseReads, [0, 4], true)) {
            throw new InvalidArgumentException('Contact preview database-read count must be zero or four.');
        }
        $validation = red_theme_preview_validate_reference_theme('starter-reference', $projectRoot);
        $prepared = red_theme_contact_preview_prepare_rows($rows);
        $contract = red_theme_preview_contract($prepared['fixture'], $validation);
        $result = red_theme_preview_render_prepared_contract(
            $validation,
            $contract,
            'read-only-contact-preview',
            red_theme_contact_preview_scope($databaseReads)
        );
        $result['source'] = $prepared['source'];

        return $result;
    }
}

if (!function_exists('red_theme_contact_preview_render')) {
    function red_theme_contact_preview_render($connection, $projectRoot = null)
    {
        $read = red_theme_contact_preview_read_rows($connection);
        $result = red_theme_contact_preview_render_rows(
            $read['rows'],
            $projectRoot,
            $read['scope']['databaseReads']
        );
        if ($result['scope'] !== $read['scope']) {
            throw new RuntimeException('Contact preview side-effect scope changed during rendering.');
        }

        return $result;
    }
}
