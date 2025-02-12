<?php
// echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
// echo "post_max_size: " . ini_get('post_max_size') . "<br>";

require_once 'save_search.php';

// エラーレポートを有効にする（開発時のみ）
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 環境変数を参照
$openai_api_key = getenv("API_Key");

function encodeImageToBase64($imagePath) {
    if (empty($imagePath) || !file_exists($imagePath)) {
        return ''; // 空の文字列を返してエラーを防ぐ
    }
    $imageData = file_get_contents($imagePath);
    return base64_encode($imageData);
}


// 画像検索を処理する関数
function getSearchImage($imagePath, $label, $openai_api_key) {
    $base64Image = encodeImageToBase64($imagePath);
    echo "<img src='data:image/jpeg;base64,$base64Image' style='max-width:100%; height:auto;'>";
    if ($label == "character") {
        $prompt = "
以下の画像に写っているキャラクターについて特定してください。
どの作品の登場人物か分からない場合でも、見た目の特徴や衣装、持ち物、背景のヒントから可能性のあるキャラクターや出典を検索して教えてください。また、画像のアイテムの値段を推測して教えてください。
出力結果は以下の出力例の通りでキャラクター名とその出典名と推測された値段のみでお願いします．

出力例は以下になります
キャラクター名:キン肉マン,出典名:キン肉マン,推定価格:10000円
        ";
        //候補が複数ある場合はキャラクター名と出典名と推測された値段の3つを1セットとして最大5個まで提示してください．
    }
    //  else if ($label == "アイドル") {

    // } else if ($label == "電車")
    

    $data = [
        'model' => 'gpt-4o',
        'messages' => [
            [
                "role" => "user",
                "content" => [
                    [
                        "type" => "text",
                        "text" => $prompt
                    ],
                    [
                        "type" => "image_url",
                        "image_url" => [
                            "url" => "data:image/jpeg;base64,$base64Image"
                        ]
                    ],
                ],
            ]
        ],
        "max_tokens" => 1000,
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openai_api_key,
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        return ['error' => 'cURL Error: ' . curl_error($ch)];
    }

    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $responseData = json_decode($response, true);

    if ($http_status !== 200) {
        return ['error' => 'APIリクエストエラー: ' . $response];
    }

    if (isset($responseData['choices'][0]['message']['content'])) {
        $content = trim($responseData['choices'][0]['message']['content']);
        echo $content;
        preg_match('/キャラクター名[:：]?\s*([\p{L}0-9ぁ-んァ-ヶー・]+)\b/u', $content, $charMatches);
        $characterName = isset($charMatches[1]) ? trim($charMatches[1]) : '不明';

        // 出典名の抽出（半角・全角コロンの両方対応）
        preg_match('/出典名[:：]?\s*([\p{L}0-9ぁ-んァ-ヶー・]+)\b/u', $content, $sourceMatches);
        $sourceName = isset($sourceMatches[1]) ? trim($sourceMatches[1]) : '不明';

        // 価格の抽出（数値 + 円）
        preg_match('/(\d{1,6})\s?円/u', $content, $matches);
        $price = isset($matches[1]) ? number_format((int)$matches[1]) : '不明';

    
        if ($price) {
            return [
                'character' => $characterName,
                'source' => $sourceName,
                'price' => $price,
                'message' => $content
            ];
        } else {
            return [
                'error' => '価格が抽出できませんでした。',
                'message' => $content
            ];
        }
    }
}

// フォーム処理
$searchResultsCharacter = null;
$searchResultsSourceName = null;
$guessPrice = null;
$error = null;
$imagePath = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $label = $_POST['label'];

    // 画像のアップロード処理
    if (isset($_FILES['productImage']) && $_FILES['productImage']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/searchCharacter/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $tmpName = $_FILES['productImage']['tmp_name'];
        $originalName = basename($_FILES['productImage']['name']);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($extension, $allowedExtensions)) {
            $newFileName = uniqid('img_', true) . '.' . $extension;
            $destination = $uploadDir . $newFileName;

            if (move_uploaded_file($tmpName, $destination)) {
                $imagePath = $destination;
            } else {
                $error = '画像のアップロードに失敗しました。';
            }
        } else {
            $error = '許可されていないファイル形式です。';
        }
    }
    // 日本標準時を設定
    date_default_timezone_set('Asia/Tokyo');

    if (!$error) {

        if (empty($imagePath) || !file_exists($imagePath)) {
            $error = "画像のアップロードが正常に完了していません。";
        } else {
            $result = getSearchImage($imagePath, $label, $openai_api_key);
            if (isset($result['price'])) {
                $searchResultsCharacter = $result['character'];
                $searchResultsSourceName = $result['source'];
                $guessPrice = $result['price'];
    
                // 提案結果を保存
                $searchResultsData = [
                    'date' => date('Y-m-d H:i:s'),
                    'searchResultsCharacter' => $searchResultsCharacter,
                    'searchResultsSourceName' => $searchResultsSourceName,
                    'guessPrice' => $guessPrice,
                    'imagePath' => $imagePath,
                ];
    
                saveSearchResults($searchResultsData);
            } else {
                $error = $result['error'];
            }
        }    
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>画像検索 - フリマアプリ</title>
    <link rel="stylesheet" href="css/search_character.css">
    <!-- Font Awesome（オプション） -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Google Fonts（オプション） -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <h2>画像検索</h2>

        <?php if ($error): ?>
            <div class="error"><?php echo nl2br(htmlspecialchars($error)); ?></div>
        <?php endif; ?>

        <?php if ($guessPrice): ?>
            <div class="price-suggestion">
                <h3>キャラクター名: <?php echo htmlspecialchars($searchResultsCharacter); ?></h3>
                <h3>出典名: <?php echo htmlspecialchars($searchResultsSourceName); ?></h3>
                <h3>推定価格: <?php echo htmlspecialchars($guessPrice); ?> 円</h3>
                <p><?php //echo nl2br(htmlspecialchars($message)); ?></p>
                <div class="link-container">
                    <a href="saved_searchResults.php" class="history-link">検索履歴を見る</a>
                    <a href="search_character.php" class="history-link">画像検索に戻る</a>
                    <a href="start.html" class="back-btn">ホームに戻る</a>
                </div>
            </div>
        <?php else: ?>
            <form action="search_character.php" method="post" enctype="multipart/form-data" class="price-form">
                <div class="form-group">
                    <label for="productImage">検索画像:</label>
                    <input type="file" id="productImage" name="productImage" accept="image/*" required>
                </div>
                <div class="form-group">
                    <label for="label">種類:</label>
                    <select id="label" name="label" required>
                        <option value="character">キャラクター</option>
                        <option value="vehicle">乗り物</option>
                    </select>
                </div>
                <button type="submit" name="submit">画像を検索する</button>
                <div class="link-container">
                    <a href="saved_searchResults.php" class="history-link">検索履歴を見る</a>
                    <a href="buy.html" class="back-btn">購入者支援メニューに戻る</a>
                </div>
            </form>

        <?php endif; ?>
    </div>
</body>
</html>