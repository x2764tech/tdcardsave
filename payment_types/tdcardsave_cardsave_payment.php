<?php
/*
 * Cardsave direct payment module
 * 
 * Developed by Matthew Caddoo, Twin Dots Limited 
 * Follow us @twindots
 * twindots.co.uk
 */

use TDCardSave\PaymentProcessor;

class Tdcardsave_Cardsave_Payment extends Shop_PaymentType
{
    /**
     * @return Phpr_Validation
     */
    protected static function _createValidation()
    {
        /*
                * Validate input data
                */
        $validation = new Phpr_Validation();

        $validation->add('CardName', 'Card Holder Name')->fn('trim')->
            required('Please enter the name as it appears on the card');

        $validation->add('CardNumber', 'Credit Card Number')->fn('trim')->
            required('Please enter a credit card number')->regexp('/^[0-9]*$/', 'Credit card number can only contain digits');

        $validation->add('CV2', 'CV2')->fn('trim')->required('Please enter the card\'s security code')->
            regexp('/^[0-9]*$/', 'Card security code must contain only digits');

        $validation->add('StartMonth', 'Start month')->fn('trim')->
            regexp('/^[0-9]*$/', 'Credit card start month can contain only digits.');

        $validation->add('StartYear', 'Start year')->fn('trim')->
            regexp('/^[0-9]*$/', 'Credit card start year can contain only digits.');

        $validation->add('ExpMonth', 'Expiration month')->fn('trim')->
            required('Please specify a card expiration month.')->
            regexp('/^[0-9]*$/', 'Credit card expiration month can contain only digits.');

        $validation->add('ExpYear', 'Expiration year')->fn('trim')->
            required('Please specify a card expiration year.')->
            regexp('/^[0-9]*$/', 'Credit card expiration year can contain only digits.');

        $validation->add('IssueNumber', 'Issue Number')->fn('trim')->
            regexp('/^[0-9]*$/', 'Issue number must contain only digits');
        return $validation;
    }

    /**
     * Payment method information
     * 
     * @return array 
     */
    public function get_info()
    {
        return array(
            'name' => 'Cardsave Direct Integration',
            'description' => 'A more advanced method of Cardsave payment integration'
        );
    }
    
