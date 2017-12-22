# yii2-pagestate
PageState Extension for yii2

extend your controller with \alotacents\pagestate\Controller

to change persister and config override controller init function

    function init(){
      parent::init()

      $session = Yii::$app->session;
      $session->open();

      $this->enablePageSate = true; // if false pagestate will not be saved
      $this->pageStatePersister = [
        'class' => \alotacents\pagestate\HiddenFieldPageStatePersister, //Default can be omitted
        'userKey' => $session->id, 
        //Key used for encryption it's mixed with clientStateIdentifier. Can be set to false for don't use
        'clientStateIdentifier' => 'Namespace\Class', 
        //Default is the current controller classname. Can be set to false for don't use
        'clientStateLength' => -1, 
        // Used to chunk the ClientState field into multiple of specified size 0, and -1 mean no chunking
      ];
    }

in a action you'll have a pagestate property recommend using php reference 

    function actionTest(){
      $pagestate = &this->pagestate;

      if(!isset($pagestate['Counter'])){
        $pagestate['Counter'] = 0
      }

      $pagestate['Counter']++;

      return $this->render('view', ['count'=>$pagestate['counter']);
    }
    
behavior is for attaching to any component and setting a load and save of properties 
these properties will always be saved to the form even with enablePageSate = false; simmilar to asp.Net ControlState

    CounterWidget::widget([
        'id' => 'widget1',
        'as pagestate' => [
            'class' => \alotacents\pagestate\behaviors\PageStateBehavior::className(),
            'loadComponentState' => function($state) {
                    if(isset($state) && is_array($state)) {
                        $this->guess = $state[1];
                        $this->count = $state[0];
                    }
            },
            'saveComponentState' => function() {
                return [++$this->count, Yii::$app->request->get('g', '')];
            }
        ]
    ]);
