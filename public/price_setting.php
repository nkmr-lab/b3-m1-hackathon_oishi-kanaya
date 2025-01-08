<?php
// price_setting.php

// エラーレポートを有効にする（開発時のみ）
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// apikeyを持ってくる
function loadEnv($filePath) {
    if (!file_exists($filePath)) {
        throw new Exception(".env file not found at $filePath");
    }
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // キーと値を分割（"="で分割し、値の周囲のダブルクォートを削除）
        list($key, $value) = explode("=", $line, 2);
        // echo "こっちがkey $key";
        // echo "こっちがvalue $value";
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\""); // 前後の空白とダブルクォートを削除
        //  echo "こっちがkey $key";
        // echo "こっちがvalue $value";
        // 環境変数として設定
        putenv("$key=$value");

    }
}
// .envを読み込む
loadEnv(__DIR__ . "/api.env");

// 環境変数を参照
$openai_api_key = getenv("API_Key");
echo "Your API Key is: $openai_api_key";


// OpenAI APIキーを設定

// 価格提案を処理する関数
function getPriceSuggestion($productType, $productCondition, $usageCount, $originalPrice, $productDescription, $openai_api_key) {
    $prompt = "
以下の商品情報を基に、フリーマーケットで販売するにふさわしい適正価格を具体的な金額（円）で提案してください。商品の状況が「中古」の場合や使用回数が多い場合ははかなり値段を下げてください。回答は「XXXX円」の形式のみでお願いします。他の説明やテキストは不要です。
- 商品タイプ: " . ($productType === 'existing' ? '既存の商品' : '手作り') . "
- 商品の状態: " . $productCondition . "
- 使用回数: " . $usageCount . "
- 元値: " . $originalPrice . " 円
- 商品説明: " . $productDescription . "
";

    $data = [
        'model' => 'gpt-4', // 必要に応じて 'gpt-3.5-turbo' に変更
        'messages' => [
            ['role' => 'system', 'content' => 'あなたは優秀な販売価格アドバイザーです。以下の指示に従ってください。'],
            ['role' => 'user', 'content' => $prompt],
        ],
        'max_tokens' => 50, // 必要に応じて調整
        'temperature' => 0.5, // 必要に応じて調整
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openai_api_key,
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    // 開発中のみSSL検証を無効化（本番環境では有効にする）
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        return ['error' => 'cURL Error: ' . curl_error($ch)];
    }

    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $responseData = json_decode($response, true);

    if ($http_status !== 200) {
        // APIリクエストが失敗した場合
        return ['error' => 'APIリクエストエラー: ' . $response];
    }

    if (isset($responseData['choices'][0]['message']['content'])) {
        // レスポンスのデバッグ表示（開発時のみ）
        // file_put_contents('api_response.log', $response . PHP_EOL, FILE_APPEND);

        // 価格を抽出
        $content = trim($responseData['choices'][0]['message']['content']);
        preg_match('/(\d{1,6})\s?円/', $content, $matches);
        $price = isset($matches[1]) ? $matches[1] : null;
        if ($price) {
            return ['price' => $price, 'message' => $content];
        } else {
            // 価格が抽出できなかった場合、レスポンス全体を返す
            return ['error' => '価格が抽出できませんでした。レスポンス: ' . $content];
        }
    } else {
        // レスポンスに期待するデータが含まれていない場合、レスポンス全体を返す
        return ['error' => 'APIレスポンスに価格情報が含まれていません。レスポンス: ' . $response];
    }
}

// 価格提案の処理
$suggestedPrice = null;
$suggestionMessage = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    // フォームデータの取得
    $productType = $_POST['productType'];
    $productCondition = $_POST['productCondition'];
    $usageCount = $_POST['usageCount'];
    $originalPrice = $_POST['originalPrice'];
    $productDescription = $_POST['productDescription'];

    // 画像のアップロード処理（必要に応じて）
    if (isset($_FILES['productImage']) && $_FILES['productImage']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        $tmpName = $_FILES['productImage']['tmp_name'];
        $originalName = basename($_FILES['productImage']['name']);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

        // MIMEタイプの確認
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpName);
        finfo_close($finfo);
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];

        if (in_array($mimeType, $allowedMimeTypes)) {
            $newFileName = uniqid('img_', true) . '.' . $extension;
            $destination = $uploadDir . $newFileName;

            if (move_uploaded_file($tmpName, $destination)) {
                // 画像URLはプロンプトに含めない
                // 画像は必要に応じて保存するだけ
                // $imageUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/' . $destination;
            } else {
                $error = '画像のアップロードに失敗しました。';
            }
        } else {
            $error = '許可されていないファイル形式です。';
        }
    } else {
        $error = '画像がアップロードされていません。';
    }

    // 価格提案の取得
    if (!$error) {
        $result = getPriceSuggestion($productType, $productCondition, $usageCount, $originalPrice, $productDescription, $openai_api_key);
        if (isset($result['price'])) {
            $suggestedPrice = $result['price'];
            $suggestionMessage = $result['message'];
        } elseif (isset($result['error'])) {
            $error = $result['error'];
        } else {
            $error = '未知のエラーが発生しました。';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>適正価格設定 - フリマアプリ</title>
    <link rel="stylesheet" href="css/sell.css">
    <!-- Font Awesome（オプション） -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Google Fonts（オプション） -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <h2>適正価格設定</h2>

        <?php if ($error): ?>
            <div class="error"><?php echo nl2br(htmlspecialchars($error)); ?></div>
        <?php endif; ?>

        <?php if ($suggestedPrice): ?>
            <div class="price-suggestion">
                <h3>推奨販売価格: <?php echo htmlspecialchars($suggestedPrice); ?> 円</h3>
                <p><?php echo nl2br(htmlspecialchars($suggestionMessage)); ?></p>
                <!-- 実際の出品処理はここに追加 -->
            </div>
            <a href="sell.html" class="back-btn"><i class="fas fa-arrow-left"></i> 出品メニューに戻る</a>
        <?php else: ?>
            <form action="price_setting.php" method="post" enctype="multipart/form-data" class="price-form">
                <label for="productImage">商品画像:</label>
                <input type="file" id="productImage" name="productImage" accept="image/*" required>

                <label for="productType">商品タイプ:</label>
                <select id="productType" name="productType" required>
                    <option value="existing">既存の商品</option>
                    <option value="handmade">手作り</option>
                </select>

                <label for="productCondition">商品の状態:</label>
                <select id="productCondition" name="productCondition" required>
                    <option value="new">新品</option>
                    <option value="like_new">ほぼ新品</option>
                    <option value="used">中古</option>
                </select>

                <label for="usageCount">使用回数:</label>
                <input type="number" id="usageCount" name="usageCount" min="0" required>

                <label for="originalPrice">元値（円）:</label>
                <input type="number" id="originalPrice" name="originalPrice" min="0" required>

                <label for="productDescription">商品説明:</label>
                <textarea id="productDescription" name="productDescription" rows="4" required></textarea>

                <button type="submit" name="submit"><i class="fas fa-dollar-sign"></i> 価格を提案してもらう</button>
            </form>
            <a href="sell.html" class="back-btn"><i class="fas fa-arrow-left"></i> 出品メニューに戻る</a>
        <?php endif; ?>
    </div>
</body>
</html>






