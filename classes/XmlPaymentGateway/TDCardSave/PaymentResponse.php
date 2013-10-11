<?php
namespace TDCardSave;

include_once('CommonFunctions.php');

define("CARDSAVE_PAYMENT_STATUS_SUCCESS", 0);
define("CARDSAVE_PAYMENT_STATUS_REQUIRES_3D_SECURE", 3);
define("CARDSAVE_PAYMENT_STATUS_REFERRED", 4);
define("CARDSAVE_PAYMENT_STATUS_DECLINED", 5);
define("CARDSAVE_PAYMENT_STATUS_DUPLICATE", 20);


class PaymentResponse
{
    const CARDSAVE_CHECK_FAILED = "FAILED";
    const CARD_REFERRED_MESSAGE = "Card Referred";
    const CARDSAVE_CARD_DECLINED_MESSAGE = "Card declined";

    public $previousTransactionMessage;
    public $previousTransactionStatusCode;

    /**
     * @param $retXml - xml returned from gateway
     */
    function __construct($retXml)
    {
        $this->statusCode = GetXMLValue("StatusCode", $retXml, "[0-9]+");
        $this->xmlString = $retXml;
        if (!is_numeric($this->statusCode) || $this->statusCode == 50) {
            $this->wasSuccessful = false;
            return;
        }
        $this->wasSuccessful = true;
        $this->message = GetXMLValue("Message", $retXml, ".+");
        $this->crossReference = GetCrossReference($retXml);
        $this->addressNumericCheckResult = GetXMLValue("AddressNumericCheckResult", $retXml, ".+");
        $this->postCodeCheckResult = GetXMLValue("PostCodeCheckResult", $retXml, ".+");
        $this->cv2CheckResult = GetXMLValue("CV2CheckResult", $retXml, ".+");
        $this->threeDSecureAuthenticationCheckResult = GetXMLValue("ThreeDSecureAuthenticationCheckResult", $retXml, ".+");

        if ($this->isDuplicate()) {
            $this->_parsePreviousResult();
        }
    }

    public function isDuplicate()
    {
        return $this->statusCode == CARDSAVE_PAYMENT_STATUS_DUPLICATE;
    }

    protected function _parsePreviousResult()
    {
        $soapPreviousTransactionResult = null;

        if (!preg_match('#<PreviousTransactionResult>(.+)</PreviousTransactionResult>#iU', $this->xmlString, $soapPreviousTransactionResult))
            return;

        $PreviousTransactionResult = $soapPreviousTransactionResult[1];

        $this->previousTransactionMessage = GetXMLValue("Message", $PreviousTransactionResult, ".+");
        $this->previousTransactionStatusCode = GetXMLValue("StatusCode", $PreviousTransactionResult, ".+");

    }

    public function getPaqReq()
    {
        return GetXMLValue("PaREQ", $this->xmlString, ".+");
    }

    public function getAcsUrl()
    {
        return GetXMLValue("ACSURL", $this->xmlString, ".+");
    }

    public function paymentWasSuccessful()
    {
        return $this->statusCode == CARDSAVE_PAYMENT_STATUS_SUCCESS;
    }

    public function requires3DSecure()
    {
        return $this->statusCode == CARDSAVE_PAYMENT_STATUS_REQUIRES_3D_SECURE;
    }

    public function transactionReferred()
    {
        return $this->statusCode == CARDSAVE_PAYMENT_STATUS_REFERRED;
    }

    public function addressCheckPassed()
    {
        return $this->isSuccessfulCheck($this->addressNumericCheckResult);
    }

    /**
     * @param $result
     * @return bool
     */
    protected function isSuccessfulCheck($result)
    {
        return $result != self::CARDSAVE_CHECK_FAILED;
    }

    public function postCodeCheckPassed()
    {
        return $this->isSuccessfulCheck($this->postCodeCheckResult);
    }

    public function cv2CheckPassed()
    {
        return $this->isSuccessfulCheck($this->cv2CheckResult);
    }

    public function threeDSecureCheckPassed()
    {
        return $this->isSuccessfulCheck($this->threeDSecureAuthenticationCheckResult);
    }

    public function cardReferred()
    {
        return $this->paymentDeclined() && $this->message == self::CARD_REFERRED_MESSAGE;
    }

    public function paymentDeclined()
    {
        return $this->statusCode == CARDSAVE_PAYMENT_STATUS_DECLINED;
    }

    public function cardDeclined()
    {
        return $this->paymentDeclined() && $this->message == self::CARDSAVE_CARD_DECLINED_MESSAGE;
    }

    public function previousTransactionWasSuccess()
    {
        return $this->isDuplicate() && $this->previousTransactionStatusCode == CARDSAVE_PAYMENT_STATUS_SUCCESS;
    }

    public function getAuthCode()
    {
        return GetXMLValue("AuthCode", $this->xmlString, ".+");
    }
} 