<?php

namespace Modules\Administrator\Services;

use Modules\Administrator\Repositories\RoleRepository;
use Modules\Administrator\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;

class RoleService
{
    protected $roleRepository;

    public function __construct(RoleRepository $roleRepository)
    {
        $this->roleRepository = $roleRepository;
    }

    protected function currentCompanyId(): ?int
    {
        $id = session('company.company_id');
        if ($id === null || $id === '') {
            return null;
        }

        return (int) $id;
    }

    /**
     * Get all roles with pagination
     */
    public function getAllRoles($perPage = 10)
    {
        $companyId = $this->currentCompanyId();
        if ($companyId === null) {
            return new LengthAwarePaginator([], 0, $perPage);
        }

        return $this->roleRepository->paginate($perPage, $companyId);
    }

    /**
     * Get all permissions grouped by group_name
     */
    public function getAllPermissionsGrouped()
    {
        return $this->roleRepository->getAllPermissionsGrouped();
    }

    /**
     * Create a new role with permissions
     */
    public function createRole(array $data)
    {
        try {
            DB::beginTransaction();
            
            // New roles are always "Other"; only seeders may create "Master" roles
            $companyId = session('company.company_id');
            $role = $this->roleRepository->create([
                'name' => $data['name'],
                'role_type' => 'Other',
                'company_id' => $companyId,
                'del_status' => 'Live',
            ]);
            
            // Attach permissions if provided
            if (!empty($data['permissions'])) {
                $permissions = $this->roleRepository->getPermissionsByIds($data['permissions']);
                if ($permissions->count() > 0) {
                    $this->roleRepository->syncPermissions($role, $data['permissions']);
                } else {
                    DB::rollBack();
                    throw new \Exception('Selected permissions are invalid');
                }
            }
            
            DB::commit();
            return $role;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating role: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update an existing role with permissions
     */
    public function updateRole(Role $role, array $data)
    {
        try {
            DB::beginTransaction();

            if ($role->isMaster()) {
                throw new \Exception(__('Master roles cannot be edited.'));
            }
            
            // Update the role name
            $this->roleRepository->update($role, ['name' => $data['name']]);
            
            // Sync permissions if provided
            if (isset($data['permissions'])) {
                $permissions = $this->roleRepository->getPermissionsByIds($data['permissions']);
                if ($permissions->count() > 0) {
                    $this->roleRepository->syncPermissions($role, $data['permissions']);
                } else {
                    DB::rollBack();
                    throw new \Exception('Selected permissions are invalid');
                }
            }
            
            DB::commit();
            return $role;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating role: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete a role
     */
    public function deleteRole(Role $role)
    {
        try {
            DB::beginTransaction();

            if ($role->isMaster()) {
                throw new \Exception(__('Master roles cannot be deleted.'));
            }
            
            // Check if role is assigned to any users
            if ($role->users()->where('users.del_status', 'Live')->count() > 0) {
                throw new \Exception('Cannot delete role as it is assigned to users');
            }
            
            $this->roleRepository->delete($role);
            
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting role: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get the role repository instance
     */
    public function getRepository(): RoleRepository
    {
        return $this->roleRepository;
    }

    /**
     * Get role by ID with permissions
     */
    public function getRoleById(int $id)
    {
        return $this->roleRepository->findWithRelations($id);
    }

    /**
     * Search roles by name
     */
    public function searchRoles(string $name)
    {
        $companyId = $this->currentCompanyId();
        if ($companyId === null) {
            return collect();
        }

        return $this->roleRepository->searchByName($name, $companyId);
    }

    /**
     * Get role statistics
     */
    public function getRoleStatistics()
    {
        $companyId = $this->currentCompanyId();

        return [
            'total_roles' => $companyId === null
                ? 0
                : $this->roleRepository->getCountForCompany($companyId),
            'total_permissions' => $this->roleRepository->getPermissionCount(),
        ];
    }

    /**
     * Check if role name exists
     */
    public function roleNameExists(string $name, ?int $excludeId = null)
    {
        $companyId = $this->currentCompanyId();
        if ($companyId === null) {
            return false;
        }

        return $this->roleRepository->nameExists($name, $companyId, $excludeId);
    }
}
