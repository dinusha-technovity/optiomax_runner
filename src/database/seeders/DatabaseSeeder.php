<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SidebarMenusDataSeeder::class);
        $this->call(Tenant_WidgetsDataSeeder::class);
        $this->call(WidgetsDataSeeder::class);
        $this->call(Tenantassest_requisition_availability_typeSeeder::class);
        $this->call(Tenantassest_requisition_period_typeSeeder::class);
        $this->call(Tenantassest_requisition_priority_typeSeeder::class);
        // $this->call(TenantAsset_categoriesSeeder::class);
        // $this->call(TenantAssetsubcategoriesSeeder::class);
        $this->call(TenantAssetTypesSeeder::class);
        $this->call(TenantPrefixTypesSeeder::class);
        $this->call(TenantPrefixesSeeder::class);
        $this->call(TenantRequestTypesSeeder::class);
        $this->call(TenantWorkflowBehaviorTypesSeeder::class);
        $this->call(TenantWorkflowTypesSeeder::class);
        $this->call(TenantWorkflowConditionTagDefinitionsSeeder::class);
        $this->call(TenantPackagesSeeder::class);

        $this->call(TenantPermissionSeeder::class);
        $this->call(Tbl_menuSeeder::class);
        $this->call(Portal_WidgetsDataSeeder::class);
        // $this->call(TenantTbl_menuSeeder::class);
        $this->call(CountryCodeSeeder::class);
        $this->call(WorkflowConditionQueryTagSeeder::class);
        $this->call(WorkflowConditionSystemVariableSeeder::class);

        $this->call(WorkOrderBudgetCodesSeeder::class);
        $this->call(WorkOrderMaintenanceTypesSeeder::class);
        $this->call(WorkOrderTechnicianSeeder::class);
        $this->call(WorkOrderTypeSeeder::class);
        $this->call(DocumentCategorySeeder::class);
        $this->call(DocumentCategoryFieldSeeder::class);
        $this->call(DepreciationMethodSeeder::class);
        $this->call(AssetRecievedConditionTypesSeeder::class);
        $this->call(WarrentyConditionTypesSeeder::class);
        $this->call(AssetRequisitionAcquisitionTypesSeeder::class);
        $this->call(TimePeriodSeeder::class);
        $this->call(PackageAddonsSeeder::class);
        $this->call(PackageDiscountsSeeder::class);
        $this->call(PackageFeaturesSeeder::class);

    }
}
