<?php

declare(strict_types=1);

/**
 * Help Search API endpoint for Ghidar
 * Searches help articles by title and content
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Core\Database;
use PDO;

try {
    $context = UserContext::requireCurrentUser();
    $pdo = Database::ensureConnection();

    $query = trim($_GET['q'] ?? '');
    
    if (empty($query)) {
        Response::jsonSuccess(['articles' => []]);
        exit;
    }

    $searchQuery = "
        SELECT * FROM `help_articles`
        WHERE title LIKE :search
           OR content LIKE :search
           OR excerpt LIKE :search
        ORDER BY 
            CASE 
                WHEN title LIKE :exact THEN 1
                WHEN title LIKE :search THEN 2
                ELSE 3
            END,
            created_at DESC
        LIMIT 20
    ";

    $searchTerm = '%' . $query . '%';
    $exactTerm = $query;
    
    $stmt = $pdo->prepare($searchQuery);
    $stmt->execute([
        'search' => $searchTerm,
        'exact' => $exactTerm,
    ]);
    
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format articles
    $formattedArticles = [];
    foreach ($articles as $article) {
        $formattedArticles[] = [
            'id' => (int) $article['id'],
            'title' => $article['title'],
            'content' => $article['content'],
            'excerpt' => $article['excerpt'] ?? substr(strip_tags($article['content']), 0, 150) . '...',
            'category' => $article['category'],
            'tags' => $article['tags'] ? json_decode($article['tags'], true) : [],
            'related_articles' => $article['related_articles'] ? json_decode($article['related_articles'], true) : [],
            'created_at' => $article['created_at'],
            'updated_at' => $article['updated_at'] ?? null,
        ];
    }

    Response::jsonSuccess([
        'articles' => $formattedArticles,
    ]);

} catch (\RuntimeException $e) {
    Response::jsonError('AUTH_ERROR', $e->getMessage(), 401);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
}

