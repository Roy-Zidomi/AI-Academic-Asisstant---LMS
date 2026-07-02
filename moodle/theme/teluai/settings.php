<?php
// ============================================================================
// Tel-U AI LMS Theme — Admin Settings
// ============================================================================
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings = new theme_boost_admin_settingspage_tabs('themesettingteluai', get_string('configtitle', 'theme_teluai'));

    // ── Tab: General ──────────────────────────────────────────────────
    $page = new admin_settingpage('theme_teluai_general', get_string('generalsettings', 'theme_teluai'));

    // Logo upload.
    $name = 'theme_teluai/logo';
    $title = get_string('logo', 'theme_teluai');
    $description = get_string('logo_desc', 'theme_teluai');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'logo');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $settings->add($page);

    // ── Tab: Advanced ─────────────────────────────────────────────────
    $page = new admin_settingpage('theme_teluai_advanced', get_string('advancedsettings', 'theme_teluai'));

    // Raw SCSS to include before the content.
    $name = 'theme_teluai/scsspre';
    $title = get_string('rawscsspre', 'theme_teluai');
    $description = get_string('rawscsspre_desc', 'theme_teluai');
    $setting = new admin_setting_scsscode($name, $title, $description, '', PARAM_RAW);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Raw SCSS to include after the content.
    $name = 'theme_teluai/scss';
    $title = get_string('rawscss', 'theme_teluai');
    $description = get_string('rawscss_desc', 'theme_teluai');
    $setting = new admin_setting_scsscode($name, $title, $description, '', PARAM_RAW);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $settings->add($page);
}
