<?php
require_once 'save_suggestion.php'; // 関数を読み込む

// 保存された履歴を取得
$savedSuggestions = getPriceSuggestions();

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>保存履歴 - フリマアプリ</title>
    <link rel="stylesheet" href="css/suggestion_results.css">
</head>
<body>
    <div class="container">
        <h2>提案履歴</h2>
        <?php if (!empty($savedSuggestions)): ?>
            <ul>
                <?php foreach ($savedSuggestions as $suggestion): ?>
                    <li>
                        <strong>保存日時:</strong> <?php echo htmlspecialchars($suggestion['date']); ?><br>
                        <img src="<?php echo htmlspecialchars($suggestion['imagePath']); ?>" alt="商品画像">
                        <p><strong>商品タイプ:</strong> <?php echo htmlspecialchars($suggestion['productType']); ?></p>
                        <p><strong>商品の状態:</strong> <?php echo htmlspecialchars($suggestion['productCondition']); ?></p>
                        <p><strong>使用回数:</strong> <?php echo htmlspecialchars($suggestion['usageCount']); ?></p>
                        <p><strong>元値:</strong> <?php echo htmlspecialchars($suggestion['originalPrice']); ?> 円</p>
                        <p><strong>商品説明:</strong><br><?php echo nl2br(htmlspecialchars($suggestion['productDescription'])); ?></p>
                        <p><strong>推奨価格:</strong> <?php echo htmlspecialchars($suggestion['suggestedPrice']); ?> 円</p>
                        <!-- <p><strong>メッセージ:</strong><br><?php echo nl2br(htmlspecialchars($suggestion['suggestionMessage'])); ?></p> -->
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>保存された履歴はありません。</p>
        <?php endif; ?>

        <a href="price_setting.php" class="back-btn">価格設定に戻る</a>
    </div>
</body>
</html>

