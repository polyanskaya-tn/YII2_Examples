<?php

namespace app\controllers;

use Yii;
use yii\web\UploadedFile;
use yii\data\Pagination;
use yii\imagine\Image;
use Imagine\Image\ManipulatorInterface;

use app\models\Comments;
use app\models\Sites;
use app\models\Actions;
use app\models\Catalogs;
use app\models\Menu;
use app\models\Settings;
use app\models\Blog;

class CommentController extends MainController {

    public function actionIndex() {
        $site = Sites::currentSite();
        $data['phone'] = Settings::param('phone');
        $data['phoneUrl'] = str_replace([" ", "-", "(", ")"], ["", "", "", ""], $data['phone']);
        $data['phone2'] = Settings::param('phone2');
        $data['phoneUrl2'] = str_replace([" ", "-", "(", ")"], ["", "", "", ""], $data['phone2']);
        $data['actions'] = Actions::find()->where(['site' => $site])
                ->andWhere(["<=", "UNIX_TIMESTAMP(STR_TO_DATE(`start`, '%d.%m.%Y'))", time()])
                ->andWhere([">=", "UNIX_TIMESTAMP(STR_TO_DATE(`finish`, '%d.%m.%Y'))", time() - 86400])->all();
        
        $data['blog'] = Comments::find()->where(['publish' => Comments::COMMENT_PUBLISHED, 'site' => $site])->orderBy(['time' => SORT_DESC])->limit(10)->all();
        $menu = Menu::find()->where(['site' => $site, 'visible' => 1])->addOrderBy('root, lft')->all();
        $data['menu'] = $menu;
        if ($site == Sites::ROSE25_SITE) {
            $data['catalogs'] = Catalogs::find()->where(['site' => $site])->addOrderBy('sort')->all();
            $data['catalogs_menu'] = Catalogs::generateCatalogMenu($data['catalogs']);
        }
 /*       
        if (isset($_GET['search'])) {
            $query = Blog::find()->where(['status' => Blog::BLOG_PUBLISHED, 'site' => $site])->andWhere(['like', 'name', $_GET['search']])->orderBy(['created_at' => SORT_ASC]);
            $countQuery = clone $query;
            $pages = new Pagination([
                'totalCount' => $countQuery->count(),
                'pageSize' => 10,
                'defaultPageSize' => 10
            ]);
            $posts = $query->offset($pages->offset)
                ->limit($pages->limit)
                ->all();
            $data['posts'] = $posts;
            $data['pages'] = $pages;
        } else*/ {
            $query = Comments::find()->where(['publish' => Comments::COMMENT_PUBLISHED, 'site' => $site])->orderBy(['time' => SORT_DESC]);
            $countQuery = clone $query;
            $pages = new Pagination([
                'totalCount' => $countQuery->count(),
                'pageSize' => 10,
                'defaultPageSize' => 10
            ]);
            $posts = $query->offset($pages->offset)
                ->limit($pages->limit)
                ->all();
            $data['posts'] = $posts;
            $data['pages'] = $pages;
        }

        return $this->render('index', $data);
    }
    
    public function actionCreate(){
        $model = new Comments();
        
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            
            $model->site = Sites::currentSite();
            $model->publish = 0;
            $model->sort = 0;
            $model->time = time();
            $model->save();
            
            $this->imageSave($model);
            $model->imageFile = null;
            
            $this->sendMails($model);
            
            return $this->redirect(['index']);
        }
        
