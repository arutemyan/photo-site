<?php

/**
 * マイグレーション 007: postsテーブルの統合
 *
 * group_postsのデータをpostsテーブルに統合：
 * - posts.post_typeカラムを追加（0=single, 1=group）
 * - group_postsのデータをpostsにコピー（新IDで自動採番）
 * - group_post_imagesを新しいpost_idで複製
 * - group_postsテーブルを削除
 *
 * 対象DB: gallery.db
 */

use App\Database\DatabaseHelper;
use App\Database\MigrationHelper;

return [
    'name' => 'unify_posts_table',

    'up' => function (PDO $db, MigrationHelper $helper) {
        $driver = DatabaseHelper::getDriver($db);
        $intType = DatabaseHelper::getIntegerType($db);

        error_log("Migration 007: Starting posts table unification for driver: {$driver}");

        // ステップ1: posts.post_typeカラムを追加
        if ($helper->addColumnIfNotExists($db, 'posts', 'post_type', "{$intType} DEFAULT 0 NOT NULL")) {
            error_log("Migration 007: Added post_type column to posts table");
        }

        // ステップ2: group_postsテーブルが存在するかチェック
        if (!$helper->tableExists($db, 'group_posts')) {
            error_log("Migration 007: group_posts table does not exist, skipping migration");
            return;
        }

        // ステップ3: group_postsのレコード数を確認
        $stmt = $db->query("SELECT COUNT(*) as count FROM group_posts");
        $groupPostsCount = $stmt->fetchColumn();
        error_log("Migration 007: Found {$groupPostsCount} group_posts records to migrate");

        if ($groupPostsCount === 0) {
            error_log("Migration 007: No group_posts to migrate, dropping table");
            $db->exec("DROP TABLE group_posts");
            return;
        }

        // ステップ4: トランザクション開始
        error_log("Migration 007: Starting transaction");
        $db->exec("BEGIN TRANSACTION");

        try {
            // ステップ5: 一時マッピングテーブルを作成
            error_log("Migration 007: Creating temporary mapping table");

            if ($driver === 'sqlite') {
                $db->exec("CREATE TEMP TABLE group_posts_id_mapping (old_id INTEGER, new_id INTEGER)");
            } elseif ($driver === 'mysql') {
                $db->exec("CREATE TEMPORARY TABLE group_posts_id_mapping (old_id INT, new_id INT)");
            } elseif ($driver === 'postgresql') {
                $db->exec("CREATE TEMP TABLE group_posts_id_mapping (old_id INTEGER, new_id INTEGER)");
            }

            // ステップ6: group_postsのデータを取得
            error_log("Migration 007: Fetching group_posts data");
            $stmt = $db->query("
                SELECT id, title, detail, is_sensitive, is_visible, sort_order, created_at, updated_at,
                       tag1, tag2, tag3, tag4, tag5, tag6, tag7, tag8, tag9, tag10
                FROM group_posts ORDER BY id
            ");
            $groupPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // ステップ7: group_postsのデータをpostsにINSERTし、マッピングを記録
            error_log("Migration 007: Inserting group_posts into posts table");
            $insertStmt = $db->prepare("
                INSERT INTO posts (post_type, title, tags, detail, is_sensitive, is_visible, sort_order, created_at, updated_at,
                                   tag1, tag2, tag3, tag4, tag5, tag6, tag7, tag8, tag9, tag10)
                VALUES (1, ?, '', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $mappingStmt = $db->prepare("INSERT INTO group_posts_id_mapping (old_id, new_id) VALUES (?, ?)");

            $migratedCount = 0;
            foreach ($groupPosts as $groupPost) {
                $insertStmt->execute([
                    $groupPost['title'],
                    $groupPost['detail'],
                    $groupPost['is_sensitive'],
                    $groupPost['is_visible'],
                    $groupPost['sort_order'],
                    $groupPost['created_at'],
                    $groupPost['updated_at'],
                    $groupPost['tag1'],
                    $groupPost['tag2'],
                    $groupPost['tag3'],
                    $groupPost['tag4'],
                    $groupPost['tag5'],
                    $groupPost['tag6'],
                    $groupPost['tag7'],
                    $groupPost['tag8'],
                    $groupPost['tag9'],
                    $groupPost['tag10']
                ]);

                // 新しく挿入されたIDを取得
                $newId = (int)$db->lastInsertId();

                // マッピングを記録
                $mappingStmt->execute([$groupPost['id'], $newId]);

                $migratedCount++;
            }

            error_log("Migration 007: Migrated {$migratedCount} posts from group_posts to posts");

            // ステップ8: group_post_imagesに新しいレコードをINSERT
            error_log("Migration 007: Copying group_post_images with new post_ids");

            $stmt = $db->query("
                SELECT
                    m.new_id,
                    gpi.display_order,
                    gpi.image_path,
                    gpi.thumb_path
                FROM group_post_images gpi
                JOIN group_posts gp ON gpi.group_post_id = gp.id
                JOIN group_posts_id_mapping m ON gp.id = m.old_id
                ORDER BY gpi.id
            ");
            $imagesToCopy = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $imageInsertStmt = $db->prepare("
                INSERT INTO group_post_images (group_post_id, display_order, image_path, thumb_path)
                VALUES (?, ?, ?, ?)
            ");

            $copiedImagesCount = 0;
            foreach ($imagesToCopy as $image) {
                $imageInsertStmt->execute([
                    $image['new_id'],
                    $image['display_order'],
                    $image['image_path'],
                    $image['thumb_path']
                ]);
                $copiedImagesCount++;
            }

            error_log("Migration 007: Copied {$copiedImagesCount} images to new post_ids");

            // ステップ9: group_postsテーブルを削除
            error_log("Migration 007: Dropping group_posts table");
            $db->exec("DROP TABLE group_posts");

            // ステップ10: コミット
            error_log("Migration 007: Committing transaction");
            $db->exec("COMMIT");

            error_log("Migration 007: Migration completed successfully");
            error_log("Migration 007: Summary - Migrated {$migratedCount} posts, copied {$copiedImagesCount} images");

        } catch (Exception $e) {
            error_log("Migration 007: Migration failed: " . $e->getMessage());
            error_log("Migration 007: Rolling back transaction");
            $db->exec("ROLLBACK");
            throw $e;
        }
    }
];
