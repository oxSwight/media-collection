<?php
// src/includes/pagination.php

function get_pagination_params(int $defaultPerPage = 20): array
{
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = max(1, min(100, (int)($_GET['per_page'] ?? $defaultPerPage)));
    
    return [
        'page' => $page,
        'per_page' => $perPage,
        'offset' => ($page - 1) * $perPage
    ];
}

function render_pagination(int $currentPage, int $totalPages, string $baseUrl = ''): string
{
    if ($totalPages <= 1) {
        return '';
    }
    
    $queryParams = $_GET;
    unset($queryParams['page']);
    $queryString = http_build_query($queryParams);
    
    $buildUrl = function($pageNum) use ($baseUrl, $queryString) {
        $params = $queryString ? $queryString . '&page=' . $pageNum : 'page=' . $pageNum;
        return $baseUrl . '?' . $params;
    };
    
    $html = '<div class="pagination">';
    
    // Предыдущая страница
    if ($currentPage > 1) {
        $html .= '<a href="' . htmlspecialchars($buildUrl($currentPage - 1)) . '" class="pagination-btn">← ' . htmlspecialchars(t('common.prev_page')) . '</a>';
    } else {
        $html .= '<span class="pagination-btn disabled">← ' . htmlspecialchars(t('common.prev_page')) . '</span>';
    }
    
    // Номера страниц
    $html .= '<div class="pagination-numbers">';
    
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);
    
    if ($startPage > 1) {
        $html .= '<a href="' . htmlspecialchars($buildUrl(1)) . '" class="pagination-number">1</a>';
        if ($startPage > 2) {
            $html .= '<span class="pagination-ellipsis">...</span>';
        }
    }
    
    for ($i = $startPage; $i <= $endPage; $i++) {
        if ($i === $currentPage) {
            $html .= '<span class="pagination-number active">' . $i . '</span>';
        } else {
            $html .= '<a href="' . htmlspecialchars($buildUrl($i)) . '" class="pagination-number">' . $i . '</a>';
        }
    }
    
    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $html .= '<span class="pagination-ellipsis">...</span>';
        }
        $html .= '<a href="' . htmlspecialchars($buildUrl($totalPages)) . '" class="pagination-number">' . $totalPages . '</a>';
    }
    
    $html .= '</div>';
    
    // Следующая страница
    if ($currentPage < $totalPages) {
        $html .= '<a href="' . htmlspecialchars($buildUrl($currentPage + 1)) . '" class="pagination-btn">' . htmlspecialchars(t('common.next_page')) . ' →</a>';
    } else {
        $html .= '<span class="pagination-btn disabled">' . htmlspecialchars(t('common.next_page')) . ' →</span>';
    }
    
    $html .= '</div>';
    
    return $html;
}

