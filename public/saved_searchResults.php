<?php
require_once 'save_search.php'; // 関数を読み込む

// 保存された履歴を取得
$savedResults = getSearchImages();

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>検索履歴 - フリマアプリ</title>
    <link rel="stylesheet" href="css/suggestion_results.css">
</head>
<body>
    <div class="container">
        <h2>検索履歴</h2>
        <?php if (!empty($savedResults)): ?>
            <ul>
                <?php foreach ($savedResults as $result): ?>
                    <li>
                        <strong>保存日時:</strong> <?php echo htmlspecialchars($result['date']); ?><br>
                        <img src="<?php echo htmlspecialchars($result['imagePath']); ?>" alt="検索画像">
                        <p><strong>キャラクター名:</strong> <?php echo htmlspecialchars($result['searchResultsCharacter']); ?></p>
                        <p><strong>出典名:</strong> <?php echo htmlspecialchars($result['searchResultsSourceName']); ?></p>                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>保存された履歴はありません。</p>
        <?php endif; ?>

        <a href="search_character.php" class="back-btn">画像検索に戻る</a>
    </div>
</body>
</html>

