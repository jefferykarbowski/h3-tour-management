#!/bin/bash
# Verify critical PHP files on Pantheon match Git

echo "Checking critical files on Pantheon Dev..."
echo "=========================================="

echo -e "\n1. Checking h3-tour-management.php version..."
terminus remote:drush h3vt.dev -- eval "echo file_get_contents(ABSPATH . 'wp-content/plugins/h3-tour-management/h3-tour-management.php');" | grep "Version:"

echo -e "\n2. Checking if React class files exist..."
terminus remote:drush h3vt.dev -- eval "
  \$plugin_dir = ABSPATH . 'wp-content/plugins/h3-tour-management/';
  echo 'React Uploader: ' . (file_exists(\$plugin_dir . 'includes/class-h3tm-react-uploader.php') ? 'EXISTS' : 'MISSING') . PHP_EOL;
  echo 'React Table: ' . (file_exists(\$plugin_dir . 'includes/class-h3tm-react-tours-table.php') ? 'EXISTS' : 'MISSING') . PHP_EOL;
  echo 'Trait: ' . (file_exists(\$plugin_dir . 'includes/traits/trait-h3tm-page-renderers.php') ? 'EXISTS' : 'MISSING') . PHP_EOL;
"

echo -e "\n3. Checking if trait contains React render calls..."
terminus remote:drush h3vt.dev -- eval "
  \$trait = file_get_contents(ABSPATH . 'wp-content/plugins/h3-tour-management/includes/traits/trait-h3tm-page-renderers.php');
  echo 'React Uploader call: ' . (strpos(\$trait, 'H3TM_React_Uploader::render_uploader') !== false ? 'FOUND' : 'MISSING') . PHP_EOL;
  echo 'React Table call: ' . (strpos(\$trait, 'H3TM_React_Tours_Table::render_table') !== false ? 'FOUND' : 'MISSING') . PHP_EOL;
"

echo -e "\n4. Checking main plugin requires..."
terminus remote:drush h3vt.dev -- eval "
  \$main = file_get_contents(ABSPATH . 'wp-content/plugins/h3-tour-management/h3-tour-management.php');
  echo 'Uploader require: ' . (strpos(\$main, \"require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-react-uploader.php'\") !== false ? 'FOUND' : 'MISSING') . PHP_EOL;
  echo 'Table require: ' . (strpos(\$main, \"require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-react-tours-table.php'\") !== false ? 'FOUND' : 'MISSING') . PHP_EOL;
"

echo -e "\n=========================================="
echo "If any MISSING appears above, the GitHub updater didn't update all files."
echo "Solution: Manually push to Pantheon or deactivate/reactivate plugin."
