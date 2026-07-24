<?php
/**
 * Red Sphere administrator FTP upload workspace.
 */
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/bootstrap.php';
red_start_session();
red_require_admin();
require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_article_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_authorization_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_ftp_ui_helpers.php';

if (empty($_SESSION['alias'])) {
    header('Location: http://'.BASE_URL.'');
    exit;
}

// Preserve the standard Add Content request contract even though FTP storage is global.
$Type = red_admin_post_text('Type');
$CountPage = red_admin_post_text('CountPage');
$Section = red_admin_post_text('Section');
$Category = red_admin_post_text('Category');
$SubCategory = red_admin_post_text('SubCategory');
$Article = red_admin_post_text('Article');
$Layout = red_admin_post_text('Layout');
$VarPosition = red_admin_article_position_column($_POST['VarPosition'] ?? '', 'PagePosition');
if ($VarPosition === null) {
    echo 'no';
    exit;
}

$Language = substr(red_admin_post_text('Language'), 0, 2);
$csrfToken = red_csrf_token();

$authorizationDb = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
red_admin_require_component_selection($authorizationDb->connection, 'FTP');
$authorizationDb->close();

$uploadUrl = '/admin/bin/post_ftp.php?'.http_build_query(
    [
        'UC' => 'FTP',
        'Language' => $Language,
    ],
    '',
    '&',
    PHP_QUERY_RFC3986
);
$ftpFiles = red_admin_ftp_file_library($_SERVER['DOCUMENT_ROOT']);
$ftpFileCount = count($ftpFiles);
$ftpAllowedExtensions = red_admin_ftp_allowed_extensions();
$ftpAccept = '.' . implode(',.', $ftpAllowedExtensions);
$ftpMaxBytes = red_upload_ftp_max_bytes();
$ftpMaxMegabytes = (int) ($ftpMaxBytes / 1024 / 1024);
$ftpScript = '/admin/assets/js/ftp-form.js';
$ftpScriptVersion = filemtime($_SERVER['DOCUMENT_ROOT'].$ftpScript);
?>

<div class="cp_viewall red-admin-article-return">
    <button type="button" class="red-admin-article-return__button" onclick="showdiv('add_content_grid'); return false;">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6"></path></svg>
        <span>All content types</span>
    </button>
    <span class="red-admin-article-return__divider" aria-hidden="true">/</span>
    <span aria-current="page">FTP</span>
</div>

<form
    id="ftp-upload-workspace"
    class="cp red-admin-article-form red-admin-ftp-form"
    method="post"
    enctype="multipart/form-data"
    action="<?php echo red_admin_area_html($uploadUrl); ?>"
    data-red-ftp-form
    data-upload-url="<?php echo red_admin_area_html($uploadUrl); ?>"
    data-allowed-extensions="<?php echo red_admin_area_html(json_encode($ftpAllowedExtensions)); ?>"
    data-max-file-bytes="<?php echo (int) $ftpMaxBytes; ?>"
    onsubmit="return false;"
