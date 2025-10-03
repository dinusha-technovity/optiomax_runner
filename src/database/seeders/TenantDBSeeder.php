<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TenantDBSeeder extends Seeder
{ 
    public function run(): void
    {
        $this->call(Tenant_WidgetsDataSeeder::class);
        $this->call(TenantAssetTypesSeeder::class);
        $this->call(TenantDesignationSeeder::class);
        $this->call(TenantPrefixTypesSeeder::class);
        $this->call(TenantPrefixesSeeder::class);
        $this->call(TenantRequestTypesSeeder::class);
        $this->call(TenantWorkflowBehaviorTypesSeeder::class);
        $this->call(TenantWorkflowConditionTagDefinitionsSeeder::class);
        $this->call(TenantWorkflowTypesSeeder::class);
        $this->call(TenantPermissionSeeder::class);
        $this->call(TenantRoleSeeder::class); 
        $this->call(TenantRoleHasPermissionsSeeder::class);
        $this->call(TenantTbl_menuSeeder::class);
        // $this->call(TenantAsset_categoriesSeeder::class);
        // $this->call(TenantAssetsubcategoriesSeeder::class);
        $this->call(Tenantassest_requisition_availability_typeSeeder::class);
        $this->call(Tenantassest_requisition_period_typeSeeder::class);
        $this->call(Tenantassest_requisition_priority_typeSeeder::class);
        $this->call(CountryCodeSeeder::class);
        $this->call(WorkflowConditionQueryTagSeeder::class);
        $this->call(WorkflowConditionSystemVariableSeeder::class);
        $this->call(CurrenciesSeeder::class);
        // $this->call(ItemCategoriesSeeder::class);
        $this->call(ItemTypesSeeder::class);
        $this->call(MeasurementsSeeder::class);

        $this->call(AssetMaintainScheduleTypeSeeder::class);
        $this->call(AssetMaintainScheduleParametersSeeder::class);
        $this->call(MaintenanceTasksTypeSeeder::class);
        $this->call(ItemSupplierSeeder::class); 
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
        $this->call(ItemCategoryTypeSeeder::class);
        $this->call(AssetAvailabilityVisibilityTypeSeeder::class);
        $this->call(AssetBookingApprovalTypeSeeder::class);
        $this->call(AssetAvailabilityTermTypesTableSeeder::class);
        $this->call(AssetAvailabilityBlockoutReasonTypesTableSeeder::class);
        $this->call(AssetBookingTypeSeeder::class);
        $this->call(AssetBookingCancellingFeeTypesTableSeeder::class);
        $this->call(AssetBookingCancellationRefundPolicyTypeSeeder::class);
        $this->call(WorkOrderPriorityLevelsSeeder::class);
        $this->call(AssetMaintenancePriorityLevelsSeeder::class);
        $this->call(AssetBookingPurposeOrUseCaseTypeSeeder::class);
    }
}