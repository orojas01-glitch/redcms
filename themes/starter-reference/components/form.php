<?php
/** Fixture-preview Form view. Data is prepared and validated by RED-CMS core. */
$form = $redThemeFormContext;
?>
<section id="preview-form" class="starter-component starter-component--form" aria-labelledby="preview-form-title">
    <p class="starter-component__label">Form component</p>
    <h2 id="preview-form-title"><?= htmlspecialchars($form['title'], ENT_QUOTES, 'UTF-8') ?></h2>
    <form aria-describedby="preview-form-note">
        <?php foreach ($form['fields'] as $field) : ?>
            <label for="preview-field-<?= htmlspecialchars($field['name'], ENT_QUOTES, 'UTF-8') ?>">
                <span>
                    <?= htmlspecialchars($field['label'], ENT_QUOTES, 'UTF-8') ?>
                    <?php if ($field['required']) : ?><span aria-hidden="true"> *</span><?php endif; ?>
                </span>
            </label>
            <?php if ($field['type'] === 'textarea') : ?>
                <textarea
                    id="preview-field-<?= htmlspecialchars($field['name'], ENT_QUOTES, 'UTF-8') ?>"
                    name="<?= htmlspecialchars($field['name'], ENT_QUOTES, 'UTF-8') ?>"
                    rows="4"
                    <?php if ($field['autocomplete'] !== '') : ?>autocomplete="<?= htmlspecialchars($field['autocomplete'], ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>
                    <?php if ($field['required']) : ?>required<?php endif; ?>
                ></textarea>
            <?php else : ?>
                <input
                    id="preview-field-<?= htmlspecialchars($field['name'], ENT_QUOTES, 'UTF-8') ?>"
                    name="<?= htmlspecialchars($field['name'], ENT_QUOTES, 'UTF-8') ?>"
                    type="<?= htmlspecialchars($field['type'], ENT_QUOTES, 'UTF-8') ?>"
                    <?php if ($field['autocomplete'] !== '') : ?>autocomplete="<?= htmlspecialchars($field['autocomplete'], ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>
                    <?php if ($field['required']) : ?>required<?php endif; ?>
                >
            <?php endif; ?>
        <?php endforeach; ?>
        <button type="button" aria-disabled="true"><?= htmlspecialchars($form['submitLabel'], ENT_QUOTES, 'UTF-8') ?></button>
        <p id="preview-form-note" class="starter-form-note">Fixture display only. No submission target is connected.</p>
    </form>
</section>
