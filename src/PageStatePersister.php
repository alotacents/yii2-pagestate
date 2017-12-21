<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace alotacents\pagestate;

use Yii;
use yii\base\Exception;
use yii\helpers\Html;

/**
 * Class PageStatePersister
 * @package common\components
 */
abstract class PageStatePersister extends \yii\base\BaseObject
{
    const FIELD_CLIENTSTATE_PREFIX = "__CLIENTSTATE";
    const FIELD_CLIENTSTATE_COUNT = self::FIELD_CLIENTSTATE_PREFIX . "COUNT";
    const FIELD_CLIENTSTATE_GENERATOR = self::FIELD_CLIENTSTATE_PREFIX . "GENERATOR";
    const CLIENTSTATEENCRYPTIONID = "__CLIENTSTATEENCRYPTED";

    private $_userKey;

    private $_clientStateLength;

    private $_clientStateIdentifier;
    /**
     * @var array
     */
    private $_pageState;
    /**
     * @var array
     */
    private $_componentState;

    /**
     * @var string
     */
    private $_clientState;

    /**
     * @return mixed
     */
    public function getPageState()
    {
        return $this->_pageState;
    }

    /**
     * @param $value
     */
    public function setPageState($value)
    {
        $this->_pageState = $value;
    }

    /**
     * @return mixed
     */
    public function getComponentState()
    {
        return $this->_componentState;
    }

    /**
     * @param $value
     */
    public function setComponentState($value)
    {
        $this->_componentState = $value;
    }

    public function getClientState()
    {

        if ($this->_clientState === null) {
            $this->_clientState = '';
            try {
                //$requestValueCollection = $_REQUEST;
                $requestValueCollection = $this->requestParams();
                if ($requestValueCollection != null) {
                    // If ViewStateChunking is disabled(-1) or there is no ViewStateFieldCount, return the __CLIENTSTATE field
                    $fieldCountStr = isset($requestValueCollection[self::FIELD_CLIENTSTATE_COUNT]) ? $requestValueCollection[self::FIELD_CLIENTSTATE_COUNT] : null;
                    if ($this->getClientStateLength() == -1 || $fieldCountStr == null) {
                        $this->_clientState = $requestValueCollection[self::FIELD_CLIENTSTATE_PREFIX];
                        return $this->_clientState;
                    }

                    // Build up the entire persisted state from all the viewstate fields
                    $numViewStateFields = (int)$fieldCountStr;
                    if ($numViewStateFields < 0) {
                        throw new Exception('Viewstate Invalid');
                    }

                    // The view state is split into __CLIENTSTATE, __CLIENTSTATE1, __CLIENTSTATE2, ... fields
                    for ($i = 0; $i < $numViewStateFields; ++$i) {
                        $key = self::FIELD_CLIENTSTATE_PREFIX;

                        // For backwards compat we always need the first chunk to be __CLIENTSTATE
                        if ($i > 0) {
                            $key .= $i;
                        }

                        $requestViewStateChunk = $requestValueCollection[$key];
                        if ($requestViewStateChunk === null) {
                            throw new Exception('ViewState MissingViewStateField ' . $key);
                        }

                        $this->_clientState .= $requestViewStateChunk;
                    }
                }

            } catch (Exception $e) {
                //ViewStateException.ThrowViewStateError($e, $state);
            }
        }

        return $this->_clientState;
    }

    public function setClientState($value)
    {
        return $this->_clientState = $value;
    }

    protected function requestParams()
    {
        $request = Yii::$app->getRequest();
        if ($request instanceof \yii\web\Request) {
            $params = $request->getIsPost() ? $request->getBodyParams() : $request->getQueryParams();
        } else {
            //$params = $_REQUEST;
            $params = [];
        }

        return $params;
    }

    public function getClientStateLength()
    {
        return $this->_clientStateLength;
    }

    public function setClientStateLength($value)
    {
        if ($value == 0 || $value < -1) {
            throw new Exception('The MaxClientStateLength property is not equal to -1 or a positive number');
        }
        $this->_clientStateLength = $value;
    }

    // This is a non-cryptographic hash code that can be used to identify which Page generated
    // a __CLIENTSTATE field. It shouldn't be considered sensitive information since its inputs
    // are assumed to be known by all parties.

    public function clientStateField($value, $options = [])
    {
        $hiddenInputs = [];

        if ($value != null) {
            $split_length = isset($options['length']) ? (int)$options['length'] : -1;
            if ($split_length <= 0) {
                $chunks = [$value];
            } else {
                $chunks = str_split($value, $split_length);
            }

            $chunksCount = count($chunks);
            if ($chunksCount > 1) {
                $hiddenInputs[] = Html::hiddenInput(self::FIELD_CLIENTSTATE_COUNT, $chunksCount,
                    ['id' => self::FIELD_CLIENTSTATE_COUNT]);
            }

            foreach ($chunks as $idx => $chunk) {
                $name = self::FIELD_CLIENTSTATE_PREFIX;
                if ($idx > 0) {
                    $name .= $idx;
                }
                $hiddenInputs[] = Html::hiddenInput($name, $chunk, ['id' => $name]);
            }

            // DevDiv #461378: Write out an identifier so we know who generated this __CLIENTSTATE field.
            // It doesn't need to be MACed since the only thing we use it for is error suppression,
            // similar to how __PREVIOUSPAGE works.
            if (isset($options['identifier'])) {
                $hiddenInputs[] = Html::hiddenInput(self::FIELD_CLIENTSTATE_GENERATOR, $options['identifier'], ['id' => self::FIELD_CLIENTSTATE_GENERATOR]);
                //$hiddenInputs[] = Html::hiddenInput(self::FIELD_CLIENTSTATE_GENERATOR, sprintf('%X8', $options['identifier'] & 0xFFFFFFFF), ['id' => self::FIELD_CLIENTSTATE_GENERATOR]);
            }
        } else {
            $hiddenInputs[] = Html::hiddenInput(self::FIELD_CLIENTSTATE_PREFIX, '',
                ['id' => self::FIELD_CLIENTSTATE_PREFIX]);
        }

        return Html::tag('div', implode(PHP_EOL, $hiddenInputs), ['class' => 'yiiHidden', 'style' => 'display:none']);
    }

