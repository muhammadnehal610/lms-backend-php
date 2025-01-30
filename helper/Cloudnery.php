<?php
require 'vendor/autoload.php';  // Ensure Cloudinary SDK is loaded via Composer

use Cloudinary\Cloudinary;
use Cloudinary\Uploader;

/**
 * Helper function to upload a file to Cloudinary
 *
 * @param array $file The uploaded file from $_FILES
 * @param string $folder (Optional) Folder name in Cloudinary for organization
 * @return string|false The secure URL of the uploaded file or false on failure
 */
function uploadFileToCloudinary($file, $folder = 'uploads')
{
    try {
        // Initialize Cloudinary instance
        $cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => 'dahcmghkw',
                'api_key'    => '494411244286585',
                'api_secret' => 'IqlIORfDqf_xfA_44D60arPBRLY',
            ],
        ]);

        // Log the temporary file path to debug
        error_log('Temporary file path: ' . $file['tmp_name']);

        // Check if the file exists
        if (!file_exists($file['tmp_name'])) {
            error_log('File does not exist: ' . $file['tmp_name']);
            return false;
        }

        // Upload the file to Cloudinary
        $uploadResponse = $cloudinary->uploadApi()->upload(
            $file['tmp_name'], // Temporary file path
            [
                'folder'         => $folder,                // Folder name
                'public_id'      => 'file-' . uniqid(),     // Unique file name
                'resource_type' => 'raw' // Use 'raw' for non-image files like ZIP
                // 'access_control' => [
                //     [
                //         'access_type'   => 'anonymous',   // Set access type as anonymous for public access
                //         'allowed_roles' => ['*']          // Set public access for all users
                //     ]
                // ]
            ]
        );

        // Return the secure URL of the uploaded file
        return $uploadResponse['secure_url'];

    } catch (Exception $e) {
        // Log the error for debugging
        error_log('Cloudinary Upload Error: ' . $e->getMessage());

        // Return false to indicate failure
        return false;
    }
}
