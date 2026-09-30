<?php
/**
 * OneSignal Web Push Helper
 * Include this file to send push notifications to users.
 */

function send_onesignal_notification($headings, $contents, $external_ids = [], $url = '') {
    $app_id = get_setting('onesignal_app_id', '');
    $rest_key = get_setting('onesignal_rest_key', '');
    
    if (empty($app_id) || empty($rest_key)) {
        return false;
    }

    $fields = [
        'app_id' => $app_id,
        'headings' => ["en" => $headings],
        'contents' => ["en" => $contents],
    ];

    if (!empty($external_ids)) {
        $fields['include_external_user_ids'] = $external_ids;
    } else {
        $fields['included_segments'] = ['All'];
    }

    if (!empty($url)) {
        $fields['url'] = $url;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json; charset=utf-8',
        'Authorization: Basic ' . $rest_key
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}
