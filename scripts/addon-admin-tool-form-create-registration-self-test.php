<?php
/**
 * Dependency-free checks for administrator-form creation registrations.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/includes/addon_runtime_helpers.php';

$assertions = 0;
$packageId = 'redcms.create-fixture';
$toolId = 'redcms.create-fixture/products';
$formId = 'redcms.create-fixture/product-editor';

function red_addon_create_registration_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_create_registration_manifest($includeCreate = true)
{
    global $toolId, $formId;
    $contract = [
        'tool' => $toolId,
        'form' => $formId,
        'label' => 'Edit product',
        'description' => 'Edit one bounded product.',
        'permission' => 'fixture.products.manage',
        'method' => 'POST',
        'csrf' => 'required',
        'encoding' => 'application/json',
        'maxBodyBytes' => 32768,
        'fields' => [[
            'key' => 'title',
            'label' => 'Title',
            'type' => 'text',
            'required' => true,
            'maxLength' => 200,
        ]],
    ];
    if ($includeCreate) {
        $contract['create'] = [
            'label' => 'Add product',
            'description' => 'Create one bounded product.',
        ];
    }
    return [
        'provides' => [
            'components' => [],
            'services' => [],
            'adminTools' => [$toolId],
            'adapters' => [],
        ],
        'adminToolActionContracts' => [],
        'adminToolFormContracts' => [$contract],
        'publicMutationContracts' => [],
        'componentEditors' => [],
        'routes' => [],
    ];
}

function red_addon_create_registration_error(callable $operation)
{
    try {
        $operation();
    } catch (Throwable $throwable) {
        return $throwable->getMessage();
    }
    return '';
}

try {
    $registry = new RED_Addon_Runtime_Registry(
        $packageId,
        red_addon_create_registration_manifest()
    );
    $registry->registerAdminTool($toolId, static function () {
        return null;
    });

    red_addon_create_registration_assert(
        str_contains(
            red_addon_create_registration_error(
                static function () use ($registry) {
                    $registry->assertComplete();
                }
            ),
            'adminToolFormValueLoaders: ' . $formId
        ),
        'a schema-bearing form still requires its current-value loader'
    );

    $registry->registerAdminToolFormValueLoader(
        $formId,
        static function () {
            return null;
        }
    );
    red_addon_create_registration_assert(
        str_contains(
            red_addon_create_registration_error(
                static function () use ($registry) {
                    $registry->assertComplete();
                }
            ),
            'adminToolFormInitialValueLoaders: ' . $formId
        ),
        'a declared create workflow requires one initial-value loader'
    );

    $initialLoader = static function () {
        return null;
    };
    $registry->registerAdminToolFormInitialValueLoader(
        $formId,
        $initialLoader
    );
    red_addon_create_registration_assert(
        str_contains(
            red_addon_create_registration_error(
                static function () use ($registry) {
                    $registry->assertComplete();
                }
            ),
            'adminToolFormCreators: ' . $formId
        ),
        'a declared create workflow requires one atomic creator'
    );

    $creator = static function () {
        return null;
    };
    red_addon_create_registration_assert(
        str_contains(
            red_addon_create_registration_error(
                static function () use ($registry, $formId, $creator) {
                    $registry->registerAdminToolFormCreator(
                        $formId,
                        $creator,
                        []
                    );
                }
            ),
            'requires one to eight package tables'
        ) && str_contains(
            red_addon_create_registration_error(
                static function () use ($registry, $formId, $creator) {
                    $registry->registerAdminToolFormCreator(
                        $formId,
                        $creator,
                        ['RED_Addon_Installations']
                    );
                }
            ),
            'transaction table is invalid'
        ),
        'creator registration rejects empty and core-owned table scopes'
    );

    $registry->registerAdminToolFormCreator(
        $formId,
        $creator,
        ['RED_Addon_Create_Items', 'RED_Addon_Create_Options']
    );
    $registry->assertComplete();
    $context = new RED_Addon_Runtime_Context(
        [$packageId],
        [$packageId => $registry]
    );
    red_addon_create_registration_assert(
        $registry->handler(
            'adminToolFormInitialValueLoaders',
            $formId
        ) === $initialLoader
            && $registry->handler('adminToolFormCreators', $formId) === $creator
            && $registry->metadata('adminToolFormCreators', $formId) === [
                'tables' => [
                    'RED_Addon_Create_Items',
                    'RED_Addon_Create_Options',
                ],
            ]
            && $context->owner(
                'adminToolFormInitialValueLoaders',
                $formId
            ) === $packageId
            && $context->owner('adminToolFormCreators', $formId) === $packageId,
        'complete creation registrations retain exact handlers, table scope, and package ownership'
    );

    red_addon_create_registration_assert(
        str_contains(
            red_addon_create_registration_error(
                static function () use ($registry, $formId, $initialLoader) {
                    $registry->registerAdminToolFormInitialValueLoader(
                        $formId,
                        $initialLoader
                    );
                }
            ),
            'registration is duplicated'
        ) && str_contains(
            red_addon_create_registration_error(
                static function () use ($registry, $formId, $creator) {
                    $registry->registerAdminToolFormCreator(
                        $formId,
                        $creator,
                        ['RED_Addon_Create_Items']
                    );
                }
            ),
            'registration is duplicated'
        ),
        'each declared creation registration is single-owner and single-binding'
    );

    $undeclared = new RED_Addon_Runtime_Registry(
        $packageId,
        red_addon_create_registration_manifest(false)
    );
    red_addon_create_registration_assert(
        str_contains(
            red_addon_create_registration_error(
                static function () use ($undeclared, $formId, $initialLoader) {
                    $undeclared->registerAdminToolFormInitialValueLoader(
                        $formId,
                        $initialLoader
                    );
                }
            ),
            'registration is undeclared'
        ) && str_contains(
            red_addon_create_registration_error(
                static function () use ($undeclared, $formId, $creator) {
                    $undeclared->registerAdminToolFormCreator(
                        $formId,
                        $creator,
                        ['RED_Addon_Create_Items']
                    );
                }
            ),
            'registration is undeclared'
        ),
        'forms without a create declaration cannot register creation handlers'
    );

    printf(
        "Add-on administrator form creation registration self-test passed (%d assertions).\n",
        $assertions
    );
} catch (Throwable $throwable) {
    fwrite(
        STDERR,
        $throwable->getMessage() . ' (after ' . $assertions . " assertions)\n"
    );
    exit(1);
}
