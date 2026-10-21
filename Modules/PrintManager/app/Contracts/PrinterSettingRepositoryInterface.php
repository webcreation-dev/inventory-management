<?php

namespace Modules\PrintManager\Contracts;

use Modules\PrintManager\Models\PrintSetting;

interface PrinterSettingRepositoryInterface
{
    public function findForScope(?int $companyId, ?int $branchId): ?PrintSetting;

    public function upsertForScope(?int $companyId, ?int $branchId, array $data): PrintSetting;

    /**
     * @return \Illuminate\Support\Collection<int, PrintSetting>
     */
    public function listByCompany(?int $companyId);
}
