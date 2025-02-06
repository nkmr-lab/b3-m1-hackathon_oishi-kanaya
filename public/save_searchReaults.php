<?php

// 環境変数をロードする関数
function loadEnv($filePath) {
    if (!file_exists($filePath)) {
        throw new Exception(".env file not found at $filePath");
    }
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        list($key, $value) = explode("=", $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"");
        putenv("$key=$value");
    }
}

// JSONファイルに提案結果を保存する関数
function saveSearchResults($data) {
    $filePath = __DIR__ . '/results_search.json';

    // 既存のデータを読み込む
    if (file_exists($filePath)) {
        $existingData = json_decode(file_get_contents($filePath), true);
    } else {
        $existingData = [];
    }

    // 新しいデータを追加
    $existingData[] = $data;

    // JSON形式で保存
    file_put_contents($filePath, json_encode($existingData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// 保存された履歴を取得する関数
function getSearchImage() {
    $filePath = __DIR__ . '/price_suggestions.json';

    if (file_exists($filePath)) {
        return json_decode(file_get_contents($filePath), true);
    }

    return [];
}
