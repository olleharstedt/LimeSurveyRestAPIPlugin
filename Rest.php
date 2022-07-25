<?php

use LimeSurvey\PluginManager\PluginBase;
use LimeSurvey\Menu\MenuItem;
use LimeSurvey\Menu\Menu;

class Rest extends PluginBase
{
    protected $storage = 'DbStorage';
    protected static $description = 'Expose basic GET/POST/PATCH calls as REST endpoint';
    protected static $name = 'Rest';

    public function init()
    {
        $this->subscribe('newDirectRequest');
    }

    public function newDirectRequest()
    {
        $event = $this->event;
        if ($event->get('target') == 'rest') {
            $request = $event->get('request');
            $this->v1($request);
        }
    }

    /**
     * Version 1 of this API.
     *
     * Example link: https://localhost/index.php?r=plugins/direct/plugin/rest/function/v1&model=survey&id=282267
     *
     * @param LSHttpRequest $request
     */
    public function v1(LSHttpRequest $request)
    {
        header('Content-Type: application/json');
        try {
            $this->branchOnMethod($_SERVER['REQUEST_METHOD'], $request);
        } catch (CHttpException $ex) {
            error_log($ex->getMessage());
            http_response_code($ex->statusCode);
            echo json_encode($ex->getMessage());
            die;
        }
    }

    protected function branchOnMethod($method, LSHttpRequest $request)
    {
        /** @var string */
        $method = $_SERVER['REQUEST_METHOD'];
        switch ($method) {
            case 'GET':
                $this->doGet();
                break;
            case 'POST':
                $this->doPost();
                break;
            case 'PATCH':
                $this->doPatch();
                break;
            default:
                throw new Exception('Unsupported request method: ' . $method);
        }
    }

    /**
     */
    protected function doGet()
    {
        $model = ucfirst(htmlentities($_GET['model']));
        if (!class_exists($model)) {
            throw new CHttpException(400, 'No such model exists: ' . $model);
        }
        $id = (int) $_GET['id'];

        $this->checkPermission('GET', $model, $id);

        $refClass = new ReflectionClass($model);
        $modelInstance = $refClass->newInstance($model);
        //$modelClass = $refClass->callFun
        $foundModel = $modelInstance::model()->findByPk($id);
        if (empty($foundModel)) {
            throw new CHttpException(404, 'Found no model with id ' . $id);
        }

        $attributes = $foundModel->getAttributes();
        echo json_encode($attributes);
    }

    /**
     * @param string $method GET, POST, etc
     * @param string $model Survey, Question, etc
     * @param int $id
     * @return void
     * @throws CHttpException with code 403
     */
    protected function checkPermission($method, $model, $id)
    {
        $access = null;
        if ($method === 'GET') {
            $access = 'read';
        }

        if ($model === 'Survey') {
            if (!Permission::model()->hasSurveyPermission($id, 'survey', $access)) {
                throw new CHttpException(403, 'No permission');
            }
        } else {
            throw new CHttpException(500, 'Found no permission to check for model ' . $model);
        }
    }
}
