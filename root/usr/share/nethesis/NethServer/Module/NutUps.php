<?php
/*
 * Copyright (C) 2013 Nethesis S.r.l.
 * http://www.nethesis.it - support@nethesis.it
 * 
 * This script is part of NethServer.
 * 
 * NethServer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License,
 * or any later version.
 * 
 * NethServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with NethServer.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace NethServer\Module;
use Nethgui\System\PlatformInterface as Validate;

class NutUps extends \Nethgui\Controller\AbstractController
{
    private $models = NULL;
    private $modes = array("master", "slave");
    private $devices = array('auto','/dev/ttyS0','/dev/ttyS1','/dev/ttyS2','/dev/ttyUSB0','/dev/ttyUSB1');

    private function readModels() 
    {
        $this->models = array();
        $handle = @fopen("/usr/share/driver.list", "r");
        if ($handle) {
            while (($buffer = fgets($handle, 4096)) !== false) {
                if ($buffer[0] == "#") {
                    continue;
                }
                $tmp = explode("\t",trim($buffer));
                if ($tmp[0]) {
                    if (!isset($tmp[5]) ) {
                        continue;
                    }
                    $driver = str_replace('"','',$tmp[5]);
                    
                    $man = str_replace('"','',$tmp[0]);
                    $mod = str_replace('"','',$tmp[3]);
                    $n = (int)str_replace('"','',$tmp[2]);
                    $star = "";
                    for ($i=0; $i<$n; $i++) {
                        $star.="*";
                    }
                    $desc = "$man - $mod ($star)";
                    $this->models[$desc] = $driver;
                }
            }
            if (!feof($handle)) {
                $this->models = array();
            }
            fclose($handle);
        }
    }

    // Add the module under the 'Configuration' section, at position 80
    protected function initializeAttributes(\Nethgui\Module\ModuleAttributesInterface $base)
    {
        return \Nethgui\Module\SimpleModuleAttributesProvider::extendModuleAttributes($base, 'Configuration', 80);
    }

    // Declare all parameters
    public function initialize()
    {
        parent::initialize();

        if (! $this->models) {
            $this->readModels();
        }
        $this->declareParameter('status', Validate::SERVICESTATUS, array('configuration', 'ups', 'status'));
        $this->declareParameter('Password', Validate::NOTEMPTY, array('configuration', 'ups', 'Password'));
        $this->declareParameter('Model', $this->createValidator()->memberOf(array_values($this->models)), array('configuration', 'ups', 'Model'));
        $this->declareParameter('Device', $this->createValidator()->memberOf($this->devices), array('configuration', 'ups', 'Device'));
        $this->declareParameter('Mode', $this->createValidator()->memberOf($this->modes), array('configuration', 'ups', 'Mode'));
        $this->declareParameter('Master', Validate::HOSTADDRESS, array('configuration', 'ups', 'Master'));
    }

    public function prepareView(\Nethgui\View\ViewInterface $view)
    {
        parent::prepareView($view);
        
        if (! $this->models) {
            $this->readModels();
        }

        $view['statusDatasource'] = array(array('enabled',$view->translate('enabled_label')),array('disabled',$view->translate('disabled_label')));
        $view['models'] = $this->models;
        $view['ModeDatasource'] = array_map(function($fmt) use ($view) {
            return array($fmt, $view->translate($fmt . '_label'));
        }, $this->modes);
        $view['DeviceDatasource'] = array_map(function($fmt) use ($view) {
            return array($fmt, $view->translate($fmt . '_label'));
        }, $this->devices);
    }


    // Execute actions when saving parameters
    protected function onParametersSaved($changes)
    {
        // Signal nethserver-nut-save event after saving props to db
        $this->getPlatform()->signalEvent('nethserver-nut-save@post-process');
    }

}

