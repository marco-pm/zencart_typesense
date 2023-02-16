<?php
/**
 * @package  Typesense Plugin for Zen Cart
 * @author   marco-pm
 * @version  1.0.0
 * @see      https://github.com/marco-pm/zencart_typesense
 * @license  GNU Public License V2.0
 */

require('includes/application_top.php');

use Zencart\Plugins\Catalog\Typesense\TypesenseZencart;
?>

<!doctype html>
<html <?php echo HTML_PARAMS; ?>>
<head>
    <?php require DIR_WS_INCLUDES . 'admin_html_head.php'; ?>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700&display=swap"/>
    <script>
        const typesenseI18n = {
            <?php
            $constants = get_defined_constants(true);
            foreach ($constants['user'] as $key => $value) {
                if (strpos($key, 'TYPESENSE_DASHBOARD') === 0) {
                    echo $key . ': "' . str_replace('"', '\"', $value) . '",' . PHP_EOL;
                }
            }
            ?>
        };
    </script>
    <style>
        body, html, table {
            font-size: 16px;
        }

        .row, .navbar {
            font-size: 11px !important;
        }
    </style>
</head>
<body>
<!-- header //-->
<?php require(DIR_WS_INCLUDES . 'header.php'); ?>
<!-- header_eof //-->

<?php if (!TypesenseZencart::isTypesenseEnabledAndConfigured()) { ?>
    <div class="container-fluid text-center">
        <h1><?php echo HEADING_TITLE; ?></h1>
        <h2 style="padding: 2rem 0">
            <?php echo TEXT_TYPESENSE_NOT_ENABLED_OR_CONFIGURED; ?>
        </h2>
    </div>
<?php } else { ?>
    <div id="main"></div>
    <script src="/<?php echo str_replace(DIR_FS_CATALOG, '', ($pluginManager->getPluginVersionDirectory('Typesense', $installedPlugins))); ?>admin/typesense_dashboard.min.js"></script>
<?php } ?>

<!-- footer //-->
<?php require(DIR_WS_INCLUDES . 'footer.php'); ?>
<!-- footer_eof //-->
</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
