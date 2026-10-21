<?php

namespace Modules\PrintManager\Repositories;

use Modules\PrintManager\Contracts\PrinterSettingRepositoryInterface;
use Modules\PrintManager\Models\PrintSetting;

class PrinterSettingRepository implements PrinterSettingRepositoryInterface
{
    public function findForScope(?int $companyId, ?int $branchId): ?PrintSetting
    {
        if ($companyId === null) {
            return null;
        }

        $query = PrintSetting::query()
            ->active()
            ->where('company_id', $companyId);

        if ($branchId !== null) {
            $setting = (clone $query)->where('branch_id', $branchId)->first();
            if ($setting) {
                return $setting;
            }
        }

        return $query->whereNull('branch_id')->first();
    }

    public function upsertForScope(?int $companyId, ?int $branchId, array $data): PrintSetting
    {
        return PrintSetting::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'branch_id' => $branchId,
            ],
            $data
        );
    }

    public function listByCompany(?int $companyId)
    {
        if ($companyId === null) {
            return collect();
        }

        return PrintSetting::query()
            ->where('company_id', $companyId)
            ->orderBy('branch_id')
            ->get();
    }
}
