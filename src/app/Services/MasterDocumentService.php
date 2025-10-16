<?php

namespace App\Services;

use App\Repositories\MasterDocumentRepository;

class MasterDocumentService
{
      protected $MasterDocumentRepository;

      public function __construct(MasterDocumentRepository $MasterDocumentRepository)
      {
            $this->MasterDocumentRepository = $MasterDocumentRepository;
      }

      /**
       * Create an  document reated controller.
       *
       * @param array $data
       * @return void
       */


      public function allDocumentCategories($category_id = null)
      {
            return $this->MasterDocumentRepository->allDocumentCategories($category_id);
      }

      public function allDocumentFields($category_id = null)
      {
            return $this->MasterDocumentRepository->allDocumentFields($category_id);
      }

      public function setDocumentUploadRecord($filesData)
      {
            return $this->MasterDocumentRepository->setDocumentUploadRecord($filesData);
      }
      public function getUploadeddocs($id)
      {
            return $this->MasterDocumentRepository->getUploadeddocs($id);
      }

      public function deleteDocument($id, $tenantId, $user_id, $user_name)
      {
            return $this->MasterDocumentRepository->deleteDocument($id, $tenantId, $user_id, $user_name);
      }

      public function uploadThumbnail($filesData)
      {
            return $this->MasterDocumentRepository->uploadThumbnail($filesData);
      }

      public function getUploadeddocsByName($name)
      {
            return $this->MasterDocumentRepository->getUploadeddocsByName($name);
      }
      public function deleteDocumentByName($name, $tenantId, $user_id, $user_name)
      {
            return $this->MasterDocumentRepository->deleteDocumentByName($name, $tenantId, $user_id, $user_name);
      }
}
