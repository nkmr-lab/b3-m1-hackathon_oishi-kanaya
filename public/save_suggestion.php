<?php

// JSONファイルに提案結果を保存する関数
function savePriceSuggestion($data) {
    $filePath = __DIR__ . '/price_suggestions.json';

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
function getPriceSuggestions() {
    $filePath = __DIR__ . '/price_suggestions.json';

    if (file_exists($filePath)) {
        return json_decode(file_get_contents($filePath), true);
    }

    return [];
}