    /**
     * Construct form for administration area to configure module
     * 
     * @param $host_obj ActiveRecord object containing configuration fields values
     * @param string $context Form context 
     */
    public function build_config_ui($host_obj, $context = null)
    {
        if ($context !== 'preview') 
        {
            $host_obj->add_field('merchant_id', 'Merchant ID')->tab('Configuration')->
            renderAs(frm_text)->comment('Cardsave 15 digit Merchant ID', 'above')->
            validation()->fn('trim')->required('Please provide Merchant ID.');
            
            $host_obj->add_field('password', 'Password')->tab('Configuration')->
            renderAs(frm_text)->comment('Cardsave account password', 'above')->
            validation()->fn('trim')->required('Please provide a Cardsave account password');
            
            $host_obj->add_field('hash_method', 'Hash Method')->tab('Configuration')->
            renderAs(frm_dropdown)->comment('Hashing Method', 'above')->
            validation()->fn('trim')->required('Please provide a Hash Method');
            
            $host_obj->add_field('shared_key', 'Shared Key')->tab('Configuration')->
            renderAs(frm_text)->comment('Shared Key', 'above')->
            validation()->fn('trim')->required('Please provide a Shared Key');
            
            $host_obj->add_field('transaction_type', 'Transaction Type')->
            tab('Configuration')->renderAs(frm_dropdown)->
            comment('The type of credit card transaction you want to perform.', 'above');
 
            $host_obj->add_field('order_status', 'Order Status')->tab('Configuration')->
            renderAs(frm_dropdown)->comment('Select status to assign the order in 
            case of successful payment.', 'above', true);
        }
    }

    public function register_access_points()
    {
        return array(
            'ls_tdcardsave_authenticate_3d'=>'authenticate_3d',
            'ls_tdcardsave_redirect_3d'=>'redirect_3d'
        );
    }


    /**
     * Defines the types of payments
     * 
     * @param int $current_key_value
     * @return array 
     */
    public function get_transaction_type_options($current_key_value = -1)
    {
        $options = array(
            'PREAUTH'=>'Pre-authorization',
            'SALE'=>'Purchase'
        );
 
        if ($current_key_value === -1) {
            return $options;
        }
 
        return isset($options[$current_key_value]) ? $options[$current_key_value] : null;
    }
    
    
    /**
     * Hashing option dropdown
     * 
     * @param int $current_key_value
     * @return array 
     */
    public function get_hash_method_options($current_key_value = -1)
    {
        $options = array(
            'SHA1' => 'SHA1',
            'HMACMD5' => 'HMACMD5',
            'MD5' => 'MD5',
            'HMACSHA1' => 'HMACSHA1'
        );
        if ($current_key_value === -1) {
            return $options;
        }
        return isset($options[$current_key_value]) ? $options[$current_key_value] : null;
    }
    
    /**
     * Gets order status
     * 
     * @param int $current_key_value
     * @return string 
     */
    public function get_order_status_options($current_key_value = -1)
    {
        if ($current_key_value === -1)
            return Shop_OrderStatus::create()->order('name')->find_all()->as_array('name', 'id');
 
        return Shop_OrderStatus::create()->find($current_key_value)->name;
    }
 
    /**
     * Peforms basic validation on payment gateway options
     * 
     * @param $host_obj 
     */
    public function validate_config_on_save($host_obj)
    {
        if (strlen($host_obj->password)>15) {
            $host_obj->field_error('password', 'Password must be 15 characters or shorter');
        }
    }
 
    /**
     * Prevent orders place with this payment method from being deleted
     * 
     * @param $host_obj
     * @param object $status 
     */
    public function status_deletion_check($host_obj, $status)
    {
        if ($host_obj->order_status == $status->id)
            throw new Phpr_ApplicationException('This status cannot be deleted because it is used in the Cardsave direct payment method.');
    }
    
    /**
     * Defines the payment form fields
     * 
     * @param $host_obj 
     */
    public function build_payment_form($host_obj)
    {
        $host_obj->add_field('CardName', 'Card Holder Name')->renderAs(frm_text)->
            comment('Cardholder Name', 'above')->validation()->fn('trim')->
            required('Please specify a cardholder name');
        
        $host_obj->add_field('CardNumber', 'Credit Card Number')->renderAs(frm_text)->
            validation()->fn('trim')->required('Please specify a credit card number')->
            regexp('/^[0-9]+$/', 'Credit card number can contain only digits.');
 
        $host_obj->add_field('StartMonth', 'Start Month', 'left')->renderAs(frm_text)->
            renderAs(frm_text)->validation()->fn('trim')->numeric();
        
        $host_obj->add_field('StartYear', 'Start Year', 'right')->renderAs(frm_text)->
            renderAs(frm_text)->validation()->fn('trim')->numeric();
        
        $host_obj->add_field('ExpMonth', 'Expiration Month', 'left')->renderAs(frm_text)->
            renderAs(frm_text)->validation()->fn('trim')->required('Please specify card expiration month')->numeric();
        
        $host_obj->add_field('ExpYear', 'Expiration Year', 'right')->renderAs(frm_text)->
            renderAs(frm_text)->validation()->fn('trim')->required('Please specify card expiration year')->numeric();
        
        $host_obj->add_field('CV2', 'CV2', 'left')->renderAs(frm_text)->validation()->
            fn('trim')->required('Please specify Card Verification Number')->numeric();
                
        $host_obj->add_field('IssueNumber', 'Issue Number', 'right')->renderAs(frm_text)->validation()->
            fn('trim')->numeric();
    }
    
    /**
     * Prevent sensitive information being logged
     * 
     * @param array $fields
     * @return array
     */
    private function prepare_fields_log($fields)
    {
        unset($fields['CV2']);
        if(isset($fields['IssueNumber']))
            unset($fields['IssueNumber']);
        if(isset($fields['CardNumber']))
            $fields['CardNumber'] = '...'.substr($fields['CardNumber'], -4);
 
        return $fields;
    }
    
    /**
     * Prevent sensitive information from gateway
     * 
     * @param array $response
     * @return array 
     */
    private function prepare_response_log($response)
    {
        return $response;
    }
    
    /**
     * Process the payment and catch any errors 
     * 
     * @param array $data
     * @param $host_obj
     * @param Shop_Order $order
     * @param bool $back_end 
     */
    public function process_payment_form($data, $host_obj, $order, $back_end = false)
    {
        try {
            $validation = self::_createValidation();
            /*
            * Prepare and send request to the payment gateway, and parse the server response
            */
            if (!$validation->validate($data)) {
                traceLog( "Payment Validation failed" );
                $validation->throwException();
            }


            require_once(PATH_APP . '/modules/tdcardsave/classes/XmlPaymentGateway/TDCardSave/PaymentProcessor.php');

            $currency = Shop_CurrencySettings::get();
            $currency_code = $currency->iso_4217_code;

            $processor = new PaymentProcessor($host_obj->merchant_id, $host_obj->password);

            $processor->orderId = $order->id;
            $processor->amountInPence = $order->total * 100;


            $processor->currencyCode = $currency_code;


            $processor->cardName = $validation->fieldValues['CardName'];
            $processor->cardNumber = $validation->fieldValues['CardNumber'];
            $processor->expiryMonth = $validation->fieldValues['ExpMonth'];
            $processor->expiryYear = $validation->fieldValues['ExpYear'];
            $processor->startMonth = $validation->fieldValues['StartMonth'];
            $processor->startYear = $validation->fieldValues['StartYear'];
            $processor->cv2 = $validation->fieldValues['CV2'];
            $processor->issueNumber = $validation->fieldValues['IssueNumber'];

            $processor->countryCode = $order->billing_country->code_iso_numeric;
            $address = preg_split("/[\r\n]+/", $order->billing_street_addr );
            $address_length = count($address);
            $processor->address1 =  $address_length > 0 ? $address[0] : '';;
            $processor->address2 =  $address_length > 1 ? $address[1] : '';
            $processor->address3 = $address_length > 2 ? $address[2] : '';
            $processor->address4 = $address_length > 3 ? $address[3] : '';
            $processor->city = $order->billing_city;
            if(isset($order->billing_state->code))
                $processor->state = $order->billing_state->code;
            $processor->postcode = $order->billing_zip;
            $processor->country = $order->billing_country->name;
            $processor->emailAddress = $order->billing_email;
            $processor->phoneNumber = $order->billing_phone;

            $processor->ipAddress = Phpr::$request->getUserIp();


            $payment_result = $processor->processPayment();
        } catch(Exception $ex) {
            if (!$back_end)
                throw new Phpr_ApplicationException('Payment Declined');
            else
                throw new Phpr_ApplicationException('Error: '.$ex->getMessage().' on line: '.$ex->getLine());
        }

        $this->_processPaymentResult($data, $host_obj, $order, $back_end, $payment_result);
    }

    public function redirect_3d($params)
    {
        die(include(PATH_APP.'/modules/tdcardsave/3d_form.htm'));
    }

    public function authenticate_3d($params)
    {
        $is_backend = array_key_exists(1, $params) ? $params[1] === 'backend' : false;

        $order = null;

        $order_hash = array_key_exists(0, $params) ? $params[0] : null;
        if (!$order_hash)
            throw new Phpr_ApplicationException('Order not found');

        $order = Shop_Order::create()->find_by_order_hash($order_hash);

        if (!$order)
            throw new Phpr_ApplicationException('Order not found.');

        if (!$order->payment_method)
            throw new Phpr_ApplicationException('Payment method not found.');

        $order->payment_method->define_form_fields();

        $host_obj = $order->payment_method;

        $payment_method_obj = $host_obj->get_paymenttype_object();

        if (!($payment_method_obj instanceof Tdcardsave_Cardsave_Payment))
            throw new Phpr_ApplicationException('Invalid payment method.');

        require_once(PATH_APP . '/modules/tdcardsave/classes/XmlPaymentGateway/TDCardSave/PaymentProcessor.php');

        $processor = new PaymentProcessor($host_obj->merchantId, $host_obj->password);

        $pa_res = post('PaRes');
        $cross_reference = post('MD');
        $payment_result = $processor->process3DSecureResult( $pa_res, $cross_reference);

        $this->_processPaymentResult(array( "Cross Reference" => $cross_reference, "PaRes" => $pa_res  ), $host_obj, $order, $is_backend, $payment_result);
    }

    protected function _processPaymentResult($data, $host_obj, $order, $back_end, $payment_result)
    {
        $response_data = array();
        try {
            $response_data = array();
            $response_data['Auth Code'] = $payment_result->getAuthCode();
            $response_data['Address Numeric Check Result'] = $payment_result->addressNumericCheckResult;
            $response_data['Postcode Check Result'] = $payment_result->postCodeCheckResult;
            $response_data['CV2 Result'] = $payment_result->cv2CheckResult;
            $response_data['3D Secure Result'] = $payment_result->threeDSecureAuthenticationCheckResult;

            if (!isset($payment_result) || $payment_result == null) {
                throw new Exception('Unable to communicate with payment gateway');
            } else {
                $response_message = $payment_result->message;
                $response_code = $payment_result->statusCode;

                if ($payment_result->paymentWasSuccessful()) {
                    $this->log_payment_attempt(
                        $order,
                        'Successful payment',
                        1,
                        $this->prepare_fields_log($data),
                        $response_data,
                        $payment_result->xmlString,
                        $response_data['CV2 Result'],
                        null,
                        $response_data['Address Numeric Check Result']
                    );

                    /* Update order status */
                    Shop_OrderStatusLog::create_record($host_obj->order_status,$order);

                    /* Mark as processed */
                    $order->set_payment_processed();


                } elseif ($payment_result->requires3DSecure()) {

                    $authentication_url = root_url('/ls_tdcardsave_authenticate_3d/' . $order->order_hash . ($back_end ? '/backend' : ''), true);
                    $data = array(
                        "ACSURL" => $payment_result->getAcsUrl(),
                        "PAReq" => $payment_result->getPaqReq(),
                        "MD" => $payment_result->crossReference,
                        "TermUrl" => $authentication_url);

                    $encoded = array();
                    foreach ($data as $key => $value)
                        $encoded[] = $key . "=" . urlencode($value);

                    $params = implode("&", $encoded);

                    Phpr::$response->redirect(root_url('ls_tdcardsave_redirect_3d/?' . $params));

                } elseif ($payment_result->transactionReferred()) {
                    throw new Exception('Transaction referred');
                } elseif ($payment_result->paymentDeclined()) {
                    throw new Exception("Credit card payment declined: $response_message");
                } elseif ($payment_result->isDuplicate()) {
                    if ($payment_result->previousTransactionWasSuccess()) {
                        $this->log_payment_attempt(
                            $order,
                            'Successful (Duplicate) payment',
                            1,
                            $this->prepare_fields_log($data),
                            $this->prepare_response_log($response_data),
                            $payment_result->xmlString
                        );

                        /* Update order status */
                        Shop_OrderStatusLog::create_record($host_obj->order_status, $order);

                        /* Mark as processed */
                        $order->set_payment_processed();
                    } else {
                        throw new Exception("Duplicate Transaction was not successful - " . $payment_result->previousTransactionMessage);
                    }
                } else {
                    throw new Exception("Unknown Error processing transaction");
                }
            }

        } catch (Exception $ex) {
            /*
            * Log invalid payment attempt
            */
            $data = $data ? : array();
            $response_data = $response_data ? : array();
            $response_text = isset($payment_result) ? $payment_result->xmlString : null;


            $this->log_payment_attempt(
                $order,
                $ex->getMessage(),
                0,
                $this->prepare_fields_log($data),
                $this->prepare_response_log($response_data),
                $response_text
            );

            if (!$back_end)
                throw new Phpr_ApplicationException('Payment Declined');
            else
                throw new Phpr_ApplicationException('Error: ' . $ex->getMessage() . ' on line: ' . $ex->getLine());
        }

        if (!$back_end)
        {
            $return_page = $order->payment_method->receipt_page;
            if ($return_page)
                Phpr::$response->redirect(root_url($return_page->url.'/'.$order->order_hash).'?utm_nooverride=1');
            else
                throw new Phpr_ApplicationException('SagePay Direct Receipt page is not found.');
        } else
        {
            Phpr::$response->redirect(url('/shop/orders/payment_accepted/'.$order->id.'?utm_nooverride=1&nocache'.uniqid()));
        }
    }
}