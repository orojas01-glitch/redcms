<?php
/** Fixture-preview Form view. Data is prepared and validated by RED-CMS core. */
$form = $redThemeFormContext;
?>
<section id="preview-form" class="redcms-component redcms-component--form" aria-labelledby="preview-form-title" data-reveal>
    <p class="section-kicker">Conversemos</p>
    <h2 id="preview-form-title"><?= htmlspecialchars($form['title'], ENT_QUOTES, 'UTF-8') ?></h2>
    <form class="contact-form" aria-describedby="preview-form-note">
        <?php foreach ($form['fields'] as $field) : ?>
            <label for="preview-field-<?= htmlspecialchars($field['name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($field['label'], ENT_QUOTES, 'UTF-8') ?><?php if ($field['required']) : ?><span aria-hidden="true"> *</span><?php endif; ?></label>
            <?php if ($field['type'] === 'textarea') : ?>
                <textarea id="preview-field-<?= htmlspecialchars($field['name'], ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars($field['name'], ENT_QUOTES, 'UTF-8') ?>" rows="4" <?php if ($field['autocomplete'] !== '') : ?>autocomplete="<?= htmlspecialchars($field['autocomplete'], ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?> <?php if ($field['required']) : ?>required<?php endif; ?>></textarea>
            <?php else : ?>
                <input id="preview-field-<?= htmlspecialchars($field['name'], ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars($field['name'], ENT_QUOTES, 'UTF-8') ?>" type="<?= htmlspecialchars($field['type'], ENT_QUOTES, 'UTF-8') ?>" <?php if ($field['autocomplete'] !== '') : ?>autocomplete="<?= htmlspecialchars($field['autocomplete'], ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?> <?php if ($field['required']) : ?>required<?php endif; ?>>
            <?php endif; ?>
        <?php endforeach; ?>
        <button class="button button--primary" type="button" aria-disabled="true"><?= htmlspecialchars($form['submitLabel'], ENT_QUOTES, 'UTF-8') ?></button>
        <p id="preview-form-note" class="template-note">Vista local solamente. Ningún destino de envío está conectado.</p>
    </form>
</section>
