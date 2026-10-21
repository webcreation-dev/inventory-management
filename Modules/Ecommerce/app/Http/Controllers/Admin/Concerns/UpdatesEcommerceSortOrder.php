<?php

namespace Modules\Ecommerce\Http\Controllers\Admin\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait UpdatesEcommerceSortOrder
{
    protected function updateSortOrderFor(Request $request, string $modelClass): JsonResponse
    {
        $validated = $request->validate([
            'sorted_ids' => ['required', 'array', 'min:1'],
            'sorted_ids.*' => ['integer'],
        ]);

        $sortedIds = collect($validated['sorted_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        foreach ($sortedIds as $index => $id) {
            $modelClass::query()
                ->forCompany($this->companyId())
                ->live()
                ->where('id', $id)
                ->update(['sort_id' => $index + 1]);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Order updated successfully.'),
        ]);
    }

    protected function nextSortId(string $modelClass): int
    {
        $max = (int) $modelClass::query()
            ->forCompany($this->companyId())
            ->live()
            ->max('sort_id');

        return $max + 1;
    }
}
