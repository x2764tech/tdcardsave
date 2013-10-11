<?php
namespace TDCardSave;

include_once('CommonFunctions.php');
include_once('PaymentResponse.php');


/**
 * @property string requestXml
 */
class PaymentProcessor
{
    public $cardName;
    public $cardNumber;
    public $expiryMonth;
    public $expiryYear;
    public $cv2;
    public $issueNumber;
    public $address1;
    public $address2;
    public $address3;
    public $address4;
    public $city;
    public $state;
    public $postcode;
    public $country;
    public $emailAddress;
    public $phoneNumber;
    public $ipAddress;
    public $orderId;
    public $countryCode;
    public $currencyCode;
    public $webAddress;
    public $startMonth;
    public $startYear;
    public $amountInPence;
    private $merchantId;
    private $password;


    function __construct($merchantId, $password)
    {
        $this->ipAddress = $_SERVER['REMOTE_ADDR'];
        $this->merchantId = $merchantId;
        $this->password = $password;
    }

    /**
     * @param $OrderID
     * @param $MerchantID
     * @param $Password
     * @param $CountryCode
     * @param $CurrencyCode
     * @return string
     */
    public function CreatePaymentXml()
    {
        $OrderDescription = "Order " . $this->orderId; //Order Description for this new transaction


        $CardName = stripGWInvalidChars($this->cardName);

        $CardNumber = $this->cardNumber;
        $ExpMonth = $this->expiryMonth;
        $ExpYear = $this->expiryYear;
        $StartMonth = $this->startMonth;
        $StartYear = $this->startYear;
        $CV2 = $this->cv2;
        $IssueNumber = $this->issueNumber;

        $Address1 = stripGWInvalidChars($this->address1);
        $Address2 = stripGWInvalidChars($this->address2);
        $Address3 = stripGWInvalidChars($this->address3);
        $Address4 = stripGWInvalidChars($this->address4);
        $City = stripGWInvalidChars($this->city);
        $State = stripGWInvalidChars($this->state);
        $Postcode = stripGWInvalidChars($this->postcode);
        $Country = stripGWInvalidChars($this->country);
        $EmailAddress = stripGWInvalidChars($this->emailAddress);
        $PhoneNumber = stripGWInvalidChars($this->phoneNumber);

        $IPAddress = $this->ipAddress;

//XML to send to the Gateway - put your merchant ID & Password in the appropriate place.
        $xml = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
xmlns:xsd="http://www.w3.org/2001/XMLSchema"
xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
<soap:Body>
<CardDetailsTransaction xmlns="https://www.thepaymentgateway.net/">
<PaymentMessage>
<MerchantAuthentication MerchantID="' . $this->merchantId . '" Password="' . $this->password . '" />
<TransactionDetails Amount="' . $this->amountInPence . '" CurrencyCode="' . $this->currencyCode . '">
<MessageDetails TransactionType="SALE" />
<OrderID>' . $this->orderId . '</OrderID>
<OrderDescription>' . $OrderDescription . '</OrderDescription>
<TransactionControl>
<EchoCardType>TRUE</EchoCardType>
<EchoAVSCheckResult>TRUE</EchoAVSCheckResult>
<EchoCV2CheckResult>TRUE</EchoCV2CheckResult>
<EchoAmountReceived>TRUE</EchoAmountReceived>
<DuplicateDelay>20</DuplicateDelay>
<CustomVariables>
<GenericVariable Name="MyInputVariable" Value="Ping" />
</CustomVariables>
</TransactionControl>
</TransactionDetails>
<CardDetails>
<CardName>' . $CardName . '</CardName>
<CardNumber>' . $CardNumber . '</CardNumber>
<StartDate Month="' . $StartMonth . '" Year="' . $StartYear . '" />
<ExpiryDate Month="' . $ExpMonth . '" Year="' . $ExpYear . '" />
<CV2>' . $CV2 . '</CV2>
<IssueNumber>' . $IssueNumber . '</IssueNumber>
</CardDetails>
<CustomerDetails>
<BillingAddress>
<Address1>' . $Address1 . '</Address1>
<Address2>' . $Address2 . '</Address2>
<Address3>' . $Address3 . '</Address3>
<Address4>' . $Address4 . '</Address4>
<City>' . $City . '</City>
<State>' . $State . '</State>
<PostCode>' . $Postcode . '</PostCode>
<CountryCode>' . $this->countryCode . '</CountryCode>
</BillingAddress>
<EmailAddress>' . $EmailAddress . '</EmailAddress>
<PhoneNumber>' . $PhoneNumber . '</PhoneNumber>
<CustomerIPAddress>' . $IPAddress . '</CustomerIPAddress>
</CustomerDetails>
<PassOutData>Some data to be passed out</PassOutData>
</PaymentMessage>
</CardDetailsTransaction>
</soap:Body>
</soap:Envelope>';
        return $xml;
    }

