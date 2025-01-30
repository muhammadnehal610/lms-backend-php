<?php

class Team
{
    private $db;
    public function __construct($db)
    {
        $this->db = $db;
    }

    public function addTeam($data)
    {
        try {
            // Insert team into the database
            $query = 'INSERT INTO teams (name, description) VALUES (:name,:description)';
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':name', $data->name, PDO::PARAM_STR);
            $stmt->bindParam(':description', $data->description, PDO::PARAM_STR);
            $stmt->execute();
            return true;
        } catch (Exception $e) {
            // Log the error for debugging
            error_log("Error adding team: " . $e->getMessage());
            return false;
        }
    }

    public function getAllTeams()
    {
        $query = "SELECT * FROM teams";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function updateTeam($team_id, $data)
    {
        $query = 'UPDATE teams SET name=:name , description=:description WHERE id=:team_id';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':name', $data->name);
        $stmt->bindParam(':description', $data->description);
        $stmt->bindParam(':team_id', $team_id);
        $stmt->execute();
        return true;
    }
    public function deleteTeam($team_id)
    {
        $query = 'DELETE FROM teams WHERE id=:team_id';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':team_id', $team_id);
        $stmt->execute();
        return true;
    }
    public function getTeam($team_id)
    {
        $query = 'SELECT * FROM teams WHERE id=:team_id';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':team_id', $team_id);
        $stmt->execute();
        $team = $stmt->fetch(PDO::FETCH_ASSOC);
        $getTeamMembers = 'SELECT t.*, p.first_name, p.email, p.access_level
        FROM team_members t
        LEFT JOIN person p ON t.user_id = p.id
        WHERE t.team_id = :team_id';
        $getTeamMembersStmt = $this->db->prepare($getTeamMembers);
        $getTeamMembersStmt->bindParam(':team_id', $team['id']);
        $getTeamMembersStmt->execute();
        $teamMembers = $getTeamMembersStmt->fetchAll(PDO::FETCH_ASSOC);
        $team['members'] = $teamMembers;
        return $team;
    }
    public function addTeamMembers($team_id, $usersInfo)
    {
        $errors = [];
        $successCount = 0;

        foreach ($usersInfo as $userInfo) {
            // Check if the user is already a member of the team
            $query = 'SELECT id FROM team_members WHERE team_id = :team_id AND user_id = :user_id';
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':team_id', $team_id, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $userInfo['id'], PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                // Skip if the user is already a team member
                $errors[] = "User {$userInfo['email']} is already a team member.";
                continue;
            }

            // Insert the new user into the team_members table
            $query = 'INSERT INTO team_members (team_id, user_id) VALUES (:team_id, :user_id)';
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':team_id', $team_id, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $userInfo['id'], PDO::PARAM_INT);
            $result = $stmt->execute();

            if ($result) {
                $successCount++;
            } else {
                $errors[] = "Failed to add user {$userInfo['email']} to the team.";
            }
        }

        return [
            'error' => !empty($errors),
            'message' => $successCount . ' users successfully added to the team.',
            'errors' => $errors,
        ];
    }
    public function getTeamMembers($team_id)
    {
        $query = 'SELECT * FROM team_members WHERE id=:team_id';
        $stmt = $this->db->query($query);
        $stmt->bindParam(':team_id', $team_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function removeTeamMembers($team_id, $user_id)
    {
        $query = 'DELETE FROM team_members WHERE team_id=:team_id AND user_id=:user_id';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':team_id', $team_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return true;
    }
    public function addTeamInCourse($course_id, $team_id)
    {
        try {
            // Check if the team exists
            $teamQuery = 'SELECT id FROM teams WHERE id = :team_id';
            $teamStmt = $this->db->prepare($teamQuery);
            $teamStmt->bindParam(':team_id', $team_id, PDO::PARAM_INT);
            $teamStmt->execute();
            $team = $teamStmt->fetch(PDO::FETCH_ASSOC);

            if (!$team) {
                return [
                    "error" => true,
                    "message" => "Team not found."
                ];
            }

            // Check if the course exists
            $courseQuery = 'SELECT id FROM course WHERE id = :course_id';
            $courseStmt = $this->db->prepare($courseQuery);
            $courseStmt->bindParam(':course_id', $course_id, PDO::PARAM_INT);
            $courseStmt->execute();
            $course = $courseStmt->fetch(PDO::FETCH_ASSOC);

            if (!$course) {
                return [
                    "error" => true,
                    "message" => "Course not found."
                ];
            }

            // Check if the team already exists in the course (team_courses table)
            $teamExistQuery = 'SELECT id FROM team_courses WHERE team_id = :team_id AND course_id = :course_id';
            $teamExistStmt = $this->db->prepare($teamExistQuery);
            $teamExistStmt->bindParam(':course_id', $course_id);
            $teamExistStmt->bindParam(':team_id', $team_id);
            $teamExistStmt->execute();
            $teamExist = $teamExistStmt->fetch(PDO::FETCH_ASSOC);
            if ($teamExist) {
                return [
                    'error' => true,
                    'message' => 'Team already exists in the course.'
                ];
            }

            // Add the team to the course (team_courses table)
            $teamCourseQuery = 'INSERT INTO team_courses (team_id, course_id) VALUES (:team_id, :course_id)';
            $teamCourseStmt = $this->db->prepare($teamCourseQuery);
            $teamCourseStmt->bindParam(':team_id', $team_id, PDO::PARAM_INT);
            $teamCourseStmt->bindParam(':course_id', $course_id, PDO::PARAM_INT);
            $teamCourseStmt->execute();

            // Get team members' user_id, first_name, and email
            $membersQuery = 'SELECT tm.user_id, p.first_name, p.email 
                             FROM team_members tm
                             LEFT JOIN person p ON tm.user_id = p.id
                             WHERE tm.team_id = :team_id';
            $membersStmt = $this->db->prepare($membersQuery);
            $membersStmt->bindParam(':team_id', $team_id, PDO::PARAM_INT);
            $membersStmt->execute();
            $teamMembers = $membersStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($teamMembers)) {
                return [
                    "error" => true,
                    "message" => "No members found in the team."
                ];
            }

            // Loop through each team member and enroll them in the course, checking for duplicates
            $enrollQuery = 'INSERT INTO enrollment (course_id, user_id, name, email, additional_info)
                            SELECT :course_id, :user_id, :first_name, :email, :additional_info 
                            FROM DUAL
                            WHERE NOT EXISTS (
                                SELECT 1 FROM enrollment 
                                WHERE course_id = :course_id AND user_id = :user_id
                            )';
            $enrollStmt = $this->db->prepare($enrollQuery);

            foreach ($teamMembers as $member) {
                // Bind parameters for each member
                $enrollStmt->bindParam(':course_id', $course_id, PDO::PARAM_INT);
                $enrollStmt->bindParam(':user_id', $member['user_id'], PDO::PARAM_INT);
                $enrollStmt->bindParam(':first_name', $member['first_name'], PDO::PARAM_STR);
                $enrollStmt->bindParam(':email', $member['email'], PDO::PARAM_STR);
                $additionalInfo = 'Not Started'; // Default additional info
                $enrollStmt->bindParam(':additional_info', $additionalInfo, PDO::PARAM_STR);
                $enrollStmt->execute();
            }

            return [
                "error" => false,
                "message" => "Team and its members successfully added to the course."
            ];
        } catch (PDOException $e) {
            // Handle exceptions
            return [
                "error" => true,
                "message" => "An error occurred: " . $e->getMessage()
            ];
        }
    }
}