<?php
/**
 * HA Discovery
 * @package project
 * @author Wizard <sergejey@gmail.com>
 * @copyright http://majordomo.smartliving.ru/ (c)
 * @version 0.1 (wizard, 19:10:32 [Oct 11, 2024])
 */

use ByJG\JinjaPhp\Template;

//
//
class ha_discovery extends module
{
    /**
     * ha_discovery
     *
     * Module class constructor
     *
     * @access private
     */
    function __construct()
    {
        $this->name = "ha_discovery";
        $this->title = "HA Discovery";
        $this->module_category = "<#LANG_SECTION_DEVICES#>";
        $this->checkInstalled();
    }

    /**
     * saveParams
     *
     * Saving module parameters
     *
     * @access public
     */
    function saveParams($data = 1)
    {
        $p = array();
        if (isset($this->id)) {
            $p["id"] = $this->id;
        }
        if (isset($this->view_mode)) {
            $p["view_mode"] = $this->view_mode;
        }
        if (isset($this->edit_mode)) {
            $p["edit_mode"] = $this->edit_mode;
        }
        if (isset($this->data_source)) {
            $p["data_source"] = $this->data_source;
        }
        if (isset($this->tab)) {
            $p["tab"] = $this->tab;
        }
        return parent::saveParams($p);
    }

    /**
     * getParams
     *
     * Getting module parameters from query string
     *
     * @access public
     */
    function getParams()
    {
        global $id;
        global $mode;
        global $view_mode;
        global $edit_mode;
        global $data_source;
        global $tab;
        if (isset($id)) {
            $this->id = $id;
        }
        if (isset($mode)) {
            $this->mode = $mode;
        }
        if (isset($view_mode)) {
            $this->view_mode = $view_mode;
        }
        if (isset($edit_mode)) {
            $this->edit_mode = $edit_mode;
        }
        if (isset($data_source)) {
            $this->data_source = $data_source;
        }
        if (isset($tab)) {
            $this->tab = $tab;
        }
    }

    /**
     * Run
     *
     * Description
     *
     * @access public
     */
    function run()
    {
        global $session;
        $out = array();
        if ($this->action == 'admin') {
            $this->admin($out);
        } else {
            $this->usual($out);
        }
        if (isset($this->owner->action)) {
            $out['PARENT_ACTION'] = $this->owner->action;
        }
        if (isset($this->owner->name)) {
            $out['PARENT_NAME'] = $this->owner->name;
        }
        $out['VIEW_MODE'] = $this->view_mode;
        $out['EDIT_MODE'] = $this->edit_mode;
        $out['MODE'] = $this->mode;
        $out['ACTION'] = $this->action;
        $out['DATA_SOURCE'] = $this->data_source;
        $out['TAB'] = $this->tab;
        $this->data = $out;
        $p = new parser(DIR_TEMPLATES . $this->name . "/" . $this->name . ".html", $this->data, $this);
        $this->result = $p->result;
    }

