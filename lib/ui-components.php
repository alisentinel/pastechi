<?php
declare(strict_types=1);

function render_create_message_block_template(): void
{
    ?>
    <template id="messageBlockTemplate">
        <div class="message-block border rounded p-2">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <small class="text-secondary message-block-label">Paste 1</small>
                <button type="button" class="btn btn-sm btn-outline-secondary remove-message-btn">Remove</button>
            </div>
            <textarea class="form-control message-input" rows="5" placeholder="<?= htmlspecialchars(t('create.content_placeholder'), ENT_QUOTES, 'UTF-8') ?>" required></textarea>
        </div>
    </template>
    <?php
}

function render_discussion_input_form(): void
{
    ?>
    <form id="discussionForm">
        <textarea id="discussionInput" class="form-control mb-2" rows="3" placeholder="<?= htmlspecialchars(t('paste.message_placeholder'), ENT_QUOTES, 'UTF-8') ?>" maxlength="2000"></textarea>
        <div class="d-flex align-items-center justify-content-between gap-2">
            <small class="text-secondary"><?= htmlspecialchars(t('paste.send_hint_multiline'), ENT_QUOTES, 'UTF-8') ?></small>
            <button type="submit" class="btn btn-sm btn-outline-light"><?= htmlspecialchars(t('paste.send'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </form>
    <?php
}