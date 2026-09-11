<?php
/**
 * Elif Pen - İletişim / teklif formu işleyicisi
 *
 * Formlar js/general.js içindeki AJAX çağrısı ile bu dosyaya POST edilir.
 * Başarılı gönderimde 200, hatalı durumda 4xx/5xx döner; arayüzdeki modal
 * bu duruma göre mesaj gösterir.
 *
 * KURULUM: Aşağıdaki $alici adresini kendi e-posta adresinizle değiştirin.
 * Paylaşımlı hostinglerde mail() yerine SMTP (PHPMailer) kullanmak
 * teslim edilebilirlik açısından daha güvenlidir.
 */

declare(strict_types=1);

$alici    = 'yasinozsoy@yahoo.com';
$siteAdi  = 'Elif Pen';
$logDosya = __DIR__ . '/gonderilen-formlar.log';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Yalnızca POST kabul edilir.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Tek satırlık alanlardan başlık enjeksiyonuna yarayan karakterleri temizler. */
function temizle(string $deger): string
{
    return trim(str_replace(["\r", "\n", "%0a", "%0d"], ' ', strip_tags($deger)));
}

$form    = temizle($_POST['form']    ?? 'Web Formu');
$ad      = temizle($_POST['name']    ?? '');
$telefon = temizle($_POST['phone']   ?? '');
$eposta  = temizle($_POST['email']   ?? '');
$konu    = temizle($_POST['subject'] ?? '');
$mesaj   = trim(strip_tags($_POST['message'] ?? ''));

$hatalar = [];

if ($ad === '') {
    $hatalar[] = 'Ad soyad zorunludur.';
}
if ($telefon === '' && $eposta === '') {
    $hatalar[] = 'Telefon veya e-posta adresinden en az biri gereklidir.';
}
if ($eposta !== '' && !filter_var($eposta, FILTER_VALIDATE_EMAIL)) {
    $hatalar[] = 'E-posta adresi geçersiz.';
}
if (mb_strlen($mesaj) > 5000) {
    $hatalar[] = 'Mesaj çok uzun.';
}

if ($hatalar) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'errors' => $hatalar], JSON_UNESCAPED_UNICODE);
    exit;
}

$govde = "Yeni form gönderimi\n"
    . "-------------------------------\n"
    . "Form      : {$form}\n"
    . "Ad Soyad  : {$ad}\n"
    . "Telefon   : {$telefon}\n"
    . "E-posta   : {$eposta}\n"
    . "Konu      : {$konu}\n"
    . "Tarih     : " . date('d.m.Y H:i') . "\n"
    . "IP        : " . ($_SERVER['REMOTE_ADDR'] ?? '-') . "\n"
    . "-------------------------------\n\n"
    . $mesaj . "\n";

$baslik = '=?UTF-8?B?' . base64_encode("{$siteAdi} - {$form}") . '?=';

$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'From: ' . $siteAdi . ' <' . $alici . '>',
];

if ($eposta !== '') {
    $headers[] = 'Reply-To: ' . $eposta;
}

$gonderildi = @mail($alici, $baslik, $govde, implode("\r\n", $headers));

// Mail sunucusu yapılandırılmamış olsa bile talep kaybolmasın diye kayıt tutulur.
@file_put_contents($logDosya, $govde . str_repeat('=', 50) . "\n", FILE_APPEND | LOCK_EX);

if (!$gonderildi) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'E-posta gönderilemedi.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
