<?php
require_once 'models/Team.php';
require_once 'models/User.php';
use \Firebase\JWT\JWT;
use Firebase\JWT\Key;
class TeamController
{
    private $db;
    private $TeamModel;
    private $userModel;
    public function __construct($db)
    {
        $this->db = $db;
        $this->TeamModel = new Team($db);
        $this->userModel = new User($db);
    }

    public function addTeam($data)
    {
        // Validation: Check if `name` and `description` are empty or missing
        if (empty($data->name) || empty($data->description)) {
            return [
                'status' => 400,
                'message' => 'Invalid input: Name and description are required.'
            ];
        }

        // Call the model function to add the team
        $addTeam = $this->TeamModel->addTeam($data);

        if ($addTeam) {
            return [
                'status' => 200,
                'message' => 'Team created successfully'
            ];
        } else {
            return [
                'status' => 500,
                'message' => 'Failed to create team'
            ];
        }
    }
    public function getAllTeams()
    {
        $result = $this->TeamModel->getAllTeams();

        return [
            'status' => 200,
            'message' => 'teams fetch successfully',
            'data' => $result
        ];


    }

    public function updateTeam($team_id, $data)
    {
        if (empty($data->name) || empty($data->description)) {
            return [
                'status' => 400,
                'message' => 'invalid input'
            ];
        }
        $updateTeam = $this->TeamModel->updateTeam($team_id, $data);
        if ($updateTeam) {
            return [
                'status' => 200,
                'message' => 'update successfully'
            ];
        } else {
            return [
                'status' => 400,
                'message' => 'failed to update'
            ];
        }
    }
    public function deleteTeam($team_id)
    {
        if (empty($team_id)) {
            return [
                'status' => 400,
                'message' => 'please enter the id'
            ];
        }
        $deleteTeam = $this->TeamModel->deleteTeam($team_id);
        if ($deleteTeam) {
            return [
                'status' => 200,
                'message' => 'delete successfully'
            ];
        } else {
            return [
                'status' => 400,
                'message' => 'failed to delete'
            ];
        }
    }
    public function getTeam($team_id)
    {
        if (empty($team_id)) {
            return [
                'status' => 400,
                'message' => 'please enter id'
            ];
        }
        $team = $this->TeamModel->getTeam($team_id);

        return [
            'status' => 200,
            'message' => 'fetched successfully',
            'data' => $team
        ];

    }
    public function addTeamMember($team_id, $user_id)
    {
        if (empty($team_id) || empty($user_id)) {
            return [
                'status' => 400,
                'message' => 'please enter team id and user id'
            ];
        }
        $userInfo = $this->userModel->getUserById($user_id);
        $addTeamMember = $this->TeamModel->addTeamMembers($team_id, $userInfo);
        return $addTeamMember;
    }
    public function getTeamMembers($team_id)
    {
        if (empty($team_id)) {
            return [
                'status' => 400,
                'message' => 'please enter id'
            ];
        }
        $team = $this->TeamModel->getTeam($team_id);
        return [
            'status' => 200,
            'message' => 'team members fetch successfully'
        ];
    }
    public function removeTeamMembers($team_id, $user_id)
    {
        if (empty($team_id) || empty($user_id)) {
            return [
                'status' => 400,
                'message' => 'please enter team id and user id'
            ];
        }
        $removeTeamMembers = $this->TeamModel->removeTeamMembers($team_id, $user_id);
        if ($removeTeamMembers) {
            return [
                'status' => 200,
                'message' => 'team member remove successfully'
            ];
        } else {
            return [
                'status' => 400,
                'message' => 'failed to remove team member'
            ];
        }
    }

    public function addTeamInCourse($courseId, $teamId)
    {
        if (empty($teamId) || empty($courseId)) {
            return [
                'status' => 400,
                'message' => 'please enter team id and course id'.$teamId
            ];
        }
        $team = $this->TeamModel->addTeamInCourse($courseId, $teamId);
        return $team;
    }
}