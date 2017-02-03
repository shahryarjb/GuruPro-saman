<?php
/**
 * @package     Joomla - > Site and Administrator payment info
 * @subpackage  com_Guru
 * @subpackage 	trangell_Saman
 * @copyright   trangell team => https://trangell.com
 * @copyright   Copyright (C) 20016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined( '_JEXEC' ) or die( 'Restricted access' );
if (!class_exists ('checkHack')) {
	require_once( JPATH_PLUGINS . '/k2store/payment_trangellsaman/trangell_inputcheck.php');
}

jimport('joomla.application.menu');
jimport( 'joomla.html.parameter' );

class plgGurupaymentTrangellSaman extends JPlugin{

	var $_db = null;
    
	function plgGurupaymentTrangellSaman(&$subject, $config){
		$this->_db = JFactory :: getDBO();
		parent :: __construct($subject, $config);
	}
	
	function onReceivePayment(&$post){
		if($post['processor'] != 'trangellsaman'){
			return 0;
		}	
		
		$params = new JRegistry($post['params']);
		$default = $this->params;
        
		$out['sid'] = $post['sid'];
		$out['order_id'] = $post['order_id'];
		$out['processor'] = $post['processor'];
		$Amount = round($this->getPayerPrice($out['order_id']),0);

		if(isset($post['txn_id'])){
			$out['processor_id'] = JRequest::getVar('tx', $post['txn_id']);
		}
		else{
			$out['processor_id'] = "";
		}
		if(isset($post['custom'])){
			$out['customer_id'] = JRequest::getInt('cm', $post['custom']);
		}
		else{
			$out['customer_id'] = "";
		}
		if(isset($post['mc_gross'])){
			$out['price'] = JRequest::getVar('amount', JRequest::getVar('mc_amount3', JRequest::getVar('mc_amount1', $post['mc_gross'])));
		}
		else{
			$out['price'] = $Amount;
		}
		$out['pay'] = $post['pay'];
		if(isset($post['email'])){
			$out['email'] = $post['email'];
		}
		else{
			$out['email'] = "";
		}
		$out["Itemid"] = $post["Itemid"];

		$cancel_return = JURI::root().'index.php?option=com_guru&controller=guruBuy&processor='.$param['processor'].'&task='.$param['task'].'&sid='.$param['sid'].'&order_id='.$post['order_id'].'&pay=fail';		
		//=====================================================================

		$app	= JFactory::getApplication();
		$jinput = $app->input;
		$resNum = $jinput->post->get('ResNum', '0', 'INT');
		$trackingCode = $jinput->post->get('TRACENO', '0', 'INT');
		$stateCode = $jinput->post->get('stateCode', '1', 'INT');
		
		$refNum = $jinput->post->get('RefNum', 'empty', 'STRING');
		if (checkHack::strip($refNum) != $refNum )
			$refNum = "illegal";
		$state = $jinput->post->get('State', 'empty', 'STRING');
		if (checkHack::strip($state) != $state )
			$state = "illegal";
		$cardNumber = $jinput->post->get('SecurePan', 'empty', 'STRING'); 
		if (checkHack::strip($cardNumber) != $cardNumber )
			$cardNumber = "illegal";
			
		$price = $Amount;	
		$merchantId = $params->get('samanmerchantId');
	
		if (
			checkHack::checkNum($resNum) &&
			checkHack::checkNum($trackingCode) &&
			checkHack::checkNum($stateCode) 
		 ){
			if (isset($state) && ($state == 'OK' || $stateCode == 0)) {
				try {
					$soap    = new SoapClient('https://sep.shaparak.ir/payments/referencepayment.asmx?WSDL');
					$resultCode    = $soap->VerifyTransaction($refNum, $merchantId);

					if ($resultCode == $price) {
						$out['pay'] = 'ipn';
						$message = "کد پیگیری".$trackingCode;
						$app->enqueueMessage($message, 'message');
					}
					else {
						$out['pay'] = 'fail';
						$msg= $this->getGateMsg($state); 
						$app->redirect($cancel_return, '<h2>'.$msg.'</h2>', $msgType='Error'); 	
					}
				}
				catch(\SoapFault $e)  {
					$out['pay'] = 'fail';
					$msg= $this->getGateMsg('error'); 
					$app->redirect($cancel_return, '<h2>'.$msg.'</h2>', $msgType='Error'); 
				}
			}
			else {
				$out['pay'] = 'fail';
				$msg= $this->getGateMsg($state);
				$app->redirect($cancel_return, '<h2>'.$msg.'</h2>', $msgType='Error'); 
			}
		}
		else {
			$out['pay'] = 'fail';
			$msg= $this->getGateMsg('hck2'); 
			$app->redirect($cancel_return, '<h2>'.$msg.'</h2>', $msgType='Error'); 
		}

		return $out;
	}

	function onSendPayment(&$post){
		if($post['processor'] != 'trangellsaman'){
			return false;
		}

		$params = new JRegistry($post['params']);
		$param['option'] = $post['option'];
		$param['controller'] = $post['controller'];
		$param['task'] = $post['task'];
		$param['processor'] = $post['processor'];
		$param['order_id'] = @$post['order_id'];
		$param['sid'] = @$post['sid'];
		$param['Itemid'] = isset($post['Itemid']) ? $post['Itemid'] : '0';
		foreach ($post['products'] as $i => $item){ $price += $item['value']; }  
		$cancel_return = JURI::root().'index.php?option=com_guru&controller=guruBuy&processor='.$param['processor'].'&task='.$param['task'].'&sid='.$param['sid'].'&order_id='.$post['order_id'].'&pay=fail';

		$app	= JFactory::getApplication();
		$merchantId = $params->get('samanmerchantId');
		$reservationNumber = time();
		$totalAmount =  round($price,0);
		$callBackUrl  = JURI::root().'index.php?option=com_guru&controller=guruBuy&processor='.$param['processor'].'&task='.$param['task'].'&sid='.$param['sid'].'&order_id='.$post['order_id'].'&customer_id='.intval($post['customer_id']).'&pay=wait';
		$sendUrl = "https://sep.shaparak.ir/Payment.aspx";
		return '
				<form id="paymentForm" method="post" action="'.$sendUrl.'">
					<input type="hidden" name="Amount" value="'.$totalAmount.'" />
					<input type="hidden" name="MID" value="'.$merchantId.'" />
					<input type="hidden" name="ResNum" value="'.$reservationNumber.'" />
					<input type="hidden" name="RedirectURL" value="'.$callBackUrl.'" />
				</form>
				<script type="text/javascript">
				document.getElementById("paymentForm").submit();
				</script>'
			;

	}

	function getGateMsg ($msgId) {
		switch($msgId){
			case '-1': $out=  'خطای داخل شبکه مالی'; break;
			case '-2': $out=  'سپردها برابر نیستند'; break;
			case '-3': $out=  'ورودی های حاوی کاراکترهای غیر مجاز می باشد'; break;
			case '-4': $out=  'کلمه عبور یا کد فروشنده اشتباه است'; break;
			case '-5': $out=  'Database excetion'; break;
			case '-6': $out=  'سند قبلا برگشت کامل یافته است'; break;
			case '-7': $out=  'رسید دیجیتالی تهی است'; break;
			case '-8': $out=  'طول ورودی های بیش از حد مجاز است'; break;
			case '-9': $out=  'وجود کاراکترهای غیر مجاز در مبلغ برگشتی'; break;
			case '-10': $out=  'رسید دیجیتالی حاوی کاراکترهای غیر مجاز است'; break;
			case '-11': $out=  'طول ورودی های کمتر از حد مجاز است'; break;
			case '-12': $out=  'مبلغ برگشت منفی است'; break;
			case '-13': $out=  'مبلغ برگشتی برای برگشت جزیی بیش از مبلغ برگشت نخورده رسید دیجیتالی است'; break;
			case '-14': $out=  'چنین تراکنشی تعریف نشده است'; break;
			case '-15': $out=  'مبلغ برگشتی به صورت اعشاری داده شده است'; break;
			case '-16': $out=  'خطای داخلی سیستم'; break;
			case '-17': $out=  'برگشت زدن جزیی تراکنشی که با کارت بانکی غیر از بانک سامان انجام پذیرفته است'; break;
			case '-18': $out=  'IP Adderess‌ فروشنده نامعتبر'; break;
			case 'Canceled By User': $out=  'تراکنش توسط خریدار کنسل شده است'; break;
			case 'Invalid Amount': $out=  'مبلغ سند برگشتی از مبلغ تراکنش اصلی بیشتر است'; break;
			case 'Invalid Transaction': $out=  'درخواست برگشت یک تراکنش رسیده است . در حالی که تراکنش اصلی پیدا نمی شود.'; break;
			case 'Invalid Card Number': $out=  'شماره کارت اشتباه است'; break;
			case 'No Such Issuer': $out=  'چنین صادر کننده کارتی وجود ندارد'; break;
			case 'Expired Card Pick Up': $out=  'از تاریخ انقضا کارت گذشته است و کارت دیگر معتبر نیست'; break;
			case 'Allowable PIN Tries Exceeded Pick Up': $out=  'رمز (PIN) کارت ۳ بار اشتباه وارد شده است در نتیجه کارت غیر فعال خواهد شد.'; break;
			case 'Incorrect PIN': $out=  'خریدار رمز کارت (PIN) را اشتباه وارده کرده است'; break;
			case 'Exceeds Withdrawal Amount Limit': $out=  'مبلغ بیش از سقف برداشت می باشد'; break;
			case 'Transaction Cannot Be Completed': $out=  'تراکنش تایید شده است ولی امکان سند خوردن وجود ندارد'; break;
			case 'Response Received Too Late': $out=  'تراکنش در شبکه بانکی  timeout خورده است'; break;
			case 'Suspected Fraud Pick Up': $out=  'خریدار فیلد CVV2 یا تاریخ انقضا را اشتباه وارد کرده و یا اصلا وارد نکرده است.'; break;
			case 'No Sufficient Funds': $out=  'موجودی به اندازه کافی در حساب وجود ندارد'; break;
			case 'Issuer Down Slm': $out=  'سیستم کارت بانک صادر کننده در وضعیت عملیاتی نیست'; break;
			case 'TME Error': $out=  'کلیه خطاهای دیگر بانکی که باعث ایجاد چنین خطایی می گردد'; break;
			case '1': $out=  'تراکنش با موفقیت انجام شده است'; break;
			case 'error': $out ='خطا غیر منتظره رخ داده است';break;
			case 'hck2': $out = 'لطفا از کاراکترهای مجاز استفاده کنید';break;
			case 'notff': $out = 'سفارش پیدا نشد';break;
			case 'price': $out = 'مبلغ وارد شده کمتر از ۱۰۰۰ ریال می باشد';break;
			default: $out ='خطا غیر منتظره رخ داده است';break;
		}
		return $out;
	}


	function getPayerPrice ($id) {
		$user = JFactory::getUser();
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('amount')
			->from($db->qn('#__guru_order'));
		$query->where(
			$db->qn('userid') . ' = ' . $db->q($user->id) 
							. ' AND ' . 
			$db->qn('id') . ' = ' . $db->q($id)
		);
		$db->setQuery((string)$query); 
		$result = $db->loadResult();
		return $result;
	}
}
?>