    function processPayment()
    {
        return $this->_sendRequest(
            $this->CreatePaymentXml(),
            array(
                'SOAPAction:https://www.thepaymentgateway.net/CardDetailsTransaction',
                'Content-Type: text/xml; charset = utf-8',
                'Connection: close'
            ));
    }

    public function process3DSecureResult($paRes, $md)
    {
        return $this->_sendRequest(
            $this->CreateThreeSecureAuthenticationXml($paRes, $md),
            array(
                'SOAPAction:https://www.thepaymentgateway.net/ThreeDSecureAuthentication',
                'Content-Type: text/xml; charset = utf-8',
                'Connection: close'
            )
        );
    }

    protected function _sendRequest($xml, $headers)
    {
        $this->requestXml = $xml;
        $gwId = 1;
        $domain = "cardsaveonlinepayments.com";
        $port = "4430";
        $transactionAttempt = 1;

        //XML Headers used in cURL - remember to change the function after thepaymentgateway.net in SOAPAction when changing the XML to call a different function


        //It will attempt each of the gateway servers (gw1, gw2 & gw3) 3 times each before totally failing
        while ($gwId <= 3 && $transactionAttempt <= 3) {

            //builds the URL to post to (rather than it being hard coded - means we can loop through all 3 gateway servers)
            $url = 'https://gw' . $gwId . '.' . $domain . ':' . $port . '/';

            //initialise cURL
            $curl = curl_init();

            //set the options
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $this->requestXml);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_ENCODING, 'UTF-8');
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

            //Execute cURL request
            //$ret = returned XML
            $ret = curl_exec($curl);
            //$err = returned error number
            $err = curl_errno($curl);
            //retHead = returned XML header
            $retHead = curl_getinfo($curl);

            //close cURL connection
            curl_close($curl);
            $curl = null;

            //if no error returned
            if ($err == 0) {
                //Get the status code
                $paymentResponse = new PaymentResponse($ret);

                if ($paymentResponse->wasSuccessful)
                    return $paymentResponse;
            }

            //increment the transaction attempt if <=2
            if ($transactionAttempt <= 2) {
                $transactionAttempt += 1;
            } else {
                //reset transaction attempt to 1 & increment $gwID
                //(to use next numeric gateway number (eg. use gw2 rather than gw1 now))
                $transactionAttempt = 1;
                $gwId++;
            }
        }
    }

    private function CreateThreeSecureAuthenticationXml($paRes, $md)
    {
        return '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
xmlns:xsd="http://www.w3.org/2001/XMLSchema"
xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
<soap:Body>
<ThreeDSecureAuthentication xmlns="https://www.thepaymentgateway.net/">
<ThreeDSecureMessage>
<MerchantAuthentication MerchantID="'. $this->merchantId .'" Password="'. $this->password .'" />
<ThreeDSecureInputData CrossReference="'. $md .'">
<PaRES>'. $paRes .'</PaRES>
</ThreeDSecureInputData>
<PassOutData>Some data to be passed out</PassOutData>
</ThreeDSecureMessage>
</ThreeDSecureAuthentication>
</soap:Body>
</soap:Envelope>';
    }
}


?>