<?php

/**
 * マイグレーション 001: タグを分割カラム（tag1～tag10）に移行
 *
 * - postsテーブルにtag1～tag10カラムを追加
 * - 既存のtagsカラムからデータを移行
 * - インデックスを作成
 */

return [
    'name' => 'add_tag_columns',

    'up' => function (PDO $db) {
        // tag1～tag10カラムを追加（INTEGER型でタグIDを保存）
        for ($i = 1; $i <= 10; $i++) {
            $db->exec("ALTER TABLE posts ADD COLUMN tag{$i} INTEGER");
        }

        // tag1～tag10にインデックスを追加（整数なので高速）
        for ($i = 1; $i <= 10; $i++) {
            $db->exec("CREATE INDEX IF NOT EXISTS idx_posts_tag{$i} ON posts(tag{$i})");
        }

        // 既存のtagsカラムからtag1～tag10にデータを移行
        $stmt = $db->query("SELECT id, tags FROM posts WHERE tags IS NOT NULL AND tags != ''");
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($posts as $post) {
            $tags = $post['tags'];
            if (empty($tags)) {
                continue;
            }

            // カンマで分割し、前後のスペース/タブを除去
            $tagArray = array_map('trim', explode(',', $tags));
            $tagArray = array_filter($tagArray, function($tag) {
                return !empty($tag);
            });

            // 最大10個まで
            $tagArray = array_slice($tagArray, 0, 10);

            // タグ名をタグIDに変換
            $tagIds = [];
            foreach ($tagArray as $tagName) {
                // タグを取得または作成
                $stmt = $db->prepare("INSERT OR IGNORE INTO tags (name) VALUES (?)");
                $stmt->execute([$tagName]);

                $stmt = $db->prepare("SELECT id FROM tags WHERE name = ?");
                $stmt->execute([$tagName]);
                $tag = $stmt->fetch();

                if ($tag) {
                    $tagIds[] = (int)$tag['id'];
                }
            }

            // tag1～tag10にタグIDを保存
            if (!empty($tagIds)) {
                $updates = [];
                $params = [];
                for ($i = 0; $i < count($tagIds); $i++) {
                    $colNum = $i + 1;
                    $updates[] = "tag{$colNum} = ?";
                    $params[] = $tagIds[$i];
                }

                $params[] = $post['id'];
                $sql = "UPDATE posts SET " . implode(', ', $updates) . " WHERE id = ?";
                $updateStmt = $db->prepare($sql);
                $updateStmt->execute($params);
            }
        }

        // 注意: tagsカラムは後方互換性のため残す（将来的に削除可能）
        // $db->exec("ALTER TABLE posts DROP COLUMN tags"); // SQLiteではDROPがサポートされていない
    }
];
