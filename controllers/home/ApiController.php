<?php
namespace app\controllers\home;

use Yii;
use yii\helpers\Url;
use yii\web\Response;
use app\models\Config;
use app\models\Module;
use app\models\Api;
use app\models\api\CreateApi;
use app\models\api\UpdateApi;
use app\models\api\DeleteApi;
use app\models\ProjectLog;
use app\models\projectLog\CreateLog;

class ApiController extends PublicController
{
    public $checkLogin = false;

    public function actionDebug($id)
    {
        $api = Api::findModel($id);

        $project = $api->module->project;

        // 获取当前版本
        $project->current_version = $api->module->version;

        return $this->display('debug', ['project' => $project, 'api' => $api]);
    }

    /**
     * 添加接口
     * @param $module_id 模块ID
     * @return array|string
     */
    public function actionCreate($module_id)
    {
        $request = Yii::$app->request;

        $module = Module::findModel(['encode_id' => $module_id]);

        $api = new CreateApi();

        if($request->isPost){

            Yii::$app->response->format = Response::FORMAT_JSON;

            if(!$api->load($request->post())){
                return ['status' => 'error', 'message' => '数据加载失败'];
            }

            if(!$api->store()){
                return ['status' => 'error', 'message' => $api->getErrorMessage(), 'label' => $api->getErrorLabel()];
            }

            $callback = url('home/api/show', ['id' => $api->encode_id]);

            return ['status' => 'success', 'message' => '创建成功', 'callback' => $callback];

        }

        return $this->display('create', ['api' => $api, 'module' => $module]);
    }

    /**
     * 更新接口
     * @param $id 接口ID
     * @return array|string
     */
    public function actionUpdate($id)
    {
        $request = Yii::$app->request;

        $api = UpdateApi::findModel(['encode_id' => $id]);

        if($request->isPost){

            Yii::$app->response->format = Response::FORMAT_JSON;

            if(!$api->load($request->post())){
                return ['status' => 'error', 'message' => '数据加载失败'];
            }

            if ($api->store()) {
                $callback = url('home/api/show', ['id' => $api->encode_id]);
                return ['status' => 'success', 'message' => '编辑成功', 'callback' => $callback];
            }

            return ['status' => 'error', 'message' => $api->getErrorMessage(), 'label' => $api->getErrorLabel()];

        }

        return $this->display('update', ['api' => $api]);
    }
    
    /**
     * 删除接口
     * @param $id 接口ID
     * @return array|string
     */
    public function actionDelete($id)
    {
        $request = Yii::$app->request;

        $api = DeleteApi::findModel(['encode_id' => $id]);

        if($request->isPost){

            Yii::$app->response->format = Response::FORMAT_JSON;

            if(!$api->load($request->post())){
                return ['status' => 'error', 'message' => '加载数据失败'];
            }

            if ($api->delete()) {
                $callback = url('home/project/show', ['id' => $api->module->project->encode_id]);
                return ['status' => 'success', 'message' => '删除成功', 'callback' => $callback];
            }

            return ['status' => 'error', 'message' => $api->getErrorMessage(), 'label' => $api->getErrorLabel()];

        }

        return $this->display('delete', ['api' => $api]);
    }

    /**
     * 接口详情
     * @param $id 接口ID
     * @return string
     */
    public function actionShow($id, $tab = 'home')
    {
        $api = Api::findModel(['encode_id' => $id]);

        if(!$api->id){
            return $this->error('抱歉，接口不存在或者已被删除');
        }

        if(!Yii::$app->user->identity->isAdmin && $api->status !== $api::ACTIVE_STATUS){
            return $this->error('抱歉，接口已被禁用或已被删除');
        }

        if($api->project->isPrivate()) {

            if(Yii::$app->user->isGuest) {
                return $this->redirect(['home/account/login','callback' => Url::current()]);
            }

            if(!$api->project->hasAuth(['project' => 'look'])) {
                return $this->error('抱歉，您无权查看');
            }
        }

        $assign['api'] = $api;
        $assign['project'] = $api->project;

        $params = Yii::$app->request->queryParams;

        switch ($tab) {
            case 'home':
                $view  = '/home/api/home';
                break;
            case 'field':
                $assign['field'] = $api->field;
                $view  = '/home/field/home';
                break;
            case 'history':
                $params['object_name'] = 'api';
                $params['object_id']   = $api->id;
                $assign['history'] = ProjectLog::findModel()->search($params);
                $view  = '/home/history/api';
                break;
            default:
                $view  = '/home/api/home';
                break;
        }

        return $this->display($view, $assign);

    }

    /**
     * 导出接口文档
     * @param $id 接口ID
     * @return string
     */
    public function actionExport($id)
    {
        $api = Api::findModel(['encode_id' => $id]);

        if(!$api->project->hasAuth(['api' => 'export'])) {
            return $this->error('抱歉，您没有操作权限');
        }

        $account = Yii::$app->user->identity;
        $cache   = Yii::$app->cache;

        $config = Config::findOne(['type' => 'app']);

        $cache_key = 'api_' . $id . '_' . $account->id;
        $cache_interval = (int)$config->export_time;

        if($cache_interval >0 && $cache->get($cache_key) !== false){
            $remain_time = $cache->get($cache_key)  - time();
            if($remain_time >0 && $remain_time < $cache_interval){
                return $this->error("抱歉，导出太频繁，请{$remain_time}秒后再试!", 5);
            }
        }

        $file_name = "[{$api->module->title}]" . $api->title . '离线文档.html';

        // 记录操作日志
        $log = new CreateLog();
        $log->project_id  = $api->id;
        $log->object_name = 'api';
        $log->object_id   = $api->id;
        $log->type        = 'export';
        $log->content     = '导出了 ' . '<code>' . $file_name . '</code>';

        if(!$log->store()){
            return $this->error($log->getErrorMessage());
        }

        header ("Content-Type: application/force-download");
        header ("Content-Disposition: attachment;filename=$file_name");

        // 限制导出频率, 60秒一次
        $cache_interval >0 && Yii::$app->cache->set($cache_key, time() + $cache_interval, $cache_interval);

        return $this->display('export', ['api' => $api]);
    }
}
