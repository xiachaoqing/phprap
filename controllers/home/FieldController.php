<?php
namespace app\controllers\home;

use Yii;
use yii\helpers\Html;
use yii\web\Response;
use app\models\field\UpdateField;

class FieldController extends PublicController
{
    /**
     * 更新字段
     * @param $id
     * @param string $method
     * @return array|string
     */
    public function actionUpdate($id, $method = 'form')
    {
        $request = Yii::$app->request;

        $model = UpdateField::findModel(['encode_id' => $id]);

        $assign['project'] = $model->api->project;
        $assign['api']     = $model->api;
        $assign['field']   = $model;

        if($request->isPost){

            Yii::$app->response->format = Response::FORMAT_JSON;

            $model->header_fields   = $this->form2json($request->post('header'));
            $model->request_fields  = $this->form2json($request->post('request'));
            $model->response_fields = $this->form2json($request->post('response'));

            if($model->store()) {
                $callback = url('home/api/show', ['id' => $model->api->encode_id, 'tab' => 'field']);
                return ['status' => 'success', 'message' => '编辑成功', 'callback' => $callback];
            }

            return ['status' => 'error', 'message' => $model->getErrorMessage(), 'label' => $model->getErrorLabel()];

        }

        return $this->display('/home/field/' . $method, $assign);
    }

    /**
     * 表单过滤后转json
     * @param $table
     * @return false|string
     */
    private function form2json($table)
    {
        if(!is_array($table) || !$table){
            return;
        }
        $array = [];
        foreach ($table as $k => $v) {
            foreach ($v as $k1 => $v1) {
                $array[$k1][$k] = trim(Html::encode($v1));
            }
        }

        return json_encode($array, JSON_UNESCAPED_UNICODE);
    }

}
