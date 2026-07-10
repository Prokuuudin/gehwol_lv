<?php

require_once __DIR__ . '/includes/storage.php';
require_once __DIR__ . '/includes/links.php';

header('Content-Type: application/json; charset=utf-8');

$type = $_GET['type'] ?? '';

switch ($type) {
    case 'products':
        $rows = sort_rows(load_collection('products'));
        $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
        if ($categoryId) {
            $rows = array_values(array_filter($rows, fn($r) => (int)$r['category_id'] === $categoryId));
        }
        $out = array_map(fn($r) => [
            'id' => (int)$r['id'],
            'category_id' => (int)$r['category_id'],
            'name' => $r['name'],
            'image' => $r['image'],
            'link' => 'php/' . product_link((int)$r['id']),
        ], $rows);
        echo json_encode($out);
        break;

    case 'news':
        $out = array_map(fn($r) => [
            'id' => (int)$r['id'],
            'title' => $r['title'],
            'image' => $r['image'],
            'link' => 'php/' . news_link((int)$r['id']),
        ], sort_rows(load_collection('news')));
        echo json_encode($out);
        break;

    case 'articles':
        $out = array_map(fn($r) => [
            'id' => (int)$r['id'],
            'title' => $r['title'],
            'image' => $r['image'],
            'link' => 'php/' . article_link((int)$r['id']),
        ], sort_rows(load_collection('articles')));
        echo json_encode($out);
        break;

    case 'categories':
        // only admin-created rows: every seeded row (groups and leaves) is
        // already present in the static HTML tree
        $rows = array_values(array_filter(
            sort_rows(load_collection('categories')),
            fn($r) => empty($r['link_url']) && empty($r['seeded'])
        ));
        $out = array_map(fn($r) => [
            'id' => (int)$r['id'],
            'parent_id' => $r['parent_id'] !== null ? (int)$r['parent_id'] : null,
            'name' => $r['name'],
            'link' => 'php/' . category_link($r),
        ], $rows);
        echo json_encode($out);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'unknown type']);
}
