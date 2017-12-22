<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace alotacents\pagestate\behaviors;

use Yii;
use yii\base\Behavior;
use alotacents\pagestate\Controller;
use Closure;

class PageStateBehavior extends Behavior
{

    private $_loadComponentState;
    private $_saveComponentState;

    public function getLoadComponentState(){
        return $this->_loadComponentState;
    }

    public function setLoadComponentState(Closure $function){
        return $this->_loadComponentState = $function;
    }

    public function getSaveComponentState(){
        return $this->_saveComponentState;
    }

    public function setSaveComponentState(Closure $function){
        return $this->_saveComponentState = $function;
    }

    public function loadComponentStateInternal($state){
        $owner = $this->owner;
        $load = $this->getLoadComponentState();

        if($load instanceof Closure){
            $load = $load->bindTo($owner, $owner);
            $load($state);
        }

    }

    public function saveComponentStateInternal(){
        $owner = $this->owner;
        $save = $this->getSaveComponentState();

        if($save instanceof Closure){
            $save = $save->bindTo($owner, $owner);
            return $save();
        }

        return null;

    }

    /**
     * Attaches the behavior object to the component.
     * The default implementation will set the [[owner]] property
     * and attach event handlers as declared in [[events]].
     * Make sure you call the parent implementation if you override this method.
     * @param \yii\base\Component $owner the component that this behavior is to be attached to.
     */
    public function attach($owner)
    {
        parent::attach($owner);

        $controller = Yii::$app->controller;
        if($controller instanceof Controller){
            $controller->registerComponentState($this);
        }
    }

}
