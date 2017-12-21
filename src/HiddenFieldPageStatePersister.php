<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace alotacents\pagestate;

class HiddenFieldPageStatePersister extends PageStatePersister
{
    public function load(){

        $clientState = null;
        $clientState = $this->getClientState();

        if (!empty($clientState)) {
            $combinedState = $this->unserialize($clientState);

            $this->setComponentState(isset($combinedState[0]) ? $combinedState[0] : null);
            $this->setPageState(isset($combinedState[1]) ? $combinedState[1] : null);
        }
    }

    public function save(){
        $componentState =  $this->getComponentState();
        $pageState = $this->getPageState();

        if ($componentState != null || $pageState != null) {

            $clientState = $this->serialize([$componentState, $pageState]);

            $this->setClientState($clientState);
        }
    }
}
