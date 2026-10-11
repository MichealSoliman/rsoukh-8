<?php
/**
 * معالج إرسال نموذج تسجيل بيانات العملاء - رسوخ العمرانية
 * Rosookh Al Omraniyah - Form Submission & Email Handler
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=UTF-8');

// السماح فقط بطلبات POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'طريقة الطلب غير مسموح بها. يجب استخدام POST.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// تحميل الإعدادات
$configFile = __DIR__ . '/config.php';
$config = file_exists($configFile) ? require $configFile : [];

$recipientEmail = !empty($config['recipient_email']) ? trim($config['recipient_email']) : '';
$fromName = !empty($config['from_name']) ? trim($config['from_name']) : 'رسوخ العمرانية - روف الروضه';
$timezone = !empty($config['timezone']) ? $config['timezone'] : 'Asia/Riyadh';
$projectTitle = !empty($config['project_title']) ? $config['project_title'] : 'بيانات العميل روف الروضه';
$projectLoc = !empty($config['project_loc']) ? $config['project_loc'] : 'مستشفي ارادة شارع الامل';
$subjectText = !empty($config['email_subject']) ? $config['email_subject'] : 'بيانات العميل روف الروضه - مستشفي ارادة شارع الامل';
$mailerType = !empty($config['mailer_type']) ? strtolower(trim($config['mailer_type'])) : 'mail';

// ضبط المنطقة الزمنية
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set($timezone);
}

// قراءة البيانات
$rawInput = file_get_contents('php://input');
$data = [];

if (!empty($rawInput)) {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $data = $decoded;
    }
}

if (empty($data)) {
    $data = $_POST;
}

// 1. فحص حقل مكافحة البريد العشوائي (Honeypot)
if (!empty($data['website_url_check'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'تم رفض الطلب كإجراء أمني لمنع الرسائل الآلية.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// استخراج البيانات وتطهيرها
$fullName = isset($data['full_name']) ? trim(strip_tags((string)$data['full_name'])) : '';
$phoneNumber = isset($data['phone_number']) ? trim(strip_tags((string)$data['phone_number'])) : '';
$city = isset($data['city']) ? trim(strip_tags((string)$data['city'])) : '';
$paymentMethod = isset($data['payment_method']) ? trim(strip_tags((string)$data['payment_method'])) : '';

$errors = [];

// 2. التحقق من الاسم
if (empty($fullName)) {
    $errors['full_name'] = 'الاسم الكامل مطلوب.';
} elseif (mb_strlen($fullName, 'UTF-8') < 3) {
    $errors['full_name'] = 'يرجى إدخال اسم صحيح مكون من 3 أحرف على الأقل.';
}

// 3. التحقق من رقم الجوال السعودي حصراً وتنسيقه
if (empty($phoneNumber)) {
    $errors['phone_number'] = 'رقم الجوال مطلوب.';
} else {
    $cleanPhone = preg_replace('/[\s\-\(\)\+]/', '', $phoneNumber);
    if (str_starts_with($cleanPhone, '00966')) {
        $cleanPhone = substr($cleanPhone, 5);
    } elseif (str_starts_with($cleanPhone, '966')) {
        $cleanPhone = substr($cleanPhone, 3);
    }
    if (str_starts_with($cleanPhone, '05')) {
        $cleanPhone = substr($cleanPhone, 1);
    }

    if (preg_match('/^5[0-9]{8}$/', $cleanPhone)) {
        $phoneNumber = '+966' . $cleanPhone;
    } else {
        $errors['phone_number'] = 'يرجى إدخال رقم جوال سعودي صحيح (مثال: 05xxxxxxxx).';
    }
}

// 4. التحقق من المدينة
if (empty($city)) {
    $errors['city'] = 'المدينة مطلوبة.';
}

// 5. التحقق من طريقة الدفع
$allowedPayments = ['كاش (نقدًا)', 'تحويل بنكي', 'كاش', 'بنك'];
if (empty($paymentMethod)) {
    $errors['payment_method'] = 'يرجى اختيار طريقة الدفع.';
} elseif (!in_array($paymentMethod, $allowedPayments, true)) {
    $errors['payment_method'] = 'طريقة الدفع المحددة غير صالحة.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'يرجى تصحيح الأخطاء في البيانات المدخلة.',
        'errors' => $errors
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// التحقق من تعيين بريد إلكتروني مستلم صالح
if (empty($recipientEmail) || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'لم يتم إعداد بريد إلكتروني مستلم صالح في ملف الإعدادات (config.php).'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$submissionDate = date('Y-m-d');
$submissionTime = date('h:i A');
$submissionFullTime = date('Y-m-d H:i:s');

// تحديد بريد الإرسال
$serverDomain = !empty($_SERVER['SERVER_NAME']) ? preg_replace('/^www\./', '', $_SERVER['SERVER_NAME']) : 'rosookh.com';
if (!preg_match('/^[a-zA-Z0-9\.\-]+$/', $serverDomain)) {
    $serverDomain = 'rosookh.com';
}
$fromEmail = !empty($config['from_email']) ? trim($config['from_email']) : "noreply@{$serverDomain}";

// قالب البريد الإلكتروني الفخم المتوافق مع هوية رسوخ العمرانية
$emailHtml = '
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($subjectText, ENT_QUOTES, 'UTF-8') . '</title>
    <style>
        body {
            margin: 0;
            padding: 24px 10px;
            background-color: #040e18;
            font-family: \'Cairo\', \'Segoe UI\', Tahoma, Arial, sans-serif;
            color: #e2e8f0;
            direction: rtl;
            text-align: right;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #061626;
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid rgba(200, 164, 93, 0.35);
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.55);
        }
        .header {
            padding: 32px 25px 25px 25px;
            background: linear-gradient(180deg, #0b223a 0%, #061626 100%);
            border-bottom: 2px solid #c8a45d;
            text-align: center;
        }
        .brand-title {
            color: #c8a45d;
            font-size: 26px;
            font-weight: bold;
            margin: 0 0 6px 0;
            letter-spacing: -0.3px;
        }
        .badge {
            display: inline-block;
            background: rgba(200, 164, 93, 0.15);
            color: #e6c888;
            border: 1px solid rgba(200, 164, 93, 0.4);
            padding: 5px 16px;
            border-radius: 20px;
            font-size: 13px;
            margin-top: 8px;
        }
        .content {
            padding: 30px 24px;
        }
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
        }
        .data-row {
            background-color: rgba(255, 255, 255, 0.03);
            border-radius: 8px;
        }
        .data-label {
            padding: 14px 18px;
            color: #94a3b8;
            font-size: 14px;
            font-weight: 600;
            width: 35%;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            border-right: 3px solid #c8a45d;
        }
        .data-value {
            padding: 14px 18px;
            color: #ffffff;
            font-size: 15px;
            font-weight: 500;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .highlight {
            color: #c8a45d;
            font-weight: bold;
        }
        .phone-link {
            color: #c8a45d;
            text-decoration: none;
            direction: ltr;
            display: inline-block;
            font-weight: 600;
        }
        .footer {
            padding: 20px;
            background-color: #030c15;
            text-align: center;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            font-size: 12px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="brand-title">رسوخ العمرانية</h1>
            <div style="margin-top: 14px; margin-bottom: 8px; padding: 12px 18px; background: rgba(200, 164, 93, 0.22); border: 2px solid #c8a45d; border-radius: 10px; color: #fce7b0; font-size: 17px; font-weight: bold; line-height: 1.7; text-align: center;">
                📍 ' . htmlspecialchars($projectTitle, ENT_QUOTES, 'UTF-8') . '<br>
                🏥 ' . htmlspecialchars($projectLoc, ENT_QUOTES, 'UTF-8') . '
            </div>
            <div class="badge">طلب تسجيل اهتمام</div>
        </div>
        
        <div class="content">
            <table class="data-table" role="presentation">
                <tr class="data-row">
                    <td class="data-label">المشروع / النموذج</td>
                    <td class="data-value highlight">' . htmlspecialchars($projectTitle . ' - ' . $projectLoc, ENT_QUOTES, 'UTF-8') . '</td>
                </tr>
                <tr class="data-row">
                    <td class="data-label">اسم الشركة</td>
                    <td class="data-value highlight">رسوخ العمرانية</td>
                </tr>
                <tr class="data-row">
                    <td class="data-label">الاسم الكامل</td>
                    <td class="data-value">' . htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') . '</td>
                </tr>
                <tr class="data-row">
                    <td class="data-label">رقم الجوال</td>
                    <td class="data-value">
                        <a href="tel:' . htmlspecialchars($phoneNumber, ENT_QUOTES, 'UTF-8') . '" class="phone-link">
                            ' . htmlspecialchars($phoneNumber, ENT_QUOTES, 'UTF-8') . '
                        </a>
                    </td>
                </tr>
                <tr class="data-row">
                    <td class="data-label">المدينة</td>
                    <td class="data-value">' . htmlspecialchars($city, ENT_QUOTES, 'UTF-8') . '</td>
                </tr>
                <tr class="data-row">
                    <td class="data-label">طريقة الدفع</td>
                    <td class="data-value highlight">' . htmlspecialchars($paymentMethod, ENT_QUOTES, 'UTF-8') . '</td>
                </tr>
                <tr class="data-row">
                    <td class="data-label">تاريخ ووقت الطلب</td>
                    <td class="data-value">' . htmlspecialchars($submissionDate . ' | ' . $submissionTime, ENT_QUOTES, 'UTF-8') . '</td>
                </tr>
                <tr class="data-row">
                    <td class="data-label">نوع الطلب</td>
                    <td class="data-value highlight">تسجيل اهتمام</td>
                </tr>
                <tr class="data-row">
                    <td class="data-label">المصدر</td>
                    <td class="data-value">صفحة تسجيل الاهتمام (روف الروضه)</td>
                </tr>
            </table>
        </div>

        <div class="footer">
            تم إرسال هذا البريد تلقائياً عبر نظام تسجيل العملاء لشركة رسوخ العمرانية &copy; ' . date('Y') . '
        </div>
    </div>
</body>
</html>
';

$mailSuccess = false;
$errorMessage = '';

if ($mailerType === 'smtp' && !empty($config['smtp']['host'])) {
    // الإرسال عبر خادم SMTP
    require_once __DIR__ . '/smtp_mailer.php';
    $smtpConf = $config['smtp'];
    $mailer = new SimpleSmtpMailer(
        $smtpConf['host'],
        (int)$smtpConf['port'],
        $smtpConf['username'],
        $smtpConf['password'],
        $smtpConf['encryption'] ?? 'ssl',
        (int)($smtpConf['timeout'] ?? 15)
    );
    $mailSuccess = $mailer->send($fromEmail, $fromName, $recipientEmail, $subjectText, $emailHtml);
    if (!$mailSuccess) {
        $errorMessage = $mailer->getLastError();
    }
} else {
    // الإرسال عبر دالة mail() الافتراضية في PHP
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subjectText) . '?=';
    $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';

    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'Content-Transfer-Encoding: 8bit';
    $headers[] = 'From: ' . $encodedFromName . ' <' . $fromEmail . '>';
    $headers[] = 'Reply-To: ' . $fromEmail;
    $headers[] = 'X-Mailer: PHP/' . phpversion();
    $headersString = implode("\r\n", $headers);

    try {
        $mailSuccess = @mail($recipientEmail, $encodedSubject, $emailHtml, $headersString);
    } catch (\Throwable $e) {
        $mailSuccess = false;
        $errorMessage = $e->getMessage();
    }
}

// تسجيل الطلب كنسخة احتياطية آمنة
$logEntry = sprintf(
    "[%s] [%s - %s] Name: %s | Phone: %s | City: %s | Payment: %s | Result: %s\n",
    $submissionFullTime,
    $projectTitle,
    $projectLoc,
    $fullName,
    $phoneNumber,
    $city,
    $paymentMethod,
    $mailSuccess ? 'SENT' : 'FAILED'
);
@file_put_contents(__DIR__ . '/submissions_log.txt', $logEntry, FILE_APPEND | LOCK_EX);

if ($mailSuccess) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'تم إرسال بياناتك بنجاح، شكرًا لتواصلك مع رسوخ العمرانية.'
    ], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'تعذر إرسال البيانات حاليًا، يرجى المحاولة مرة أخرى.',
        'details' => $errorMessage ?: 'يرجى مراجعة إعدادات البريد في config.php أو التأكد من إعدادات الاستضافة.'
    ], JSON_UNESCAPED_UNICODE);
}
