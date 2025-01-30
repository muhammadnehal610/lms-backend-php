<?php
class BulkImportModel
{
    public $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function addPerson($data)
    {
        try {
            // Hash the password
            $hashedPassword = password_hash($data->password, PASSWORD_BCRYPT);

            // Insert query for the new user
            $query = 'INSERT INTO person (
                first_name, last_name, username, status, access_level, password, email, profile_type, title, company, 
                website, inactive_date, external_employee_id, address1, address2, city, state_province, zip_code, 
                country, timezone, language, date_format, brand, work_phone, mobile_phone, skype, twitter, created_at, 
                updated_at, last_login, manager
            ) VALUES (
                :first_name, :last_name, :username, :status, :access_level, :password, :email, :profile_type, :title, :company, 
                :website, :inactive_date, :external_employee_id, :address1, :address2, :city, :state_province, :zip_code, 
                :country, :timezone, :language, :date_format, :brand, :work_phone, :mobile_phone, :skype, :twitter, 
                NOW(), NOW(), :last_login, :manager
            )';

            $stmt = $this->conn->prepare($query);

            // Bind parameters
            $stmt->bindParam(':first_name', $data->first_name);
            $stmt->bindParam(':last_name', $data->last_name);
            $stmt->bindParam(':username', $data->username);
            $stmt->bindParam(':status', $data->status);
            $stmt->bindParam(':access_level', $data->access_level);
            $stmt->bindParam(':password', $hashedPassword);
            $stmt->bindParam(':email', $data->email);
            $stmt->bindParam(':profile_type', $data->profile_type);
            $stmt->bindParam(':title', $data->title);
            $stmt->bindParam(':company', $data->company);
            $stmt->bindParam(':website', $data->website);
            $stmt->bindParam(':inactive_date', $data->inactive_date);
            $stmt->bindParam(':external_employee_id', $data->external_employee_id);
            $stmt->bindParam(':address1', $data->address1);
            $stmt->bindParam(':address2', $data->address2);
            $stmt->bindParam(':city', $data->city);
            $stmt->bindParam(':state_province', $data->state_province);
            $stmt->bindParam(':zip_code', $data->zip_code);
            $stmt->bindParam(':country', $data->country);
            $stmt->bindParam(':timezone', $data->timezone);
            $stmt->bindParam(':language', $data->language);
            $stmt->bindParam(':date_format', $data->date_format);
            $stmt->bindParam(':brand', $data->brand);
            $stmt->bindParam(':work_phone', $data->work_phone);
            $stmt->bindParam(':mobile_phone', $data->mobile_phone);
            $stmt->bindParam(':skype', $data->skype);
            $stmt->bindParam(':twitter', $data->twitter);
            $stmt->bindParam(':last_login', $data->last_login);
            $stmt->bindParam(':manager', $data->manager);

            // Execute the query
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Database Insert Error: " . $e->getMessage());
            return false; // Failure
        }
    }

    public function checkDuplicate($data)
    {
        $query = "SELECT COUNT(*) as count FROM person WHERE email = :email OR username = :username";
        $stmt = $this->conn->prepare($query);

        // Bind the parameters
        $stmt->bindParam(':email', $data->email);
        $stmt->bindParam(':username', $data->username);

        // Execute the query
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['count'] > 0;
    }
}