    /**
     * @return mixed
     */
    abstract public function load();

    /**
     * @return mixed
     */
    abstract public function save();

    protected function verifyClientStateIdentifier($identifier)
    {
        // Returns true if we can parse the incoming identifier and it matches our own.
        // If we can't parse the identifier, then by definition we didn't generate it.
        return $identifier != null
            && $identifier == $this->getClientStateIdentifierHash();
    }

    public function getClientStateIdentifierHash()
    {
/*
        $page = Yii::$app->controller;

        $pageHashCode = hexdec(hash('fnv1a32', strtolower(Yii::$app->request->getBaseUrl())));
        $pageHashCode += hexdec(hash('fnv1a32', $page::className()));
*/

        $pageHashCode = $this->getClientStateIdentifier();

        if($pageHashCode !== null){
            $pageHashCode = hexdec(hash('fnv1a32', $pageHashCode));
            // convert to 32bit int
            $pageHashCode = ($pageHashCode & 0xFFFFFFFF);
            /*if ($pageHashCode & 0x80000000) {
                $pageHashCode = ~(~$pageHashCode & 0xFFFFFFFF);
            }*/

            $pageHashCode = strtoupper(str_pad(dechex($pageHashCode), 8, '0', STR_PAD_LEFT));
        }

        return $pageHashCode;
    }

    public function getClientStateIdentifier()
    {
        return $this->_clientStateIdentifier;
    }

    public function setClientStateIdentifier($value)
    {
        if(!is_scalar($value)){
            throw new Exception('The indentifier needs to be a string');
        }

        if(is_bool($value)){
            $value = null;
        } elseif(is_numeric($value)){
            $value = strval($value);
        }

        $this->_clientStateIdentifier = $value;
    }

    protected function serialize($object){

        $macKey = $this->generateMac();
        $data = serialize($object);
        if($macKey !== false)
            $data=Yii::$app->security->hashData($data, $macKey);

        if(extension_loaded('zlib'))
            $data=gzcompress($data);

        return base64_encode($data);
    }

    protected function generateMac() {
        static $macKeyBytes;

        if ($macKeyBytes === null) {
            // Note: duplicated (somewhat) in GetSpecificPurposes, keep in sync

            // Use the page's directory and class name as part of the key (ASURT 64044)
            $pageHashCode = $this->getClientStateIdentifierHash();

            $userKey = $this->getUserKey();

            if(isset($pageHashCode) || isset($userKey)) {
                $bytes = array_fill(0, 4, 0);
                //$bytes = array_values(unpack("C*", pack("L", $pageHashCode & 0xFFFFFFFF)));
                if ($userKey != null) {
                    // Modify the key with the ViewStateUserKey, if any (ASURT 126375)
                    $bytes = array_merge($bytes, unpack('C*', $userKey));
                    /*
                    int count = Encoding.Unicode.GetByteCount(viewStateUserKey);
                    new byte[count + 4];
                    Encoding.Unicode.GetBytes(viewStateUserKey, 0, viewStateUserKey.Length, _macKeyBytes, 4);
                    */
                }

                if (isset($pageHashCode)) {
                    $pageInt = hexdec($pageHashCode);

                    $bytes[0] = $pageInt & 0xFF;
                    $bytes[1] = ($pageInt >> 8) & 0xFF;
                    $bytes[2] = ($pageInt >> 16) & 0xFF;
                    $bytes[3] = ($pageInt >> 24) & 0xFF;
                }

                $macKeyBytes = call_user_func_array('pack', array_merge(['C*'], $bytes));
            } else {
                $macKeyBytes = false;
            }
        }

        return $macKeyBytes;
    }

    public function getUserKey()
    {
        return $this->_userKey;
    }

    public function setUserKey($value)
    {
        if (!is_scalar($value)) {
            throw new Exception('The user key needs to be a string');
        }

        if(is_bool($value)){
            $value = null;
        } elseif(is_numeric($value)){
            $value = strval($value);
        }

        return $this->_userKey = $value;
    }

    protected function unserialize($string){

        $macKey = $this->generateMac();

        $data = [];
        if(is_string($string)) {
            if (($data = base64_decode($string)) !== false) {
                if (extension_loaded('zlib')) {
                    $data = @gzuncompress($data);
                }

                if ($macKey !== false && ($decrypted = Yii::$app->security->validateData($data, $macKey)) !== false) {
                    $data = $decrypted;
                }

                if($data === 'b:0;'){
                    $data = false;
                } else {
                    if (($mixed = @unserialize($data)) !== false) {
                        $data = $mixed;
                    } else {
                        throw new \Exception('invalid serial');
                    }
                }
            }

            if(!is_array($data)){
                $data = [];
            }
        }

        return $data;
    }
}
