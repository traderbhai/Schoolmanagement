<?php

namespace App\Services;

use Illuminate\Support\Collection;

class DashboardViewModelService
{
    public function metric(string $label, mixed $value, ?string $route = null, string $tone = 'primary'): array
    {
        return compact('label', 'value', 'route', 'tone');
    }

    public function sourceList(string $title, iterable $items, array $filters = []): array
    {
        return [
            'title' => $title,
            'items' => $items instanceof Collection ? $items : collect($items),
            'filters' => $filters,
        ];
    }

    public function emptyState(string $title, string $message, array $actions = []): array
    {
        return compact('title', 'message', 'actions');
    }
}

