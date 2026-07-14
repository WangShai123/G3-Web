<?php
use JEALER\G3\Utilities\Element;
use JEALER\G3\Utilities\Message;

echo Element::tip(
    __('Quickly create a G3 Web theme project with complete architecture.', 'G3'),
    '',
    'default',
    'mt-4'
);
?>
<section class="mt-4">
    <form class="j-form is-vertical is-item-vertical">
        <!-- Theme Name -->
        <div class="form-item">
            <label for="name" class="item-label is-required">
                <?php echo __('Theme Name', 'G3'); ?>
            </label>
            <div class="form-control">
                <input type="text" class="j-input" id="name" placeholder="Enter theme name" autocomplete="name"
                    required />
            </div>
        </div>

        <!-- Folder -->
        <div class="form-item">
            <label for="folder" class="item-label is-required">
                <?php echo __('Folder Name', 'G3'); ?>
            </label>
            <div class="form-control">
                <input type="text" class="j-input" id="folder" placeholder="Enter folder name" autocomplete="off"
                    required />
            </div>
        </div>

        <!-- URL -->
        <div class="form-item">
            <label for="url" class="item-label is-required">
                <?php echo __('Theme URL', 'G3'); ?>
            </label>
            <div class="form-control">
                <input type="url" class="j-input" id="url" placeholder="eg: https://g3system.com" autocomplete="url" />
            </div>
        </div>

        <!-- Description -->
        <div class="form-item">
            <label for="description" class="item-label is-required">
                <?php echo __('Theme Description', 'G3'); ?>
            </label>
            <div class="form-control">
                <textarea class="j-input" id="description" placeholder="Enter description" autocomplete="off"
                    rows="3"></textarea>
            </div>
        </div>

        <!-- Author -->
        <div class="form-item">
            <label for="author" class="item-label is-required">
                <?php echo __('Author'); ?>
            </label>
            <div class="form-control">
                <input type="text" class="j-input" id="author" placeholder="Enter author name" autocomplete="name" />
            </div>
        </div>

        <!-- Author URL -->
        <div class="form-item">
            <label for="authorUrl" class="item-label is-required">
                <?php echo __('Author URL', 'G3'); ?>
            </label>
            <div class="form-control">
                <input type="url" class="j-input" id="authorUrl" placeholder="eg: https://g3system.com"
                    autocomplete="url" />
            </div>
        </div>

        <!-- Version -->
        <div class="form-item">
            <label for="version" class="item-label is-required">
                <?php echo __('Version'); ?>
            </label>
            <div class="form-control">
                <input type="text" class="j-input" id="version" placeholder="1.0.0" autocomplete="off" required />
            </div>
        </div>

        <div class="form-buttons">
            <button type="button" class="j-button is-primary" id="createTheme"><?php echo __('Submit'); ?></button>
            <button type="reset" class="j-button is-ghost"><?php echo __('Cancel'); ?></button>
        </div>
    </form>
</section>

<style>
    section {
        background-color: var(--background);
        border: 1px solid var(--gray-a6);
        padding: calc(var(--space)*3);
    }

    form.j-form {
        max-width: 320px;
    }

    form.j-form .form-item textarea {
        resize: none;
    }
</style>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const { Toast, postJson, q, restUrl } = jui
        const { success, error } = Toast
        const newTheme = q('#createTheme');
        newTheme.addEventListener('click', (e) => {
            e.preventDefault()
            const name = q('#name').value
            const folder = q('#folder').value
            const url = q('#url').value
            const description = q('#description').value
            const author = q('#author').value
            const authorUrl = q('#authorUrl').value
            const version = q('#version').value
            if (!name || !folder || !url || !description || !author || !authorUrl || !version) {
                error('<?php _e('No data supplied.'); ?>')
                return
            }
            postJson(restUrl + '/api/v1/theme/generate', {
                name,
                folder,
                url,
                description,
                author,
                authorUrl,
                version
            }).then(data => {
                newTheme.disabled = true
                switch (data.code) {
                    case 200:
                        success(data.message, 2000);
                        const tip = `
                            <div class="j-tip is-success mb-2" id="createTip">
                                <div class="tip-content">
                                    <div><b><?php echo Message::created(); ?></b></div>
                                    <div><?php echo __('Theme Name'); ?>: ${data.data.name}</div>
                                    <div><?php echo __('Theme Directory'); ?>: ${data.data.path}</div>
                                </div>
                            </div>
                            `;
                        q('form.j-form').insertAdjacentHTML('beforebegin', tip);
                        setTimeout(() => {
                            if (q('#createTip')) q('#createTip').remove();
                            q('form.j-form').reset()
                        }, 5000);
                        break;
                    case 400:
                        error(data.message, 2000)
                        break;
                    case 422:
                        error(data.data.errors[0], 2000)
                        break;
                    default:
                        error(data.message, 2000)
                        break;
                }
            }).then(() => { setTimeout(() => { newTheme.disabled = false }, 2000) });
        });
        q('form').addEventListener('reset', (e) => {
            newTheme.disabled = false;
            if (q('#createTip')) {
                for (const tip of document.querySelectorAll('#createTip')) {
                    tip.remove();
                }
            }
        })
    });
</script>
