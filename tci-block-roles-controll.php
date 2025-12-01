<?php

/**
 * Plugin Name: TCI Block Role Control
 * Plugin URI: https://github.com/tcicit/tci-block-roles-control/
 * Description: Allows enabling and disabling blocks based on user roles.
 * Version: 1.30
 * Author: Thomas Cigolla
 * Author URI: https://cigolla.ch
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tci-block-roles-control
 */

 if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 

 // Load language files
function block_roles_control_load_textdomain()
{
    load_plugin_textdomain('tci-block-roles-control', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
add_action('plugins_loaded', 'block_roles_control_load_textdomain');


// Get all registered user roles
function block_roles_control_get_user_roles()
{
    return wp_roles()->roles;
}

// Blocks blocks for certain user roles
function block_roles_control_allowed_blocks($allowed_blocks, $block_editor_context)
{
    $user = wp_get_current_user();
    $user_roles = $user->roles;

    if (!empty($user_roles)) {
        $role = $user_roles[0]; // Use the user's first role
        $allowed_blocks_for_role = get_option("allowed_blocks_$role", []);
        return !empty($allowed_blocks_for_role) ? $allowed_blocks_for_role : $allowed_blocks;
    }

    return $allowed_blocks;
}

// Add filters to restrict blocks
add_filter('allowed_block_types_all', 'block_roles_control_allowed_blocks', 10, 2);

// Retrieve all registered blocks and their categories
function block_roles_control_get_registered_blocks()
{
    $block_categories = [];
    $blocks = WP_Block_Type_Registry::get_instance()->get_all_registered();

    foreach ($blocks as $block_name => $block_data) {
        if (!empty($block_data->title)) {
            $category = isset($block_data->category) ? $block_data->category : 'uncategorized';
            $block_categories[$category][$block_name] = $block_data->title;
        }
    }

    return $block_categories;
}

// Admin menu under Add tools
function block_roles_control_admin_menu()
{
    add_management_page(
        __('tci Block Role Control', 'tci-block-roles-control'),
        __('Block Role Control', 'tci-block-roles-control'),
        'manage_options',
        'block-roles-control',
        'block_roles_control_settings_page'
    );
}
add_action('admin_menu', 'block_roles_control_admin_menu');


//  Admin settings page
function block_roles_control_settings_page()
{
    $user_roles = block_roles_control_get_user_roles(); //Get all user roles

    // Check whether a role has been selected
    $selected_role = isset($_POST['selected_role']) ? sanitize_text_field(wp_unslash($_POST['selected_role'])) : '';

    // Save the settings, but verify the nonce first
    if (isset($_POST['block_roles_control_save']) && check_admin_referer('block_roles_control_save_action', 'block_roles_control_nonce')) {
        if ($selected_role) {
            // Sanitize and unslash the incoming data
            $allowed_blocks = isset($_POST["allowed_blocks_$selected_role"])
                ? array_map('sanitize_text_field', wp_unslash($_POST["allowed_blocks_$selected_role"]))
                : [];
            update_option("allowed_blocks_$selected_role", $allowed_blocks);

            echo '<div class="updated"><p>' . esc_html(__('Settings for role', 'tci-block-roles-control')) . ' ' . esc_html($selected_role) . ' ' . esc_html(__('saved!', 'tci-block-roles-control')) . '</p></div>';
        }
    }

    // Reset all settings if the reset button was clicked
    if (isset($_POST['block_roles_control_reset']) && check_admin_referer('block_roles_control_reset_action', 'block_roles_control_reset_nonce')) {
        foreach (array_keys($user_roles) as $role) {
            delete_option("allowed_blocks_$role");
        }
        // Unset selected role to refresh the view
        $selected_role = '';
        echo '<div class="updated"><p>' . esc_html__('All settings have been reset.', 'tci-block-roles-control') . '</p></div>';
    }


    // Retrieve all registered blocks by category
    $blocks_by_category = block_roles_control_get_registered_blocks();
?>
    <div class="wrap">
        <h1><?php esc_html_e('tci Block Role Control', 'tci-block-roles-control'); ?></h1>
        <form method="post">
            <?php wp_nonce_field('block_roles_control_save_action', 'block_roles_control_nonce'); ?>
            <h2><?php esc_html_e('Select a User Role', 'tci-block-roles-control'); ?></h2>
            <select name="selected_role" onchange="this.form.submit()">
                <option value=""><?php esc_html_e('Select a role', 'tci-block-roles-control'); ?></option>
                <?php foreach ($user_roles as $role => $details) : ?>
                    <option value="<?php echo esc_attr($role); ?>" <?php selected($selected_role, $role); ?>>
                        <?php echo esc_html($details['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($selected_role) : ?>
            <form method="post">
                <?php wp_nonce_field('block_roles_control_save_action', 'block_roles_control_nonce'); ?>
                <input type="hidden" name="selected_role" value="<?php echo esc_attr($selected_role); ?>">
                <h2><?php
                    // translators: %s: User role name
                    printf(esc_html__('Blocks for role: %s', 'tci-block-roles-control'), esc_html($user_roles[$selected_role]['name'])); ?></h2>
                <fieldset>
                    <legend><?php
                            // translators: %s: User role name
                            printf(esc_html__('Select blocks available for role %s:', 'tci-block-roles-control'), esc_html($user_roles[$selected_role]['name'])); ?></legend>

                    <?php
                    $allowed_blocks = get_option("allowed_blocks_$selected_role", []);

                    // Show blocks by category
                    foreach ($blocks_by_category as $category => $blocks) : ?>
                        <h3><?php echo esc_html(ucfirst($category)); ?></h3>
                        <label>
                            <input type="checkbox" class="toggle-category" data-category="<?php echo esc_attr($category); ?>" <?php checked(array_reduce($blocks, function ($carry, $block) use ($allowed_blocks) {
                                                                                                                                    return $carry || in_array($block, $allowed_blocks);
                                                                                                                                }, false)); ?>>
                            <?php
                            // translators: %s: Category name
                            printf(esc_html__('Enable all %s blocks', 'tci-block-roles-control'), esc_html($category)); ?>
                        </label><br>
                        <?php foreach ($blocks as $block => $block_label) : ?>
                            <label>
                                <input type="checkbox" name="allowed_blocks_<?php echo esc_attr($selected_role); ?>[]" value="<?php echo esc_attr($block); ?>" <?php checked(in_array($block, $allowed_blocks)); ?> class="block-checkbox <?php echo esc_attr($category); ?>">
                                <?php echo esc_html($block_label); ?>
                            </label><br>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </fieldset>
                <p>
                    <input type="submit" name="block_roles_control_save" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'tci-block-roles-control'); ?>">
                </p>
            </form>
        <?php endif; ?>

        <hr style="margin-top: 20px;">

        <h2><?php esc_html_e('Reset Settings', 'tci-block-roles-control'); ?></h2>
        <p><?php esc_html_e('This will remove all block restrictions for all user roles. The default WordPress settings will apply again.', 'tci-block-roles-control'); ?></p>
        <form method="post" onsubmit="return confirm('<?php esc_attr_e('Are you sure you want to reset all settings?', 'tci-block-roles-control'); ?>');">
            <?php wp_nonce_field('block_roles_control_reset_action', 'block_roles_control_reset_nonce'); ?>
            <p>
                <input type="submit" name="block_roles_control_reset" class="button button-secondary" value="<?php esc_attr_e('Reset All Settings', 'tci-block-roles-control'); ?>">
            </p>
        </form>
    </div>
<?php
}
