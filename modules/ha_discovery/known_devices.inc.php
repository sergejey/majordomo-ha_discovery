<?php

$models = array(
    'RTCGQ01LM' => array(                    // Xiaomi motion sensor RTCGQ01LM
        'motion' => array(
            'properties' => array(
                'occupancy' => 'status',
                'battery' => 'batteryLevel',
            ),
            'settings' => array(
                'batteryOperated' => 1,
            )
        )
    )
);