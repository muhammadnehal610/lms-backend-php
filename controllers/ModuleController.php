<?php
require_once 'models/Modules.php';
require_once 'models/Course.php';
class ModuleController
{
    public $modules;
    public $courseModel;
    public function __construct($db)
    {
        $this->modules = new Modules($db);
        $this->courseModel = new Course($db);
    }
    public function addModule($data, $course_id)
    {
        if (!isset($data)) {
            return [
                'status' => 400,
                'msg' => 'enter valid json data'
            ];
        }
        $result = $this->modules->addModule($data, $course_id);
        if ($result) {
            return $result;
        } else {
            return [
                'status' => 400,
                'msg' => 'module failed to added'
            ];
        }
    }
   
    public function getModuleById($module_id)
    {
        $result = $this->modules->getModuleById($module_id);
        return $result;
    }
    public function changeModuleMandatory($module_id, $mandatory)
    {
        $result = $this->modules->changeModuleMandatory($module_id, $mandatory);
        if ($result) {
            return [
                'status' => 200,
                'msg' => 'mandatory update successfully'
            ];
        } else {
            return [
                'status' => 400,
                'msg' => 'failed to update mandatory'
            ];
        }
    }
    public function deleteModuleById($module_id)
    {
        $result = $this->modules->deleteModuleById($module_id);
        if ($result) {
            return [
                'status' => 200,
                'msg' => 'delete successfull'
            ];
        } else {
            return [
                'status' => 400,
                'msg' => 'delete failed'
            ];
        }
    }
    public function updateModule($moduleId, $data)
    {


        // Ensure data is set and not empty
        if (!isset($data) || empty($data)) {
            return [
                'status' => 400,
                'msg' => 'Invalid or empty JSON data'
            ];
        }
        $result = $this->modules->updateModule($moduleId, $data);
        if ($result) {
            return [
                'status' => 200,
                'msg' => 'module update successfully'
            ];
        } else {
            return [
                'status' => 400,
                'msg' => 'failed to update'
            ];
        }
    }
    public function updateModuleQuestion($question_Id, $data)
    {
        $result = $this->modules->updateModuleQuestion($question_Id, $data);
        if ($result) {
            return [
                'status' => 200,
                'msg' => 'question updated successfully'
            ];
        } else {
            return [
                'status' => 400,
                'msg' => 'failed to update'
            ];
        }
    }
    public function startModule($user_id, $module_id, $course_id)
    {
        $result = $this->modules->startModule($user_id, $module_id, $course_id);
        if ($result) {
            return [
                'error' => false,
                'status' => 200,
                'massege' => 'module start successfully'
            ];
        } else {
            return [
                'error' => true,
                'status' => 400,
                'massege' => 'failed to start module'
            ];
        }
    }
    public function endModule($user_id, $module_id, $course_id)
    {
        $result = $this->modules->completeModule($user_id, $module_id);
        $this->courseModel->checkAndMarkCourseComplete($course_id, $user_id);
        if ($result) {
            return [
                'error' => false,
                'status' => 200,
                'massege' => 'module end successfully'
            ];
        } else {
            return [
                'error' => true,
                'status' => 400,
                'massege' => 'failed to end module'
            ];
        }
    }
    public function assessmentAswers($user_id, $module_id, $answers, $courseId)
    {
        $result = $this->modules->assessmentAnswer($user_id, $module_id, $answers, $courseId);
        $this->courseModel->checkAndMarkCourseComplete($courseId, $user_id);
        return $result;
    }
    public function getScormFile($module_id, $courseId, $userId)
    {
        return $this->modules->getScormFile($module_id,  $courseId, $userId);
    }
    public function getAchievement($user_id)
    {
        $result = $this->modules->getAchievement($user_id);
        return [
            'error' => false,
            'status' => 200,
            'message' => 'Achievement fetched successfully',
            'data' => $result
        ];
    }
}