        return $this->renderAjax('popup', [
            'model' => $model
        ]); 
    } 

    public function actionSend() {
        $err = [];
        $data = Yii::$app->request->post();
        //var_dump($data);
        //validation
        if (empty($data['name'])) {
            $err[] = "Не введено имя!";
        }
        if (empty($data['phone'])) {
            $err[] = "Не введен телефон!";
        }
        if (empty($data['email'])) {
            $err[] = "Не введен email!";
        }
        if (empty($data['text'])) {
            $err[] = "Не введен отзыв!";
        }
        if (empty($data['agree'])) {
            $err[] = "Отсутствует согласие на обработку персональных данных!";
        }
 
        if (!$err) {
            //send mail to client
            $site = Sites::findOne(Sites::currentSite());
            $data['site'] = $site->name;
            $body = $this->renderPartial('email', $data);
            $this->sendMailToClient($data['email'], "Рады получить Ваш отзыв", $body);
            //send mail to admin
            $body = $this->renderPartial('emailadmin', $data);
            $this->sendMail("Hовый отзыв", $body);
            //save to database
            $this->saveComment();
            return "ok|Спасибо за Ваш отзыв! Мы свяжемся с Вами";
        } else {
            return "err|" . implode("\n", $err);
        }
        
        
        /*
        $model = new Comments();
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            $model->site = Sites::currentSite();
            $model->publish = 0;
            $model->sort = 0;
            $model->time = time();
            $this->imageSave($model);
            $model->imageFile = null;
            $model->save();
            return $this->redirect("/#comment_ok");
        } else {
            return $this->redirect("/#comment_err");
        }
         * 
         */
        //return "ok|Спасибо за Ваш отзыв! Мы свяжемся с Вами";
    }
    
   
    
    public function saveComment()
    {
        $model = new Comments();
        
        $data = Yii::$app->request->post();
        
        //if ($model->load(Yii::$app->request->post()) && $model->save()) {
        $query = Comments::findBySql('SELECT max(id) as id FROM `comments`')->one();
        $model->id=$query->id+1;
        
        Yii::info('My log id : '.$model->id);
        
            $model->name = $data['name'];
            $model->text = $data['text'];
            $model->email = $data['email'];
            $model->phone = $data['phone'];
            
            $model->site = Sites::currentSite();
            $model->publish = 0;
            $model->sort = 0;
            $model->time = time();
            $this->imageSave($model);
            $model->imageFile = null;
            $model->save();
            return true;
        //} else {
        //    return false;
        //}
    }
    
    public function actionEmailUsOpen() {
        return $this->renderPartial('email-us');
    }

    public function actionEmailUsSend(){
        $model = new Comments();
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            $model->site = Sites::currentSite();
            $model->publish = 0;
            $model->sort = 0;
            $model->time = time();
            $model->save();
            $body = $this->renderPartial('email-text', ['model'=>$model]);
            $this->sendMail("Индивидуальный заказ " . Sites::findOne($model->site)->name, $body);
            return $this->redirect("/#individ_ok");
        } else {
            return $this->redirect("/#individ_err");
        }
    }

    private function sendMails($model) {
        //send mail to client
        $data['site'] = $model->site;
        $data['name'] = $model->name;
        $data['text'] = $model->text;
        $data['email'] = $model->email;
        $data['phone'] = $model->phone;
        $body = $this->renderPartial('email', $data);
        $this->sendMailToClient($data['email'], "Рады получить Ваш отзыв", $body);
        //send mail to admin
        $body = $this->renderPartial('emailadmin', $data);
        $this->sendMail("Hовый отзыв", $body);
    }
    
    public function sendMailToClient($toEmail, $subject, $body) {
       /* if (YII_ENV_DEV) {
            $msg = "Send e-mail!<br />";
            $msg .= "To: " . $toEmail . "<br />";
            $msg .= "Subject: " . $subject . "<br />";
            $msg .= "Body: " . $body . "<br />";
            Yii::$app->session->setFlash('info', $msg);
            return;
        }
        */
        Yii::$app->mailer->compose()
            ->setTo(trim($toEmail))
            ->setFrom([$this::FROM_EMAIL => Settings::param('name')])
            ->setSubject($subject)
            ->setHtmlBody($body)
            ->send();
    }
    
    private function imageSave($model) {
        $model->imageFile = UploadedFile::getInstance($model, 'imageFile');
        if (!$model->imageFile) 
            { return; }
        if ($model->upload()) {
            if (file_exists(Yii::getAlias('@webroot/img/comments/' . $model->id . '/orig.jpg'))) {
                Image::thumbnail('@webroot/img/comments/' . $model->id . '/orig.jpg', 70, 70, ManipulatorInterface::THUMBNAIL_INSET)
                        ->save(Yii::getAlias('@webroot/img/comments/' . $model->id . '/mini.jpg'), ['quality' => 100]);
                Image::thumbnail('@webroot/img/comments/' . $model->id . '/orig.jpg', 350, 350, ManipulatorInterface::THUMBNAIL_INSET)
                        ->save(Yii::getAlias('@webroot/img/comments/' . $model->id . '/small.jpg'), ['quality' => 100]);
            }
        }
    }

}
