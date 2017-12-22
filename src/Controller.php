<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace alotacents\pagestate;

use Closure;
use Yii;
use yii\base\Component;
use yii\base\Exception;
use yii\di\Instance;
use alotacents\pagestate\behaviors\PageStateBehavior;


class Controller extends \yii\web\Controller
{
    private $_persister;

    private $_registeredComponents;

    public $pageState;

    public $enablePageState = true;

    public function init(){

        parent::init();

        Yii::$app->on(\yii\base\Application::EVENT_BEFORE_ACTION, [$this, 'loadState']);
        Yii::$app->on(\yii\base\Application::EVENT_AFTER_REQUEST, [$this, 'saveState']);
    }

    public function registerComponentState(PageStateBehavior $behavior) {
        if ($behavior == null) {
            throw new \Exception('Component can not be null');
        }
        /*
        if (control.ControlState == ControlState.PreRendered) {
            throw new InvalidOperationException(SR.GetString(SR.Page_MustCallBeforeAndDuringPreRender, "RegisterRequiresControlState"));
        }
        */

        if ($this->_registeredComponents == null) {
            $this->_registeredComponents = [];
        }

        $component = $behavior->owner;
        // Don't do anything if RegisterRequiresControlState is called multiple times on the same control.
        if (!in_array($component, $this->_registeredComponents, true)) {
            $this->_registeredComponents[] = $component;

            $componentStates = $this->getPageStatePersister()->getComponentState();
            if ($componentStates != null) {
                $uniqueID = $component->id;

                $state = isset($componentStates[$uniqueID]) ? $componentStates[$uniqueID] : null;

                //throw new \Exception(var_export($component->behaviors,true));
                $behavior->loadComponentStateInternal($state);
            }
        }
    }

    protected function getPageStatePersister(){

        if ($this->_persister === null) {
            $this->setPageStatePersister([]);
        }

        return $this->_persister;
    }

    public function setPageStatePersister($value){
/*
        if(!is_object($value) || is_callable($value)){
            $value = Yii::createObject($value, [$this]);
        }
        $persister = Instance::ensure($value, PageStatePersister::className());
*/

        if(is_array($value)){
            $config = ['class'=>HiddenFieldPageStatePersister::className()];
            if(($id = $this::className()) !== null){
                $config['clientStateIdentifier'] = $id;
            }
            $value = array_merge($config, $value);
        }

        $persister = Instance::ensure($value, PageStatePersister::className());
        //$persister->setPage($this);

        $this->_persister = $persister;
    }


    protected function loadState() {

        Yii::$app->off(\yii\base\Application::EVENT_BEFORE_ACTION, [$this, 'loadState']);

        $statePair = $this->loadPageStateFromPersistenceMedium();

        $componentStates = isset($statePair[0]) ? $statePair[0] : null;
        if ($componentStates != null) {
            //$this->_controlsRequiringPostBack = (ArrayList)controlStates[PageRegisteredControlsThatRequirePostBackKey];

            if ($this->_registeredComponents != null) {
                foreach ($this->_registeredComponents as $component){

                    $uniqueID = $component->id;
                    $state = isset($componentStates[$uniqueID]) ? $componentStates[$uniqueID] : null;

                    $component->loadComponentStateInternal($state);
                }
            }
        }

        $this->pageState = isset($statePair[1]) ? $statePair[1] : [];
    }


/// <devdoc>
///    <para>Loads any saved view state information to the page. Override this method if
///       you want to load the page view state in anything other than a hidden field.</para>
/// </devdoc>

    protected function loadPageStateFromPersistenceMedium() {
        $persister = $this->getPageStatePersister();
        try {
            $persister->load();
        } catch (\Exception $e) {
            //VSWhidbey 201601. Ignore the exception in cross-page post
            //since this might be a cross application postback.
/*
            if (_pageFlags[isCrossPagePostRequest]) {
                return null;
            }

            // DevDiv #461378: Ignore validation errors for cross-page postbacks.
            if (ShouldSuppressMacValidationException($e)) {
                if (Context != null && Context.TraceIsEnabled) {
                    Trace.Write("aspx.page", "Ignoring page state", e);
                }

                $ViewStateMacValidationErrorWasSuppressed = true;
                return null;
            }

            $e.WebEventCode = WebEventCodes.RuntimeErrorViewStateFailure;
*/
            throw $e;
        }
        return [$persister->componentState, $persister->pageState];
    }

    protected function saveState() {
    // Don't do anything if no one cares about the view state (see ASURT 73020)
    // Note: If _needToPersistViewState is false, control state should also be ignored.
        //if (!_needToPersistViewState)
        //    return;

        Yii::$app->off(\yii\base\Application::EVENT_AFTER_REQUEST, [$this, 'saveState']);

        $componentStates = null;

        if($this->_registeredComponents !== null){
            foreach ($this->_registeredComponents as $component) {

                $uniqueID = $component->id;
                $state = $component->saveComponentStateInternal();

                if(!isset($componentStates[$uniqueID]) && $state !== null){
                    $componentStates[$uniqueID] = $state;
                }
            }
        }

        $pageStates = null;
        if($this->enablePageState === true && $this->pageState !== []){
            $pageStates = $this->pageState;
        }

        $statePair = [$componentStates, $pageStates];

        $this->savePageStateToPersistenceMedium($statePair);

    }

    /// <devdoc>
    ///    <para>Saves any view state information for the page. Override
    ///       this method if you want to save the page view state in anything other than a hidden field.</para>
    /// </devdoc>
    protected function savePageStateToPersistenceMedium($state) {
        $persister = $this->getPageStatePersister();

        $persister->componentState = (isset($state[0]) ? $state[0] : null);
        $persister->pageState = (isset($state[1]) ? $state[1] : null);

        $persister->save();

        $response = Yii::$app->response;
        if($response instanceof \yii\web\Response) {
            if ($response->format === \yii\web\Response::FORMAT_HTML) {
                if (isset($response->data)) {
                    $options = [
                        'length' => $persister->getClientStateLength(),
                        'identifier' => $persister->getClientStateIdentifierHash(),
                    ];
                    $response->data = preg_replace('/<form[^>]*>/',
                        '$0' . $persister->clientStateField($persister->getClientState(), $options), $response->data);
                }
            }
        }
    }


}

