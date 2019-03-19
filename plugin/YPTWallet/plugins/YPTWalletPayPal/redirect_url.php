<?php

header('Content-Type: application/json');

if (empty($global['systemRootPath'])) {
    $global['systemRootPath'] = '../../../../';
}
require_once $global['systemRootPath'] . 'videos/configuration.php';
require_once $global['systemRootPath'] . 'objects/user.php';

$plugin = YouPHPTubePlugin::loadPluginIfEnabled("YPTWallet");
$paypal = YouPHPTubePlugin::loadPluginIfEnabled("PayPalYPT");
// how to get the users_ID from the PayPal call back IPN?
$users_id = User::getId();

$invoiceNumber = uniqid();

$payment = $paypal->execute();

//check if there is a token and this token has a user (recurrent payments)
error_log("Redirect_URL line:".__LINE__." Start ");
if (!empty($_GET['token'])) {
    error_log("Redirect_URL line:".__LINE__." \$_GET['token'] ".$_GET['token']);
    if(YouPHPTubePlugin::isEnabledByName("Subscription")){
        error_log("Redirect_URL line:".__LINE__." \$payment->getId ".$payment->getId());
        $subscription = Subscription::getFromAgreement($payment->getId());
        
        if (!empty($subscription)) {
            $users_id = $subscription['users_id'];
            error_log("Redirect_URL line:".__LINE__." \$subscription ".$subscription);
        } else {
            error_log("Redirect_URL line:".__LINE__." \$subscription ".$subscription);
            if (!empty($users_id) && !empty($_SESSION['recurrentSubscription']['plans_id'])) {
                //save token
                $subscription = SubscriptionTable::getOrCreateSubscription($users_id, $_SESSION['recurrentSubscription']['plans_id'] , $payment->getId());
                error_log("Redirect_URL line:".__LINE__." \$subscription ".$subscription);
                unset($_SESSION['recurrentSubscription']['plans_id']);
            }
        }
    }
}
error_log("Redirect_URL line:".__LINE__." END ");

if (empty($users_id)) {
    error_log("Redirect URL error, Not found user or token");
    die();
}

//var_dump($amount);
$obj = new stdClass();
$obj->error = true;
if (!empty($payment)) {
    $amount = PayPalYPT::getAmountFromPayment($payment);
    $plugin->addBalance($users_id, $amount->total, "Paypal payment", json_encode($payment));
    $obj->error = false;
    if (!empty($_SESSION['addFunds_Success'])) {
        if(!empty($subscription)){
            Subscription::renew($subscription['users_id'], $subscription['subscriptions_plans_id']);
        }
        header("Location: {$_SESSION['addFunds_Success']}");
        unset($_SESSION['addFunds_Success']);
    } else {
        header("Location: {$global['webSiteRootURL']}plugin/YPTWallet/view/addFunds.php?status=success");
    }
} else {
    if (!empty($_SESSION['addFunds_Fail'])) {
        header("Location: {$_SESSION['addFunds_Fail']}");
        unset($_SESSION['addFunds_Fail']);
    } else {
        header("Location: {$global['webSiteRootURL']}plugin/YPTWallet/view/addFunds.php?status=fail");
    }
}
error_log(json_encode($obj));
error_log("PAYPAL redirect_url GET:  " . json_encode($_GET));
error_log("PAYPAL redirect_url POST: " . json_encode($_POST));
?>