<?php
use JEALER\G3\Utilities\Frontend;
Frontend::loadStyle('jui');
?>
<div class="j-tip is-default mt-4">
    <div class="tip-title"><?php echo __('Tip', 'G3'); ?></div>
    <div class="tip-content">
        <?php
        // 快速创建一个拥有完整架构的G3 Web 主题项目
        echo '<div>' . __('Quickly create a G3 Web theme project with complete architecture.', 'G3') . '</div>';
        ?>
    </div>
</div>

<section class="mt-4">
    <form class="j-form is-vertical is-item-vertical">
        <!-- Theme Name -->
        <div class="form-item">
            <label for="name" class="item-label is-required">
                <?php echo __('Theme Name'); ?>
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
                <?php echo __('Theme URL'); ?>
            </label>
            <div class="form-control">
                <input type="url" class="j-input" id="url" placeholder="eg: https://g3system.com" autocomplete="url" />
            </div>
        </div>

        <!-- Description -->
        <div class="form-item">
            <label for="description" class="item-label is-required">
                <?php echo __('Theme Description'); ?>
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
<script type="module">
    import JUI from '<?php echo G3_JS_URL . '/es/jui.js'; ?>'
    document.addEventListener('DOMContentLoaded', function () {
        document.documentElement.classList.add('j-theme-indigo', 'j-radius-sm');
        document.querySelector('#createTheme').addEventListener('click', function (e) {
            const fetchUrl = window.location.origin;
            fetch(fetchUrl + '/wp-json/api/v1/theme/generate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    name: document.querySelector('#name').value,
                    folder: document.querySelector('#folder').value,
                    url: document.querySelector('#url').value,
                    description: document.querySelector('#description').value,
                    author: document.querySelector('#author').value,
                    authorUrl: document.querySelector('#authorUrl').value,
                    version: document.querySelector('#version').value
                })
            })
                .then(response => response.json())
                .then(data => {
                    this.disabled = true
                    console.log(data)
                    switch (data.code) {
                        case 200:
                            JUI.Toast.success(data.message, 2000);
                            setTimeout(() => { this.disabled = false }, 2000)
                            const tip = `
                            <div class="j-tip is-success mb-2" id="createTip">
                                <div class="tip-content">
                                    <div><b><?php echo __('Theme Created Successfully'); ?></b></div>
                                    <div><?php echo __('Theme Name'); ?>: ${data.data.name}</div>
                                    <div><?php echo __('Theme Directory'); ?>: ${data.data.path}</div>
                                </div>
                            </div>
                            `;
                            document.querySelector('form.j-form').insertAdjacentHTML('beforebegin', tip);
                            break;
                        case 400:
                            JUI.Toast.error(data.message, 2000)
                            setTimeout(() => { this.disabled = false }, 2000)
                            break;
                        case 422:
                            JUI.Toast.error(data.data.errors[0], 2000)
                            setTimeout(() => { this.disabled = false }, 2000)
                            break;
                        default:
                            JUI.Toast.error(data.message, 2000)
                            setTimeout(() => { this.disabled = false }, 2000)
                            break;
                    }
                });
        });
        document.querySelector('form').addEventListener('reset', function (e) {
            document.querySelector('#createTheme').disabled = false;
            if (document.querySelector('#createTip')) document.querySelector('#createTip').remove();
        });
    });
</script>