>
    <fieldset>
        <legend class="red-admin-visually-hidden">Upload a public file</legend>

        <div class="red-admin-article-shell">
            <header class="red-admin-article-header red-admin-ftp-header">
                <span class="red-admin-article-header__icon red-admin-ftp-header__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M4 7.5h6l2 2h8v9.5H4z"></path>
                        <path d="M12 16V9.5M9.5 12l2.5-2.5 2.5 2.5"></path>
                    </svg>
                </span>
                <div class="red-admin-article-header__copy">
                    <span class="red-admin-article-header__eyebrow">Add Content</span>
                    <h2>Upload a file</h2>
                    <p>Upload once, then copy a ready-to-share public link.</p>
                </div>
                <span class="red-admin-article-header__badge red-admin-ftp-header__badge">FTP</span>
            </header>

            <section class="red-admin-article-panel red-admin-ftp-upload-panel" aria-labelledby="ftp-upload-title">
                <div class="red-admin-article-panel__heading">
                    <div>
                        <span class="red-admin-article-panel__step">01</span>
                        <h3 id="ftp-upload-title">Upload &amp; copy link</h3>
                    </div>
                    <p>One file · maximum <?php echo (int) $ftpMaxMegabytes; ?> MB</p>
                </div>

                <div class="red-admin-ftp-notice">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.5a8.5 8.5 0 1 0 0 17 8.5 8.5 0 0 0 0-17zM12 10v6M12 7.5h.01"></path></svg>
                    <div>
                        <strong>Files receive a public web address.</strong>
                        <span>Use this area for files visitors may open or download. The private server location is never exposed.</span>
                    </div>
                </div>

                <article class="red-admin-upload-card red-admin-ftp-upload" data-ftp-uploader aria-busy="false">
                    <div id="dropbox" class="red-admin-upload-dropzone red-admin-ftp-dropzone" data-ftp-dropzone>
                        <input
                            class="red-admin-ftp-file-input"
                            type="file"
                            name="pic"
                            id="ftp-file-input"
                            accept="<?php echo red_admin_area_html($ftpAccept); ?>"
                            tabindex="-1"
                            aria-hidden="true"
                            hidden
                            data-ftp-input
                        />
                        <svg class="red-admin-upload-dropzone__icon" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M4 16.5V19h16v-2.5M12 4v10M8 8l4-4 4 4"></path>
                        </svg>
                        <strong>Drop a file here</strong>
                        <span>or choose one from your computer</span>
                        <button type="button" class="red-admin-upload-browse red-admin-ftp-browse" data-ftp-browse>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.5 6.5h6l2 2h9v10h-17z"></path><path d="M14.5 13.5h3M16 12v3"></path></svg>
                            Browse computer
                        </button>
                        <small>Images, PDF, Office, text or ZIP · maximum <?php echo (int) $ftpMaxMegabytes; ?> MB</small>
                    </div>

                    <div class="red-admin-ftp-selection" data-ftp-selection hidden>
                        <span class="red-admin-ftp-selection__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M6 3.75h9.25L19 7.5v12.75H6z"></path><path d="M15 3.75V7.5h4"></path></svg>
                        </span>
                        <div>
                            <strong data-ftp-selection-name></strong>
                            <span data-ftp-selection-meta></span>
                        </div>
                        <span class="red-admin-ftp-selection__status" data-ftp-upload-status>Ready</span>
                    </div>

                    <div class="red-admin-upload-progress red-admin-ftp-progress" aria-hidden="true">
                        <span data-ftp-progress></span>
                    </div>
                </article>

                <div class="red-admin-ftp-result" data-ftp-result hidden>
                    <span class="red-admin-ftp-result__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M5 12.5l4 4L19 6.5"></path></svg>
                    </span>
                    <div class="red-admin-ftp-result__copy">
                        <span>Upload complete</span>
                        <strong data-ftp-result-name></strong>
                        <a href="#" target="_blank" rel="noopener" data-ftp-result-url></a>
                    </div>
                    <div class="red-admin-ftp-result__actions">
                        <a class="red-admin-ftp-action red-admin-ftp-action--secondary" href="#" target="_blank" rel="noopener" data-ftp-result-open>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 5h5v5M11 13l8-8M19 13v6H5V5h6"></path></svg>
                            Open file
                        </a>
                        <button type="button" class="red-admin-ftp-action red-admin-ftp-action--copy" data-ftp-copy-path="" data-copy-default-label="Copy link">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="11" height="11" rx="2"></rect><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"></path></svg>
                            <span data-copy-label>Copy link</span>
                        </button>
                    </div>
                </div>

                <p class="red-admin-ftp-live" data-ftp-live role="status" aria-live="polite"></p>
            </section>

            <details class="red-admin-article-advanced red-admin-ftp-library" data-ftp-library>
                <summary>
                    <span class="red-admin-article-advanced__icon red-admin-ftp-library__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M4 7.5h6l2 2h8v9.5H4z"></path><path d="M4 7.5V5h6l2 2h8v2.5"></path></svg>
                    </span>
                    <span class="red-admin-article-advanced__copy">
                        <strong>Files in this folder</strong>
                        <small>Browse and copy links from the shared /images/articles directory</small>
                    </span>
                    <span class="red-admin-article-advanced__badge" data-ftp-library-count><?php echo (int) $ftpFileCount; ?> <?php echo $ftpFileCount === 1 ? 'file' : 'files'; ?></span>
                    <svg class="red-admin-article-advanced__chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 10l5 5 5-5"></path></svg>
                </summary>

                <div class="red-admin-article-advanced__body red-admin-ftp-library__body">
                    <div class="red-admin-ftp-library__toolbar">
                        <div>
                            <strong>Shared upload directory</strong>
                            <span>Article images may also appear here because RED-CMS uses the same folder.</span>
                        </div>
                        <label class="red-admin-ftp-search" for="ftp-library-search">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6"></circle><path d="M15 15l4.5 4.5"></path></svg>
                            <span class="red-admin-visually-hidden">Search files by name or type</span>
                            <input type="search" id="ftp-library-search" placeholder="Search files" autocomplete="off" data-ftp-search />
                        </label>
                    </div>

                    <ul class="red-admin-ftp-file-list" data-ftp-file-list>
                        <?php foreach ($ftpFiles as $ftpFile) { ?>
                            <li
                                class="red-admin-ftp-file"
                                data-ftp-file
                                data-search-value="<?php echo red_admin_area_html(strtolower($ftpFile['name'].' '.$ftpFile['extension'].' '.$ftpFile['typeLabel'])); ?>"
                            >
                                <span class="red-admin-ftp-file__icon red-admin-ftp-file__icon--<?php echo red_admin_area_html($ftpFile['kind']); ?>" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><path d="M6 3.75h9.25L19 7.5v12.75H6z"></path><path d="M15 3.75V7.5h4"></path></svg>
                                    <small><?php echo red_admin_area_html(strtoupper($ftpFile['extension'])); ?></small>
                                </span>
                                <div class="red-admin-ftp-file__details">
                                    <strong title="<?php echo red_admin_area_html($ftpFile['name']); ?>"><?php echo red_admin_area_html($ftpFile['name']); ?></strong>
                                    <span><?php echo red_admin_area_html($ftpFile['typeLabel']); ?> · <?php echo red_admin_area_html($ftpFile['sizeLabel']); ?> · <?php echo red_admin_area_html($ftpFile['modifiedLabel']); ?></span>
                                </div>
                                <div class="red-admin-ftp-file__actions">
                                    <a class="red-admin-ftp-file__open" href="<?php echo red_admin_area_html($ftpFile['publicPath']); ?>" target="_blank" rel="noopener" aria-label="Open <?php echo red_admin_area_html($ftpFile['name']); ?> in a new tab">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 5h5v5M11 13l8-8M19 13v6H5V5h6"></path></svg>
                                        Open
                                    </a>
                                    <button
                                        type="button"
                                        class="red-admin-ftp-file__copy"
                                        data-ftp-copy-path="<?php echo red_admin_area_html($ftpFile['publicPath']); ?>"
                                        data-copy-default-label="Copy link"
                                        aria-label="Copy public link for <?php echo red_admin_area_html($ftpFile['name']); ?>"
                                    >
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="11" height="11" rx="2"></rect><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"></path></svg>
                                        <span data-copy-label>Copy link</span>
                                    </button>
                                </div>
                            </li>
                        <?php } ?>
                    </ul>

                    <div class="red-admin-ftp-empty" data-ftp-empty<?php echo $ftpFileCount > 0 ? ' hidden' : ''; ?>>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7.5h6l2 2h8v9.5H4z"></path></svg>
                        <strong>No files in this folder yet</strong>
                        <span>Your first successful upload will appear here automatically.</span>
                    </div>
                    <p class="red-admin-ftp-no-results" data-ftp-no-results hidden>No files match that search.</p>
                </div>
            </details>

            <input type="hidden" name="csrf_token" value="<?php echo red_admin_area_html($csrfToken); ?>" />
        </div>
    </fieldset>
</form>

<script src="<?php echo red_admin_area_html($ftpScript); ?>?v=<?php echo rawurlencode((string) $ftpScriptVersion); ?>"></script>
