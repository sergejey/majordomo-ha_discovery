<?php
chdir(dirname(__FILE__) . '/../');
include_once("./config.php");
include_once("./lib/loader.php");
include_once("./lib/threads.php");
set_time_limit(0);
$db = new mysql(DB_HOST, '', DB_USER, DB_PASSWORD, DB_NAME);
include_once("./load_settings.php");
include_once(DIR_MODULES . "control_modules/control_modules.class.php");
$ctl = new control_modules();

include_once(ROOT . "3rdparty/phpmqtt/phpMQTT.php");
include_once(DIR_MODULES . 'ha_discovery/ha_discovery.class.php');

$ha_discovery_module = new ha_discovery();
$ha_discovery_module->getConfig();

$client_name = "MajorDoMo HA Discovery";
$client_name = $client_name . ' (#' . uniqid() . ')';

if ($ha_discovery_module->config['MQTT_AUTH']) {
    $username = $ha_discovery_module->config['MQTT_USERNAME'];
    $password = $ha_discovery_module->config['MQTT_PASSWORD'];
}

if ($ha_discovery_module->config['MQTT_HOST']) {
    $host = $ha_discovery_module->config['MQTT_HOST'];
} else {
    $host = 'localhost';
}

if ($ha_discovery_module->config['MQTT_PORT']) {
    $port = $ha_discovery_module->config['MQTT_PORT'];
} else {
    $port = 1883;
}

if ($ha_discovery_module->config['BASE_TOPIC']) {
    $query = $ha_discovery_module->config['BASE_TOPIC'] . '/#';
} else {
    $query = 'homeassistant/#';
}

$subscribed_topics = array();

$mqtt_client = new Bluerhinos\phpMQTT($host, $port, $client_name);
if ($ha_discovery_module->config['MQTT_AUTH']) {
    $connect = $mqtt_client->connect(true, NULL, $username, $password);
    if (!$connect) {
        exit(1);
    }
} else {
    $connect = $mqtt_client->connect();
    if (!$connect) {
        exit(1);
    }
}

echo date("H:i:s") . " running " . basename(__FILE__) . PHP_EOL;


$latest_check = 0;
$checkEvery = 5; // poll every 5 seconds

$query_list = explode(',', $query);
$total = count($query_list);
echo date('H:i:s') . " Topics to watch: $query (Total: $total)\n";
for ($i = 0; $i < $total; $i++) {
    $path = trim($query_list[$i]);
    echo date('H:i:s') . " Path: $path\n";
    $topics[$path] = array("qos" => 0, "function" => "procmsg");
}

foreach ($topics as $k => $v) {
    echo date('H:i:s') . " Subscribing to: $k  \n";
    $rec = array($k => $v);
    $mqtt_client->subscribe($rec, 0);
}
$previousMillis = 0;

$check_subscriptions = 0;
while ($mqtt_client->proc()) {

    $queue = checkOperationsQueue('ha_discovery_queue');
    foreach ($queue as $mqtt_data) {
        $topic = $mqtt_data['DATANAME'];
        $data_value = json_decode($mqtt_data['DATAVALUE'], true);
        $value = $data_value['v'];
        $qos = 0;
        if (isset($data_value['q'])) {
            $qos = $data_value['q'];
        }
        $retain = 0;
        if (isset($data_value['r'])) {
            $retain = $data_value['r'];
        }
        if ($topic != '') {
            DebMes("Publishing to $topic: $value", 'ha_discovery_set');
            $mqtt_client->publish($topic, $value, $qos, $retain);
        }
    }

    if ($check_subscriptions < time()) {
        $check_subscriptions = time() + 1 * 60 * 60;
        $components = SQLSelect("SELECT MQTT_TOPIC FROM ha_components WHERE MQTT_TOPIC!=''");
        $total = count($components);
        for ($i = 0; $i < $total; $i++) {
            if (!isset($subscribed_topics[$components[$i]['MQTT_TOPIC']])) {
                DebMes("Subscribing to " . $components[$i]['MQTT_TOPIC'], 'ha_discovery_cycle');
                $subscribed_topics[$components[$i]['MQTT_TOPIC']] = 1;
                $rec = array($components[$i]['MQTT_TOPIC'] => array("qos" => 0, "function" => "procmsg"));
                $mqtt_client->subscribe($rec, 0);
            }
        }
    }

    $currentMillis = round(microtime(true) * 10000);

    if ($currentMillis - $previousMillis > 10000) {
        $previousMillis = $currentMillis;
        setGlobal((str_replace('.php', '', basename(__FILE__))) . 'Run', time(), 1);
        if (file_exists('./reboot') || isset($_GET['onetime'])) {
            $mqtt_client->close();
            $db->Disconnect();
            exit;
        }
    }
}


$mqtt_client->close();


function procmsg($topic, $msg)
{
    global $check_subscriptions;
    global $ha_discovery_module;
    global $latest_msg;
    $new_msg = time() . ' ' . $topic . ': ' . $msg;
    if ($latest_msg == $new_msg) return;

    if (preg_match('/\/config$/', $topic)) {
        $check_subscriptions = time() + 5;
    }

    if (function_exists('callAPI')) {
        callAPI('/api/module/ha_discovery', 'POST', array('topic' => $topic, 'msg' => $msg));
    } else {
        $ha_discovery_module->processMessage($topic, $msg);
    }
}

DebMes("Unexpected close of cycle: " . basename(__FILE__));
