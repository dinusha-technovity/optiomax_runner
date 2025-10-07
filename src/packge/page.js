'use client'
import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { FaCheckCircle, FaStar, FaInfoCircle } from "react-icons/fa";
import { HiOutlineExclamationCircle } from "react-icons/hi";
import { MdDiscount } from "react-icons/md";
import SideHeader from '../components/sideheader';
import SubNavBar from '../components/subnavbar';
import { useTenantPackagesWithAddonsQuery } from '../_lib/redux/features/tenantpackages/tenant_packages_api';

export default function PackageSelectionPage() {
  const [billingCycle, setBillingCycle] = useState('Monthly');
  const [selectedPackage, setSelectedPackage] = useState(null);
  const [selectedPackageType, setSelectedPackageType] = useState('INDIVIDUAL');

  const {
    data: packagesData,
    isLoading,
    isError,
    error,
    refetch,
  } = useTenantPackagesWithAddonsQuery({ 
    billingCycle,
    packageType: selectedPackageType 
  });

  const [filteredPackages, setFilteredPackages] = useState([]);
  const [availableAddons, setAvailableAddons] = useState([]);
  const [availableDiscounts, setAvailableDiscounts] = useState([]);

  useEffect(() => {
    if (!isLoading && !isError && packagesData?.success) {
      const { packages, addons, discounts } = packagesData.data;
      
      // Filter packages based on selected package type and billing cycle
      const filtered = packages.filter(pkg => {
        const packageData = billingCycle === 'Monthly' ? pkg.monthly : pkg.yearly;
        return packageData && 
               (pkg.allowed_package_types?.includes(selectedPackageType) || 
                !pkg.allowed_package_types);
      });
      
      setFilteredPackages(filtered);
      setAvailableAddons(addons || []);
      setAvailableDiscounts(discounts || []);
    }
  }, [isLoading, isError, packagesData, billingCycle, selectedPackageType]);

  const getPackageData = (pkg) => {
    return billingCycle === 'Monthly' ? pkg.monthly : pkg.yearly;
  };

  const formatPrice = (price) => {
    return price ? `$${parseFloat(price).toFixed(0)}` : '$0';
  };

  const getDiscountBadge = (packageData) => {
    if (packageData?.has_discount && packageData?.discount_percentage) {
      return (
        <div className="absolute top-2 left-2 bg-red-500 text-white px-2 py-1 rounded-md text-xs font-bold flex items-center">
          <MdDiscount className="mr-1" />
          {Math.abs(packageData.discount_percentage)}% OFF
        </div>
      );
    }
    return null;
  };

  const getPopularBadge = (pkg) => {
    if (pkg.is_popular) {
      return (
        <div className="absolute top-2 right-2 bg-blue-500 text-white px-2 py-1 rounded-md text-xs font-bold flex items-center">
          <FaStar className="mr-1" />
          Popular
        </div>
      );
    }
    return null;
  };

  const canSelectPackageType = (pkg) => {
    return pkg.allowed_package_types && pkg.allowed_package_types.length > 1;
  };

  const handlePackageSelection = (pkg) => {
    const packageData = getPackageData(pkg);
    if (packageData) {
      setSelectedPackage({
        name: pkg.name,
        id: packageData.id,
        type: billingCycle,
        packageType: selectedPackageType,
        price: packageData.effective_price || packageData.price,
        setup_fee: packageData.setup_fee || 0,
        trial_days: packageData.trial_days || 0
      });
    }
  };

  if (isLoading) {
    return (
      <div className="bg-white">
        <div className="h-screen w-screen flex isolate">
          <SideHeader />
          <div className='2xl:w-[70%] xl:w-[75%] h-screen w-screen'>
            <SubNavBar />
            <div className="flex justify-center items-center h-64">
              <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
              <span className="ml-3 text-gray-600">Loading packages...</span>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="bg-white">
      <div className="h-screen w-screen flex isolate">
        <SideHeader />
        <div className='2xl:w-[70%] xl:w-[75%] h-screen w-screen overflow-y-auto'>
          <SubNavBar />

          <div className="flex flex-col items-center justify-center">
            <p className='text-left text-sm font-normal leading-[22px] text-black'>Sign Up</p>
            <h1 className="text-center text-[26px] font-bold leading-[71px] text-black">
              Choose the package that suits your need
            </h1>
          </div>

          {/* Package Type Selection */}
          <div className='flex item-center justify-center mb-4'>
            <div className="flex bg-gray-100 rounded-lg p-1">
              <button
                type="button"
                className={`px-6 py-2 rounded-md text-sm font-medium transition-colors ${
                  selectedPackageType === 'INDIVIDUAL' 
                    ? 'bg-white text-blue-600 shadow-sm' 
                    : 'text-gray-600 hover:text-gray-900'
                }`}
                onClick={() => setSelectedPackageType('INDIVIDUAL')}
              >
                Individual
              </button>
              <button
                type="button"
                className={`px-6 py-2 rounded-md text-sm font-medium transition-colors ${
                  selectedPackageType === 'ENTERPRISE' 
                    ? 'bg-white text-blue-600 shadow-sm' 
                    : 'text-gray-600 hover:text-gray-900'
                }`}
                onClick={() => setSelectedPackageType('ENTERPRISE')}
              >
                Enterprise
              </button>
            </div>
          </div>

          {/* Billing Cycle Selection */}
          <div className='flex item-center justify-center mb-6'>
            <div className="flex">
              <button
                type="button"
                className={`px-4 py-1 ${
                  billingCycle === 'Monthly' ? 'border-b-4 border-black font-bold' : 'font-normal'
                } text-center text-sm leading-[22px] text-black`}
                onClick={() => setBillingCycle('Monthly')}
              >
                Monthly
              </button>
              <button
                type="button"
                className={`px-4 py-1 ${
                  billingCycle === 'Yearly' ? 'border-b-4 border-black font-bold' : 'font-normal'
                } text-center text-sm leading-[22px] text-black`}
                onClick={() => setBillingCycle('Yearly')}
              >
                Yearly
                {billingCycle === 'Yearly' && (
                  <span className="ml-2 text-xs bg-green-100 text-green-800 px-2 py-1 rounded-full">
                    Save up to 10%
                  </span>
                )}
              </button>
            </div>
          </div>

          <div className='flex item-center justify-center'>
            <div className="container mx-auto px-6 pb-12">
              {/* Pricing Table */}
              <div className="grid 2xl:grid-cols-5 xl:grid-cols-4 lg:grid-cols-3 md:grid-cols-2 sm:grid-cols-1 gap-6 items-start">
                {/* Feature Labels */}
                <div className="h-[400px] p-5 2xl:flex xl:flex lg:flex md:hidden sm:hidden xs:hidden items-end">
                  <div className="h-[180px] pt-[35px] space-y-3 text-sm">
                    <div className='flex items-center'>
                      <p className="text-sm font-medium leading-[24px] text-black">Credits</p>
                      <HiOutlineExclamationCircle className='ml-[6px]' color="#71717A" size="12px" />
                    </div>
                    <div className='flex items-center'>
                      <p className="text-sm font-medium leading-[24px] text-black">Workflows</p>
                      <HiOutlineExclamationCircle className='ml-[6px]' color="#71717A" size="12px" />
                    </div>
                    <div className='flex items-center'>
                      <p className="text-sm font-medium leading-[24px] text-black">Users</p>
                      <HiOutlineExclamationCircle className='ml-[6px]' color="#71717A" size="12px" />
                    </div>
                    <div className='flex items-center'>
                      <p className="text-sm font-medium leading-[24px] text-black">Storage</p>
                      <HiOutlineExclamationCircle className='ml-[6px]' color="#71717A" size="12px" />
                    </div>
                    <div className='flex items-center'>
                      <p className="text-sm font-medium leading-[24px] text-black">Support</p>
                    </div>
                    {billingCycle === 'Yearly' && (
                      <div className='flex items-center'>
                        <p className="text-sm font-medium leading-[24px] text-black">Trial Period</p>
                      </div>
                    )}
                  </div>
                </div>

                {filteredPackages.map((pkg, idx) => {
                  const packageData = getPackageData(pkg);
                  if (!packageData) return null;

                  const isSelected = selectedPackage?.id === packageData.id;
                  
                  return (
                    <div 
                      key={`${pkg.name}-${billingCycle}-${idx}`}
                      className={`${
                        isSelected ? 'border-[2px] border-[#11459E] shadow-lg' : 'border-[2px] border-gray-200'
                      } bg-white h-[400px] rounded-lg p-5 flex flex-col relative cursor-pointer hover:shadow-md transition-all duration-200`}    
                      onClick={() => handlePackageSelection(pkg)}
                    >
                      {getDiscountBadge(packageData)}
                      {getPopularBadge(pkg)}
                      
                      <div className='flex justify-between items-center mt-8'>
                        <h3 className='text-left text-lg font-semibold leading-[30px] text-black'>
                          {pkg.name}
                        </h3>
                        {isSelected && (
                          <FaCheckCircle color="#22C55E" size="16px" />
                        )}
                      </div>

                      <div className="mt-2">
                        <div className="flex items-baseline">
                          <p className="text-3xl font-bold text-black">
                            {formatPrice(packageData.effective_price || packageData.price)}
                          </p>
                          <span className="text-base font-normal text-gray-600 ml-1">
                            /{billingCycle === "Monthly" ? "month" : "year"}
                          </span>
                        </div>
                        
                        {packageData.has_discount && (
                          <div className="flex items-center mt-1">
                            <span className="text-sm text-gray-500 line-through mr-2">
                              {formatPrice(packageData.discount_price)}
                            </span>
                            <span className="text-sm text-green-600 font-medium">
                              Save {Math.abs(packageData.discount_percentage)}%
                            </span>
                          </div>
                        )}

                        {packageData.setup_fee > 0 && (
                          <p className="text-sm text-gray-600 mt-1">
                            + ${packageData.setup_fee} setup fee
                          </p>
                        )}
                      </div>

                      <p className="mt-3 text-sm font-normal leading-[20px] text-gray-600">
                        {pkg.description}
                      </p>

                      <div className="mt-4 border-t border-gray-200 h-[180px] pt-4 space-y-3 text-sm flex-grow">
                        <div className='flex items-center'>
                          <FaCheckCircle color="#10B981" size="12px" />
                          <p className="ml-2 text-sm font-medium leading-[20px] text-black">
                            {packageData.credits?.toLocaleString() || 0} credits
                          </p>
                        </div>
                        <div className='flex items-center'>
                          <FaCheckCircle color="#10B981" size="12px" />
                          <p className="ml-2 text-sm font-medium leading-[20px] text-black">
                            {packageData.workflows} workflows
                          </p>
                        </div>
                        <div className='flex items-center'>
                          <FaCheckCircle color="#10B981" size="12px" />
                          <p className="ml-2 text-sm font-medium leading-[20px] text-black">
                            {packageData.users} users
                          </p>
                        </div>
                        <div className='flex items-center'>
                          <FaCheckCircle color="#10B981" size="12px" />
                          <p className="ml-2 text-sm font-medium leading-[20px] text-black">
                            {packageData.max_storage_gb}GB storage
                          </p>
                        </div>
                        {packageData.support && (
                          <div className='flex items-center'>
                            <FaCheckCircle color="#10B981" size="12px" />
                            <p className="ml-2 text-sm font-medium leading-[20px] text-black">
                              Live Chat Support
                            </p>
                          </div>
                        )}
                        {packageData.trial_days > 0 && billingCycle === 'Yearly' && (
                          <div className='flex items-center'>
                            <FaCheckCircle color="#10B981" size="12px" />
                            <p className="ml-2 text-sm font-medium leading-[20px] text-black">
                              {packageData.trial_days} days free trial
                            </p>
                          </div>
                        )}
                      </div>
                    </div>
                  );
                })}
              </div>

              {/* Available Discounts Info */}
              {availableDiscounts.length > 0 && (
                <div className="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-4">
                  <h4 className="text-sm font-medium text-blue-900 mb-2 flex items-center">
                    <FaInfoCircle className="mr-2" />
                    Available Discounts
                  </h4>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    {availableDiscounts.slice(0, 4).map((discount, idx) => (
                      <div key={idx} className="text-sm text-blue-800">
                        <strong>{discount.code}:</strong> {discount.description}
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
          </div>

          <div className='flex item-center justify-center pb-8'>
            <div className='flex justify-around 2xl:w-[20%] xl:w-[25%] lg:w-[30%] md:w-[40%] sm:w-[50%] space-x-6'>
              <Link
                href='/'
                className="btn btn-secondary btn-outline"
              >
                Back
              </Link>
              <Link
                href={selectedPackage ? 
                  `/register?package=${selectedPackage.name}&type=${selectedPackage.type}&packageType=${selectedPackage.packageType}&id=${selectedPackage.id}` : 
                  '#'
                }
                className={`btn btn-primary ${selectedPackage ? '' : 'opacity-50 cursor-not-allowed'}`}
              >
                Continue
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
