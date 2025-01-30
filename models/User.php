<?php
class User
{
    private $conn;
    public $table_name = "person";
    public $role_name;
    public $id;
    public $name;
    public $email;
    public $username;
    public $password;
    public $role_id;
    public $jwt_token;
    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function addPerson($data)
    {
        // Check if email or username already exists
        $query = "SELECT COUNT(*) as count FROM person WHERE email = :email OR username = :username";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $data->email);
        $stmt->bindParam(":username", $data->username);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row['count'] > 0) {
            return true; // Indicates that the email or username already exists
        }

        $query = 'INSERT INTO person (
        first_name, last_name, username, access_level, password, email, profile_type, title, company, 
        website, inactive_date, external_employee_id, address1,address2, city, state_province, zip_code, country, 
        timezone, language, date_format, brand, work_phone, mobile_phone, skype, twitter, created_at, updated_at , manager
    ) VALUES (
        :first_name, :last_name, :username, :access_level, :password, :email, :profile_type, :title, :company, 
        :website, :inactive_date, :external_employee_id, :address1,:address2, :city, :state_province, :zip_code, :country, 
        :timezone, :language, :date_format, :brand, :work_phone, :mobile_phone, :skype, :twitter, NOW(), NOW() ,:manager
    )';

        $stmt = $this->conn->prepare($query);

        // Bind all parameters
        $stmt->bindParam(':first_name', $data->first_name);
        $stmt->bindParam(':last_name', $data->last_name);
        $stmt->bindParam(':username', $data->username);
        $stmt->bindParam(':access_level', $data->access_level);
        $stmt->bindParam(':password', $data->password); // Ensure password is hashed before calling this function
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
        $stmt->bindParam(':manager', $data->manager);

        // Execute the insert query
        if ($stmt->execute()) {
            return false; // Indicates successful insertion
        }

        return true; // Indicates a failure in insertion
    }

    // Model: register function
    public function register($data, $firstName, $lastName, $hashedPassword)
    {

        // Prepare the SQL query to insert into the 'person' table
        $query = "INSERT INTO person (first_name, last_name, email, password) 
              VALUES (:first_name, :last_name, :email, :password)";
        $stmt = $this->conn->prepare($query);

        // Bind the parameters to the query
        $stmt->bindParam(':first_name', $firstName);
        $stmt->bindParam(':last_name', $lastName);
        $stmt->bindParam(':email', $data->email);
        $stmt->bindParam(':password', $hashedPassword);

        // Execute the query and return the result
        if ($stmt->execute()) {
            return ['status' => 201, 'message' => 'User registered successfully.'];
        } else {
            return ['status' => 500, 'message' => 'Unable to register user.'];
        }
    }



    // Login a user
    public function login()
    {
        $query = "SELECT id, first_name, last_name, email, password, profile_type, access_level
                  FROM person 
                  WHERE email = :email";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $this->email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($this->password, $row['password'])) {

                // Corrected query to update the last_login field
                $query = "UPDATE person SET last_login = NOW() WHERE id = :userId";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':userId', $row['id']);
                $stmt->execute();

                return $row;  // Return the result with role_name and profile_type
            }
        }

        return null;
    }




    // Check if email exists in the database
    public function emailExists($email)
    {
        $query = "SELECT id FROM person WHERE email = :email";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);

        $stmt->execute();

        return $stmt->rowCount() < 0;  // Return true if email exists
    }

    public function usernameExists()
    {
        $query = "SELECT id FROM users WHERE user_name=:username";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":username", $this->username);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }



    // Forget password: update user's password
    public function forgetPassword($newPassword)
    {
        // Hash the new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $query = "UPDATE  person 
                  SET password = :password 
                  WHERE email = :email";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":password", $hashedPassword);
        $stmt->bindParam(":email", $this->email);

        return $stmt->execute();  // Return true if password updated successfully
    }
    // Inside User.php Model



    public function getAllUsers($keyword, $status, $order)
    {
        $query = "SELECT *, TIMESTAMPDIFF(DAY, last_login, NOW()) AS days_since_last_login FROM person WHERE 1";

        // Add condition for keyword search
        if (!empty($keyword)) {
            $query .= " AND (first_name LIKE :keyword OR last_name LIKE :keyword OR email LIKE :keyword OR access_level LIKE :keyword)";
        }

        // Add condition for status filter (active or inactive)
        if ($status == 'active') {
            $query .= " AND status = 1";  // Active users
        } elseif ($status == 'inactive') {
            $query .= " AND status = 0";  // Inactive users
        }

        // Order the results by last login time in the specified order
        $query .= " ORDER BY last_login $order";

        // Prepare the statement
        $stmt = $this->conn->prepare($query);

        // Bind the keyword parameter if it exists
        if (!empty($keyword)) {
            $stmt->bindValue(':keyword', '%' . $keyword . '%', PDO::PARAM_STR);
        }

        // Execute the query
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Count total records (without filtering)
        $countQuery = "SELECT COUNT(*) FROM person";
        $countstmt = $this->conn->prepare($countQuery);
        $countstmt->execute();
        $totalRecords = $countstmt->fetchColumn();

        // Return data and total records count
        return [
            'data' => $users,
            'totalRecords' => $totalRecords,
        ];
    }
    public function getUserById($ids)
    {
        // Ensure $ids is a valid non-empty array
        if (!is_array($ids) || empty($ids)) {
            return [
                "error" => true,
                "message" => "Invalid or empty IDs array.",
            ];
        }
    
        // Filter valid numeric IDs
        $validIds = array_filter($ids, 'is_numeric');
    
        if (empty($validIds)) {
            return [
                "error" => true,
                "message" => "No valid IDs provided.",
            ];
        }
    
        // Prepare the query using placeholders
        $placeholders = implode(',', array_fill(0, count($validIds), '?'));
        $query = "SELECT * FROM person WHERE id IN ($placeholders)";
        $stmt = $this->conn->prepare($query);
    
        // Bind the IDs to the placeholders
        foreach ($validIds as $index => $id) {
            $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
        }
    
        // Execute the query
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        // Process and return results
        $result = [];
        foreach ($ids as $id) {
            if (is_numeric($id)) {
                $user = array_filter($users, fn($u) => $u['id'] == $id);
                $result[] = $user ? current($user) : ['id' => (int)$id, 'error' => 'User not found.'];
            } else {
                $result[] = ['id' => $id, 'error' => 'Invalid ID format.'];
            }
        }
    
        return $result;
    }
    


    public function getInstructor()
    {
        $query = "SELECT * FROM person WHERE access_level = 'instructor' ";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user;
    }
    public function getInstructorById($instructorId)
    {
        $instructor_Id = (int)$instructorId;
      
        $query = "SELECT * FROM person WHERE access_level = 'instructor' AND id=:instructor_id ";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":instructor_id",$instructor_Id , PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user;
    }


    public function deleteUser($id)
    {
        $query = "UPDATE " . $this->table_name . " SET status = 'inactive' WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }


    // Revoke JWT token (used in logout)
    public function revokeJwtToken()
    {
        $query = "UPDATE " . $this->table_name . " SET jwt_token = NULL WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    // Paginate users (e.g., for large datasets)
    public function getUsersWithPagination($limit, $offset)
    {
        $query = "SELECT * FROM users LIMIT :limit OFFSET :offset";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateLogoutTime($logoutTime)
    {
        // Prepare the SQL query to update the inactive_date and status in the person table
        $query = "UPDATE person 
                  SET status = 0, 
                      inactive_date = :inactive_date, 
                      last_login = NULL 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
    
        // Bind the parameters to the query
        $stmt->bindParam(':inactive_date', $logoutTime);
        $stmt->bindParam(':id', $this->id);
    
        // Execute the query and return the result
        if ($stmt->execute()) {
            return true;
        } else {
            return false;
        }
    }
    


}
?>