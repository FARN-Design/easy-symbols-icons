<?php
namespace Farn\EasyIcon\menuPages;


use Farn\Core\License;


$tab = $_GET['tab'] ?? "default";

?>
<div class="wrap">
    <h1><?php echo __("Settings", "easyvcard"); ?></h1>
    <nav class="nav-tab-wrapper">
        <a href="?page=ei_settings-page&tab=default" class="nav-tab  <?php echo $tab == "default" ? "nav-tab-active": "" ?>"><?php echo __("General", "easyicon"); ?></a>
<!--        <a href="?page=ei_settings-page&tab=license" class="nav-tab  --><?php //echo $tab == "default" ? "nav-tab-active": "" ?><!--">--><?php //echo __("License", "easyicon"); ?><!--</a>-->
    </nav>
<?php

switch ($tab) {
//    case "license":{
//        displayLicenseTab();
//        break;
//    }
    case "default":
    default:{
        displayGeneralTab();
    }
}

function displayGeneralTab(){
    ?>
    <div class="wrap">
        <hr class="wp-header-end">
        <form method="post" name="general_setting" id="general_setting" action="">
            <table class="form-table">
                <thead>
<!--                    TODO-->
                </thead>
                <tbody>
<!--                    TODO-->
                </tbody>
            </table>
        </form>
    </div>
<?php
}
function displayLicenseTab(){
    ?>
    <div class="importMapping wrap">
        <table class="form-table">
            <tbody>
            <tr>
                <th scope="row"><?php echo __("License Key", "easyIcon"); ?></th>
                <td>
<!--                    TODO-->
                </td>
            </tr>
            </tbody>
        </table>
    </div>
<?php
}