    /**
     * BackEnd
     *
     * Module backend
     *
     * @access public
     */
    function admin(&$out)
    {
        $this->getConfig();

        $out['MQTT_HOST'] = $this->config['MQTT_HOST'];
        $out['MQTT_PORT'] = $this->config['MQTT_PORT'];
        $out['MQTT_QUERY'] = $this->config['MQTT_QUERY'];
        if (!$out['MQTT_HOST']) {
            $out['MQTT_HOST'] = 'localhost';
        }
        if (!$out['MQTT_PORT']) {
            $out['MQTT_PORT'] = '1883';
        }
        $out['MQTT_USERNAME'] = $this->config['MQTT_USERNAME'];
        $out['MQTT_PASSWORD'] = $this->config['MQTT_PASSWORD'];
        $out['MQTT_AUTH'] = $this->config['MQTT_AUTH'];
        $out['BASE_TOPIC'] = $this->config['BASE_TOPIC'];
        $out['DEBUG_MODE'] = $this->config['DEBUG_MODE'];
        $out['CREATE_DEVICES_AUTOMATICALLY'] = isset($this->config['CREATE_DEVICES_AUTOMATICALLY']) ? $this->config['CREATE_DEVICES_AUTOMATICALLY'] : false;
        $out['UNPAIR_DEVICES_AUTOMATICALLY'] = isset($this->config['UNPAIR_DEVICES_AUTOMATICALLY']) ? $this->config['UNPAIR_DEVICES_AUTOMATICALLY'] : false;

        if ($this->view_mode == 'update_settings') {
            $this->config['MQTT_HOST'] = gr('mqtt_host', 'trim');
            $this->config['MQTT_USERNAME'] = gr('mqtt_username', 'trim');
            $this->config['MQTT_PASSWORD'] = gr('mqtt_password', 'trim');
            $this->config['MQTT_AUTH'] = gr('mqtt_auth', 'int');
            $this->config['MQTT_PORT'] = gr('mqtt_port', 'int');
            $this->config['BASE_TOPIC'] = gr('base_topic');
            $this->config['DEBUG_MODE'] = gr('debug_mode', 'int');
            $this->config['CREATE_DEVICES_AUTOMATICALLY'] = gr('create_devices_automatically', 'int');
            $this->config['UNPAIR_DEVICES_AUTOMATICALLY'] = gr('unpair_devices_automatically', 'int');

            $this->saveConfig();
            setGlobal('cycle_ha_discovery', 'restart');

            if (!$this->config['DEBUG_MODE']) {
                SQLExec("TRUNCATE TABLE ha_history;");
            }

            $this->redirect("?");
        }
        if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
            $out['SET_DATASOURCE'] = 1;
        }
        if ($this->data_source == 'ha_devices' || $this->data_source == '') {
            if ($this->view_mode == '' || $this->view_mode == 'search_ha_devices') {
                $this->search_ha_devices($out);
            }

            if ($this->view_mode == 'check_permit_join') {
                $this->checkPermitJoinsLinked();
                $this->redirect("?data_source=ha_devices");
            }

            if ($this->view_mode == 'edit_ha_devices') {
                $this->edit_ha_devices($out, $this->id);
            }
            if ($this->view_mode == 'delete_ha_devices') {
                $this->delete_ha_devices($this->id);
                $this->redirect("?data_source=ha_devices");
            }
            if ($this->view_mode == 'unlink_ha_devices') {
                $this->unlink_ha_devices($this->id);
                $this->redirect("?data_source=ha_devices");
            }
        }
        if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
            $out['SET_DATASOURCE'] = 1;
        }
        if ($this->data_source == 'ha_components') {
            if ($this->view_mode == '' || $this->view_mode == 'search_ha_components') {
                $this->search_ha_components($out);
            }
            if ($this->view_mode == 'edit_ha_components') {
                $this->edit_ha_components($out, $this->id);
            }
        }
        if ($this->view_mode == 'list_unsupported') {
            $this->list_unsupported($out);
        }
    }

    function list_unsupported(&$out)
    {
        $device_id = gr('device_id');
        if ($device_id) {
            $out['DEVICE_ID'] = $device_id;
            $devices = SQLSelect("SELECT ID, MODEL, MANUFACTURER, DEVICE_PAYLOAD FROM ha_devices WHERE ID=" . $device_id);
        } else {
            $devices = SQLSelect("SELECT ID, MODEL, MANUFACTURER, DEVICE_PAYLOAD FROM ha_devices ORDER BY MODEL, TITLE");
        }
        $res_devices = array();
        $total = count($devices);
        $seen = array();
        for ($i = 0; $i < $total; $i++) {
            $device = $devices[$i];
            $device['DEVICE_PAYLOAD'] = json_decode($device['DEVICE_PAYLOAD'], true);
            if (trim($device['MODEL']) == '' && trim($device['MANUFACTURER']) == '') continue;
            $key = $device['MANUFACTURER'] . '.' . $device['MODEL'];
            if (isset($seen[$key])) continue;
            $seen[$key] = 1;
            $supported = $this->checkDeviceType($device['ID']);
            if (!$supported) {
                $properties = SQLSelect("SELECT HA_COMPONENT, HA_OBJECT, VALUE, COMPONENT_PAYLOAD, DATA_PAYLOAD, LINKED_OBJECT, LINKED_PROPERTY, LINKED_METHOD FROM ha_components WHERE HA_DEVICE_ID=" . $device['ID']);
                $total_p = count($properties);
                for ($ip = 0; $ip < $total_p; $ip++) {
                    $properties[$ip]['COMPONENT_PAYLOAD'] = json_decode($properties[$ip]['COMPONENT_PAYLOAD'], true);
                    $properties[$ip]['DATA_PAYLOAD'] = json_decode($properties[$ip]['DATA_PAYLOAD'], true);
                    if ($properties[$ip]['LINKED_OBJECT'] != '') {
                        $sdevice = SQLSelectOne("SELECT ID, TYPE FROM devices WHERE LINKED_OBJECT='" . $properties[$ip]['LINKED_OBJECT'] . "'");
                        if (isset($sdevice['TYPE'])) {
                            $properties[$ip]['LINKED_OBJECT'] .= ' (device type: ' . $sdevice['TYPE'] . ')';
                        }
                    }
                    foreach ($properties[$ip] as $k => $v) {
                        if ($v === "") unset($properties[$ip][$k]);
                    }
                }
                $device['PROPERTIES'] = $properties;
                $res_devices[] = $device;
            }
        }
        if (count($res_devices) > 0) {
            $out['DEVICES'] = $res_devices;
            $out['DETAILS'] = json_encode($res_devices, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
        }
    }

    /**
     * FrontEnd
     *
     * Module frontend
     *
     * @access public
     */
    function usual(&$out)
    {
        $this->admin($out);
    }

    function api($params)
    {
        if ($_REQUEST['topic']) {
            $this->processMessage($_REQUEST['topic'], $_REQUEST['msg']);
        }
        if (isset($params['create_device_id']) && $this->canCreateDevice($params['create_device_id'])) {
            $id = (int)$params['create_device_id'];
            $this->log("API call to create device: " . $id, "new_device");
            clearTimeOut('ha_discovery_new_component_' . $id);
            $this->createDevice($id);
        }
        if (isset($params['component_device_id']) && isset($params['sdevice_id'])) {
            $sdevice_id = (int)$params['sdevice_id'];
            $id = (int)$params['component_device_id'];
            $this->log("API call to check missing components for device: " . $id, "new_device");
            $this->linkMissingAttributes($id, $sdevice_id);
            $this->createDevice($id, $sdevice_id);
        }
        if (isset($params['component_id']) && isset($params['set_value'])) {
            $this->setValue((int)$params['component_id'], $params['set_value']);
        }
    }

    function processMessage($topic, $msg)
    {

        //$this->log("$topic :\n$msg", 'ha_discovery');
        startMeasure('ha_processMessage');

        $this->getConfig();
        $base_topic = $this->config['BASE_TOPIC'];
        if (!$base_topic) $base_topic = 'homeassistant';

        $original_topic = $topic;

        if (preg_match('/^' . $base_topic . '\/(.+?)\/config$/', $topic, $m)) {
            //CONFIG
            $topic = $m[1];
            $items = explode('/', $topic);
            $last_index = count($items) - 1;
            if ($last_index < 0) {
                $this->log("Incorrect message", "error");
                endMeasure('ha_processMessage');
                return false;
            }

            $component = $items[0];
            $object_id = $items[$last_index];
            if ($last_index > 1) {
                $node_id = $items[$last_index - 1];
            } else {
                $node_id = '';
            }
            $data = json_decode($msg, true);
            $this->replaceAbbreviations($data);
            $this->log("Component: $component\nNode Id: $node_id\nObject Id: $object_id\nData:\n" . json_encode($data, JSON_PRETTY_PRINT));
            if (isset($data['device'])) {
                $device_id = $this->processDevice($data['device'], $node_id);
                if (!$device_id) {
                    $this->log("Could not add device:\n" . json_encode($data['device'], JSON_PRETTY_PRINT), 'error');
                    endMeasure('ha_processMessage');
                    return false;
                }
                if (isset($data['device'])) unset($data['device']);
                if (isset($data['origin'])) unset($data['origin']);

                $component_ids = array();

                $component_id = $this->processComponent($device_id, $component, $object_id, $data);
                if ($component_id) {
                    $component_ids[] = $component_id;
                }
                if ($component == 'light' && isset($data['brightness']) && $data['brightness']) {
                    $add_component_id = $this->processComponent($device_id, 'light_brightness', $object_id . '_brightness', $data);
                    if ($add_component_id) {
                        $component_ids[] = $add_component_id;
                    }
                }
                if ($component == 'light' && isset($data['supported_color_modes']) && is_array($data['supported_color_modes'])) {
                    foreach ($data['supported_color_modes'] as $color_mode) {
                        $add_component_id = $this->processComponent($device_id, 'light_' . $color_mode, $object_id . '_' . $color_mode, $data);
                        if ($add_component_id) {
                            $component_ids[] = $add_component_id;
                        }
                    }
                }

                if ($component == 'climate' && isset($data['current_temperature_topic'])) {
                    $new_data = $data;
                    $new_data['state_topic'] = $data['current_temperature_topic'];
                    if (isset($data['current_temperature_template'])) {
                        $new_data['value_template'] = $data['current_temperature_template'];
                    }
                    $add_component_id = $this->processComponent($device_id, 'climate_temperature', 'climate_temperature', $new_data);
                    if ($add_component_id) {
                        $component_ids[] = $add_component_id;
                    }
                }
                if ($component == 'climate' && isset($data['temperature_state_topic'])) {
                    $new_data = $data;
                    $new_data['state_topic'] = $data['temperature_state_topic'];
                    if (isset($data['temperature_state_template'])) {
                        $new_data['value_template'] = $data['temperature_state_template'];
                    }
                    if (isset($data['temperature_command_topic'])) {
                        $new_data['command_topic'] = $data['temperature_command_topic'];
                    }
                    $add_component_id = $this->processComponent($device_id, 'climate_setpoint', 'climate_setpoint', $new_data);
                    if ($add_component_id) {
                        $component_ids[] = $add_component_id;
                    }
                }
                if ($component == 'climate' && isset($data['mode_state_topic'])) {
                    $new_data = $data;
                    $new_data['state_topic'] = $data['mode_state_topic'];
                    if (isset($data['mode_state_template'])) {
                        $new_data['value_template'] = $data['mode_state_template'];
                    }
                    if (isset($data['mode_command_topic'])) {
                        $new_data['command_topic'] = $data['mode_command_topic'];
                    }
                    $add_component_id = $this->processComponent($device_id, 'climate_mode', 'climate_mode', $new_data);
                    if ($add_component_id) {
                        $component_ids[] = $add_component_id;
                    }
                }
                if ($component == 'climate' && isset($data['preset_mode_state_topic'])) {
                    $new_data = $data;
                    $new_data['state_topic'] = $data['preset_mode_state_topic'];
                    if (isset($data['preset_mode_value_template'])) {
                        $new_data['value_template'] = $data['preset_mode_value_template'];
                    }
                    if (isset($data['preset_mode_command_topic'])) {
                        $new_data['command_topic'] = $data['preset_mode_command_topic'];
                    }
                    $add_component_id = $this->processComponent($device_id, 'climate_preset', 'climate_preset', $new_data);
                    if ($add_component_id) {
                        $component_ids[] = $add_component_id;
                    }
                }
                if ($component == 'climate' && isset($data['action_topic'])) {
                    $new_data = $data;
                    $new_data['state_topic'] = $data['action_topic'];
                    if (isset($data['action_template'])) {
                        $new_data['value_template'] = $data['action_template'];
                    }
                    $add_component_id = $this->processComponent($device_id, 'climate_action', 'climate_action', $new_data);
                    if ($add_component_id) {
                        $component_ids[] = $add_component_id;
                    }
                }

                if (is_array($component_ids) && count($component_ids) > 0) {
                    SQLExec("UPDATE ha_components SET SRC_TOPIC='" . DBSafe($original_topic) . "' WHERE ID IN (" . implode(',', $component_ids) . ")");
                }

            } else {
                $this->log("No device data:\n" . json_encode($data, JSON_PRETTY_PRINT), 'error');
                endMeasure('ha_processMessage');
                return false;
            }
        } elseif (preg_match('/^' . $base_topic . '\/(.+)\/log$/', $topic, $m)) {
            $this->log($msg, 'log');
        } else {
            $data = json_decode($msg, true);
            if (is_array($data)) {
                $this->replaceAbbreviations($data);
            } else {
                $data = $msg;
            }
            $components = SQLSelect("SELECT * FROM ha_components WHERE MQTT_TOPIC='" . DBSafe($topic) . "'");
            $total = count($components);
            if ($total > 0) {
                $this->log("Processing data for " . $topic . ":\n" . $msg, 'data');
                for ($i = 0; $i < $total; $i++) {
                    $this->processComponentMessage($components[$i], $data);
                }
            } else {
                $this->log("Message from unknown component: $topic", 'error');
            }
        }
        endMeasure('ha_processMessage');

    }

    function processComponentMessage($component, $data, $force = false)
    {
        startMeasure('ha_processComponentMessage');
        SQLExec("UPDATE ha_devices SET UPDATED='" . date('Y-m-d H:i:s') . "' WHERE ID=" . (int)$component['HA_DEVICE_ID']);
        $payload = json_decode($component['COMPONENT_PAYLOAD'], true);
        $old_value = $component['VALUE'];
        if (!is_array($data)) {
            $component['VALUE'] = $data;
        } elseif (isset($payload['value_template'])) {
            $component['VALUE'] = $this->parseJingaTemplate($data, $payload['value_template']);
        }
        if ($component['HA_COMPONENT'] == 'light' && isset($data['state'])) {
            $component['VALUE'] = strtolower($data['state']);
        }
        if ($component['HA_COMPONENT'] == 'light_brightness' && isset($data['brightness'])) {
            $component['VALUE'] = $data['brightness'];
        }
        if ($component['HA_COMPONENT'] == 'light_rgb' && isset($data['color']) && isset($data['color']['r']) && isset($data['color']['g']) && isset($data['color']['b'])) {
            $r = (int)$data['color']['r'];
            $g = (int)$data['color']['g'];
            $b = (int)$data['color']['b'];
            $component['VALUE'] = sprintf("#%02x%02x%02x", $r, $g, $b);
        }

        if (isset($payload['payload_off']) && $component['VALUE'] == $payload['payload_off']) {
            $component['VALUE'] = 0;
        } elseif (isset($payload['payload_on']) && $component['VALUE'] == $payload['payload_on']) {
            $component['VALUE'] = 1;
        }

        if (isset($payload['payload'])) {
            $component['VALUE'] = ($component['VALUE'] == $payload['payload']) ? 1 : 0;
        }

        if ($component['HA_COMPONENT'] == 'binary_sensor'
            && isset($payload['device_class']) && in_array(strtolower($payload['device_class']), array('door', 'window', 'garage_door', 'lock', 'opening', 'window'))) {
            $component['VALUE'] = $component['VALUE'] ? 'open' : 'close';
        }

        if (is_null($component['VALUE'])) $component['VALUE'] = '';
        $component['DATA_PAYLOAD'] = json_encode($data, JSON_PRETTY_PRINT);
        if ($component['VALUE'] != $old_value
            || ($component['HA_COMPONENT'] == 'linkquality')
            || ($component['HA_COMPONENT'] == 'action')
            || ($component['HA_COMPONENT'] == 'device_automation' && $component['VALUE'] == 1)) {
            if ($component['HA_COMPONENT'] == 'device_automation') {
                $this->log("New value " . $component['VALUE'] . " <> $old_value (" . json_encode($component) . ")", 'update_automation');
            }
            $component['UPDATED'] = date('Y-m-d H:i:s');
        }
        SQLUpdate('ha_components', $component);

        if (isset($this->config['DEBUG_MODE']) && $this->config['DEBUG_MODE']) {
            $hist = array();
            $hist['HA_DEVICE_ID'] = $component['HA_DEVICE_ID'];
            $hist['HA_COMPONENT_ID'] = $component['ID'];
            $hist['TOPIC'] = $component['MQTT_TOPIC'];
            $hist['DESTINATION'] = 0;
            $hist['DATA_PAYLOAD'] = $component['DATA_PAYLOAD'];
            $hist['VALUE'] = $component['VALUE'];
            $hist['UPDATED'] = date('Y-m-d H:i:s');;
            SQLInsert('ha_history', $hist);

            $keep_total = 20;
            $tmp = SQLSelect("SELECT ID FROM ha_history WHERE HA_COMPONENT_ID=" . $hist['HA_COMPONENT_ID'] . " ORDER BY ID DESC LIMIT $keep_total");
            if (count($tmp) == $keep_total) {
                SQLExec("DELETE FROM ha_history WHERE HA_COMPONENT_ID=" . $hist['HA_COMPONENT_ID'] . " AND ID<" . $tmp[$keep_total - 1]['ID']);
            }

        }

        $linked_object = $component['LINKED_OBJECT'];
        $linked_property = $component['LINKED_PROPERTY'];
        if ($linked_object) {
            $value = $component['VALUE'];
            $value = strtolower($value);

            if ($value == 'false' || $value == 'off' || $value == 'no' || $value == 'open' || $value == 'offline') {
                $value = 0;
            } elseif ($value == 'true' || $value == 'on' || $value == 'yes' || $value == 'close' || $value == 'online') {
                $value = 1;
            }

            if ($component['READ_CODE'] != '') {
                setEvalCode($component['READ_CODE']);
                eval($component['READ_CODE']);
                setEvalCode();
            }

            if ($linked_property) {
                $old_linked_value = gg($linked_object . '.' . $linked_property);
            } else {
                $old_linked_value = $old_value;
            }

            if ($component['VALUE'] != $old_value
                || $value != $old_linked_value
                || ($component['HA_OBJECT'] == 'action')
                || ($component['HA_OBJECT'] == 'linkquality')
                || ($component['HA_COMPONENT'] == 'device_automation' && $component['VALUE'] == 1)
                || $force
            ) {
                if ($linked_property) {
                    setGlobal($linked_object . '.' . $linked_property, $value, array($this->name => '0'));
                }
                if ($component['LINKED_METHOD'] && ($component['HA_COMPONENT'] != 'device_automation' || $component['VALUE'] == 1)) {
                    callMethod($linked_object . '.' . $component['LINKED_METHOD'], array(
                        'VALUE' => $value, 'NEW_VALUE' => $value, 'TITLE' => $component['HA_OBJECT']
                    ));
                }
            }

        }
        endMeasure('ha_processComponentMessage');


    }

    function parseJingaTemplate($data, $templateString)
    {

        require_once DIR_MODULES . 'ha_discovery/php-jinga/Loader/LoaderInterface.php';
        require_once DIR_MODULES . 'ha_discovery/php-jinga/Loader/FileSystemLoader.php';
        require_once DIR_MODULES . 'ha_discovery/php-jinga/Loader/StringLoader.php';

        require_once DIR_MODULES . 'ha_discovery/php-jinga/Undefined/UndefinedInterface.php';
        require_once DIR_MODULES . 'ha_discovery/php-jinga/Undefined/DefaultUndefined.php';
        require_once DIR_MODULES . 'ha_discovery/php-jinga/Undefined/DebugUndefined.php';
        require_once DIR_MODULES . 'ha_discovery/php-jinga/Undefined/StrictUndefined.php';

        require_once DIR_MODULES . 'ha_discovery/php-jinga/Exception/LoaderException.php';
        require_once DIR_MODULES . 'ha_discovery/php-jinga/Exception/TemplateParseException.php';

        require_once DIR_MODULES . 'ha_discovery/php-jinga/Template.php';

        $templateString = str_replace("']['", '.', $templateString);
        $templateString = str_replace("['", '.', $templateString);
        $templateString = preg_replace("/'\\]\W/", '', $templateString);

        $template = new Template($templateString);
        $value = '';
        try {
            $value = $template->render(array('value_json' => $data));
            if ($value === false) $value = 0;
            if ($value === true) $value = 1;
        } catch (Exception $e) {

        } finally {

        }
        return $value;
    }

    function processComponent($device_id, $component_type, $object_id, $data)
    {
        startMeasure('ha_processComponent');
        $rec = SQLSelectOne("SELECT * FROM ha_components WHERE HA_DEVICE_ID=" . $device_id . " AND HA_OBJECT='" . $object_id . "'");
        $rec['HA_DEVICE_ID'] = $device_id;
        $rec['HA_COMPONENT'] = $component_type;
        $rec['HA_OBJECT'] = $object_id;
        $rec['COMPONENT_PAYLOAD'] = json_encode($data, JSON_PRETTY_PRINT);
        if (isset($data['state_topic'])) {
            $rec['MQTT_TOPIC'] = $data['state_topic'];
        } elseif (isset($data['topic'])) {
            $rec['MQTT_TOPIC'] = $data['topic'];
        }
        if (!isset($rec['ID'])) {
            $rec['UPDATED'] = date('Y-m-d H:i:s');
            $rec['ID'] = SQLInsert('ha_components', $rec);

            $ha_device_rec = SQLSelectOne("SELECT * FROM ha_devices WHERE ID=" . $rec['HA_DEVICE_ID']);
            if (isset($this->config['CREATE_DEVICES_AUTOMATICALLY']) && $this->config['CREATE_DEVICES_AUTOMATICALLY']) {
                $id = (int)$rec['HA_DEVICE_ID'];
                if ($ha_device_rec['SDEVICE_ID']) {
                    $sdid = $ha_device_rec['SDEVICE_ID'];
                    $timer_code = "callAPI('/api/module/ha_discovery','GET',array('component_device_id'=>$id,'sdevice_id'=>$sdid));";
                    setTimeOut('ha_discovery_new_component_' . $id, $timer_code, 5);
                    $this->log("Setting timer for new component: $timer_code", "new_device");
                } elseif (!timeOutExists('ha_discovery_new_device_' . $id)) {
                    $timer_code = "callAPI('/api/module/ha_discovery','GET',array('create_device_id'=>$id));";
                    setTimeOut('ha_discovery_new_device_' . $id, $timer_code, 3);
                    $this->log("Setting timer from component: $timer_code", "new_device");
                }
            }
        } else {
            SQLUpdate('ha_components', $rec);
        }
        if (preg_match('/join/is', $rec['HA_OBJECT'])) {
            $this->checkPermitJoinsLinked();
        }
        endMeasure('ha_processComponent');
        return $rec['ID'];
    }

    function processDevice($data, $node_id = '')
    {
        if (!isset($data['identifiers']) || !is_array($data['identifiers'])) {
            if (isset($data['name'])) {
                $identifier = $data['name'];
            } else {
                return 0;
            }
        } else {
            $identifier = $data['identifiers'][0];
        }

        startMeasure('ha_processDevice');

        $device_payload = json_encode($data, JSON_PRETTY_PRINT);
        $rec = SQLSelectOne("SELECT * FROM ha_devices WHERE IDENTIFIER='" . $identifier . "'");
        if (!isset($rec['ID']) && $node_id != '') {
            $rec = SQLSelectOne("SELECT * FROM ha_devices WHERE IEEEADDR='" . $node_id . "'");
        }

        if ($node_id != '') {
            $rec['IEEEADDR'] = $node_id;
        }

        $rec['UPDATED'] = date('Y-m-d H:i:s');

        $title = '';
        if (isset($data['name']) && $data['name'] != '') {
            $title = $data['name'];
        }

        if (!isset($rec['ID']) || strlen($device_payload) > $rec['DEVICE_PAYLOAD']) {
            $rec['DEVICE_PAYLOAD'] = $device_payload;
        }
        if (isset($data['model']) && $data['model'] != '') {
            $rec['MODEL'] = $data['model'];
            if ($title == '') $title = $rec['MODEL'];
        }
        if (isset($data['manufacturer']) && $data['manufacturer'] != '') {
            $rec['MANUFACTURER'] = $data['manufacturer'];
        }
        if (isset($data['sw_version']) && $data['sw_version'] != '') {
            $rec['SW_VERSION'] = $data['sw_version'];
        }
        if (isset($data['hw_version']) && $data['hw_version'] != '') {
            $rec['HW_VERSION'] = $data['hw_version'];
        }


        if ($title == '') {
            $title = $identifier;
        }

        if (!isset($rec['TITLE']) || $rec['TITLE'] == '') {
            $rec['TITLE'] = $title;
        }

        if (isset($rec['ID'])) {
            SQLUpdate('ha_devices', $rec);
        } else {
            $rec['IDENTIFIER'] = $identifier;
            $rec['ID'] = SQLInsert('ha_devices', $rec);
            if (isset($this->config['CREATE_DEVICES_AUTOMATICALLY']) && $this->config['CREATE_DEVICES_AUTOMATICALLY']) {
                $id = (int)$rec['ID'];
                $timer_code = "callAPI('/api/module/ha_discovery','GET',array('create_device_id'=>$id));";
                setTimeOut('ha_discovery_new_device_' . $id, $timer_code, 3);
                setTimeOut('ha_discovery_new_device_' . $id . '_2nd', $timer_code, 8);
                $this->log("Setting timer: $timer_code", "new_device");
            }
        }
        endMeasure('ha_processDevice');
        return $rec['ID'];
    }

    function replaceAbbreviations(&$var)
    {
        $supported_abbreviations = array();
        require DIR_MODULES . 'ha_discovery/abbreviations.inc.php';
        if (is_array($var)) {
            foreach ($var as $k => $v) {
                if (!is_numeric($k)) {
                    foreach ($supported_abbreviations as $old_key => $new_key) {
                        if ($k == $old_key) {
                            $var[$new_key] = $var[$old_key];
                            unset($var[$old_key]);
                        }
                    }
                }
            }
            foreach ($var as $k => $v) {
                if (is_array($v)) {
                    $this->replaceAbbreviations($var[$k]);
                }
            }
        }
    }

    function linkDevice($device_id, $simple_device_id, $exclude_taken = false)
    {

        $types = $this->checkDeviceType($device_id, $exclude_taken);
        $sdevice = SQLSelectOne("SELECT * FROM devices WHERE ID=" . (int)$simple_device_id);
        if (!isset($sdevice['ID'])) return false;

        $linked_object = $sdevice['LINKED_OBJECT'];

        if ($types) {
            if (isModuleInstalled('zigbeedev')) {
                $zigbeeDevProperties = SQLSelect("SELECT * FROM zigbeeproperties WHERE LINKED_OBJECT='" . $linked_object . "'");
                $total = count($zigbeeDevProperties);
                for ($i = 0; $i < $total; $i++) {
                    $property = $zigbeeDevProperties[$i]['LINKED_PROPERTY'];
                    $zigbeeDevProperties[$i]['LINKED_OBJECT'] = '';
                    $zigbeeDevProperties[$i]['LINKED_PROPERTY'] = '';
                    SQLUpdate('zigbeeproperties', $zigbeeDevProperties[$i]);
                    removeLinkedPropertyIfNotUsed('zigbeeproperties', $linked_object, $property, 'zigbeedev');
                }
            }
        }


        foreach ($types as $type) {
            if (is_array($type['properties'])) {
                foreach ($type['properties'] as $k => $v) {
                    $prop = SQLSelectOne("SELECT * FROM ha_components WHERE HA_DEVICE_ID=" . $device_id . " AND HA_OBJECT='" . $k . "'");
                    if (!isset($prop['ID'])) {
                        continue;
                    }
                    $prop['LINKED_OBJECT'] = $linked_object;
                    if (is_array($v)) {
                        foreach ($v as $key => $value) {
                            $prop[$key] = $value;
                        }
                    } else {
                        $prop['LINKED_PROPERTY'] = $v;
                    }
                    SQLUpdate('ha_components', $prop);
                    addLinkedProperty($prop['LINKED_OBJECT'], $prop['LINKED_PROPERTY'], $this->name);
                    if ($prop['VALUE'] != '') {
                        $this->processComponentMessage($prop, json_decode($prop['DATA_PAYLOAD'], true), true);
                    }
                }
            }
            if (is_array($type['methods'])) {
                foreach ($type['methods'] as $k => $v) {
                    $prop = SQLSelectOne("SELECT * FROM ha_components WHERE HA_DEVICE_ID=" . $device_id . " AND HA_OBJECT='" . $k . "'");
                    if (!isset($prop['ID'])) {
                        continue;
                    }
                    $prop['LINKED_OBJECT'] = $linked_object;
                    $prop['LINKED_METHOD'] = $v;
                    SQLUpdate('ha_components', $prop);
                }
            }
            if (is_array($type['settings'])) {
                foreach ($type['settings'] as $k => $v) {
                    setGlobal($linked_object . '.' . $k, $v);
                }
            }
        }

    }

    function linkMissingAttributes($device_id, $sdevice_id)
    {
        $ha_device = SQLSelectOne("SELECT * FROM ha_devices WHERE ID=" . (int)$device_id);
        $s_device = SQLSelectOne("SELECT * FROM devices WHERE ID=" . (int)$sdevice_id);
        if (!isset($ha_device['ID']) || !isset($s_device['LINKED_OBJECT'])) return false;

        $linked_object = $s_device['LINKED_OBJECT'];

        $this->log("Checking new attributes for " . json_encode($ha_device) . " (device_id: $device_id) " . $_SERVER['REQUEST_URI'], 'new_device');
        $components = SQLSelect("SELECT * FROM ha_components WHERE HA_DEVICE_ID=" . (int)$ha_device['ID']);

        $values = array();
        $definition = array();
        $definition_unfiltered = array();
        $total = count($components);
        for ($i = 0; $i < $total; $i++) {
            if ($components[$i]['LINKED_OBJECT'] == '') {
                $definition[$components[$i]['HA_COMPONENT']][$components[$i]['HA_OBJECT']] = json_decode($components[$i]['COMPONENT_PAYLOAD'], true);
            }
            $definition_unfiltered[$components[$i]['HA_COMPONENT']][$components[$i]['HA_OBJECT']] = json_decode($components[$i]['COMPONENT_PAYLOAD'], true);
            $values[$components[$i]['HA_COMPONENT']][$components[$i]['HA_OBJECT']] = $components[$i]['VALUE'];
        }

        $data = array();
        //check if battery operated
        if (isset($definition['sensor']['battery'])) {
            $data['properties']['battery'] = 'batteryLevel';
            $data['settings']['batteryOperated'] = 1;
        }
        // check if link quality set
        if (isset($definition['sensor']['linkquality'])) {
            $data['settings']['aliveTimeout'] = 2 * 24; // 2 days
            $data['methods']['linkquality'] = 'keepAlive';
        }

        // --------------------------------

        if (is_array($data['properties'])) {
            foreach ($data['properties'] as $k => $v) {
                $prop = SQLSelectOne("SELECT * FROM ha_components WHERE HA_DEVICE_ID=" . $device_id . " AND HA_OBJECT='" . $k . "'");
                if (!isset($prop['ID'])) {
                    continue;
                }
                $prop['LINKED_OBJECT'] = $linked_object;
                if (is_array($v)) {
                    foreach ($v as $key => $value) {
                        $prop[$key] = $value;
                    }
                } else {
                    $prop['LINKED_PROPERTY'] = $v;
                }
                SQLUpdate('ha_components', $prop);
                addLinkedProperty($prop['LINKED_OBJECT'], $prop['LINKED_PROPERTY'], $this->name);
            }
        }

        if (is_array($data['methods'])) {
            foreach ($data['methods'] as $k => $v) {
                $prop = SQLSelectOne("SELECT * FROM ha_components WHERE HA_DEVICE_ID=" . $device_id . " AND HA_OBJECT='" . $k . "'");
                if (!isset($prop['ID'])) {
                    continue;
                }
                $prop['LINKED_OBJECT'] = $linked_object;
                $prop['LINKED_METHOD'] = $v;
                SQLUpdate('ha_components', $prop);
            }
        }

        if (is_array($data['settings'])) {
            foreach ($data['settings'] as $k => $v) {
                sg($linked_object . '.' . $k, $v);
            }
        }


    }

    function checkDeviceType($device_id, $exclude_taken = false)
    {
        if (!$device_id) return false;
        $ha_device = SQLSelectOne("SELECT * FROM ha_devices WHERE ID=" . (int)$device_id);
        $this->log("Checking device type for " . json_encode($ha_device) . " (device_id: $device_id) " . $_SERVER['REQUEST_URI'], 'checkdevicetype');

// find by model
        if (!$exclude_taken) {
            $models = array();
            require DIR_MODULES . 'ha_discovery/known_devices.inc.php';
            foreach ($models as $model => $data) {
                if (is_integer(strpos(strtolower($ha_device['MODEL']), strtolower($model)))) {
                    return $data;
                }
            }
        }
        $data = false;
// find by components
        /*
        if ($exclude_taken) {
        $components = SQLSelect("SELECT * FROM ha_components WHERE HA_DEVICE_ID=" . (int)$ha_device['ID'] . " AND LINKED_OBJECT=''");
        } else {
        $components = SQLSelect("SELECT * FROM ha_components WHERE HA_DEVICE_ID=" . (int)$ha_device['ID']);
        }
        */
        $components = SQLSelect("SELECT * FROM ha_components WHERE HA_DEVICE_ID=" . (int)$ha_device['ID']);
        $values = array();
        $definition = array();
        $definition_unfiltered = array();
        $total = count($components);
        for ($i = 0; $i < $total; $i++) {
            if (!$exclude_taken || $components[$i]['LINKED_OBJECT'] == '') {
                $definition[$components[$i]['HA_COMPONENT']][$components[$i]['HA_OBJECT']] = json_decode($components[$i]['COMPONENT_PAYLOAD'], true);
            }
            $definition_unfiltered[$components[$i]['HA_COMPONENT']][$components[$i]['HA_OBJECT']] = json_decode($components[$i]['COMPONENT_PAYLOAD'], true);
            $values[$components[$i]['HA_COMPONENT']][$components[$i]['HA_OBJECT']] = $components[$i]['VALUE'];
        }
        $device_type = '';
        if (!$device_type && isset($definition['climate_setpoint']['climate_setpoint'])) {
            //Thermostat
            $device_type = 'thermostat';
            $data = array($device_type => array('properties' => array('climate_setpoint' => 'currentTargetValue')));
            if ($values['climate_setpoint']['climate_setpoint']) {
                $data[$device_type]['settings']['normalTargetValue'] = $values['climate_setpoint']['climate_setpoint'];
                $data[$device_type]['settings']['ecoTargetValue'] = (float)($values['climate_setpoint']['climate_setpoint']) - 2;
            }
            $data[$device_type]['settings']['status'] = 1;
            if (isset($definition['climate_temperature']['climate_temperature'])) {
                $data[$device_type]['properties']['climate_temperature'] = 'value';
            }
            if (isset($definition['climate_mode']['climate_mode'])) {
                $data[$device_type]['properties']['climate_mode'] = array(
                    'LINKED_PROPERTY' => 'disabled',
                    'READ_CODE' => 'if ($value=="off") $value=1; else $value=0;',
                    'WRITE_CODE' => 'if ($value) $value="off"; else $value=gg($linked_object.".ncno")?"cool":"heat";'
                );
            }
        }
        if (!$device_type && isset($definition['sensor']['co2'])) {
            //CO2 sensor
            $device_type = 'sensor_co2';
            $data = array($device_type => array('properties' => array('co2' => 'value')));
        }
        if (!$device_type && isset($definition['binary_sensor']['occupancy'])) {
            //motion sensor
            $device_type = 'motion';
            $data = array(
                $device_type => array(
                    'properties' => array('occupancy' => 'status'),
                    'settings' => array('isPresenceSensor' => 1)
                )
            );
        }

        if (!$device_type && isset($definition['sensor']['action']) && isset($definition['sensor']['angle'])) {
            //vibration sensor
            $device_type = 'motion';
            $data = array(
                $device_type => array(
                    'methods' => array('action' => 'motionDetected')
                )
            );
        }

        if (!$device_type && isset($definition['binary_sensor']['contact'])) {
            //openclose sensor
            $device_type = 'openclose';
            $data = array($device_type => array('properties' => array('contact' => 'status')));
        }
        if (!$device_type && isset($definition['binary_sensor']['water_leak'])) {
            //leak sensor
            $device_type = 'leak';
            $data = array($device_type => array('properties' => array('water_leak' => 'status')));
        }
        if (!$device_type && isset($definition['sensor']['temperature']) && isset($definition['sensor']['humidity'])) {
            //temperature+humidity
            $device_type = 'sensor_temphum';
            $data = array(
                $device_type => array(
                    'properties' => array(
                        'temperature' => 'value',
                        'humidity' => 'valueHumidity'
                    )
                )
            );
        }
        if (!$device_type && isset($definition['sensor']['temperature'])) {
            //temperature
            $device_type = 'sensor_temp';
            $data = array(
                $device_type => array(
                    'properties' => array(
                        'temperature' => 'value',
                    )
                )
            );
        }
        if (!$device_type && isset($definition['sensor']['humidity'])) {
            //temperature
            $device_type = 'sensor_temp';
            $data = array(
                $device_type => array(
                    'properties' => array(
                        'humidity' => 'value',
                    )
                )
            );
        }
        if (!$device_type && isset($definition['sensor']['illuminance_lux'])) {
            //light sensor
            $device_type = 'sensor_light';
            $data = array($device_type => array('properties' => array('illuminance_lux' => 'value')));
            $data[$device_type]['settings']['unit'] = 'Lux';
        }
        if (!$device_type && isset($definition['sensor']['illuminance']) && !isset($definition_unfiltered['sensor']['illuminance_lux'])) {
            //light sensor
            $device_type = 'sensor_light';
            $data = array($device_type => array('properties' => array('illuminance' => 'value')));
        }
        if (!$device_type && isset($definition['sensor']['illuminance_raw']) && !isset($definition_unfiltered['sensor']['illuminance']) && !isset($definition_unfiltered['sensor']['illuminance_lux'])) {
            //light sensor
            $device_type = 'sensor_light';
            $data = array($device_type => array('properties' => array('illuminance_raw' => 'value')));
        }
        if (!$device_type && isset($definition['light']['light']) && isset($definition['light_rgb']['light_rgb'])) {
            //dimmer
            $device_type = 'rgb';
            $data = array(
                $device_type => array(
                    'properties' => array(
                        'light' => 'status',
                        'light_rgb' => 'color'
                    )
                )
            );
        }

        if (!$device_type && isset($definition['light']['light_l1']) && isset($definition['light_brightness']['light_l1_brightness'])) {
            //dimmer
            $device_type = 'dimmer';
            $data = array(
                $device_type => array(
                    'properties' => array(
                        'light_l1' => 'status',
                        'light_l1_brightness' => 'levelWork'
                    ),
                    'settings' => array('switchLevel' => 1)
                )
            );
            $data[$device_type]['settings']['minWork'] = 0;
            if (isset($definition['light_brightness']['light_l1_brightness']['brightness_scale'])) {
                $data[$device_type]['settings']['maxWork'] = $definition['light_brightness']['light_l1_brightness']['brightness_scale'];
            } else {
                $data[$device_type]['settings']['maxWork'] = 100;
            }
        }

        if (!$device_type && isset($definition['light']['light_l2']) && isset($definition['light_brightness']['light_l2_brightness'])) {
            //dimmer
            $device_type = 'dimmer';
            $data = array(
                $device_type => array(
                    'properties' => array(
                        'light_l2' => 'status',
                        'light_l2_brightness' => 'levelWork'
                    ),
                    'settings' => array('switchLevel' => 1)
                )
            );
            $data[$device_type]['settings']['minWork'] = 0;
            if (isset($definition['light_brightness']['light_l2_brightness']['brightness_scale'])) {
                $data[$device_type]['settings']['maxWork'] = $definition['light_brightness']['light_l2_brightness']['brightness_scale'];
            } else {
                $data[$device_type]['settings']['maxWork'] = 100;
            }
        }

        if (!$device_type && isset($definition['light']['light']) && isset($definition['light_brightness']['light_brightness'])) {
            //dimmer
            $device_type = 'dimmer';
            $data = array(
                $device_type => array(
                    'properties' => array(
                        'light' => 'status',
                        'light_brightness' => 'levelWork'
                    ),
                    'settings' => array('switchLevel' => 1)
                )
            );
            $data[$device_type]['settings']['minWork'] = 0;
            if (isset($definition['light_brightness']['light_brightness']['brightness_scale'])) {
                $data[$device_type]['settings']['maxWork'] = $definition['light_brightness']['light_brightness']['brightness_scale'];
            } else {
                $data[$device_type]['settings']['maxWork'] = 100;
            }
        }
        if (!$device_type && isset($definition['light']['light'])) {
            //light / relay
            $device_type = 'relay';
            $data = array(
                $device_type => array(
                    'properties' => array(
                        'light' => 'status',
                    )
                )
            );
            $data[$device_type]['properties']['loadType'] = 'light';
        }

        $switch_types = array(
            'switch',
            'switch_1',
            'switch_2',
            'switch_3',
            'switch_4',
            'switch_l1',
            'switch_l2',
            'switch_l3',
            'switch_l4',
            'switch_left',
            'switch_center',
            'switch_right',
            'switch_top_left',
            'switch_top_center',
            'switch_top_right',
            'switch_bottom_left',
            'switch_bottom_right');

        foreach ($switch_types as $switch_type) {
            if (!$device_type && isset($definition['switch'][$switch_type])) {
                if ($switch_type == 'switch') {
                    // skipping 'switch' if there are any other types of switches
                    $result = array_intersect_key($definition_unfiltered['switch'], array_flip($switch_types));
                    if (count($result) > 1) {
                        continue;
                    }
                }
                //light / relay
                $device_type = 'relay';
                $data = array(
                    $device_type => array(
                        'properties' => array(
                            $switch_type => 'status',
                        )
                    )
                );
            }
        }

        if (!$device_type && isset($definition['sensor']['action'])) {
            $device_type = 'button';
            $data = array(
                $device_type => array(
                    'properties' => array(),
                    'methods' => array(
                        'action' => 'pressed'
                    )
                )
            );
        }

        if (!$device_type && isset($definition['device_automation']['action_single'])) {
            $device_type = 'button';
            $data = array(
                $device_type => array(
                    'properties' => array(),
                    'methods' => array(
                        'action_single' => 'pressed'
                    )
                )
            );
        }
        if (!$device_type && isset($definition['sensor']['pressure'])) {
            //pressure sensor
            $device_type = 'sensor_pressure';
            $data = array($device_type => array('properties' => array('pressure' => 'value')));
        }
        if (!$device_type && isset($definition['sensor']['power'])) {
            //power sensor
            $device_type = 'sensor_power';
            $data = array($device_type => array('properties' => array('power' => 'value')));
        }
        if (!$device_type && isset($definition['sensor']['energy']) && !isset($definition_unfiltered['sensor']['power'])) {
            //power sensor
            $device_type = 'sensor_power';
            $data = array($device_type => array('properties' => array('energy' => 'value')));
        }
        if (!$device_type && isset($definition['sensor']['current'])) {
            //current sensor
            $device_type = 'sensor_current';
            $data = array($device_type => array('properties' => array('current' => 'value')));
        }
        if (!$device_type && isset($definition['sensor']['voltage']) && !isset($definition_unfiltered['sensor']['battery'])) {
            //voltage sensor
            $device_type = 'sensor_voltage';
            $data = array($device_type => array('properties' => array('voltage' => 'value')));
        }


        //check if battery operated
        if ($device_type && is_array($data) && isset($definition['sensor']['battery'])) {
            $data[$device_type]['properties']['battery'] = 'batteryLevel';
            $data[$device_type]['settings']['batteryOperated'] = 1;
        }
        // check if link quality set
        if ($device_type && is_array($data) && isset($definition['sensor']['linkquality'])) {
            $data[$device_type]['settings']['aliveTimeout'] = 2 * 24; // 2 days
            $data[$device_type]['methods']['linkquality'] = 'keepAlive';
        }
        return $data;
    }


    function createDevice($device_id, $parent_simple_device_id = 0)
    {
        $this->log("Trying to create simple device for $device_id", 'new_device');
        include_once DIR_MODULES . 'devices/devices.class.php';
        $devices_module = new devices();
        $devices_module->setDictionary();
        if ($parent_simple_device_id) {
            $exclude_taken = true;
        } else {
            $exclude_taken = false;
        }
        $types = $this->checkDeviceType($device_id, $exclude_taken);
        if ($types == false) return;
        foreach ($types as $type => $details) {
            if (isset($devices_module->device_types[$type])) {

                $new_title = $devices_module->device_types[$type]['TITLE'] . ' 1';
                $new_title = preg_replace('/\(.+\)/', '', $new_title);
                $new_title = preg_replace('/\s+/', ' ', $new_title);
                $found_title = true;
                while ($found_title) {
                    $old_device = SQLSelectOne("SELECT ID FROM devices WHERE TITLE='" . DBSafe($new_title) . "'");
                    if (!$old_device['ID']) {
                        $found_title = false;
                        break;
                    } else {
                        $found_title = true;
                        if (preg_match('/(\d+)$/', $new_title, $m)) {
                            $idx = (int)$m[1];
                            $idx++;
                            $new_title = str_replace(' ' . $m[1], ' ' . $idx, $new_title);
                        }
                    }
                }
                $options = array('TITLE' => $new_title);
                if ($type != 'relay' && $type != 'dimmer') {
                    $options['PARENT_ID'] = $parent_simple_device_id;
                }
                $this->log("Adding new device: " . json_encode($options), 'new_device');
                if ($devices_module->addDevice($type, $options)) {
                    $added_device = SQLSelectOne("SELECT ID FROM devices WHERE TITLE='" . DBSafe($new_title) . "'");
                    SQLExec("UPDATE ha_devices SET SDEVICE_ID=" . (int)$added_device['ID'] . " WHERE SDEVICE_ID=0 AND ID=" . (int)$device_id);
                    $this->linkDevice($device_id, $added_device['ID'], $exclude_taken);
                    if (!$parent_simple_device_id) $parent_simple_device_id = $added_device['ID'];
                }
                if (isset($details['last']) && $details['last']) break;
            }
        }
        $this->createDevice($device_id, $parent_simple_device_id);
    }

    function canCreateDevice($device_id)
    {
        $device_type = $this->checkDeviceType((int)$device_id);
        if (is_array($device_type)) {
            $properties = SQLSelect("SELECT LINKED_OBJECT FROM ha_components WHERE HA_DEVICE_ID='" . (int)$device_id . "'");
            foreach ($properties as $prop) {
                if ($prop['LINKED_OBJECT'] != '') {
                    return false;
                }
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * ha_devices search
     *
     * @access public
     */
    function search_ha_devices(&$out)
    {
        require(dirname(__FILE__) . '/ha_devices_search.inc.php');
    }

    /**
     * ha_devices edit/add
     *
     * @access public
     */
    function edit_ha_devices(&$out, $id)
    {
        require(dirname(__FILE__) . '/ha_devices_edit.inc.php');
    }

    function checkPermitJoinsLinked()
    {
        $permit_joins = SQLSelect("SELECT ha_devices.*, ha_components.ID as HA_COMPONENT_ID, ha_components.COMPONENT_PAYLOAD FROM ha_components, ha_devices WHERE ha_components.HA_DEVICE_ID=ha_devices.ID AND (HA_OBJECT='permit_join' OR HA_OBJECT LIKE '%PermitJoin') ORDER BY ha_devices.TITLE");
        $total = count($permit_joins);
        for ($i = 0; $i < $total; $i++) {
            $component = SQLSelectOne("SELECT * FROM ha_components WHERE ID=" . (int)$permit_joins[$i]['HA_COMPONENT_ID']);
            if (!isset($component['ID']) || $component['LINKED_OBJECT'] != '') continue;

            // adding new pairing mode
            if ($component['MQTT_TOPIC'] != '') {
                $path = explode('/', $component['MQTT_TOPIC']);
                $object_name = 'pairing_' . $path[0];
                $object_title = '<#LANG_GENERAL_PAIRING_MODE#> ' . $path[0];
            } else {
                $object_name = 'pairing_' . $component['HA_OBJECT'];
                $object_title = '<#LANG_GENERAL_PAIRING_MODE#> ' . $component['HA_OBJECT'];
            }
            if (addClassObject("OperationalModes", $object_name)) {
                sg($object_name . '.title', $object_title);
                sg($object_name . '.active', $component['VALUE']);
                $component['LINKED_OBJECT'] = $object_name;
                $component['LINKED_PROPERTY'] = 'active';
                SQLUpdate('ha_components', $component);
                addLinkedProperty($object_name, 'active', $this->name);
            }
        }
    }

    function unlink_ha_devices($id)
    {
        $rec = SQLSelectOne("SELECT * FROM ha_devices WHERE ID='$id'");
        $this->log('Unlinking ha_device: ' . json_encode($rec, JSON_PRETTY_PRINT), 'delete');
        $permit_joins = SQLSelect("SELECT ha_devices.*, ha_components.ID as HA_COMPONENT_ID, ha_components.COMPONENT_PAYLOAD FROM ha_components, ha_devices WHERE ha_components.HA_DEVICE_ID=ha_devices.ID AND (HA_OBJECT='permit_join' OR HA_OBJECT LIKE '%PermitJoin') ORDER BY ha_devices.TITLE");
        if (isset($permit_joins[0])) {
            $total = count($permit_joins);
            for ($i = 0; $i < $total; $i++) {
                $c_payload = json_decode($permit_joins[$i]['COMPONENT_PAYLOAD'], true);
                if (isset($c_payload['state_topic'])) {
                    $topic = $c_payload['state_topic'] . '/force_remove';
                    $ieeeaddr = $rec['IEEEADDR'];
                    $send = array('v' => $ieeeaddr);
                    $payload_to_send = json_encode($send, JSON_NUMERIC_CHECK);
                    addToOperationsQueue('ha_discovery_queue', $topic, $payload_to_send, true);
                }
            }
        }
        $topics = SQLSelect("SELECT DISTINCT(SRC_TOPIC) FROM ha_components WHERE HA_DEVICE_ID=" . $rec['ID']);
        foreach ($topics as $topic) {
            $src_topic = $topic['SRC_TOPIC'];
            if ($src_topic != '') {
                $send = array('v' => '', 'r' => 1);
                $payload_to_send = json_encode($send, JSON_NUMERIC_CHECK);
                addToOperationsQueue('ha_discovery_queue', $src_topic, $payload_to_send, true);
            }
        }
        $this->delete_ha_devices($rec['ID']);
    }

    /**
     * ha_devices delete record
     *
     * @access public
     */
    function delete_ha_devices($id)
    {
        $rec = SQLSelectOne("SELECT * FROM ha_devices WHERE ID='$id'");
        $this->log('Deleting ha_device: ' . json_encode($rec, JSON_PRETTY_PRINT), 'delete');
        // some action for related tables
        SQLExec("DELETE FROM ha_components WHERE HA_DEVICE_ID='" . $rec['ID'] . "'");
        SQLExec("DELETE FROM ha_devices WHERE ID='" . $rec['ID'] . "'");
    }

    /**
     * ha_components search
     *
     * @access public
     */
    function search_ha_components(&$out)
    {
        require(dirname(__FILE__) . '/ha_components_search.inc.php');
    }

    /**
     * ha_components edit/add
     *
     * @access public
     */
    function edit_ha_components(&$out, $id)
    {
        require(dirname(__FILE__) . '/ha_components_edit.inc.php');
    }

    function setValue($component_id, $value)
    {
        startMeasure('ha_setValue');
        $component_rec = SQLSelectOne("SELECT * FROM ha_components WHERE ID=" . (int)$component_id);
        $component_type = $component_rec['HA_COMPONENT'];
        $payload = json_decode($component_rec['COMPONENT_PAYLOAD'], true);
        $command_topic = isset($payload['command_topic']) ? $payload['command_topic'] : '';
        if (!$command_topic) {
            //$this->log("Command topic not set to update value of component:\n" . json_encode($payload, JSON_PRETTY_PRINT), 'error');
            endMeasure('ha_setValue');
            return false;
        }
        $schema = isset($payload['schema']) ? $payload['schema'] : 'default';
        $payload_on = isset($payload['payload_on']) ? $payload['payload_on'] : 'ON';
        $payload_off = isset($payload['payload_off']) ? $payload['payload_on'] : 'OFF';

        if ($component_type == 'light' && $schema == 'json') {
            $data = array();
            $data['state'] = (!$value || strtolower($value) == 'off') ? $payload_off : $payload_on;
        } elseif ($component_type == 'light_brightness' && $schema == 'json') {
            $data = array();
            $data['brightness'] = $value;
        } elseif (isset($payload['payload_on']) && $value) {
            $this->log("Sending payload_on = " . (is_bool($payload['payload_on']) ? ($payload['payload_on'] ? 'true' : 'false') : $payload['payload_on']), 'set');
            $data = $payload['payload_on'];
        } elseif (isset($payload['payload_off']) && !$value) {
            $this->log("Sending payload_off = " . (is_bool($payload['payload_off']) ? ($payload['payload_off'] ? 'true' : 'false') : $payload['payload_off']), 'set');
            $data = $payload['payload_off'];
        } else {
            $data = $value;
        }

        if (is_array($data)) {
            $send = array('v' => json_encode($data, JSON_NUMERIC_CHECK));
            $this->log("Sending to $command_topic: " . $send['v'], 'set');
        } else {
            $send = array('v' => $data);
            $this->log("Sending to $command_topic: " . (is_bool($send['v']) ? ($send['v'] ? 'true' : 'false') : $send['v']), 'set');
        }

        $payload_to_send = json_encode($send, JSON_NUMERIC_CHECK);
        addToOperationsQueue('ha_discovery_queue', $command_topic, $payload_to_send, true);

        if (isset($this->config['DEBUG_MODE']) && $this->config['DEBUG_MODE']) {
            $hist = array();
            $hist['HA_DEVICE_ID'] = $component_rec['HA_DEVICE_ID'];
            $hist['HA_COMPONENT_ID'] = $component_rec['ID'];
            $hist['DESTINATION'] = 1;
            $hist['TOPIC'] = $command_topic;
            $hist['DATA_PAYLOAD'] = $send['v'];
            $hist['VALUE'] = $value;
            $hist['UPDATED'] = date('Y-m-d H:i:s');
            SQLInsert('ha_history', $hist);

            $keep_total = 20;
            $tmp = SQLSelect("SELECT ID FROM ha_history WHERE HA_COMPONENT_ID=" . $hist['HA_COMPONENT_ID'] . " ORDER BY ID DESC LIMIT $keep_total");
            if (count($tmp) == $keep_total) {
                SQLExec("DELETE FROM ha_history WHERE HA_COMPONENT_ID=" . $hist['HA_COMPONENT_ID'] . " AND ID<" . $tmp[$keep_total - 1]['ID']);
            }
        }
        endMeasure('ha_setValue');

    }

    function propertySetHandle($object, $property, $value)
    {
        $this->log("propertySetHandle($object, $property, $value)", 'set');
        $this->getConfig();
        $table = 'ha_components';
        $properties = SQLSelect("SELECT ID, LINKED_OBJECT, LINKED_PROPERTY, WRITE_CODE FROM $table WHERE LINKED_OBJECT LIKE '" . DBSafe($object) . "' AND LINKED_PROPERTY LIKE '" . DBSafe($property) . "'");
        $total = count($properties);
        $original_value = $value;
        if ($total) {
            for ($i = 0; $i < $total; $i++) {
                $value = $original_value;
                $linked_object = $properties[$i]['LINKED_OBJECT'];
                $linked_property = $properties[$i]['LINKED_PROPERTY'];
                $this->log("Setting value $value to ID " . $properties[$i]['ID'], 'set');
                if ($properties[$i]['WRITE_CODE'] != '') {
                    setEvalCode($properties[$i]['WRITE_CODE']);
                    eval($properties[$i]['WRITE_CODE']);
                    setEvalCode();
                }
                $this->setValue($properties[$i]['ID'], $value);
            }
        }
    }

    function processCycle()
    {
        $this->getConfig();
        //to-do
    }

    function processSubscription($event, &$details)
    {
        if ($event == 'DEVICE_DELETED') {
            $this->getConfig();
            $device_id = $details['device_id'];
            $this->log('DELETE_DEVICE received: ' . json_encode($details, JSON_PRETTY_PRINT), 'delete');
            if ($device_id && $this->config['UNPAIR_DEVICES_AUTOMATICALLY']) {
                $ha_device = SQLSelectOne("SELECT ID FROM ha_devices WHERE SDEVICE_ID=" . (int)$device_id);
                if (isset($ha_device['ID'])) {
                    $this->unlink_ha_devices($ha_device['ID']);
                }
            }
        }
    }

    /**
     * Install
     *
     * Module installation routine
     *
     * @access private
     */
    function install($data = '')
    {
        subscribeToEvent($this->name, 'DEVICE_DELETED');
        parent::install();

        $components = SQLSelect("SELECT ha_components.LINKED_OBJECT, ha_components.HA_DEVICE_ID, ha_devices.SDEVICE_ID, devices.ID as DEVICE_ID FROM ha_devices, ha_components LEFT JOIN devices ON ha_components.LINKED_OBJECT=devices.LINKED_OBJECT WHERE ha_components.HA_DEVICE_ID=ha_devices.ID AND ha_components.LINKED_OBJECT!='' AND ha_devices.SDEVICE_ID=0 AND devices.ID>0 ORDER BY devices.ID");
        $total = count($components);
        for ($i = 0; $i < $total; $i++) {
            SQLExec("UPDATE ha_devices SET SDEVICE_ID=" . (int)$components[$i]['DEVICE_ID'] . " WHERE SDEVICE_ID=0 AND ha_devices.ID=" . $components[$i]['HA_DEVICE_ID']);
        }

    }

    /**
     * Uninstall
     *
     * Module uninstall routine
     *
     * @access public
     */
    function uninstall()
    {
        SQLExec('DROP TABLE IF EXISTS ha_devices');
        SQLExec('DROP TABLE IF EXISTS ha_components');
        parent::uninstall();
    }

    function log($message, $file = '')
    {
        $this->getConfig();
        if ($file != '') {
            $file = 'ha_discovery_' . $file;
        } else {
            $file = 'ha_discovery';
        }
        if (isset($this->config['DEBUG_MODE']) && $this->config['DEBUG_MODE']) {
            DebMes($message, $file);
        }
    }

    /**
     * dbInstall
     *
     * Database installation routine
     *
     * @access private
     */
    function dbInstall($data)
    {
        /*
        ha_devices -
        ha_components -
        */
        $data = <<<EOD
 ha_devices: ID int(10) unsigned NOT NULL auto_increment
 ha_devices: TITLE varchar(100) NOT NULL DEFAULT ''
 ha_devices: IDENTIFIER varchar(255) NOT NULL DEFAULT ''
 ha_devices: IEEEADDR varchar(255) NOT NULL DEFAULT ''
 ha_devices: SDEVICE_ID int(10) NOT NULL DEFAULT '0'
 ha_devices: MODEL varchar(255) NOT NULL DEFAULT ''
 ha_devices: MANUFACTURER varchar(255) NOT NULL DEFAULT ''
 ha_devices: SW_VERSION varchar(255) NOT NULL DEFAULT ''
 ha_devices: HW_VERSION varchar(255) NOT NULL DEFAULT ''
 ha_devices: UPDATED datetime
 ha_devices: DEVICE_PAYLOAD text
 
 ha_components: ID int(10) unsigned NOT NULL auto_increment
 ha_components: HA_OBJECT varchar(255) NOT NULL DEFAULT ''
 ha_components: HA_COMPONENT varchar(255) NOT NULL DEFAULT ''
 ha_components: HA_DEVICE_ID int(10) NOT NULL DEFAULT '0'
 ha_components: LINKED_OBJECT varchar(100) NOT NULL DEFAULT ''
 ha_components: LINKED_PROPERTY varchar(100) NOT NULL DEFAULT ''
 ha_components: LINKED_METHOD varchar(100) NOT NULL DEFAULT ''
 ha_components: VALUE varchar(255) NOT NULL DEFAULT ''
 ha_components: MQTT_TOPIC varchar(255) NOT NULL DEFAULT ''
 ha_components: SRC_TOPIC varchar(255) NOT NULL DEFAULT '' 
 ha_components: COMPONENT_PAYLOAD text
 ha_components: DATA_PAYLOAD text
 ha_components: READ_CODE text
 ha_components: WRITE_CODE text
 ha_components: UPDATED datetime
 
 ha_history: ID int(10) unsigned NOT NULL auto_increment
 ha_history: HA_DEVICE_ID int(10) NOT NULL DEFAULT '0'
 ha_history: HA_COMPONENT_ID int(10) NOT NULL DEFAULT '0'
 ha_history: DESTINATION int(3) NOT NULL DEFAULT '0'
 ha_history: TOPIC varchar(255) NOT NULL DEFAULT ''
 ha_history: VALUE varchar(255) NOT NULL DEFAULT ''
 ha_history: DATA_PAYLOAD text
 ha_history: UPDATED datetime
 
EOD;
        parent::dbInstall($data);
    }
// --------------------------------------------------------------------
}
/*
*
* TW9kdWxlIGNyZWF0ZWQgT2N0IDExLCAyMDI0IHVzaW5nIFNlcmdlIEouIHdpemFyZCAoQWN0aXZlVW5pdCBJbmMgd3d3LmFjdGl2ZXVuaXQuY29tKQ==
*
*/
