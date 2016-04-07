<?php

include_once "core/module_system/bootstrap.php";
class_carrier::getInstance();

echo "<pre>\n";
echo "\n";
echo "<b>Kajona V5 pre update sequence</b>\n";
echo "\n";
echo "Welcome to the V5 update helper.\n";
echo "\n";
echo "This script backs up your v4 template and creates a new template pack out of it.\n";
echo "\n";
echo "\n";

if(class_module_system_module::getModuleByName("system")->getStrVersion() != "4.7.1") {
    echo "<b>Error</b>\n";
    echo "Your Kajona-Installation is currently on version ".class_module_system_module::getModuleByName("system")->getStrVersion()."\n";
    echo "You need at least version 4.7.1 to upgrate to 5.0\n";

    echo "Aborting!\n";
    die();

}

V5PreUpdate::main();


echo "\n";
echo "\n";
echo "Update succeeded, please update the packages and database now\n";

echo "</pre>\n";



class V5PreUpdate {

    public static function main()
    {
        $objUpdate = new V5PreUpdate();

        $objUpdate->backupTemplatepack();
        $objUpdate->moveProjectFiles();
    }

    private function moveProjectFiles()
    {
        echo "Updating /project redefinitions...\n";
        $arrFiles = array(
            "/project/system/config/config.php" => "/project/module_system/system/config/config.php", 
            "/project/portal/global_includes.php" => "/project/module_pages/portal/global_includes.php", 
            "/project/admin/scripts/ckeditor/config_kajona_standard.js" => "/project/module_system/admin/scripts/ckeditor/config_kajona_standard.js", 
        );
        
        foreach($arrFiles as $strSource => $strTarget) {
        
            if(is_file(__DIR__.$strSource)) {
                $this->safeCopyFile(__DIR_.$strSource, __DIR__.$strTarget);
                
                unlink(__DIR__.$strSource);
            }
        }
    }
    

    private function backupTemplatepack()
    {
        echo "Searching for currently active template pack\n";
        $strName = class_module_system_setting::getConfigValue("_packagemanager_defaulttemplate_");

        if($strName == "default") {
            echo "Creating a new template-pack based on the default pack\n";

            $strName = "kajonav4_backup";
            $this->copyRecursive(__DIR__."/templates/default", __DIR__."/templates/".$strName);

            echo "Setting ".$strName." as the default template pack\n";
            $objSetting = class_module_system_setting::getConfigByName("_packagemanager_defaulttemplate_");
            $objSetting->setStrValue($strName);
            $objSetting->updateObjectToDb();

            echo "Syncing template packs\n";
            class_module_packagemanager_template::syncTemplatepacks();

            /** @var class_module_packagemanager_template $objOneTemplate */
            foreach(class_module_packagemanager_template::getObjectList() as $objOneTemplate) {
                if($objOneTemplate->getStrName() == $strName) {
                    $objOneTemplate->setIntRecordStatus(1);
                    $objOneTemplate->updateObjectToDb();
                }
            }

        }

        echo "Adding un-modified templates to your custom template\n";

        //fetch templates
        $arrEntries = class_resourceloader::getInstance()->getFolderContent("/templates/default/tpl", array(), true);
        foreach($arrEntries as $strPath => $strEntry) {
            if(is_dir(__DIR__.$strPath)) {
                $arrFiles = scandir(__DIR__.$strPath."/");

                foreach($arrFiles as $strOneFile) {
                    if(in_array($strOneFile, array(".", ".."))) {
                        continue;
                    }

                    $this->safeCopyFile(__DIR__.$strPath."/".$strOneFile, __DIR__."/templates/".$strName."/tpl/".$strEntry."/".$strOneFile);
                }
            }
        }


    }



    private function safeCopyFile($strSource, $strTarget) {
        echo "  Copying ".$strSource ." to ".$strTarget."\n";

        if(!is_file($strTarget)) {
            if(!is_dir(dirname($strTarget))) {
                mkdir(dirname($strTarget), 0777, true);
            }
            if(copy($strSource, $strTarget)) {
                echo " <span style='color: green'>copying ".$strSource ." to ".$strTarget." succeeded</span>";
            }
            else {
                echo " <span style='color: red'>copying ".$strSource ." to ".$strTarget." failed</span>";
            }
        }
    }

    private function copyRecursive($strSourceDir, $strTargetDir) {
        
        $arrEntries = scandir($strSourceDir);

        foreach($arrEntries as $strOneEntry) {
            if($strOneEntry == "." || $strOneEntry == "..") {
                continue;
            }

            if(!is_file(_realpath_.$strTargetDir."/".$strOneEntry)) {

                if(!is_dir($strTargetDir)) {
                    mkdir($strTargetDir, 0777, true);
                }

                copy(_realpath_.$strSourceDir."/".$strOneEntry, _realpath_.$strTargetDir."/".$strOneEntry);
            }
            elseif(is_dir($strSourceDir."/".$strOneEntry)) {
                if(!is_dir($strTargetDir."/".$strOneEntry)) {
                    mkdir($strTargetDir."/".$strOneEntry, 0777, true);
                }

                $this->copyRecursive($strSourceDir."/".$strOneEntry, $strTargetDir."/".$strOneEntry);
            }
        }
    }

}
