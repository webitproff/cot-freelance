<?php

/**
 * [BEGIN_COT_EXT]
 * Hooks=standalone
 * [END_COT_EXT]
 */
/**
 * Ikassa billing Plugin
 *
 * @package ikassabilling
 * @version 2.0
 * @author CMSWorks Team
 * @copyright Copyright (c) CMSWorks.ru
 * @license BSD
 */

use cot\modules\payments\dictionaries\PaymentDictionary;
use cot\modules\payments\Repositories\PaymentRepository;
use cot\modules\payments\Services\PaymentService;

defined('COT_CODE') && defined('COT_PLUG') or die('Wrong URL');

require_once cot_incfile('ikassabilling', 'plug');
require_once cot_incfile('payments', 'module');

$m = cot_import('m', 'G', 'ALP');
$pid = cot_import('pid', 'G', 'INT');

if (empty($m))
{
	// Получаем информацию о заказе
	if (!empty($pid) && $pinfo = PaymentRepository::getInstance()->getById($pid))
	{
		cot_block($usr['id'] == $pinfo['pay_userid']);
		cot_block($pinfo['pay_status'] == 'new' || $pinfo['pay_status'] == 'process');

		$amount = number_format($pinfo['pay_summ']*$cfg['plugin']['ikassabilling']['rate'], 2, '.', '');
		
		$ikassa_form = '<form name="payment" method="post" action="https://sci.interkassa.com/" accept-charset="UTF-8"> 
			<input type="hidden" name="ik_co_id" value="'.$cfg['plugin']['ikassabilling']['shop_id'].'" /> 
			<input type="hidden" name="ik_pm_no" value="'.$pid.'" /> 
			<input type="hidden" name="ik_am" value="'.$amount.'" /> 
			<input type="hidden" name="ik_cur" value="'.$cfg['plugin']['ikassabilling']['currency'].'" /> 
			<input type="hidden" name="ik_desc" value="'.$pinfo['pay_desc'].'" /> 
			<button class="btn btn-success">'.$L['ikassabilling_formbuy'].'</button> 
			</form>';

		$t->assign(array(
			'IKASSA_FORM' => $ikassa_form,
		));
		$t->parse("MAIN.IKASSAFORM");

        // Изменяем статус "в процессе оплаты"
        PaymentService::getInstance()->setStatus($pid, PaymentDictionary::STATUS_PROCESS, 'ikassa');
	} else {
		cot_die();
	}
} elseif ($m == 'success') {
	if($_SERVER['REQUEST_METHOD'] == 'POST' && $cfg['plugin']['ikassabilling']['enablepost'])
	{
		$status_data = $_POST;
	}	
	else
	{	
		$status_data = $_GET;
	}
	
	if($status_data['ik_inv_st'] == 'success' && $status_data['ik_co_id'] == $cfg['plugin']['ikassabilling']['shop_id']) {
		
		// проверка наличия номера платежки и ее статуса
		$pinfo = PaymentRepository::getInstance()->getById($status_data['ik_pm_no']);
		if ($pinfo['pay_status'] == 'done')
		{
			$pluginBody = $L['ikassabilling_error_done'];
			$redirect = $pinfo['pay_redirect'];
		}
		elseif ($pinfo['pay_status'] == 'paid')
		{
			$pluginBody = $L['ikassabilling_error_paid'];
		}
		elseif ($pinfo['pay_status'] == 'process')
		{
			$pluginBody = $L['ikassabilling_error_wait'];
		}
		else
		{
			$pluginBody = $L['roboxbilling_error_otkaz'];
		}
	}
	elseif($status_data['ik_inv_st'] == 'waitAccept' || $status_data['ik_inv_st'] == 'process')
	{
		$pluginBody = $L['ikassabilling_error_wait'];
	}
	elseif($status_data['ik_inv_st'] == 'canceled')
	{
		$pluginBody = $L['ikassabilling_error_canceled'];
	}
	elseif($status_data['ik_inv_st'] == 'fail')
	{
		$pluginBody = $L['ikassabilling_error_fail'];
	}
	else
	{
		$pluginBody = $L['ikassabilling_error_incorrect'];
	}

	$t->assign(array(
		"IKASSA_TITLE" => $L['ikassabilling_error_title'],
		"IKASSA_ERROR" => $pluginBody
	));
	
	if($redirect){
		$t->assign(array(
			"IKASSA_REDIRECT_TEXT" => sprintf($L['ikassabilling_redirect_text'], $redirect),
			"IKASSA_REDIRECT_URL" => $redirect,
		));
	}
	
	$t->parse("MAIN.ERROR");
}
elseif ($m == 'fail')
{
	$t->assign(array(
		"IKASSA_TITLE" => $L['ikassabilling_error_title'],
		"IKASSA_ERROR" => $L['ikassabilling_error_fail']
	));
	$t->parse("MAIN.ERROR");
}
?>