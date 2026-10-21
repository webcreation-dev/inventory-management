<?php

namespace Modules\Ecommerce\Http\Controllers\Admin\Concerns;

use Illuminate\Http\JsonResponse;

trait BuildsEcommerceDataTable
{
    protected function dataTableParams(): array
    {
        return [
            'draw' => (int) request()->input('draw', 1),
            'length' => (int) request()->input('length', 10),
            'start' => (int) request()->input('start', 0),
            'search' => (string) (request()->input('search.value') ?? ''),
        ];
    }

    protected function dataTableResponse(int $draw, int $recordsTotal, int $recordsFiltered, $data): JsonResponse
    {
        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    protected function companyId(): int
    {
        return (int) session('company.company_id');
    }
}
