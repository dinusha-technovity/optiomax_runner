<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpParser\Node\Stmt\TryCatch;
 
class MasterDocumentRepository
{
    /**
     * Create an item master and related items.
     *
     * @param array $data
     * @return void
     */


    public function allDocumentCategories($category_id = null)
    {
        DB::beginTransaction();

        try {
            $result = DB::select(
                "SELECT * FROM get_active_document_categories(NULL, ?)",
                [$category_id]
            );

            if (!empty($result)) {
                $response = collect($result)->map(function ($item) {
                    return (array) $item; // Convert object to array
                })->toArray();

                // Commit the transaction
                DB::commit();

                return [
                    'success' => true,
                    'message' => 'active documents category list fetched successfully',
                    'data' => $response,
                ];
            }

            // If no data is returned
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'No matching items found.',
                'data' => [],
            ];
        } catch (\Exception $e) {
            // Handle any errors and roll back the transaction
            DB::rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    public function allDocumentFields($category_id = null)
    {
        DB::beginTransaction();

        try {
            $result = DB::select(
                "SELECT * FROM get_active_document_category_fields(NULL, NULL, ?)",
                [$category_id]
            );

            if (!empty($result)) {
                $response = collect($result)->map(function ($item) {
                    return (array) $item; // Convert object to array
                })->toArray();

                // Commit the transaction
                DB::commit();

                return [
                    'success' => true,
                    'message' => 'active documents category field list fetched successfully',
                    'data' => $response,
                ];
            }

            // If no data is returned
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'No matching fieds found.',
                'data' => [],
            ];
        } catch (\Exception $e) {
            // Handle any errors and roll back the transaction
            DB::rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    public function setDocumentUploadRecord($filesData)
    {
        // return response($filesData);
        try {
            $result = DB::select("SELECT * FROM insert_document_media_bulk(?, ?)", [
                json_encode($filesData) ?? null,
                now()
            ]);

            if (!empty($result)) {
                $response = collect($result)->map(function ($item) {
                    return (array) $item; // Convert object to array
                })->toArray();

                // Commit the transaction
                DB::commit();

                return [
                    'success' => true,
                    'message' => 'documents uploaded successfully',
                    'data' => $response,
                ];
            }
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Something went wrong.',
                'data' => [],
            ];
            
        } catch (\Exception $e) {
            // Handle any errors and roll back the transaction
            DB::rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    public function getUploadeddocs(int $id)
    {
        // dd($id);

        DB::beginTransaction();

        try {
            $result = DB::select(
                "SELECT * FROM get_document_media_file(?)",
                [$id]
            );

            if (!empty($result)) {
                $response = collect($result)->map(function ($item) {
                    return (array) $item; // Convert object to array
                })->toArray();

                // Commit the transaction
                DB::commit();

                return [
                    'success' => true,
                    'message' => 'active documents category field list fetched successfully',
                    'data' => $response,
                ];
            }

            // If no data is returned
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'No matching fieds found.',
                'data' => [],
            ];
        } catch (\Exception $e) {
            // Handle any errors and roll back the transaction
            DB::rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    public function deleteDocument(int $id, int $tenantId, int $user_id, $user_name)
    {
        DB::beginTransaction();

        try {
            $result = DB::select(
                "SELECT * FROM delete_document_media(?,?,?,?,?)",
                [$id,$tenantId, now(), $user_id, $user_name]
            );

            if (!empty($result)) {
                $response = collect($result)->map(function ($item) {
                    return (array) $item; // Convert object to array
                })->toArray();

                // Commit the transaction
                DB::commit();

                return [
                    'success' => true,
                    'message' => 'documents deleted successfully',
                    'data' => $response,
                ];
            }
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Something went wrong.',
                'data' => [],
            ];
            
        } catch (\Exception $e) {
            // Handle any errors and roll back the transaction
            DB::rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    public function uploadThumbnail($filesData)
    {
        try {
            $result = DB::select("SELECT * FROM upload_thumbnail_image(?, ?, ?, ?, ?, ?, ?, ?)", [
                $filesData['original_name'] ?? null,
                $filesData['stored_name'] ?? null,
                $filesData['size'] ?? null,
                $filesData['mime_type'] ?? null,
                $filesData['tenant_id'] ?? null,
                $filesData['user_id'] ?? null,
                $filesData['document_category_id'] ?? null,
                $filesData['document_field_id'] ?? null,
            ]);

            if (!empty($result)) {
                $response = collect($result)->map(function ($item) {
                    return (array) $item; // Convert object to array
                })->toArray();

                // Commit the transaction
                DB::commit();

                return [
                    'success' => true,
                    'data' => $response,
                ];
            }
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Something went wrong.',
                'data' => [],
            ];
            
        } catch (\Exception $e) {
            // Handle any errors and roll back the transaction
            DB::rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }
    public function getUploadeddocsByName(string $name)
    {
        // dd($id);

        DB::beginTransaction();

        try {
            $result = DB::select(
                "SELECT * FROM get_document_media_file_by_name(?)",
                [$name]
            );

            // dd($result);

            if (!empty($result)) {
                $response = collect($result)->map(function ($item) {
                    return (array) $item; // Convert object to array
                })->toArray();

                // Commit the transaction
                DB::commit();

                return [
                    'success' => true,
                    'message' => 'active documents fetched successfully',
                    'data' => $response,
                ];
            }

            // If no data is returned
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'No matching fieds found.',
                'data' => [],
            ];
        } catch (\Exception $e) {
            // Handle any errors and roll back the transaction
            DB::rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    public function deleteDocumentByName(string $name, int $tenantId, int $user_id, $user_name)
    {
        DB::beginTransaction();

        try {
            $result = DB::select(
                "SELECT * FROM delete_document_media_by_name(?,?,?,?,?)",
                [$name,$tenantId, now(), $user_id, $user_name]
            );

            if (!empty($result)) {
                $response = collect($result)->map(function ($item) {
                    return (array) $item; // Convert object to array
                })->toArray();

                // Commit the transaction
                DB::commit();

                return [
                    'success' => true,
                    'message' => 'documents deleted successfully',
                    'data' => $response,
                ];
            }
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Something went wrong.',
                'data' => [],
            ];
            
        } catch (\Exception $e) {
            // Handle any errors and roll back the transaction
            DB::rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

}
