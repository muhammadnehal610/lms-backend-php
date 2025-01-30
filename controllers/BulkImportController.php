<?php
use PhpOffice\PhpSpreadsheet\IOFactory;
require_once 'vendor/autoload.php';
require_once 'models/BulkImport.php';

class BulkImportController
{
    public $bulkImport;

    public function __construct($conn)
    {
        $this->bulkImport = new BulkImportModel($conn);
    }

    public function importUsers($file)
    {
        if (empty($file)) {
            return ['status' => 400, 'message' => 'No file uploaded.'];
        }

        $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);

        if (!in_array($fileExtension, ['xls', 'xlsx', 'csv'])) {
            return ['status' => 400, 'message' => 'Invalid file type. Only Excel or CSV files are allowed.'];
        }

        try {
            $spreadsheet = IOFactory::load($file['tmp_name']);
        } catch (Exception $e) {
            return ['status' => 500, 'message' => 'Failed to read the file: ' . $e->getMessage()];
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        $invalidRows = [];
        $validRows = [];
        $duplicateRows = [];

        foreach ($rows as $index => $row) {
            if ($index === 0) continue; // Skip the header row

            $data = $this->mapRowToData($row);

            // Validate the row
            $validationResponse = $this->validateData($data);
            if ($validationResponse['status'] === 400) {
                $row['error'] = $validationResponse['message'];
                $invalidRows[] = $row;
                continue;
            }

            // Check for duplicates
            if ($this->bulkImport->checkDuplicate($data)) {
                $row['error'] = 'Duplicate email or username.';
                $duplicateRows[] = $row;
                continue;
            }

            // If no validation issues or duplicates, add to validRows
            $validRows[] = $data;
        }

        // Insert valid rows into the database
        $insertedCount = 0;
        foreach ($validRows as $user) {
            if ($this->bulkImport->addPerson($user)) {
                $insertedCount++;
            } else {
                $user->error = 'Failed to insert into the database.';
                $invalidRows[] = (array) $user; // Add to invalidRows with error
            }
        }

        return [
            'status' => 200,
            'message' => 'Bulk import completed.',
            'insertedRows' => $insertedCount,
            'invalidRows' => array_merge($invalidRows, $duplicateRows),
            'totalValidRows' => count($validRows),
           
        ];
    }

    private function mapRowToData($row)
    {
        return (object) [
            'first_name' => $row[0] ?? null,
            'last_name' => $row[1] ?? null,
            'username' => $row[2] ?? null,
            'status' => $row[3] ?? null,
            'access_level' => $row[4] ?? null,
            'password' => $row[5] ?? null,
            'email' => $row[6] ?? null,
            'profile_type' => $row[7] ?? null,
            'title' => $row[8] ?? null,
            'company' => $row[9] ?? null,
            'website' => $row[10] ?? null,
            'inactive_date' => $row[11] ?? null,
            'external_employee_id' => $row[12] ?? null,
            'address1' => $row[13] ?? null,
            'address2' => $row[14] ?? null,
            'city' => $row[15] ?? null,
            'state_province' => $row[16] ?? null,
            'zip_code' => $row[17] ?? null,
            'country' => $row[18] ?? null,
            'timezone' => $row[19] ?? null,
            'language' => $row[20] ?? null,
            'date_format' => $row[21] ?? null,
            'brand' => $row[22] ?? null,
            'work_phone' => $row[23] ?? null,
            'mobile_phone' => $row[24] ?? null,
            'skype' => $row[25] ?? null,
            'twitter' => $row[26] ?? null,
            'manager' => $row[27] ?? null,
            'last_login' => $row[28] ?? null,
        ];
    }

    private function validateData($data)
    {
        if (empty($data->first_name) || empty($data->last_name) || empty($data->email) || empty($data->username)) {
            return ['status' => 400, 'message' => 'Missing required fields (first_name, last_name, email, or username).'];
        }

        if (!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 400, 'message' => 'Invalid email format.'];
        }

        return ['status' => 200, 'message' => 'Validation passed.'];
    }
}
