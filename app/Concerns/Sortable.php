<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait Sortable
{
    protected static function bootSortable(): void
    {
        static::creating(function (self $model): void {
            if (is_null($model->sort_order)) {
                $query = static::query();

                foreach ($model->sortableScope() as $column) {
                    $query->where($column, $model->{$column});
                }

                $model->sort_order = (int) $query->max('sort_order') + 1;
            }
        });
    }

    /**
     * Columns used to scope sort_order assignment (e.g. ['parent_id', 'category_id']).
     * Override in the model to restrict ordering to a subset of records.
     *
     * @return array<int, string>
     */
    protected function sortableScope(): array
    {
        return [];
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }
}
