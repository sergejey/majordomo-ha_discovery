<?php
/*
* @version 0.1 (wizard)
*/
global $session;
if ($this->owner->name == 'panel') {
    $out['CONTROLPANEL'] = 1;
}

$go_linked_object = gr('go_linked_object');
$go_linked_property = gr('go_linked_property');
if ($go_linked_object && $go_linked_property) {
    $tmp = SQLSelectOne("SELECT ID, HA_DEVICE_ID FROM ha_components WHERE LINKED_OBJECT = '" . DBSafe($go_linked_object) . "' AND LINKED_PROPERTY='" . DBSafe($go_linked_property) . "'");
    if ($tmp['ID']) {
        $this->redirect("?id=" . $tmp['ID'] . "&view_mode=edit_ha_devices&id=" . $tmp['HA_DEVICE_ID'] . "&tab=data&component_id=" . $tmp['ID']);
    }
}

$components = SQLSelect("SELECT DISTINCT(HA_OBJECT) FROM ha_components ORDER BY HA_OBJECT");
if (isset($components[0]['HA_OBJECT'])) {
    $out['COMPONENTS'] = $components;
}

$qry = "1";
// search filters

$search = gr('search');
if ($search != '') {
    $out['FILTER'] = 1;
    $qry .= " AND (ha_devices.TITLE LIKE '%" . DBSafe($search) . "%' OR ha_devices.MODEL LIKE '%" . DBSafe($search) . "%' OR ha_devices.IDENTIFIER LIKE '%" . DBSafe($search) . "%' OR ha_devices.MANUFACTURER LIKE '%" . DBSafe($search) . "%')";
    $out['SEARCH'] = $search;
    $out['SEARCH_URL'] = urlencode($search);
}

$ha_object = gr('ha_object');
if ($ha_object) {
    $out['FILTER'] = 1;
    $ids = array_map('current', SQLSelect("SELECT DISTINCT(HA_DEVICE_ID) FROM ha_components WHERE HA_OBJECT='" . DBSafe($ha_object) . "'"));
    $ids[] = 0;
    $qry .= " AND ha_devices.ID IN (" . implode(',', $ids) . ")";
    $out["HA_OBJECT"] = $ha_object;
}


$permit_joins = SQLSelect("SELECT ha_devices.*, ha_components.ID as HA_COMPONENT_ID, ha_components.VALUE FROM ha_components, ha_devices WHERE ha_components.HA_DEVICE_ID=ha_devices.ID AND (HA_OBJECT='permit_join' OR HA_OBJECT LIKE '%PermitJoin') ORDER BY ha_devices.TITLE");
if (isset($permit_joins[0])) {
    $total = count($permit_joins);
    $permit_join = gr('permit_join', 'int');
    for ($i = 0; $i < $total; $i++) {
        if ($permit_join && $permit_joins[$i]['ID'] == $permit_join) {
            $set = gr('set', 'int');
            $this->setValue($permit_joins[$i]['HA_COMPONENT_ID'], $set);
            sleep(3);
            $this->redirect("?id=" . $rec['ID'] . "&view_mode=" . $this->view_mode . "&tab=" . $this->tab . "&ok=1");
        }
    }
    $out['PERMIT_JOINS'] = $permit_joins;
}


// QUERY READY
global $save_qry;
if ($save_qry) {
    $qry = $session->data['ha_devices_qry'];
} else {
    $session->data['ha_devices_qry'] = $qry;
}
if (!$qry) $qry = "1";

$sortby = gr('sortby');

if (!$sortby && isset($session->data['ha_sortby'])) {
    $sortby = $session->data['ha_sortby'];
} elseif ($sortby) {
    $session->data['ha_sortby'] = $sortby;
}

if ($sortby == 'ID') {
    $sortby_ha_devices = "ha_devices.ID DESC";
} elseif ($sortby == 'IDENTIFIER') {
    $sortby_ha_devices = "ha_devices.IDENTIFIER";
} elseif ($sortby == 'IEEEADDR') {
    $sortby_ha_devices = "ha_devices.IEEEADDR";
} elseif ($sortby == 'UPDATED') {
    $sortby_ha_devices = "ha_devices.UPDATED DESC";
} elseif ($sortby == 'TITLE') {
    $sortby_ha_devices = "ha_devices.TITLE, ha_devices.ID";
} else {
    $sortby_ha_devices = "ha_devices.TITLE, ha_devices.ID";
}

$out['SORTBY'] = $sortby_ha_devices;
// SEARCH RESULTS
$res = SQLSelect("SELECT * FROM ha_devices WHERE $qry ORDER BY " . $sortby_ha_devices);
if ($res[0]['ID']) {
    //paging($res, 100, $out); // search result paging
    $total = count($res);
    for ($i = 0; $i < $total; $i++) {
        $res[$i]['DEVICE']='';
        // some action for every record if required
        $components = SQLSelect("SELECT * FROM ha_components WHERE HA_DEVICE_ID=" . $res[$i]['ID'] . " ORDER BY HA_OBJECT");
        $seen_device = array();
        foreach ($components as $k => $v) {
            $res[$i]['DATA'] .= $v['HA_OBJECT'] . ': ' . $v['VALUE'] . '; ';
            if ($v['LINKED_OBJECT'] != '') {
                $dev_rec = SQLSelectOne("SELECT ID, TITLE FROM devices WHERE LINKED_OBJECT='" . $v['LINKED_OBJECT'] . "'");
                if ($dev_rec['ID']) {
                    $device_id = $dev_rec['ID'];
                    if (!$seen_device[$dev_rec['ID']]) {
                        $seen_device[$dev_rec['ID']] = 1;
                        $res[$i]['DEVICE'] .= '<a href="/panel/devices/' . $dev_rec['ID'] . '.html?tab=settings">' . $dev_rec['TITLE'] . "</a><br/>";
                    }

                }
            }
        }
        $updated_tm = strtotime($res[$i]['UPDATED']);
        $res[$i]['UPDATED'] = getPassedText($updated_tm);
    }
    $out['RESULT_TOTAL'] = count($res);
    $out['RESULT'] = $res;
}

if (gr('ajax')) {
    header("Content-type:application/json");
    echo json_encode($res, JSON_NUMERIC_CHECK);
    exit;
}

