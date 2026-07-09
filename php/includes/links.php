<?php
// php/includes/links.php

function category_link(array $category): string
{
    if (!empty($category['link_url'])) {
        return $category['link_url'];
    }
    return 'category.php?id=' . (int)$category['id'];
}

function product_link(int $id): string
{
    return 'product.php?id=' . $id;
}

function news_link(int $id): string
{
    return 'news.php?id=' . $id;
}

function article_link(int $id): string
{
    return 'article.php?id=' . $id;
}
