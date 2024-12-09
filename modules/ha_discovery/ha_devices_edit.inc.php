<?php
/*
* @version 0.1 (wizard)
*/
if ($this->owner->name == 'panel') {
    $out['CONTROLPANEL'] = 1;
}

$out['FILTER'] = 1;

$table_name = 'ha_devices';
$rec = SQLSelectOne("SELECT * FROM $table_name WHERE ID='$id'");
if ($this->mode == 'update') {
    $ok = 1;
    // step: default
    if ($this->tab == '') {
        //updating '<%LANG_TITLE%>' (varchar, required)
        $rec['TITLE'] = gr('title');
        if ($rec['TITLE'] == '') {
            $out['ERR_TITLE'] = 1;
            $ok = 0;
        }
    }
    // step: data
    if ($this->tab == 'data') {
    }
    //UPDATING RECORD
    if ($ok) {
        if (isset($rec['ID'])) {
            SQLUpdate($table_name, $rec); // update
        } else {
            $new_rec = 1;
            $rec['ID'] = SQLInsert($table_name, $rec); // adding new record
        }
        $out['OK'] = 1;
    } else {
        $out['ERR'] = 1;
    }
}
// step: default
if ($this->tab == '') {
}
// step: data
if ($this->tab == 'data') {
}
if ($this->tab == 'data') {
    //dataset2
    $new_id = 0;
    $delete_id = gr('delete_id', 'int');
    if ($delete_id) {
        SQLExec("DELETE FROM ha_components WHERE ID='" . (int)$delete_id . "'");
    }

    $component_id = gr('component_id', 'int');
    $properties = SQLSelect("SELECT * FROM ha_components WHERE HA_DEVICE_ID='" . $rec['ID'] . "' ORDER BY HA_COMPONENT, HA_OBJECT");


    if (!$component_id) {

        if ($this->canCreateDevice($rec['ID'])) {
            if ($this->mode == 'link_device') {
                $this->linkDevice($rec['ID'], gr('device_id', 'int'));
                $this->redirect("?id=" . $rec['ID'] . "&view_mode=" . $this->view_mode . "&tab=" . $this->tab . "&ok=1");
            }
            if ($this->mode == 'create_device') {
                $this->createDevice($rec['ID']);
                $this->redirect("?id=" . $rec['ID'] . "&view_mode=" . $this->view_mode . "&tab=" . $this->tab . "&ok=1");
            }
            $out['CAN_CREATE_DEVICE'] = 1;
            $device_type = $this->checkDeviceType($rec['ID']);
            $types = array("'impossible_device_type'");
            foreach ($device_type as $type => $details) {
                $types[] = "'$type'";
            }
            $devices = SQLSelect("SELECT ID, TITLE FROM devices WHERE TYPE IN (" . implode(',', $types) . ")");
            if (isset($devices[0])) {
                $out['DEVICES_TO_LINK'] = $devices;
            }
        } else {
            $seen_objects = array();
            $linked_devices = array();
            foreach ($properties as $prop) {
                if ($prop['LINKED_OBJECT'] != '' && !isset($seen_objects[$prop['LINKED_OBJECT']])) {
                    $seen_objects[$prop['LINKED_OBJECT']] = 1;
                    $sdevice = SQLSelectOne("SELECT ID, LINKED_OBJECT, PARENT_ID FROM devices WHERE LINKED_OBJECT='" . $prop['LINKED_OBJECT'] . "'");
                    if (isset($sdevice['ID'])) {
                        $linked_devices[] = array('ID' => $sdevice['ID'], 'PARENT_ID' => $sdevice['PARENT_ID'], 'PROP_ID' => $prop['ID']);
                    }
                }
            }
            if (count($linked_devices) > 0) {

                usort($linked_devices, function ($a, $b) {
                    return (int)($a['PARENT_ID'] > $b['PARENT_ID']);
                });
                $out['LINKED_DEVICES'] = $linked_devices;
            }
        }
    } else {
        if ($this->mode == 'delete_device') {
            $component = SQLSelectOne("SELECT * FROM ha_components WHERE ID=" . (int)$component_id);
            $sdevice = SQLSelectOne("SELECT * FROM devices WHERE LINKED_OBJECT='" . $component['LINKED_OBJECT'] . "'");
            if ($component['ID'] && $sdevice['ID']) {
                SQLExec("UPDATE ha_components SET LINKED_OBJECT='', LINKED_PROPERTY='', LINKED_METHOD='' WHERE LINKED_OBJECT='" . $component['LINKED_OBJECT'] . "'");
                SQLExec("UPDATE ha_devices SET SDEVICE_ID=0 WHERE SDEVICE_ID=" . $sdevice['ID']);
                include_once DIR_MODULES . 'devices/devices.class.php';
                $dev_module = new devices();
                $dev_module->delete_devices($sdevice['ID']);
                $this->redirect("?id=" . $rec['ID'] . "&view_mode=" . $this->view_mode . "&tab=" . $this->tab . "&ok=1");
            }

        }
    }

    $total = count($properties);
    for ($i = 0; $i < $total; $i++) {
        if ($properties[$i]['ID'] == $component_id) {
            $payload = json_decode($properties[$i]['COMPONENT_PAYLOAD'], true);
            if (isset($payload['command_topic']) && $payload['command_topic'] != '') {
                $out['CAN_SET'] = 1;
                if ($properties[$i]['HA_COMPONENT'] == 'select' && isset($payload['options'])) {
                    foreach ($payload['options'] as $option) {
                        $out['SELECT_OPTIONS'][] = array('VALUE' => $option);
                    }
                    $out['CAN_SET_SELECT'] = 1;
                }
            }
            foreach ($properties[$i] as $k => $v) {
                $out['COMPONENT_' . $k] = htmlspecialchars($v);
            }
            if ($this->mode == 'refresh_data') {
                $data = json_decode($properties[$i]['DATA_PAYLOAD'], true);
                $this->processComponentMessage($properties[$i], $data);
                $this->redirect("?id=" . $rec['ID'] . "&view_mode=" . $this->view_mode . "&tab=" . $this->tab . "&component_id=" . $component_id . "&ok=1");
            }
            if ($this->mode == 'update') {
                $old_linked_object = $properties[$i]['LINKED_OBJECT'];
                $old_linked_property = $properties[$i]['LINKED_PROPERTY'];
                $properties[$i]['LINKED_OBJECT'] = gr('linked_object', 'trim');
                $properties[$i]['LINKED_PROPERTY'] = gr('linked_property', 'trim');
                $properties[$i]['LINKED_METHOD'] = gr('linked_method', 'trim');
                $write_code = gr('write_code', 'trim');
                if ($write_code != '') {
                    $errors = php_syntax_error($write_code);
                    if (!$errors) {
                        $properties[$i]['WRITE_CODE'] = $write_code;
                    }
                } else {
                    $properties[$i]['WRITE_CODE'] = '';
                }
                $read_code = gr('read_code', 'trim');
                if ($read_code != '') {
                    $errors = php_syntax_error($read_code);
                    if (!$errors) {
                        $properties[$i]['READ_CODE'] = $read_code;
                    }
                } else {
                    $properties[$i]['READ_CODE'] = '';
                }
                SQLUpdate('ha_components', $properties[$i]);
                if ($properties[$i]['LINKED_OBJECT'] && $properties[$i]['LINKED_PROPERTY']) {
                    addLinkedProperty($properties[$i]['LINKED_OBJECT'], $properties[$i]['LINKED_PROPERTY'], $this->name);
                } elseif ($old_linked_object && $old_linked_property && function_exists('removeLinkedPropertyIfNotUsed')) {
                    removeLinkedPropertyIfNotUsed('ha_components', $old_linked_object, $old_linked_property, $this->name);
                }
                if ($out['CAN_SET'] && gr('new_value') !== '') {
                    $this->setValue($properties[$i]['ID'], gr('new_value'));
                }
                $this->redirect("?id=" . $rec['ID'] . "&view_mode=" . $this->view_mode . "&tab=" . $this->tab . "&component_id=" . $component_id . "&ok=1");
            }
        }
        $properties[$i]['UPDATED'] = getPassedText(strtotime($properties[$i]['UPDATED']));
    }
    $out['PROPERTIES'] = $properties;

    $history_qry = "ha_history.HA_DEVICE_ID=" . $rec['ID'];
    if ($out['COMPONENT_ID']) {
        $history_qry .= " AND ha_history.HA_COMPONENT_ID=" . (int)$out['COMPONENT_ID'];
    }
    $history = SQLSelect("SELECT ha_history.*, ha_components.HA_OBJECT, ha_components.HA_COMPONENT FROM ha_history LEFT JOIN ha_components ON ha_history.HA_COMPONENT_ID=ha_components.ID  WHERE $history_qry ORDER BY ha_history.UPDATED DESC, ha_history.ID DESC LIMIT 20");
    $total = count($history);
    for ($i = 0; $i < $total; $i++) {
        $out['HISTORY'] .= "<div>";
        if ($history[$i]['DESTINATION'] == 1) {
            $out['HISTORY'] .= '<i class="glyphicon glyphicon-export" style="color:blue"></i> ';
        } else {
            $out['HISTORY'] .= '<i class="glyphicon glyphicon-import" style="color:green"></i> ';
        }

        $updated_tm = strtotime($history[$i]['UPDATED']);
        $diff_str = getPassedText($updated_tm);

        $out['HISTORY'] .= $diff_str . ' &mdash; <b>' . $history[$i]['HA_OBJECT'] . ' (' . $history[$i]['HA_COMPONENT'] . ')</b>';
        $out['HISTORY'] .= "<br/><small>";
        $out['HISTORY'] .= "Topic: " . $history[$i]['TOPIC'] . "<br/>";
        if ($history[$i]['DESTINATION'] == 1) {
            //out
            $out['HISTORY'] .= '<b>' . $history[$i]['VALUE'] . '</b> &xrarr; ' . htmlspecialchars($history[$i]['DATA_PAYLOAD']) . "<br/>";
        } else {
            //in
            $out['HISTORY'] .= '' . htmlspecialchars($history[$i]['DATA_PAYLOAD']) . ' &xrarr; <b>' . $history[$i]['VALUE'] . "</b><br/>";
        }
        $out['HISTORY'] .= "</small></div>&nbsp;";
    }

    if (gr('ajax')) {
        header("Content-type:application/json");
        echo json_encode($out, JSON_NUMERIC_CHECK);
        exit;
    }

}
if (is_array($rec)) {
    foreach ($rec as $k => $v) {
        if (!is_array($v)) {
            $rec[$k] = htmlspecialchars($v);
        }
    }
}
outHash($rec, $out);
